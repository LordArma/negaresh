<?php
/**
 * @package Negaresh
 * @version 4.0.0
 */
/*
Plugin Name: Negaresh
Plugin URI: https://github.com/LordArma/negaresh
Description: Negaresh tries to fix your Farsi (Persian) typos in Wordpress.
Version: 4.0.0
Text Domain: negaresh
Domain Path: /languages
Author: Lord Arma
Author URI: http://LordArma.com/
Requires at least: 5.0
Tested up to: 6.1.1
Requires PHP: 7.0
*/

if (! defined('ABSPATH')) exit;

require_once(plugin_dir_path(__FILE__).'/includes/negaresh-scripts.php');
require_once(plugin_dir_path(__FILE__).'/includes/negaresh-class.php');

$negaresh = new Negaresh();