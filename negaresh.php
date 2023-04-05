<?php
/**
 * @package Negaresh
 * @version 2.2.2
 */
/*
Plugin Name: Negaresh
Plugin URI: http://wordpress.org/plugins/negaresh/
Description: Negaresh tries to fix your Farsi (Persian) typos in Wordpress.
Version: 2.2.2
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
    
        add_settings_field('wcp_location', 'Display Location', array($this, 'locationHTML'), 'negaresh-options', 'wcp_first_section');
        register_setting('wordcountplugin', 'wcp_location', array('sanitize_callback' => array($this, 'sanitizeLocation'), 'default' => '0'));
    
        add_settings_field('wcp_headline', 'Headline Text', array($this, 'headlineHTML'), 'negaresh-options', 'wcp_first_section');
        register_setting('wordcountplugin', 'wcp_headline', array('sanitize_callback' => 'sanitize_text_field', 'default' => 'Post Statistics'));
    
        add_settings_field('wcp_wordcount', 'Word Count', array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => 'wcp_wordcount'));
        register_setting('wordcountplugin', 'wcp_wordcount', array('sanitize_callback' => 'sanitize_text_field', 'default' => '1'));
    
        add_settings_field('wcp_charactercount', 'Character Count', array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => 'wcp_charactercount'));
        register_setting('wordcountplugin', 'wcp_charactercount', array('sanitize_callback' => 'sanitize_text_field', 'default' => '1'));
    
        add_settings_field('wcp_readtime', 'Read Time', array($this, 'checkboxHTML'), 'negaresh-options', 'wcp_first_section', array('theName' => 'wcp_readtime'));
        register_setting('wordcountplugin', 'wcp_readtime', array('sanitize_callback' => 'sanitize_text_field', 'default' => '1'));
    }

    function sanitizeLocation($input) {
        if ($input != '0' AND $input != '1') {
          add_settings_error('wcp_location', 'wcp_location_error', 'Display location must be either beginning or end.');
          return get_option('wcp_location');
        }
        return $input;
      }
    
      function checkboxHTML($args) { ?>
        <input type="checkbox" name="<?php echo $args['theName'] ?>" value="1" <?php checked(get_option($args['theName']), '1') ?>>
      <?php }
    
      function headlineHTML() { ?>
        <input type="text" name="wcp_headline" value="<?php echo esc_attr(get_option('wcp_headline')) ?>">
      <?php }
    
      function locationHTML() { ?>
        <select name="wcp_location">
          <option value="0" <?php selected(get_option('wcp_location'), '0') ?>>Beginning of post</option>
          <option value="1" <?php selected(get_option('wcp_location'), '1') ?>>End of post</option>
        </select>
      <?php }

    function fix_farsi_typoes( $content ) {

        $virastar = new Virastar([
            'normalize_eol' => true,
            'decode_html_entities' => true,
            'fix_dashes' => true,
            'fix_three_dots' => true,
            'normalize_ellipsis' => true,
            'normalize_dates' => true,
            'fix_english_quotes_pairs' => true,
            'fix_english_quotes' => false,
            'fix_hamzeh' => true,
            'fix_hamzeh_arabic' => false,
            'cleanup_rlm' => true,
            'cleanup_zwnj' => true,
            'fix_arabic_numbers' => true,
            'fix_english_numbers' => true,
            'fix_numeral_symbols' => true,
            'fix_misc_non_persian_chars' => true,
            'fix_punctuations' => true,
            'fix_question_mark' => true,
            'fix_prefix_spacing' => true,
            'fix_suffix_spacing' => true,
            'fix_suffix_misc' => true,
            'fix_spacing_for_braces_and_quotes' => false,
            'fix_spacing_for_punctuations' => true,
            'fix_diacritics' => true,
            'remove_diacritics' => false,
            'fix_persian_glyphs' => true,
            'fix_misc_spacing' => true,
            'cleanup_spacing' => true,
            'cleanup_line_breaks' => true,
            'cleanup_begin_and_end' => true
        ]);

        try {
            $content = $virastar->cleanup($content);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return nl2br($content ?? '');
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