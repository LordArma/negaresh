<?php
/**
 * @package Negaresh
 * @version 2.0.1
 */
/*
Plugin Name: Negaresh
Plugin URI: http://wordpress.org/plugins/negaresh/
Description: Negaresh tries to fix your Farsi (Persian) typos in Wordpress.
Version: 2.0.1
Text Domain: negaresh
Domain Path: negaresh-languages
Author: Lord Arma
Author URI: http://LordArma.com/
License: GPL3
*/

use Alirezasedghi\Virastar\Virastar;

include('Virastar.php');

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
        'fix_spacing_for_braces_and_quotes' => true,
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

add_filter( 'the_content', 'fix_farsi_typoes' );

function negaresh_menu() {
	add_options_page( 'Negaresh Options', 'Negaresh', 'manage_options', 'negaresh', 'negaresh_options' );
}

function negaresh_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'negaresh' ) );
	}
	echo '<div class="wrap"><p>';
	_e("Currently, Negaresh doesn't support options. This feature will be added to Negaresh soon.", "negaresh");
	echo '</p></div>';
}

add_action( 'admin_menu', 'negaresh_menu' );
