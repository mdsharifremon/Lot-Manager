<?php
/**
 * Plugins screen: a warning on this plugin's row and a confirm box (assets/admin/js/confirm.js) when someone clicks Deactivate.
 * This plugin holds the site's burial records, so switching it off hides them everywhere.
 * Deleting the plugin never removes the data (see includes/install.php).
 */
if (!defined('ABSPATH')) exit;

function mccm_deactivate_message() {
    return 'Do not deactivate this plugin. It is the main data store of the site. If you deactivate it, the site will not run as expected, and the burial records will not show on the website or in the dashboard. Your data is not deleted, but it stays hidden until the plugin is active again.';
}


/** Ask before Deactivate (single link and the bulk action) on the Plugins screen only. */
add_action('admin_enqueue_scripts', 'mccm_plugin_screen_script');
function mccm_plugin_screen_script($hook) {
    if ($hook !== 'plugins.php') return;
    $file = plugin_basename(MCCM_PLUGIN_DIR . 'mccm.php');
    mccm_confirm_assets();
    wp_register_script('mccm-plugins-screen', false, array('mccm-confirm'), MCCM_VERSION, true);
    wp_enqueue_script('mccm-plugins-screen');
    $js = '(function(){var f=' . wp_json_encode($file) . ',o={title:"Deactivate Lot Manager?",message:' . wp_json_encode(mccm_deactivate_message()) . ',button:"Deactivate anyway",danger:true};'
        . 'document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a");if(!a)return;'
        . 'var tr=a.closest("tr[data-plugin]");if(tr&&tr.getAttribute("data-plugin")===f&&a.href&&a.href.indexOf("action=deactivate")>-1&&a.href.indexOf("action=deactivate-selected")<0){'
        . 'e.preventDefault();mccmConfirm(o,function(){location.href=a.href;});}});'
        . 'var form=document.getElementById("bulk-action-form");if(form){form.addEventListener("submit",function(e){if(form.dataset.ok)return;'
        . 'var sel=form.querySelector("select[name=action]"),sel2=form.querySelector("select[name=action2]");var act=(sel&&sel.value!=="-1")?sel.value:(sel2?sel2.value:"");'
        . 'if(act!=="deactivate-selected")return;var cb=form.querySelector("input[name=\"checked[]\"][value="+JSON.stringify(f)+"]");'
        . 'if(cb&&cb.checked){e.preventDefault();mccmConfirm(o,function(){form.dataset.ok="1";form.requestSubmit();});}});}})();';
	
	 // Highlight the "do not deactivate" sentence in this plugin's description (Plugins screen only).
    wp_register_style('mccm-plugins-screen', false, array(), MCCM_VERSION);
    wp_enqueue_style('mccm-plugins-screen');
    wp_add_inline_style('mccm-plugins-screen',
        'tr[data-plugin="' . esc_attr($file) . '"] .plugin-description strong{'
        . 'display:inline-block;margin-top:4px;padding:2px 8px;border-radius:3px;'
        . 'background:#fff3b0;color:#1d2327;font-weight:600;}'
    );
    wp_add_inline_script('mccm-plugins-screen', $js);
	
}
