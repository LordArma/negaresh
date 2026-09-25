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
    public const DB_VERSION = 2;
    public const PAGE = 'negaresh-options';
    public const GROUP = 'negaresh';

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

    /** Where the fixes apply (B10). An empty post type list means every post type. */
    public const SCOPE_DEFAULTS = [
        'post_types' => [],
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
     * @return array<string, bool|list<string>>
     */
    public static function defaults(): array
    {
        return self::RULE_DEFAULTS + self::SCOPE_DEFAULTS;
    }

    /**
     * Saved options merged over the defaults, so the front end always has a full set (B5).
     * Values are normalized: whatever is stored, flags are bool and post_types a list of strings.
     *
     * @return array<string, bool|list<string>>
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
            } else {
                $options[$key] = (bool) $value;
            }
        }

        return $options;
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
     */
    /**
     * @param mixed $input raw value from the settings form (or another update_option() call)
     * @return array<string, bool|list<string>>
     */
    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $clean = [];

        foreach (array_keys(self::RULE_DEFAULTS) as $key) {
            $clean[$key] = !empty($input[$key]);
        }

        $post_types = isset($input['post_types']) && is_array($input['post_types']) ? $input['post_types'] : [];
        $post_types = array_map('sanitize_key', array_map('strval', $post_types));
        $clean['post_types'] = array_values(array_intersect($post_types, array_keys($this->public_post_types())));

        $clean['apply_in_feeds'] = !empty($input['apply_in_feeds']);
        $clean['apply_in_rest'] = !empty($input['apply_in_rest']);

        return $clean;
    }

    /**
     * Moves 4.0 settings into `negaresh_options` once (B9). A site that never saved the 4.0
     * settings page has no legacy rows and simply gets the defaults (B5).
     */
    public function maybe_migrate(): void
    {
        if ((int) get_option(self::DB_VERSION_OPTION, 0) >= self::DB_VERSION) {
            return;
        }

        $legacy = [];
        foreach (self::LEGACY_OPTIONS as $name) {
            $value = get_option($name, null);
            if (null !== $value) {
                $legacy[$name] = $value;
            }
        }

        if ($legacy && false === get_option(self::OPTION, false)) {
            $options = self::defaults();
            foreach ($legacy as $name => $value) {
                // decode_html_entities is gone (B2); every other 4.0 option keeps its name as a rule key.
                if (array_key_exists($name, self::RULE_DEFAULTS)) {
                    $options[$name] = ('1' === (string) $value);
                }
            }
            update_option(self::OPTION, $options);
        }

        foreach (array_keys($legacy) as $name) {
            delete_option($name);
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /** Removes everything Negaresh stores, including 4.0 leftovers (B19). */
    public static function delete_all(): void
    {
        delete_option(self::OPTION);
        delete_option(self::DB_VERSION_OPTION);
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
        <div class="wrap">
            <h1><?php esc_html_e('Negaresh Options', 'negaresh'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::GROUP);
                do_settings_sections(self::PAGE);
                submit_button();
                ?>
            </form>
        </div>
        <?php
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
            printf(' <code dir="rtl">%s</code>', esc_html($args['example']));
        }
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
