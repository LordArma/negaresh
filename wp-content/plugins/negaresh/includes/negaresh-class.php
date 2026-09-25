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

    /** @var Negaresh_Settings */
    private $settings;

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
        add_filter('the_content', [$this, 'filter_content']);
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
        if (!is_string($content) || '' === trim($content) || !$this->should_filter()) {
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
