<?php
/**
 * @package Negaresh
 * @version 3.0.0
 */
/*
Plugin Name: Negaresh
Plugin URI: http://wordpress.org/plugins/negaresh/
Description: Negaresh tries to fix your Farsi (Persian) typos in Wordpress.
Version: 3.0.0
Text Domain: negaresh
Domain Path: /languages
Author: Lord Arma
Author URI: http://LordArma.com/
Requires at least: 5.0
Tested up to: 6.1.1
Requires PHP: 7.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (! defined('ABSPATH')) exit;

use Alirezasedghi\Virastar\Virastar;

include('Virastar.php');

class Negaresh {

    function __construct(){
        add_action( 'admin_menu', array($this, 'negaresh_menu') );
        add_filter( 'the_content', array($this, 'fix_farsi_typoes') );
        add_action('admin_init', array($this, 'settings'));
        add_action('init', array($this, 'languages'));
    }

    function languages(){
      load_plugin_textdomain('negaresh', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    function settings() {
        add_settings_section('wcp_first_section', null, null, 'negaresh-options');
    
        $feild_name = 'normalize_eol';
        $is_default = '1';
        add_settings_field($feild_name, __('Replace Windows End of Lines with Unix EOL', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'decode_html_entities';
        $is_default = '1';
        add_settings_field($feild_name, __('Converts Numeral and Selected HTML Character Sets Into Original Characters', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_dashes';
        $is_default = '1';
        add_settings_field($feild_name, __('Replaces Triple Dash to mdash and Replaces Double Dash to ndash', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));


        $feild_name = 'fix_three_dots';
        $is_default = '1';
        add_settings_field($feild_name, __('Removes Spaces Between Dots and Replaces Three Dots with Ellipsis Character', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'normalize_ellipsis';
        $is_default = '1';
        add_settings_field($feild_name, __('Replaces More Than One Ellipsis with One and Replaces (space|tab|zwnj) After Ellipsis with One Space', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        
        $feild_name = 'normalize_dates';
        $is_default = '1';
        add_settings_field($feild_name, __('Reorders Date Parts with Slash as Delimiter', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_english_quotes_pairs';
        $is_default = '1';
        add_settings_field($feild_name, __('Replaces English Quote Pairs with Their Farsi Equivalent', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_english_quotes';
        $is_default = '0';
        add_settings_field($feild_name, __('Replaces English Quote Marks with Their Farsi Equivalent', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_hamzeh';
        $is_default = '1';
        add_settings_field($feild_name, __('Fix Hamzeh', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_hamzeh_arabic';
        $is_default = '0';
        add_settings_field($feild_name, __('Fix Arabic Hamzeh', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'cleanup_rlm';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'cleanup_zwnj';
        $is_default = '0';
        add_settings_field($feild_name, __('Cleanup ZWNJ', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_arabic_numbers';
        $is_default = '1';
        add_settings_field($feild_name, __('Replaces Arabic Numbers with Their Farsi Equivalent', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_english_numbers';
        $is_default = '0';
        add_settings_field($feild_name, __('Replaces English Numbers with Their Farsi Equivalent', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_numeral_symbols';
        $is_default = '0';
        add_settings_field($feild_name, __('Fix Numeral Symbols', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_misc_non_persian_chars';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_punctuations';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_question_mark';
        $is_default = '0';
        add_settings_field($feild_name, __("Replaces Question Marks with It's Farsi Equivalent", 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = '';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_prefix_spacing';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_suffix_spacing';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_suffix_misc';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_spacing_for_braces_and_quotes';
        $is_default = '1';
        add_settings_field($feild_name, __('Removes Inside Spaces and More Than One Outside Braces and Quotes', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_spacing_for_punctuations';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_diacritics';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'remove_diacritics';
        $is_default = '0';
        add_settings_field($feild_name, __('Removes All Diacritic Characters', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_persian_glyphs';
        $is_default = '1';
        add_settings_field($feild_name, __('Converts Incorrect Farsi Glyphs to Standard Characters', 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'fix_misc_spacing';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'cleanup_spacing';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'cleanup_line_breaks';
        $feild_title = 'Cleans More Than Two Contiguous Line Breaks';
        $is_default = '1';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));

        $feild_name = 'cleanup_begin_and_end';
        $feild_title = $feild_name;
        $is_default = '0';
        add_settings_field($feild_name, __($feild_title, 'negaresh'), array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => $feild_name));
        register_setting('wordcountplugin', $feild_name, array('sanitize_callback' => 'sanitize_text_field', 'default' => $is_default));
    }
    
    function checkboxHTML($args) { ?>
      <input type="checkbox" name="<?php echo $args['theName'] ?>" value="1" <?php checked(get_option($args['theName']), '1') ?>>
    <?php }

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

$negaresh = new Negaresh();