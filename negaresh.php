<?php
/**
 * @package Negaresh
 * @version 1.0.1
 */
/*
Plugin Name: Negaresh
Plugin URI: http://wordpress.org/plugins/negaresh/
Description: Negaresh tries to fix your Farsi (Persian) typos in Wordpress.
Version: 1.0.1
Text Domain: negaresh
Domain Path: negaresh-languages
Author: Lord Arma
Author URI: http://LordArma.com/
License: GPL3
*/

function fix_farsi_typoes( $content ) {

    $common_prefixes = array(
        'می',
        'نمی',
    );

    foreach ($common_prefixes as $word) {
        $content = str_replace(" " . $word . " ", " " . $word . "‌", $content);
    }

    $common_suffixes = array(
        'هایی',
        'های',
    );

    foreach ($common_suffixes as $word) {
        $content = str_replace(" " . $word . " ", "‌" . $word + " ", $content);
    }

    $charsـwith_space_before = array(
        '.',
        '،',
        '؟',
        '!',
        ':',
        '؛',
        '»',
        ')',
        ']',
        '}',
        '…',
    );

    foreach ($charsـwith_space_before as $char) {
        $content = str_replace(" " . $char, $char, $content);
    }

    $charsـwith_space_after = array(
        '«',
        '(',
        '[',
        '{',
    );

    foreach ($charsـwith_space_after as $char) {
        $content = str_replace($char . " ", $char, $content);
    }

    $arabic_chars = array(
        'ي' => 'ی',
        'ك' => 'ک',
    );

    foreach ($arabic_chars as $char_ar => $char_fa) {
        $content = str_replace($char_ar, $char_fa, $content);
    }

    $arabic_nums = array(
        '٠' => '۰',
        '١' => '۱',
        '٢' => '۲',
        '٣' => '۳',
        '٤' => '۴',
        '٥' => '۵',
        '٦' => '۶',
        '٧' => '۷',
        '٨' => '۸',
        '٩' => '۹',
    );

    foreach ($arabic_nums as $char_ar => $char_fa) {
        $content = str_replace($char_ar, $char_fa, $content);
    }

    $english_nums = array(
        '0' => '۰',
        '1' => '۱',
        '2' => '۲',
        '3' => '۳',
        '4' => '۴',
        '5' => '۵',
        '6' => '۶',
        '7' => '۷',
        '8' => '۸',
        '9' => '۹',
    );

    
    foreach ($english_nums as $char_en => $char_fa) {
        // $content = str_replace($char_en, $char_fa, $content);
    }

    return $content;
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
