<?php

/**
 * Plugin Name: Negaresh
 * Plugin URI: https://github.com/LordArma/negaresh
 * Description: Negaresh tries to fix your Farsi (Persian) typos in WordPress.
 * Version: 5.0.0
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

define('NEGARESH_VERSION', '5.0.0');
define('NEGARESH_FILE', __FILE__);

require_once __DIR__ . '/includes/Virastar.php';
require_once __DIR__ . '/includes/negaresh-settings.php';
require_once __DIR__ . '/includes/negaresh-class.php';
require_once __DIR__ . '/includes/negaresh-editor.php';
require_once __DIR__ . '/includes/negaresh-bulk.php';
require_once __DIR__ . '/includes/negaresh-bulk-page.php';

// Real globals: WP-CLI loads plugin files from inside a function, where a plain assignment would
// only create local variables.
$GLOBALS['negaresh_settings'] = new Negaresh_Settings();
$GLOBALS['negaresh'] = new Negaresh($GLOBALS['negaresh_settings']);
$GLOBALS['negaresh_editor'] = new Negaresh_Editor($GLOBALS['negaresh'], $GLOBALS['negaresh_settings']);
$GLOBALS['negaresh_bulk'] = new Negaresh_Bulk($GLOBALS['negaresh'], $GLOBALS['negaresh_settings']);
$GLOBALS['negaresh_bulk_page'] = new Negaresh_Bulk_Page($GLOBALS['negaresh_bulk']);

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/negaresh-cli.php';
    WP_CLI::add_command('negaresh', new Negaresh_CLI($GLOBALS['negaresh_bulk'], $GLOBALS['negaresh'], $GLOBALS['negaresh_settings']));
}
