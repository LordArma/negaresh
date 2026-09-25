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
     * Elements whose contents are never touched (B3). The element and everything inside it is
     * swapped for a placeholder before Virastar runs.
     */
    const PROTECTED_ELEMENTS = ['pre', 'code', 'kbd', 'samp', 'var', 'script', 'style', 'textarea', 'svg', 'math'];

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

        return (is_string($fixed) && '' !== $fixed) ? $fixed : $content;
    }

    /**
     * Fixes a piece of HTML. Protected elements and shortcode tags are held back, then restored.
     */
    public function fix(string $html): string
    {
        $held = [];
        $token = 'negaresh-keep-' . substr(md5(uniqid('', true)), 0, 8);

        $hold = function (array $match) use (&$held, $token) {
            $held[] = $match[0];
            // Looks like an HTML tag, so Virastar preserves it exactly (B1) and spacing is kept.
            return '<' . $token . '-' . (count($held) - 1) . '>';
        };

        $elements = implode('|', self::PROTECTED_ELEMENTS);
        $html = preg_replace_callback('#<(' . $elements . ')\b[^>]*>.*?</\1\s*>#is', $hold, $html);

        if (!is_string($html)) {
            throw new \RuntimeException('protecting markup failed: ' . preg_last_error());
        }

        // Shortcode tags such as [gallery ids="1,2"] or [/caption], looked for between HTML tags
        // only (tags themselves are preserved whole by Virastar). Names start with a Latin letter,
        // so Persian text in brackets is still fixed, and so is the content a shortcode encloses.
        $parts = preg_split('#(<[^>]*>)#', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if (0 === $i % 2 && false !== strpos($part, '[')) {
                $parts[$i] = preg_replace_callback('#\[\[?/?[A-Za-z][\w-]*(?:[\s/=][^\[\]]*)?\]\]?#', $hold, $part);
            }
        }
        $html = implode('', $parts);

        $fixed = $this->virastar()->cleanup($html);

        return preg_replace_callback('#<' . preg_quote($token, '#') . '-(\d+)>#', function (array $m) use ($held) {
            return $held[(int) $m[1]];
        }, $fixed);
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
        if ($options['post_types']) {
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
    public function virastar_options(): array
    {
        $rules = array_intersect_key($this->settings->get(), Negaresh_Settings::RULE_DEFAULTS);

        return $rules + [
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
            'preserve_brackets' => false, // shortcodes are held back in fix()
            'preserve_braces' => false,
        ];
    }
}
