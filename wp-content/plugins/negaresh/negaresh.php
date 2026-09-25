<?php
/**
 * Plugin Name: Negaresh
 * Plugin URI: https://github.com/LordArma/negaresh
 * Description: Negaresh tries to fix your Farsi (Persian) typos in WordPress.
 * Version: 4.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Author: Lord Arma
 * Author URI: https://LordArma.com/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: negaresh
 * Domain Path: /languages
 *
 * @package Negaresh
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NEGARESH_VERSION', '4.1.0');
define('NEGARESH_FILE', __FILE__);

require_once __DIR__ . '/includes/Virastar.php';
require_once __DIR__ . '/includes/negaresh-settings.php';
require_once __DIR__ . '/includes/negaresh-class.php';

$negaresh = new Negaresh(new Negaresh_Settings());
