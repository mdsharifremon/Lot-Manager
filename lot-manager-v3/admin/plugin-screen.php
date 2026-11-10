<?php
/**
 * Plugins screen: a warning on this plugin's row and a confirm box when someone clicks Deactivate.
 * This plugin holds the site's burial records, so switching it off hides them everywhere.
 * Deleting the plugin never removes the data (see includes/install.php).
 */
if (!defined('ABSPATH')) exit;

function mccm_deactivate_message() {
    return 'Do not deactivate this plugin. It is the main data store of the site. If you deactivate it, the site will not run as expected, and the burial records will not show on the website or in the dashboard. Your data is not deleted, but it stays hidden until the plugin is active again.';
}

/** A short notice under the plugin's row on the Plugins screen. */
add_action('after_plugin_row_' . plugin_basename(MCCM_PLUGIN_DIR . 'mccm.php'), 'mccm_plugin_row_notice');
function mccm_plugin_row_notice($file) {
    $cols = function_exists('_get_list_table') ? _get_list_table('WP_Plugins_List_Table')->get_column_count() : 3;
    echo '<tr class="plugin-update-tr active" data-plugin="' . esc_attr($file) . '"><td colspan="' . (int) $cols . '" class="plugin-update colspanchange">';
    echo '<div class="notice inline notice-warning notice-alt"><p><strong>Main data store of the site.</strong> Keep this plugin active. If it is off, the site will not run as expected and the records will not show on the website or in the dashboard.</p></div>';
    echo '</td></tr>';
}

/** Ask before Deactivate (single link and the bulk action) on the Plugins screen only. */
add_action('admin_enqueue_scripts', 'mccm_plugin_screen_script');
function mccm_plugin_screen_script($hook) {
    if ($hook !== 'plugins.php') return;
    $file = plugin_basename(MCCM_PLUGIN_DIR . 'mccm.php');
    wp_register_script('mccm-plugins-screen', false, array(), MCCM_VERSION, true);
    wp_enqueue_script('mccm-plugins-screen');
    $js = '(function(){var f=' . wp_json_encode($file) . ',m=' . wp_json_encode(mccm_deactivate_message()) . ';'
        . 'document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a");if(!a)return;'
        . 'var tr=a.closest("tr[data-plugin]");if(tr&&tr.getAttribute("data-plugin")===f&&a.href&&a.href.indexOf("action=deactivate")>-1&&a.href.indexOf("action=deactivate-selected")<0){if(!window.confirm(m+"\n\nDeactivate anyway?")){e.preventDefault();}}});'
        . 'var form=document.getElementById("bulk-action-form");if(form){form.addEventListener("submit",function(e){'
        . 'var sel=form.querySelector("select[name=action]"),sel2=form.querySelector("select[name=action2]");var act=(sel&&sel.value!=="-1")?sel.value:(sel2?sel2.value:"");'
        . 'if(act!=="deactivate-selected")return;var cb=form.querySelector("input[name=\"checked[]\"][value="+JSON.stringify(f)+"]");'
        . 'if(cb&&cb.checked&&!window.confirm(m+"\n\nDeactivate anyway?")){e.preventDefault();}});}})();';
    wp_add_inline_script('mccm-plugins-screen', $js);
}
