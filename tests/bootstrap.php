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

require_once __DIR__ . '/stubs/WP_Post.php';

define('NEGARESH_FILE', NEGARESH_PLUGIN_DIR . '/negaresh.php');

// Same files, same order as negaresh.php, without instantiating the plugin.
require_once NEGARESH_PLUGIN_DIR . '/includes/Virastar.php';
require_once NEGARESH_PLUGIN_DIR . '/includes/negaresh-settings.php';
require_once NEGARESH_PLUGIN_DIR . '/includes/negaresh-class.php';
