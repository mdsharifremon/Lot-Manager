<?php
/**
 * Asset loading.
 *  - Admin files load ONLY on this plugin's dashboard screens (page=mccm...).
 *  - Front-end files load ONLY on pages where [mccm] or [mccm_emp] is actually rendered.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', 'mccm_admin_assets');
function mccm_admin_assets() {
    if (strpos((string) ($_GET['page'] ?? ''), 'mccm') !== 0) return;
    $css = 'assets/admin/css/admin.css';
    $js  = 'assets/admin/js/admin.js';
    wp_enqueue_style('mccm-admin', MCCM_PLUGIN_URL . $css, array(), (string) @filemtime(MCCM_PLUGIN_DIR . $css));
    wp_enqueue_script('mccm-admin', MCCM_PLUGIN_URL . $js, array('jquery'), (string) @filemtime(MCCM_PLUGIN_DIR . $js), true);
}

/* Front end: enqueue at the moment a shortcode of ours renders (works for themes, widgets and page builders). */
add_filter('do_shortcode_tag', 'mccm_public_assets', 10, 2);
function mccm_public_assets($output, $tag) {
    if ($tag !== 'mccm' && $tag !== 'mccm_emp') return $output;
    $css = 'assets/public/css/public.css';
    $js  = 'assets/public/js/public.js';
    wp_enqueue_style('mccm-public', MCCM_PLUGIN_URL . $css, array(), (string) @filemtime(MCCM_PLUGIN_DIR . $css));
    wp_enqueue_script('mccm-public', MCCM_PLUGIN_URL . $js, array('jquery'), (string) @filemtime(MCCM_PLUGIN_DIR . $js), true);
    return $output;
}
