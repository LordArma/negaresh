<?php

/**
 * Negaresh settings: one `negaresh_options` array, defaults in code, the settings page and the
 * migration from the 4.0 option layout (30 unprefixed options).
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Negaresh_Settings
{
    public const OPTION = 'negaresh_options';
    public const DB_VERSION_OPTION = 'negaresh_db_version';
    public const DB_VERSION = 3;
    public const PAGE = 'negaresh-options';
    public const GROUP = 'negaresh';

    /** Post meta: hash of the rules a post was fixed with when it was saved (I4). */
    public const FIXED_META = '_negaresh_fixed';

    /** When to fix: correct the stored text on save, or only the displayed text (I4). */
    public const MODES = ['save', 'display'];

    /**
     * Virastar rules the admin can toggle, with their defaults.
     * Defaults match the 4.0 settings page; the last three used to run silently (B18).
     */
    public const RULE_DEFAULTS = [
        // characters
        'fix_persian_glyphs' => true,
        'fix_arabic_numbers' => true,
        'fix_english_numbers' => false,
        'fix_numeral_symbols' => false,
        'fix_misc_non_persian_chars' => false,
        'fix_hamzeh' => true,
        'fix_hamzeh_arabic' => false,
        'fix_diacritics' => false,
        'remove_diacritics' => false,
        // punctuation
        'fix_dashes' => true,
        'fix_three_dots' => true,
        'normalize_ellipsis' => true,
        'fix_english_quotes_pairs' => true,
        'fix_english_quotes' => false,
        'fix_punctuations' => false,
        'fix_question_mark' => false,
        'cleanup_extra_marks' => true,
        'kashidas_as_parenthetic' => true,
        'normalize_dates' => true,
        // spacing
        'fix_prefix_spacing' => false,
        'fix_suffix_spacing' => false,
        'fix_suffix_misc' => false,
        'fix_spacing_for_braces_and_quotes' => true,
        'fix_spacing_for_punctuations' => false,
        'fix_misc_spacing' => false,
        'cleanup_spacing' => false,
        'cleanup_zwnj' => false,
        'cleanup_rlm' => false,
        // cleanup
        'cleanup_kashidas' => true,
        'normalize_eol' => true,
        'cleanup_line_breaks' => true,
        'cleanup_begin_and_end' => false,
    ];

    /**
     * When and where the fixes apply (I4, B10). An empty post type list means every post type.
     * New installs fix before saving; upgrades from 4.x keep display mode (see maybe_migrate()).
     */
    public const SCOPE_DEFAULTS = [
        'mode' => 'save',
        'post_types' => [],
        'fix_titles' => false,
        'fix_excerpts' => false,
        'apply_in_feeds' => true,
        'apply_in_rest' => true,
    ];

    /** Option names used by Negaresh 4.0 and earlier, removed by the migration (B9). */
    public const LEGACY_OPTIONS = [
        'normalize_eol', 'decode_html_entities', 'fix_dashes', 'fix_three_dots', 'normalize_ellipsis',
        'normalize_dates', 'fix_english_quotes_pairs', 'fix_english_quotes', 'fix_hamzeh',
        'fix_hamzeh_arabic', 'cleanup_rlm', 'cleanup_zwnj', 'fix_arabic_numbers', 'fix_english_numbers',
        'fix_numeral_symbols', 'fix_misc_non_persian_chars', 'fix_punctuations', 'fix_question_mark',
        'fix_prefix_spacing', 'fix_suffix_spacing', 'fix_suffix_misc', 'fix_spacing_for_braces_and_quotes',
        'fix_spacing_for_punctuations', 'fix_diacritics', 'remove_diacritics', 'fix_persian_glyphs',
        'fix_misc_spacing', 'cleanup_spacing', 'cleanup_line_breaks', 'cleanup_begin_and_end',
    ];

    /**
     * @return array<string, bool|string|list<string>>
     */
    public static function defaults(): array
    {
        return self::RULE_DEFAULTS + self::SCOPE_DEFAULTS;
    }

    /**
     * Saved options merged over the defaults, so the front end always has a full set (B5).
     * Values are normalized: whatever is stored, flags are bool, mode is one of MODES and
     * post_types a list of strings.
     *
     * @return array<string, bool|string|list<string>>
     */
    public function get(): array
    {
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $options = [];
        foreach (self::defaults() as $key => $default) {
            $value = array_key_exists($key, $saved) ? $saved[$key] : $default;
            if ('post_types' === $key) {
                $options[$key] = is_array($value) ? array_values(array_map('strval', array_filter($value, 'is_scalar'))) : [];
            } elseif ('mode' === $key) {
                $options[$key] = in_array($value, self::MODES, true) ? $value : self::SCOPE_DEFAULTS['mode'];
            } else {
                $options[$key] = (bool) $value;
            }
        }

        return $options;
    }

    /**
     * 'save' (fix the stored text) or 'display' (fix only what is shown).
     */
    public function mode(): string
    {
        $mode = $this->get()['mode'];
        return is_string($mode) ? $mode : self::SCOPE_DEFAULTS['mode'];
    }

    /**
     * A true/false option that is not a rule: fix_titles, fix_excerpts, apply_in_feeds, apply_in_rest.
     */
    public function flag(string $key): bool
    {
        $options = $this->get();
        return isset($options[$key]) && true === $options[$key];
    }

    /**
     * Identifies what a save fixes (the rules, and whether titles and excerpts are included);
     * stored with posts fixed on save.
     */
    public function rules_hash(): string
    {
        $bits = '';
        foreach ($this->rules() as $key => $on) {
            $bits .= $key . ($on ? '=1;' : '=0;');
        }
        foreach (['fix_titles', 'fix_excerpts'] as $key) {
            $bits .= $key . ($this->flag($key) ? '=1;' : '=0;');
        }
        return md5($bits);
    }

    /**
     * Post types to fix; an empty list means every post type.
     *
     * @return list<string>
     */
    public function post_types(): array
    {
        $post_types = $this->get()['post_types'];
        return is_array($post_types) ? $post_types : [];
    }

    /**
     * The Virastar rules only, as the admin set them.
     *
     * @return array<string, bool>
     */
    public function rules(): array
    {
        $options = $this->get();
        $rules = [];
        foreach (array_keys(self::RULE_DEFAULTS) as $key) {
            $rules[$key] = true === $options[$key];
        }
        return $rules;
    }

    /**
     * Sanitize callback for register_setting(). Unchecked boxes are missing from the input, so
     * every rule not present is off. Idempotent: WordPress may run it twice on the first save.
     * The "Reset rules to defaults" button submits the same form with `reset_rules` set: the rules
     * go back to their defaults, everything else is saved as shown (I5).
     */
    /**
     * @param mixed $input raw value from the settings form (or another update_option() call)
     * @return array<string, bool|string|list<string>>
     */
    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $clean = [];

        $reset = !empty($input['reset_rules']);
        foreach (self::RULE_DEFAULTS as $key => $default) {
            $clean[$key] = $reset ? $default : !empty($input[$key]);
        }

        $clean['mode'] = isset($input['mode']) && in_array($input['mode'], self::MODES, true)
            ? $input['mode'] : self::SCOPE_DEFAULTS['mode'];

        $post_types = isset($input['post_types']) && is_array($input['post_types']) ? $input['post_types'] : [];
        $post_types = array_map('sanitize_key', array_map('strval', $post_types));
        $clean['post_types'] = array_values(array_intersect($post_types, array_keys($this->public_post_types())));

        $clean['fix_titles'] = !empty($input['fix_titles']);
        $clean['fix_excerpts'] = !empty($input['fix_excerpts']);

        $clean['apply_in_feeds'] = !empty($input['apply_in_feeds']);
        $clean['apply_in_rest'] = !empty($input['apply_in_rest']);

        return $clean;
    }

    /**
     * Upgrades stored data once per DB_VERSION.
     * - 2 (4.1, B9): moves the 30 unprefixed 4.0 options into `negaresh_options`. A site that never
     *   saved the 4.0 settings page has no legacy rows and simply gets the defaults (B5).
     * - 3 (I4): sites upgrading from 4.x keep fixing on display; only new installs fix on save,
     *   so no existing site starts changing stored posts without choosing it.
     */
    public function maybe_migrate(): void
    {
        $version = (int) get_option(self::DB_VERSION_OPTION, 0);
        if ($version >= self::DB_VERSION) {
            return;
        }

        // An install is "existing" when an earlier version stored anything.
        $existing = $version > 0;
        $steps = [2 => 'migrate_to_2', 3 => 'migrate_to_3'];
        foreach ($steps as $target => $step) {
            if ($version < $target) {
                $existing = $this->$step($existing) || $existing;
            }
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * 4.0 → 4.1 (B9): the 30 unprefixed options become one `negaresh_options` array.
     *
     * @return bool whether 4.0 settings were found
     */
    private function migrate_to_2(bool $existing): bool
    {
        $legacy = [];
        foreach (self::LEGACY_OPTIONS as $name) {
            $value = get_option($name, null);
            if (null !== $value) {
                $legacy[$name] = $value;
            }
        }

        if ($legacy && false === get_option(self::OPTION, false)) {
            $options = self::RULE_DEFAULTS;
            foreach ($legacy as $name => $value) {
                // decode_html_entities is gone (B2); every other 4.0 option keeps its name.
                if (array_key_exists($name, self::RULE_DEFAULTS)) {
                    $options[$name] = ('1' === (string) $value);
                }
            }
            update_option(self::OPTION, $options);
        }

        foreach (array_keys($legacy) as $name) {
            delete_option($name);
        }

        return [] !== $legacy;
    }

    /**
     * 4.1 → 4.2 (I4): existing sites keep fixing on display; only new installs fix on save.
     *
     * @return bool always false: this step finds nothing new about the install
     */
    private function migrate_to_3(bool $existing): bool
    {
        if ($existing) {
            $options = get_option(self::OPTION, []);
            $options = is_array($options) ? $options : [];
            $options['mode'] = 'display';
            update_option(self::OPTION, $options);
        }
        return false;
    }

    /** Removes everything Negaresh stores, including 4.0 leftovers (B19). */
    public static function delete_all(): void
    {
        delete_option(self::OPTION);
        delete_option(self::DB_VERSION_OPTION);
        delete_post_meta_by_key(self::FIXED_META);
        foreach (self::LEGACY_OPTIONS as $name) {
            delete_option($name);
        }
    }

    public function add_page(): void
    {
        add_options_page(
            esc_html__('Negaresh Options', 'negaresh'),
            esc_html__('Negaresh', 'negaresh'),
            'manage_options',
            self::PAGE,
            [$this, 'render_page']
        );
    }

    public function register(): void
    {
        register_setting(self::GROUP, self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => self::defaults(),
            'show_in_rest' => false,
        ]);

        $sections = $this->sections();
        foreach ($sections as $section => $title) {
            add_settings_section('negaresh_' . $section, $title, '__return_false', self::PAGE);
        }

        add_settings_field('negaresh_mode', __('When to fix', 'negaresh'), [$this, 'render_mode'], self::PAGE, 'negaresh_mode');

        foreach ($this->rule_labels() as $key => $field) {
            add_settings_field(
                'negaresh_' . $key,
                $field['label'],
                [$this, 'render_checkbox'],
                self::PAGE,
                'negaresh_' . $field['section'],
                ['key' => $key, 'label_for' => 'negaresh_' . $key, 'example' => $field['example']]
            );
        }

        add_settings_field('negaresh_post_types', __('Post types', 'negaresh'), [$this, 'render_post_types'], self::PAGE, 'negaresh_scope');
        $scope_boxes = [
            'fix_titles' => __('Fix post titles', 'negaresh'),
            'fix_excerpts' => __('Fix excerpts written by hand', 'negaresh'),
            'apply_in_feeds' => __('Fix text in RSS feeds', 'negaresh'),
            'apply_in_rest' => __('Fix text in the REST API', 'negaresh'),
        ];
        foreach ($scope_boxes as $key => $label) {
            add_settings_field(
                'negaresh_' . $key,
                $label,
                [$this, 'render_checkbox'],
                self::PAGE,
                'negaresh_scope',
                ['key' => $key, 'label_for' => 'negaresh_' . $key, 'example' => '']
            );
        }
    }

    /**
     * @return array<string, string> section slug => title
     */
    public function sections(): array
    {
        return [
            'mode' => __('Mode', 'negaresh'),
            'characters' => __('Letters and numbers', 'negaresh'),
            'punctuation' => __('Punctuation', 'negaresh'),
            'spacing' => __('Spacing and half spaces', 'negaresh'),
            'cleanup' => __('Cleanup', 'negaresh'),
            'scope' => __('Where to apply', 'negaresh'),
        ];
    }

    /**
     * Every label is a literal string so it can be translated (B14).
     * Examples are shown as they are and are not translated.
     *
     * @return array<string, array{section: string, label: string, example: string}>
     */
    public function rule_labels(): array
    {
        return [
            'fix_persian_glyphs' => [
                'section' => 'characters',
                'label' => __('Convert presentation form glyphs to standard Persian letters', 'negaresh'),
                'example' => 'ﺳﻼﻡ → سلام',
            ],
            'fix_arabic_numbers' => [
                'section' => 'characters',
                'label' => __('Convert Arabic digits to Persian digits', 'negaresh'),
                'example' => '٤٥٦ → ۴۵۶',
            ],
            'fix_english_numbers' => [
                'section' => 'characters',
                'label' => __('Convert English digits to Persian digits', 'negaresh'),
                'example' => '123 → ۱۲۳',
            ],
            'fix_numeral_symbols' => [
                'section' => 'characters',
                'label' => __('Use Persian percent, decimal and thousands separators', 'negaresh'),
                'example' => '۵۰% → ۵۰٪',
            ],
            'fix_misc_non_persian_chars' => [
                'section' => 'characters',
                'label' => __('Replace Arabic Kaf, Yeh and Heh with Persian letters', 'negaresh'),
                'example' => 'كي → کی',
            ],
            'fix_hamzeh' => [
                'section' => 'characters',
                'label' => __('Write the ezafe after a final Heh as Hamzeh', 'negaresh'),
                'example' => 'خانه ی من → خانهٔ من',
            ],
            'fix_hamzeh_arabic' => [
                'section' => 'characters',
                'label' => __('Convert Arabic Teh Marbuta to Heh with Hamzeh (needs the Hamzeh rule)', 'negaresh'),
                'example' => 'مدرسة ما → مدرسهٔ ما',
            ],
            'fix_diacritics' => [
                'section' => 'characters',
                'label' => __('Clean up spaces and repeats around diacritics', 'negaresh'),
                'example' => '',
            ],
            'remove_diacritics' => [
                'section' => 'characters',
                'label' => __('Remove all diacritics', 'negaresh'),
                'example' => 'کِتاب → کتاب',
            ],
            'fix_dashes' => [
                'section' => 'punctuation',
                'label' => __('Convert double and triple hyphens to en and em dashes', 'negaresh'),
                'example' => '-- → –',
            ],
            'fix_three_dots' => [
                'section' => 'punctuation',
                'label' => __('Convert three dots to an ellipsis character', 'negaresh'),
                'example' => '... → …',
            ],
            'normalize_ellipsis' => [
                'section' => 'punctuation',
                'label' => __('Merge repeated ellipses and put one space after them', 'negaresh'),
                'example' => '',
            ],
            'fix_english_quotes_pairs' => [
                'section' => 'punctuation',
                'label' => __('Replace curly English quotes with Persian guillemets', 'negaresh'),
                'example' => '“متن” → «متن»',
            ],
            'fix_english_quotes' => [
                'section' => 'punctuation',
                'label' => __('Replace straight quotes with Persian guillemets', 'negaresh'),
                'example' => '"متن" → «متن»',
            ],
            'fix_punctuations' => [
                'section' => 'punctuation',
                'label' => __('Use the Persian comma and semicolon', 'negaresh'),
                'example' => ', ; → ، ؛',
            ],
            'fix_question_mark' => [
                'section' => 'punctuation',
                'label' => __('Use the Persian question mark', 'negaresh'),
                'example' => '? → ؟',
            ],
            'cleanup_extra_marks' => [
                'section' => 'punctuation',
                'label' => __('Merge repeated question and exclamation marks', 'negaresh'),
                'example' => '!!! → !',
            ],
            'kashidas_as_parenthetic' => [
                'section' => 'punctuation',
                'label' => __('Treat a Kashida next to a space as a dash', 'negaresh'),
                'example' => '',
            ],
            'normalize_dates' => [
                'section' => 'punctuation',
                'label' => __('Reorder dates written with slashes to year/month/day', 'negaresh'),
                'example' => '12/5/1402 → 1402/5/12',
            ],
            'fix_prefix_spacing' => [
                'section' => 'spacing',
                'label' => __('Join the prefixes می, نمی and بی with a half space', 'negaresh'),
                'example' => 'می روم → می‌روم',
            ],
            'fix_suffix_spacing' => [
                'section' => 'spacing',
                'label' => __('Join common suffixes with a half space', 'negaresh'),
                'example' => 'کتاب ها → کتاب‌ها',
            ],
            'fix_suffix_misc' => [
                'section' => 'spacing',
                'label' => __('Fix the ای suffix after a final Heh', 'negaresh'),
                'example' => 'خانه‌ئی → خانه‌ای',
            ],
            'fix_spacing_for_braces_and_quotes' => [
                'section' => 'spacing',
                'label' => __('Fix spaces inside and around brackets and quotes', 'negaresh'),
                'example' => '( متن ) → (متن)',
            ],
            'fix_spacing_for_punctuations' => [
                'section' => 'spacing',
                'label' => __('Remove the space before punctuation and keep one after it', 'negaresh'),
                'example' => 'سلام ، → سلام،',
            ],
            'fix_misc_spacing' => [
                'section' => 'spacing',
                'label' => __('Remove the space before honorifics and numbered references', 'negaresh'),
                'example' => 'محمد (ص) → محمد(ص)',
            ],
            'cleanup_spacing' => [
                'section' => 'spacing',
                'label' => __('Replace repeated spaces with one space', 'negaresh'),
                'example' => '',
            ],
            'cleanup_zwnj' => [
                'section' => 'spacing',
                'label' => __('Remove extra or misplaced half spaces', 'negaresh'),
                'example' => '',
            ],
            'cleanup_rlm' => [
                'section' => 'spacing',
                'label' => __('Replace right to left marks between letters with half spaces', 'negaresh'),
                'example' => '',
            ],
            'cleanup_kashidas' => [
                'section' => 'cleanup',
                'label' => __('Remove Kashidas inside words', 'negaresh'),
                'example' => 'سـلام → سلام',
            ],
            'normalize_eol' => [
                'section' => 'cleanup',
                'label' => __('Use Unix line endings', 'negaresh'),
                'example' => '',
            ],
            'cleanup_line_breaks' => [
                'section' => 'cleanup',
                'label' => __('Reduce more than two line breaks to two', 'negaresh'),
                'example' => '',
            ],
            'cleanup_begin_and_end' => [
                'section' => 'cleanup',
                'label' => __('Trim spaces at the start of lines and of the text', 'negaresh'),
                'example' => '',
            ],
        ];
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap negaresh-settings">
            <h1><?php esc_html_e('Negaresh Options', 'negaresh'); ?></h1>

            <div class="negaresh-preview card">
                <h2><?php esc_html_e('Try it', 'negaresh'); ?></h2>
                <p>
                    <?php
                    esc_html_e(
                        'Type or paste some text (HTML is fine). The result uses the boxes as they are checked on this page, before you save.',
                        'negaresh'
                    );
                    ?>
                </p>
                <label for="negaresh-preview-input" class="screen-reader-text"><?php esc_html_e('Text to fix', 'negaresh'); ?></label>
                <textarea id="negaresh-preview-input" dir="rtl" rows="4" class="large-text"></textarea>
                <label for="negaresh-preview-output"><?php esc_html_e('Result', 'negaresh'); ?></label>
                <textarea id="negaresh-preview-output" dir="rtl" rows="4" class="large-text" readonly></textarea>
                <p class="negaresh-preview-status" aria-live="polite"></p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::GROUP);
                do_settings_sections(self::PAGE);
                ?>
                <p class="submit">
                    <?php submit_button('', 'primary', 'submit', false); // empty text: WordPress's own "Save Changes" ?>
                    <?php
                    submit_button(
                        __('Reset rules to defaults', 'negaresh'),
                        'secondary negaresh-reset',
                        self::OPTION . '[reset_rules]',
                        false
                    );
                    ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Settings link first in the plugin's row on the Plugins screen (I5).
     *
     * @param array<int|string, string> $links
     * @return array<int|string, string>
     */
    public function action_links($links): array
    {
        $links = is_array($links) ? $links : [];
        $settings = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=' . self::PAGE)),
            esc_html__('Settings', 'negaresh')
        );
        array_unshift($links, $settings);
        return $links;
    }

    /**
     * Preview script and styles, on the Negaresh settings page only.
     *
     * @param mixed $hook_suffix
     */
    public function enqueue_assets($hook_suffix): void
    {
        if ('settings_page_' . self::PAGE !== $hook_suffix) {
            return;
        }
        $base = plugins_url('assets/', NEGARESH_FILE);
        wp_enqueue_style('negaresh-admin', $base . 'admin.css', [], NEGARESH_VERSION);
        wp_enqueue_script('negaresh-admin', $base . 'admin.js', ['wp-api-fetch'], NEGARESH_VERSION, true);
        wp_localize_script('negaresh-admin', 'negareshAdmin', [
            'rules' => array_keys(self::RULE_DEFAULTS),
            'working' => __('Working…', 'negaresh'),
            'failed' => __('The preview could not be made.', 'negaresh'),
            'confirmReset' => __('Set every rule back to its default? Unsaved changes to the rules are lost.', 'negaresh'),
        ]);
    }

    /**
     * @param array{key: string, example?: string} $args
     */
    public function render_checkbox(array $args): void
    {
        $options = $this->get();
        $key = $args['key'];
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
            esc_attr('negaresh_' . $key),
            esc_attr(self::OPTION . '[' . $key . ']'),
            checked(!empty($options[$key]), true, false)
        );
        if (!empty($args['example'])) {
            // Examples are Persian and shown right to left, so "before" is read first on the right:
            // the arrow has to point left. Arrows are not mirrored by the browser in RTL text.
            printf(' <code dir="rtl">%s</code>', esc_html(str_replace(' → ', ' ← ', $args['example'])));
        }
    }

    public function render_mode(): void
    {
        $mode = $this->mode();
        $choices = [
            'save' => __(
                'When a post is saved: the stored text is corrected, so what you see in the editor is what readers get',
                'negaresh'
            ),
            'display' => __('When a post is displayed: the stored text is never changed', 'negaresh'),
        ];
        echo '<fieldset>';
        foreach ($choices as $value => $label) {
            printf(
                '<label><input type="radio" name="%1$s" value="%2$s" %3$s /> %4$s</label><br />',
                esc_attr(self::OPTION . '[mode]'),
                esc_attr($value),
                checked($mode, $value, false),
                esc_html($label)
            );
        }
        echo '<p class="description">'
            . esc_html__(
                'Saving changes your posts and cannot be undone. Posts saved before are still fixed on display until they are saved again.',
                'negaresh'
            )
            . '</p>';
        echo '</fieldset>';
    }

    public function render_post_types(): void
    {
        $selected = $this->post_types();
        echo '<fieldset>';
        foreach ($this->public_post_types() as $name => $label) {
            printf(
                '<label><input type="checkbox" name="%1$s" value="%2$s" %3$s /> %4$s</label><br />',
                esc_attr(self::OPTION . '[post_types][]'),
                esc_attr($name),
                checked(in_array($name, $selected, true), true, false),
                esc_html($label)
            );
        }
        echo '<p class="description">' . esc_html__('Leave all unchecked to fix every post type.', 'negaresh') . '</p>';
        echo '</fieldset>';
    }

    /** @return array<string,string> post type name => label */
    private function public_post_types(): array
    {
        $types = [];
        foreach (get_post_types(['public' => true], 'objects') as $name => $type) {
            if ('attachment' !== $name) {
                $types[$name] = $type->labels->singular_name ?? $name;
            }
        }
        return $types;
    }
}
