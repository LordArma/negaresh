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

    /** Longest text the settings page preview accepts, in bytes (I5). */
    public const PREVIEW_MAX_LENGTH = 50000;

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

    /** @var array<string, true> md5 of comments fixed by filter_comment_content(), not yet marked */
    private $pending_comment_marks = [];

    /**
     * Opt out choices sent with the save that is running now, by post ID (0 for a new post):
     * WordPress stores post meta only after the content went through wp_insert_post_data (I6).
     *
     * @var array<int, bool>
     */
    private $skip_overrides = [];

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
        // Titles and hand written excerpts (I5), also before wptexturize (10).
        add_filter('the_title', [$this, 'filter_title'], 9, 2);
        add_filter('the_excerpt', [$this, 'filter_excerpt'], 9);
        // Comments (P3-6): display before wptexturize (10); on save after kses (10). Every way a
        // comment is saved with filters (form, REST, admin edit) runs pre_comment_content.
        add_filter('comment_text', [$this, 'filter_comment_text'], 9, 2);
        add_filter('pre_comment_content', [$this, 'filter_comment_content'], 20);
        add_action('wp_insert_comment', [$this, 'mark_comment_fixed'], 10, 2);
        add_action('edit_comment', [$this, 'mark_edited_comment']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_enqueue_scripts', [$settings, 'enqueue_assets']);
        add_filter('plugin_action_links_' . basename(dirname(NEGARESH_FILE)) . '/' . basename(NEGARESH_FILE), [$settings, 'action_links']);
    }

    public function load_textdomain(): void
    {
        // Needed for the translation bundled in languages/ (installs from GitHub): WordPress 5.8 does
        // not find it on its own (checked: 5.8.3 stays English without this call, 7.1.2 does not
        // need it). On wordpress.org, language packs take over.
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
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
        if (!is_string($content) || !$this->should_filter() || $this->already_fixed() || $this->opted_out()) {
            return $content;
        }

        return $this->safe_fix($content);
    }

    /**
     * `the_title` callback (I5), when "Fix post titles" is on.
     *
     * @param mixed $title
     * @param mixed $post_id
     * @return mixed
     */
    public function filter_title($title, $post_id = 0)
    {
        $post_id = is_numeric($post_id) ? (int) $post_id : 0;
        if (
            !is_string($title) || !$this->settings->flag('fix_titles') || !$this->should_filter($post_id)
            || $this->already_fixed($post_id) || $this->opted_out($post_id)
        ) {
            return $title;
        }
        return $this->safe_fix($title);
    }

    /**
     * `the_excerpt` callback (I5), when "Fix excerpts written by hand" is on. Automatic excerpts
     * come from the content, which is already fixed.
     *
     * @param mixed $excerpt
     * @return mixed
     */
    public function filter_excerpt($excerpt)
    {
        if (
            !is_string($excerpt) || !$this->settings->flag('fix_excerpts') || !$this->should_filter()
            || $this->already_fixed() || $this->opted_out()
        ) {
            return $excerpt;
        }
        return $this->safe_fix($excerpt);
    }

    /**
     * `comment_text` callback (P3-6), when "Fix comments" is on. Comments fixed on save with the
     * current rules are passed through.
     *
     * @param mixed $text
     * @param mixed $comment
     * @return mixed
     */
    public function filter_comment_text($text, $comment = null)
    {
        if (!is_string($text) || !$this->settings->flag('fix_comments') || !$this->should_filter()) {
            return $text;
        }
        if (
            $comment instanceof \WP_Comment
            && get_comment_meta((int) $comment->comment_ID, self::FIXED_META, true) === $this->settings->rules_hash()
        ) {
            return $text;
        }
        return $this->safe_fix($text);
    }

    /**
     * `pre_comment_content` callback (P3-6): in save mode the stored comment is corrected.
     * WordPress passes the value slashed.
     *
     * @param mixed $content
     * @return mixed
     */
    public function filter_comment_content($content)
    {
        if (!is_string($content) || !$this->settings->flag('fix_comments') || 'save' !== $this->settings->mode()) {
            return $content;
        }
        $plain = wp_unslash($content);
        $fixed = $this->safe_fix($plain);
        $this->pending_comment_marks[md5($fixed)] = true;
        return $fixed === $plain ? $content : wp_slash($fixed);
    }

    /**
     * `wp_insert_comment` and (through mark_edited_comment) `edit_comment`: marks a comment whose
     * text filter_comment_content() just fixed; any other change removes the mark.
     *
     * @param mixed $comment_id
     * @param mixed $comment
     */
    public function mark_comment_fixed($comment_id, $comment): void
    {
        if (!$comment instanceof \WP_Comment || !is_numeric($comment_id)) {
            return;
        }
        $key = md5($comment->comment_content);
        if (isset($this->pending_comment_marks[$key])) {
            unset($this->pending_comment_marks[$key]);
            update_comment_meta((int) $comment_id, self::FIXED_META, $this->settings->rules_hash());
        } else {
            delete_comment_meta((int) $comment_id, self::FIXED_META);
        }
    }

    /**
     * @param mixed $comment_id
     */
    public function mark_edited_comment($comment_id): void
    {
        if (is_numeric($comment_id)) {
            $this->mark_comment_fixed($comment_id, get_comment((int) $comment_id));
        }
    }

    /**
     * The settings page preview (I5): fixes a text with the rules as they are checked on the page,
     * not the saved ones. A rule that is missing from $rules is off, like an unchecked box.
     *
     * @param array<mixed> $rules
     */
    public function preview(string $text, array $rules): string
    {
        $normalized = [];
        foreach (array_keys(Negaresh_Settings::RULE_DEFAULTS) as $key) {
            $normalized[$key] = !empty($rules[$key]);
        }

        $saved = $this->virastar;
        $this->virastar = new Virastar($this->virastar_options($normalized));
        try {
            return $this->safe_fix($text);
        } finally {
            $this->virastar = $saved;
        }
    }

    public function register_rest_routes(): void
    {
        register_rest_route('negaresh/v1', '/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_preview'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
            'args' => [
                'text' => [
                    'required' => true,
                    'validate_callback' => static function ($value): bool {
                        return is_string($value) && strlen($value) <= self::PREVIEW_MAX_LENGTH;
                    },
                ],
                'rules' => [
                    'required' => false,
                    'validate_callback' => static function ($value): bool {
                        return null === $value || is_array($value);
                    },
                ],
            ],
        ]);
    }

    /**
     * POST negaresh/v1/preview {text, rules} → {text}.
     *
     * @return array{text: string}
     */
    public function rest_preview(\WP_REST_Request $request): array
    {
        $text = $request->get_param('text');
        $rules = $request->get_param('rules');

        return ['text' => $this->preview(is_string($text) ? $text : '', is_array($rules) ? $rules : [])];
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
        $post_id = is_array($postarr) && isset($postarr['ID']) && is_numeric($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($this->skip_for_save($post_id)) {
            return $data;
        }

        $content = wp_unslash($data['post_content']);
        $fixed = $this->safe_fix($content);
        if ($fixed !== $content) {
            $data['post_content'] = wp_slash($fixed);
        }
        $this->pending_marks[md5($fixed)] = true;

        // Titles and hand written excerpts, when enabled (I5).
        foreach (['post_title' => 'fix_titles', 'post_excerpt' => 'fix_excerpts'] as $field => $flag) {
            if (isset($data[$field]) && is_string($data[$field]) && $this->settings->flag($flag)) {
                $value = wp_unslash($data[$field]);
                $fixed_value = $this->safe_fix($value);
                if ($fixed_value !== $value) {
                    $data[$field] = wp_slash($fixed_value);
                }
            }
        }

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
    public function saves_type(string $type): bool
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
     * Records the opt out choice that arrived with the running save (block editor, I6).
     */
    public function override_skip(int $post_id, bool $skip): void
    {
        $this->skip_overrides[$post_id] = $skip;
    }

    /**
     * Whether the save running now must leave the post alone: the choice sent with this save (block
     * editor or classic editor box), else the stored one.
     */
    private function skip_for_save(int $post_id): bool
    {
        if (array_key_exists($post_id, $this->skip_overrides)) {
            return $this->skip_overrides[$post_id];
        }

        // Classic editor: the Negaresh box is part of the form being saved.
        if (
            isset($_POST[Negaresh_Editor::NONCE_FIELD])
            && false !== wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[Negaresh_Editor::NONCE_FIELD])), Negaresh_Editor::NONCE_ACTION)
        ) {
            return !empty($_POST[Negaresh_Editor::FIELD]);
        }

        return $this->opted_out($post_id);
    }

    /**
     * Whether the post (the one being displayed by default) is opted out (I6).
     */
    private function opted_out(int $post_id = 0): bool
    {
        if (0 === $post_id) {
            $post = get_post();
            if (!$post instanceof \WP_Post) {
                return false;
            }
            $post_id = $post->ID;
        }
        return 0 !== $post_id && '1' === (string) get_post_meta($post_id, Negaresh_Settings::SKIP_META, true);
    }

    /**
     * True when the post (the one being displayed by default) was fixed on save with the current
     * rules.
     */
    private function already_fixed(int $post_id = 0): bool
    {
        $post = get_post($post_id ?: null);
        if (!$post instanceof \WP_Post || 0 === $post->ID) {
            return false;
        }
        return get_post_meta($post->ID, self::FIXED_META, true) === $this->settings->rules_hash();
    }

    /**
     * fix() with every guard: only Persian content, never on invalid UTF-8, and on any failure or
     * empty result the input is returned unchanged. Uses the saved rules; ignores mode and scope
     * (callers decide whether to fix).
     */
    public function safe_fix(string $content): string
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
                if (!$closing && (in_array($name, self::PROTECTED_ELEMENTS, true) || self::marked_skip($part))) {
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

    /**
     * An element the author marked to be left alone (I6): class "negaresh-skip" or
     * data-negaresh="off". In the block editor: Advanced → Additional CSS class.
     */
    private static function marked_skip(string $tag): bool
    {
        if (1 === preg_match('/\sdata-negaresh\s*=\s*(["\']?)off\1(?=[\s\/>])/i', $tag)) {
            return true;
        }
        if (1 !== preg_match('/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))/i', $tag, $m)) {
            return false;
        }
        $classes = preg_split('/\s+/', trim(implode(' ', array_slice($m, 1))));
        return is_array($classes) && in_array('negaresh-skip', $classes, true);
    }

    private static function is_self_closing(string $markup): bool
    {
        return '/>' === substr($markup, -2);
    }

    /**
     * Whether display fixing applies now: not in wp-admin, feeds and REST as configured, and the
     * post type (of $post_id, or of the current post) in scope.
     */
    private function should_filter(int $post_id = 0): bool
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
            $type = $post_id ? get_post_type($post_id) : get_post_type();
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
     * @param array<string, bool>|null $rules the admin's rules unless given (preview)
     * @return array<string, bool>
     */
    public function virastar_options(?array $rules = null): array
    {
        return ($rules ?? $this->settings->rules()) + [
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
