<?php
/**
 * Asset loading.
 *  - Admin files load ONLY on this plugin's dashboard screens (page=mccm...).
 *  - Front-end files load ONLY on pages where [mccm] is actually rendered.
 */
if (!defined('ABSPATH')) exit;

/** Shared confirm box (own css + js). Used on our screens and on the Plugins screen. */
function mccm_confirm_assets() {
    $css = 'assets/admin/css/confirm.css';
    $js  = 'assets/admin/js/confirm.js';
    wp_enqueue_style('mccm-confirm', MCCM_PLUGIN_URL . $css, array(), (string) @filemtime(MCCM_PLUGIN_DIR . $css));
    wp_enqueue_script('mccm-confirm', MCCM_PLUGIN_URL . $js, array(), (string) @filemtime(MCCM_PLUGIN_DIR . $js), true);
}

add_action('admin_enqueue_scripts', 'mccm_admin_assets');
function mccm_admin_assets() {
    if (strpos((string) ($_GET['page'] ?? ''), 'mccm') !== 0) return;
    $css = 'assets/admin/css/admin.css';
    $js  = 'assets/admin/js/admin.js';
    mccm_confirm_assets();
    wp_enqueue_style('mccm-admin', MCCM_PLUGIN_URL . $css, array('mccm-confirm'), (string) @filemtime(MCCM_PLUGIN_DIR . $css));
    wp_enqueue_script('mccm-admin', MCCM_PLUGIN_URL . $js, array('jquery', 'mccm-confirm'), (string) @filemtime(MCCM_PLUGIN_DIR . $js), true);
}

/* Front end: enqueue at the moment a shortcode of ours renders (works for themes, widgets and page builders). */
add_filter('do_shortcode_tag', 'mccm_public_assets', 10, 2);
function mccm_public_assets($output, $tag) {
    if ($tag !== 'mccm') return $output;
    $css = 'assets/public/css/public.css';
    wp_enqueue_style('mccm-public', MCCM_PLUGIN_URL . $css, array(), (string) @filemtime(MCCM_PLUGIN_DIR . $css));
    return $output;
}
