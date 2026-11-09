<?php
/**
 * Admin menu
 * Registers the Lot Manager menu and its sub pages.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'mccm_admin_actions');

function mccm_admin_actions() {
    add_menu_page("Lot Manager", "Lot Manager", "add_users", "mccm", "mccm_screen_lots", "dashicons-location", 26);
    add_submenu_page("mccm", "All records", "All records", "add_users", "mccm", "mccm_screen_lots");
    add_submenu_page("mccm", "Add record", "Add record", "add_users", "mccm_add", "mccm_add_record_page");
    add_submenu_page("mccm", "Export", "Export", "manage_options", "mccm_export", "mccm_screen_export");
    add_submenu_page("mccm", "Import", "Import", "manage_options", "mccm_import", "mccm_screen_import");
    add_submenu_page("mccm", "Reports", "Reports", "manage_options", "mccmreports", "mccm_screen_reports");
}  //end mccm_admin_actions

/** "Add record" menu item: opens the add form directly (the form itself posts back to page=mccm). */
function mccm_add_record_page() {
    $_REQUEST['action'] = 'addentry';
    mccm_screen_lots();
}
