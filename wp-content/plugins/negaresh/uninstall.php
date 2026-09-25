<?php

/**
 * Removes every option Negaresh stores, on every site of a network (B19).
 *
 * @package Negaresh
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/negaresh-settings.php';

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $negaresh_site_id) {
        switch_to_blog($negaresh_site_id);
        Negaresh_Settings::delete_all();
        restore_current_blog();
    }
} else {
    Negaresh_Settings::delete_all();
}
