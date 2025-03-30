
<?php

use Alirezasedghi\Virastar\Virastar;

include('Virastar.php');
require_once('negaresh-settings.php');

class Negaresh {

function __construct(){
    add_action( 'admin_menu', array($this, 'negaresh_menu') );
    add_filter( 'the_content', array($this, 'fix_farsi_typoes') );
    add_action('admin_init', 'settings');
    add_action('init', array($this, 'languages'));
}

function languages(){
    $root = plugin_basename(dirname(__FILE__, 2));
    load_plugin_textdomain('negaresh', false, "$root/languages");
}

function Have_OPT( $option ){
    if (get_option($option) == '1')
        return true;

    return false;
}

function fix_farsi_typoes( $content ) {

    $virastar = new Virastar([
        'normalize_eol' => $this->Have_OPT('normalize_eol'),
        'decode_html_entities' => $this->Have_OPT('decode_html_entities'),
        'fix_dashes' => $this->Have_OPT('fix_dashes'),
        'fix_three_dots' => $this->Have_OPT('fix_three_dots'),
        'normalize_ellipsis' => $this->Have_OPT('normalize_ellipsis'),
        'normalize_dates' => $this->Have_OPT('normalize_dates'),
        'fix_english_quotes_pairs' => $this->Have_OPT('fix_english_quotes_pairs'),
        'fix_english_quotes' => $this->Have_OPT('fix_english_quotes'),
        'fix_hamzeh' => $this->Have_OPT('fix_hamzeh'),
        'fix_hamzeh_arabic' => $this->Have_OPT('fix_hamzeh_arabic'),
        'cleanup_rlm' => $this->Have_OPT('cleanup_rlm'),
        'cleanup_zwnj' => $this->Have_OPT('cleanup_zwnj'),
        'fix_arabic_numbers' => $this->Have_OPT('fix_arabic_numbers'),
        'fix_english_numbers' => $this->Have_OPT('fix_english_numbers'),
        'fix_numeral_symbols' => $this->Have_OPT('fix_numeral_symbols'),
        'fix_misc_non_persian_chars' => $this->Have_OPT('fix_misc_non_persian_chars'),
        'fix_punctuations' => $this->Have_OPT('fix_punctuations'),
        'fix_question_mark' => $this->Have_OPT('fix_question_mark'),
        'fix_prefix_spacing' => $this->Have_OPT('fix_prefix_spacing'),
        'fix_suffix_spacing' => $this->Have_OPT('fix_suffix_spacing'),
        'fix_suffix_misc' => $this->Have_OPT('fix_suffix_misc'),
        'fix_spacing_for_braces_and_quotes' => $this->Have_OPT('fix_spacing_for_braces_and_quotes'),
        'fix_spacing_for_punctuations' => $this->Have_OPT('fix_spacing_for_punctuations'),
        'fix_diacritics' => $this->Have_OPT('fix_diacritics'),
        'remove_diacritics' => $this->Have_OPT('remove_diacritics'),
        'fix_persian_glyphs' => $this->Have_OPT('fix_persian_glyphs'),
        'fix_misc_spacing' => $this->Have_OPT('fix_misc_spacing'),
        'cleanup_spacing' => $this->Have_OPT('cleanup_spacing'),
        'cleanup_line_breaks' => $this->Have_OPT('cleanup_line_breaks'),
        'cleanup_begin_and_end' => $this->Have_OPT('cleanup_begin_and_end')
    ]);

    try {
        $content = $virastar->cleanup($content);
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    return nl2br($content, true);
}

function negaresh_menu() {
    add_options_page( esc_html__('Negaresh Options', 'negaresh') , esc_html__('Negaresh', 'negaresh'), 'manage_options', 'negaresh-options', array($this, 'negaresh_html') );
}

function negaresh_html() { ?>
    <div class="wrap">
    <h1><?php esc_html_e('Negaresh Options', 'negaresh') ?></h1>
    <form action="options.php" method="POST">
    <?php
        settings_fields('wordcountplugin');
        do_settings_sections('negaresh-options');
        submit_button();
    ?>
    </form>
    </div>
<?php }

}