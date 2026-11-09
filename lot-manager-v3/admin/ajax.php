<?php
/**
 * Admin AJAX
 * The record details panel endpoint (staff only).
 */
if (!defined('ABSPATH')) exit;

/** AJAX: one record's panel. Staff only, nonce-checked, read-only, one query by primary key. */
add_action('wp_ajax_mccm_row', function () {
    if (!current_user_can('add_users') || !check_ajax_referer('mccm_row', '_wpnonce', false)) wp_die('Not allowed', '', array('response' => 403));
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'mccm WHERE UID = %d', (int) ($_GET['id'] ?? 0)), ARRAY_A);
    if (!$row) wp_die('Record not found', '', array('response' => 404));
    echo mccm_row_panel($row);
    wp_die();
});
