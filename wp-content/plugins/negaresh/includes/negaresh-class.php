<?php

/**
 * Negaresh: runs post content through Virastar at render time.
 *
 * @package Negaresh
 */

use Negaresh\Vendor\Virastar\Virastar;

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh
{
    /**
     * Elements whose contents are never touched (B3), nesting aware (I2).
     */
    public const PROTECTED_ELEMENTS = ['pre', 'code', 'kbd', 'samp', 'var', 'script', 'style', 'textarea', 'svg', 'math'];

    /**
     * Elements whose content ends at the first matching end tag, whatever it contains (HTML raw
     * text and escapable raw text elements), so they never nest.
     */
    public const RAW_TEXT_ELEMENTS = ['script', 'style', 'textarea'];

    /**
     * One piece of markup: a comment, CDATA, a declaration or processing instruction, or a tag whose
     * quoted attribute values may contain ">" (B24; wp_html_split() stops at the first ">").
     * An unterminated tag runs to the end of the text and is left alone.
     */
    public const MARKUP_PATTERN = '#(<(?:!--[\s\S]*?(?:-->|$)|!\[CDATA\[[\s\S]*?(?:\]\]>|$)|[!?][^>]*>?'
        . '|/?[A-Za-z][^\s/>]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>?))#';

    /**
     * Shortcode tags such as [gallery ids="1,2"] or [/caption]. Names start with a Latin letter, so
     * Persian text in brackets is still fixed, and so is the content an enclosing shortcode wraps.
     */
    public const SHORTCODE_PATTERN = '#(\[\[?/?[A-Za-z][\w-]*(?:[\s/=][^\[\]]*)?\]\]?)#';

    /** Whitespace a text node may start or end with; kept exactly as it is (B25). */
    private const EDGE_SPACE = '[\s\x{00A0}\x{200B}-\x{200F}\x{FEFF}]*';

    /** Post meta written when a post is fixed on save (I4). */
    public const FIXED_META = Negaresh_Settings::FIXED_META;

    /**
     * Post types that are never fixed on save even though they have an editor: their content is
     * JSON or site structure, not prose.
     */
    public const NEVER_SAVE_TYPES = [
        'revision', 'attachment', 'nav_menu_item', 'wp_template', 'wp_template_part', 'wp_global_styles',
        'wp_navigation', 'wp_font_family', 'wp_font_face', 'customize_changeset', 'oembed_cache',
        'user_request', 'custom_css',
    ];

    /** @var Negaresh_Settings */
    private $settings;

    /** @var array<string, true> md5 of contents fixed by filter_post_data() and not yet marked */
    private $pending_marks = [];

    /** @var Virastar|null built once per request, reset when the options change */
    private $virastar = null;

    public function __construct(Negaresh_Settings $settings)
    {
        $this->settings = $settings;

        add_action('plugins_loaded', [$settings, 'maybe_migrate']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$settings, 'add_page']);
        add_action('admin_init', [$settings, 'register']);
        add_action('add_option_' . Negaresh_Settings::OPTION, [$this, 'reset']);
        add_action('update_option_' . Negaresh_Settings::OPTION, [$this, 'reset']);
        // Priority 9 (B27): after do_blocks (9, registered by WordPress first) and before
        // wptexturize (10), which would already have turned "..." and quotes into entities.
        add_filter('the_content', [$this, 'filter_content'], 9);
        add_filter('wp_insert_post_data', [$this, 'filter_post_data'], 10, 2);
        add_action('save_post', [$this, 'mark_fixed'], 10, 2);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('negaresh', false, dirname(plugin_basename(NEGARESH_FILE)) . '/languages');
    }

    public function reset(): void
    {
        $this->virastar = null;
    }

    /**
     * `the_content` callback. Never breaks the page: on any failure the content is returned as is.
     *
     * @param mixed $content
     * @return mixed
     */
    public function filter_content($content)
    {
        if (!is_string($content) || !$this->should_filter() || $this->already_fixed()) {
            return $content;
        }

        return $this->safe_fix($content);
    }

    /**
     * `wp_insert_post_data` callback (I4): in save mode the stored content is corrected.
     * WordPress passes slashed values.
     *
     * @param mixed $data
     * @param mixed $postarr
     * @return mixed
     */
    public function filter_post_data($data, $postarr)
    {
        if (!is_array($data) || 'save' !== $this->settings->mode()) {
            return $data;
        }
        $type = isset($data['post_type']) && is_string($data['post_type']) ? $data['post_type'] : '';
        if (!$this->saves_type($type) || !isset($data['post_content']) || !is_string($data['post_content'])) {
            return $data;
        }

        $content = wp_unslash($data['post_content']);
        $fixed = $this->safe_fix($content);
        if ($fixed !== $content) {
            $data['post_content'] = wp_slash($fixed);
        }
        $this->pending_marks[md5($fixed)] = true;

        return $data;
    }

    /**
     * `save_post` callback: marks the post whose content filter_post_data() just fixed, so display
     * does not fix it again while the rules stay the same.
     *
     * @param mixed $post_id
     * @param mixed $post
     */
    public function mark_fixed($post_id, $post): void
    {
        if (!$post instanceof \WP_Post || !is_numeric($post_id) || wp_is_post_revision((int) $post_id)) {
            return;
        }
        $key = md5($post->post_content);
        if (!isset($this->pending_marks[$key])) {
            return;
        }
        unset($this->pending_marks[$key]);
        update_post_meta((int) $post_id, self::FIXED_META, $this->settings->rules_hash());
    }

    /**
     * Post types fixed on save: the chosen ones, or every public type with an editor, plus synced
     * patterns (wp_block), which are shown inside posts.
     */
    private function saves_type(string $type): bool
    {
        if ('' === $type || in_array($type, self::NEVER_SAVE_TYPES, true)) {
            return false;
        }
        $chosen = $this->settings->post_types();
        if ($chosen) {
            return in_array($type, $chosen, true);
        }
        return post_type_supports($type, 'editor')
            && ('wp_block' === $type || in_array($type, get_post_types(['public' => true]), true));
    }

    /**
     * True when the post being displayed was fixed on save with the current rules.
     */
    private function already_fixed(): bool
    {
        $post = get_post();
        if (!$post instanceof \WP_Post || 0 === $post->ID) {
            return false;
        }
        return get_post_meta($post->ID, self::FIXED_META, true) === $this->settings->rules_hash();
    }

    /**
     * fix() with every guard: only Persian content, never on invalid UTF-8, and on any failure or
     * empty result the input is returned unchanged.
     */
    private function safe_fix(string $content): string
    {
        if ('' === trim($content)) {
            return $content;
        }

        // Nothing to do without Arabic script letters. preg_match() also returns false on invalid
        // UTF-8, where Virastar's /u patterns would return null and wipe the post.
        if (1 !== preg_match('/[\x{0600}-\x{06FF}]/u', $content)) {
            return $content;
        }

        try {
            $fixed = $this->fix($content);
        } catch (\Throwable $e) {
            // B7: never print the error into the page.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Negaresh: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            }
            return $content;
        }

        return '' !== $fixed ? $fixed : $content;
    }

    /**
     * Fixes a piece of HTML (I2). Markup is never handed to Virastar: the HTML is split into markup
     * and text, protected elements are skipped whole, and every text node is fixed on its own with
     * its leading and trailing whitespace kept byte for byte, so no tag boundary gains or loses a
     * space (B25) and no attribute can be mistaken for text (B24).
     */
    public function fix(string $html): string
    {
        $parts = preg_split(self::MARKUP_PATTERN, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            throw new \RuntimeException('splitting markup failed: ' . (int) preg_last_error());
        }

        $out = '';
        $protected = null; // name of the protected element we are inside, if any
        $depth = 0;

        foreach ($parts as $i => $part) {
            if (0 === $i % 2) {
                $out .= null === $protected ? $this->fix_text($part) : $part;
                continue;
            }

            $out .= $part;
            $name = self::tag_name($part);
            if ('' === $name || self::is_self_closing($part)) {
                continue;
            }
            $closing = '/' === $part[1];

            if (null === $protected) {
                if (!$closing && in_array($name, self::PROTECTED_ELEMENTS, true)) {
                    $protected = $name;
                    $depth = 1;
                }
            } elseif ($name === $protected) {
                if ($closing) {
                    $depth--;
                } elseif (!in_array($name, self::RAW_TEXT_ELEMENTS, true)) {
                    $depth++;
                }
                if (0 === $depth) {
                    $protected = null;
                }
            }
        }

        return $out;
    }

    /**
     * Fixes one text node. Shortcode tags are boundaries, like HTML tags.
     */
    private function fix_text(string $text): string
    {
        if ('' === trim($text)) {
            return $text;
        }

        $pieces = preg_split(self::SHORTCODE_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $pieces) {
            throw new \RuntimeException('splitting shortcodes failed: ' . (int) preg_last_error());
        }

        $out = '';
        foreach ($pieces as $i => $piece) {
            $out .= 0 === $i % 2 ? $this->fix_piece($piece) : $piece;
        }
        return $out;
    }

    /**
     * Runs Virastar on a piece of plain text, keeping the whitespace around it.
     */
    private function fix_piece(string $piece): string
    {
        // English only text (Latin letters, no Arabic script) is left alone: quotes and digits in
        // an English sentence must not become Persian. Neutral text such as "123" is fixed.
        if (1 !== preg_match('/[\x{0600}-\x{06FF}]/u', $piece) && 1 === preg_match('/[A-Za-z]/', $piece)) {
            return $piece;
        }
        if (1 !== preg_match('/^(' . self::EDGE_SPACE . ')(.*?)(' . self::EDGE_SPACE . ')$/su', $piece, $m) || '' === $m[2]) {
            return $piece;
        }

        $fixed = $this->virastar()->cleanup($m[2]);
        if (!is_string($fixed)) {
            throw new \RuntimeException('Virastar returned no text');
        }

        return $m[1] . $fixed . $m[3];
    }

    /**
     * Lower case element name of a start or end tag; '' for comments and other markup.
     */
    private static function tag_name(string $markup): string
    {
        return 1 === preg_match('#^</?([A-Za-z][^\s/>]*)#', $markup, $m) ? strtolower($m[1]) : '';
    }

    private static function is_self_closing(string $markup): bool
    {
        return '/>' === substr($markup, -2);
    }

    private function should_filter(): bool
    {
        $options = $this->settings->get();

        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }
        if (!$options['apply_in_feeds'] && is_feed()) {
            return false;
        }
        if (!$options['apply_in_rest'] && defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        if (is_array($options['post_types']) && $options['post_types']) {
            $type = get_post_type();
            if ($type && !in_array($type, $options['post_types'], true)) {
                return false;
            }
        }

        return true;
    }

    private function virastar(): Virastar
    {
        if (null === $this->virastar) {
            $this->virastar = new Virastar($this->virastar_options());
        }
        return $this->virastar;
    }

    /**
     * Every Virastar option, explicitly (B18). Admin choices for the rules; fixed values for the
     * rest, because the input is HTML, not Markdown.
     */
    /**
     * @return array<string, bool>
     */
    public function virastar_options(): array
    {
        return $this->settings->rules() + [
            'decode_html_entities' => false, // B2: unsafe, removed from the settings
            'markdown_normalize_braces' => false,
            'markdown_normalize_lists' => false,
            'skip_markdown_ordered_lists_numbers_conversion' => false,
            'preserve_HTML' => true,
            'preserve_comments' => true,
            'preserve_entities' => true,
            'preserve_nbsp' => true,
            'preserve_URIs' => true,
            'preserve_front_matter' => false, // a post starting with --- is not front matter
            'preserve_brackets' => false, // shortcodes are split off in fix_text()
            'preserve_braces' => false,
        ];
    }
}
