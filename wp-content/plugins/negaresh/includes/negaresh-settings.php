<?php

function settings() {
    add_settings_section('wcp_first_section', null, null, 'negaresh-options');

    $feild_name = 'normalize_eol';
    $is_default = '1';
    add_settings_field($feild_name, __('Replace Windows End of Lines with Unix EOL', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'decode_html_entities';
    $is_default = '1';
    add_settings_field($feild_name, __('Converts Numeral and Selected HTML Character Sets Into Original Characters', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_dashes';
    $is_default = '1';
    add_settings_field($feild_name, __('Replaces Triple Dash to mdash and Replaces Double Dash to ndash', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));


    $feild_name = 'fix_three_dots';
    $is_default = '1';
    add_settings_field($feild_name, __('Removes Spaces Between Dots and Replaces Three Dots with Ellipsis Character', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'normalize_ellipsis';
    $is_default = '1';
    add_settings_field($feild_name, __('Replaces More Than One Ellipsis with One and Replaces (space|tab|zwnj) After Ellipsis with One Space', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    
    $feild_name = 'normalize_dates';
    $is_default = '1';
    add_settings_field($feild_name, __('Reorders Date Parts with Slash as Delimiter', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_english_quotes_pairs';
    $is_default = '1';
    add_settings_field($feild_name, __('Replaces English Quote Pairs with Their Farsi Equivalent', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_english_quotes';
    $is_default = '0';
    add_settings_field($feild_name, __('Replaces English Quote Marks with Their Farsi Equivalent', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_hamzeh';
    $is_default = '1';
    add_settings_field($feild_name, __('Fix Hamzeh', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_hamzeh_arabic';
    $is_default = '0';
    add_settings_field($feild_name, __('Fix Arabic Hamzeh', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'cleanup_rlm';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'cleanup_zwnj';
    $is_default = '0';
    add_settings_field($feild_name, __('Cleanup ZWNJ', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_arabic_numbers';
    $is_default = '1';
    add_settings_field($feild_name, __('Replaces Arabic Numbers with Their Farsi Equivalent', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_english_numbers';
    $is_default = '0';
    add_settings_field($feild_name, __('Replaces English Numbers with Their Farsi Equivalent', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_numeral_symbols';
    $is_default = '0';
    add_settings_field($feild_name, __('Fix Numeral Symbols', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_misc_non_persian_chars';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_punctuations';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_question_mark';
    $is_default = '0';
    add_settings_field($feild_name, __("Replaces Question Marks with It's Farsi Equivalent", 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_prefix_spacing';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_suffix_spacing';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_suffix_misc';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_spacing_for_braces_and_quotes';
    $is_default = '1';
    add_settings_field($feild_name, __('Removes Inside Spaces and More Than One Outside Braces and Quotes', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_spacing_for_punctuations';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_diacritics';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'remove_diacritics';
    $is_default = '0';
    add_settings_field($feild_name, __('Removes All Diacritic Characters', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_persian_glyphs';
    $is_default = '1';
    add_settings_field($feild_name, __('Converts Incorrect Farsi Glyphs to Standard Characters', 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'fix_misc_spacing';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'cleanup_spacing';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'cleanup_line_breaks';
    $feild_title = 'Cleans More Than Two Contiguous Line Breaks';
    $is_default = '1';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

    $feild_name = 'cleanup_begin_and_end';
    $feild_title = $feild_name;
    $is_default = '0';
    add_settings_field($feild_name, __($feild_title, 'negaresh'), 'checkboxHTML', 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
    register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));
}

function checkboxHTML($args) { ?>
    <input type="checkbox" name="<?php echo $args['theName'] ?>" value="1" <?php checked(get_option($args['theName']), '1') ?>>
  <?php }
