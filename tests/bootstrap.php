<?php
/**
 * PHPUnit bootstrap. Unit tests run without WordPress; WordPress functions are
 * mocked per test with Brain Monkey (see tests/Unit/TestCase.php).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('NEGARESH_TESTS_DIR', __DIR__);
define('NEGARESH_PLUGIN_DIR', dirname(__DIR__) . '/wp-content/plugins/negaresh');

// The plugin files bail out without ABSPATH.
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// negaresh-class.php pulls in Virastar.php with a plain include(), so load it
// exactly once, here, and never require Virastar.php directly (B4, B12).
require_once NEGARESH_PLUGIN_DIR . '/includes/negaresh-class.php';
