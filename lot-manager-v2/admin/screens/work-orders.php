<?php
/**
 * Screen: Work orders for one record (opened from the list: page=mccm&action=wos&id=...).
 * Saving and deleting go through admin-post.php (nonce + staff check) and redirect back here.
 * A work order belongs to a grave location (cemetery, section, lot, space), taken from the record, never from the form.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_post_mccm_wo_save', 'mccm_wo_save');
add_action('admin_post_mccm_wo_delete', 'mccm_wo_delete');
add_filter('admin_body_class', function ($c) {
    return (($_GET['page'] ?? '') === 'mccm' && ($_GET['action'] ?? '') === 'wos') ? $c . ' mccm-form-page' : $c;
});

function mccm_wo_record($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT UID, LAST, FIRST, MIDDLE, CEMETERY, SECTION, LOT, SPACE FROM `' . $wpdb->prefix . 'mccm` WHERE UID = %d', (int) $id));
}

/** One work order, only if it sits at the record's location. */
function mccm_wo_get($woid, $rec) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM `' . $wpdb->prefix . 'mccm_workorders` WHERE UID = %d AND CEMETERY = %s AND SECTION = %s AND LOT = %s AND SPACE = %s', (int) $woid, $rec->CEMETERY, $rec->SECTION, $rec->LOT, $rec->SPACE));
}

function mccm_wo_date($v) {
    $v = trim((string) $v);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return '';
    return $v;
}

function mccm_wo_messages() {
    return array(
        'saved' => array('ok', 'Work order saved.'),
        'updated' => array('ok', 'Work order updated.'),
        'deleted' => array('ok', 'Work order deleted.'),
        'err_required' => array('err', 'Work order number, date and work requested are all required.'),
        'err_wonum' => array('err', 'The work order number can be at most 10 characters.'),
        'err_long' => array('err', 'Work requested can be at most 255 characters and company at most 100.'),
        'err_date' => array('err', 'Please enter real dates.'),
        'err_gone' => array('err', 'That work order was not found for this record.'),
        'err_db' => array('err', 'The database refused the change, so nothing was saved. Try again.'),
    );
}

function mccm_wo_back($id, $msg, $woid = 0) {
    $args = array('page' => 'mccm', 'action' => 'wos', 'id' => (int) $id, 'mccm_wo' => $msg);
    if ($woid) $args['woid'] = (int) $woid;
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    exit;
}

function mccm_wo_save() {
    if (!current_user_can('add_users')) wp_die('Not allowed', '', array('response' => 403));
    $id = (int) ($_POST['id'] ?? 0);
    check_admin_referer('mccm_wo_save_' . $id);
    global $wpdb;
    $rec = mccm_wo_record($id);
    if (!$rec) wp_die('Record not found', '', array('response' => 404));
    $in = wp_unslash($_POST);
    $woid = (int) ($in['woid'] ?? 0);
    $wo = trim((string) ($in['wo'] ?? ''));
    $work = trim((string) ($in['work'] ?? ''));
    $company = trim((string) ($in['company'] ?? ''));
    $rawDate = trim((string) ($in['wodate'] ?? ''));
    $rawDone = trim((string) ($in['complete'] ?? ''));
    $date = mccm_wo_date($rawDate);
    $done = mccm_wo_date($rawDone);
    if (($rawDate !== '' && $date === '') || ($rawDone !== '' && $done === '')) mccm_wo_back($id, 'err_date', $woid);
    if ($wo === '' || $work === '' || $date === '') mccm_wo_back($id, 'err_required', $woid);
    if (mb_strlen($wo) > 10) mccm_wo_back($id, 'err_wonum', $woid);
    if (mb_strlen($work) > 255 || mb_strlen($company) > 100) mccm_wo_back($id, 'err_long', $woid);
    $row = array(
        'CEMETERY' => $rec->CEMETERY, 'SECTION' => $rec->SECTION, 'LOT' => $rec->LOT, 'SPACE' => $rec->SPACE,
        'WODATE' => $date, 'COMPLETE' => $done === '' ? null : $done, 'WORK' => $work, 'WO' => $wo, 'COMPANY' => $company,
        'LASTCHANGE' => current_time('mysql'), 'CHANGER' => wp_get_current_user()->user_login,
    );
    $fmt = array_fill(0, count($row), '%s');
    $table = $wpdb->prefix . 'mccm_workorders';
    if ($woid) {
        if (!mccm_wo_get($woid, $rec)) mccm_wo_back($id, 'err_gone');
        if (false === $wpdb->update($table, $row, array('UID' => $woid), $fmt, array('%d'))) { error_log('[wyuka-mccm] work order update failed: ' . $wpdb->last_error); mccm_wo_back($id, 'err_db', $woid); }
        mccm_wo_back($id, 'updated');
    }
    if (false === $wpdb->insert($table, $row, $fmt)) { error_log('[wyuka-mccm] work order insert failed: ' . $wpdb->last_error); mccm_wo_back($id, 'err_db'); }
    mccm_wo_back($id, 'saved');
}

function mccm_wo_delete() {
    if (!current_user_can('add_users')) wp_die('Not allowed', '', array('response' => 403));
    $id = (int) ($_GET['id'] ?? 0);
    $woid = (int) ($_GET['woid'] ?? 0);
    check_admin_referer('mccm_wodel_' . $woid);
    global $wpdb;
    $rec = mccm_wo_record($id);
    if (!$rec || !mccm_wo_get($woid, $rec)) mccm_wo_back($id, 'err_gone');
    if (false === $wpdb->delete($wpdb->prefix . 'mccm_workorders', array('UID' => $woid), array('%d'))) { error_log('[wyuka-mccm] work order delete failed: ' . $wpdb->last_error); mccm_wo_back($id, 'err_db'); }
    mccm_wo_back($id, 'deleted');
}

function mccm_wo_field($id, $label, $input, $wide = false) {
    return '<div class="mccm-field' . ($wide ? ' mccm-wide' : '') . '"><div class="mccm-label-row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></div>' . $input . '</div>';
}

function mccm_wo_screen() {
    if (!current_user_can('add_users')) wp_die('Not allowed', '', array('response' => 403));
    global $wpdb;
    $h = function ($v) { return esc_html(stripslashes((string) $v)); };
    $id = (int) ($_GET['id'] ?? 0);
    $rec = mccm_wo_record($id);
    $home = esc_url(admin_url('admin.php?page=mccm'));
    echo '<div class="wrap mccm-ui" id="mccm-ui">';
    echo '<nav class="mccm-crumbs" aria-label="Breadcrumb"><a href="' . $home . '">Lot Manager</a> <span>/</span> Work orders</nav>';
    echo '<h1 class="mccm-title">Work orders</h1>';
    if (!$rec) {
        echo '<div class="notice notice-error"><p>Record not found. <a href="' . $home . '">Back to all records</a></p></div></div>';
        return;
    }
    $msgs = mccm_wo_messages();
    $m = $msgs[$_GET['mccm_wo'] ?? ''] ?? null;
    if ($m) echo '<div class="notice notice-' . ($m[0] === 'ok' ? 'success' : 'error') . ' is-dismissible mccm-flash"><p>' . esc_html($m[1]) . '</p></div>';
    $name = trim($rec->FIRST . ' ' . $rec->MIDDLE . ' ' . $rec->LAST);
    echo '<p class="mccm-sub">' . $h($name) . ' &middot; ' . $h($rec->CEMETERY) . ' &middot; Section ' . $h($rec->SECTION) . ' &middot; Lot ' . $h($rec->LOT) . ' &middot; Space ' . $h($rec->SPACE) . '</p>';

    $edit = !empty($_GET['woid']) ? mccm_wo_get((int) $_GET['woid'], $rec) : null;
    $date = function ($v) { $v = (string) $v; return ($v === '0000-00-00') ? '' : $v; };
    $list = $wpdb->get_results($wpdb->prepare('SELECT * FROM `' . $wpdb->prefix . 'mccm_workorders` WHERE CEMETERY = %s AND SECTION = %s AND LOT = %s AND SPACE = %s ORDER BY WODATE DESC, UID DESC', $rec->CEMETERY, $rec->SECTION, $rec->LOT, $rec->SPACE));
    $printAll = wp_nonce_url(admin_url('admin-ajax.php?action=generatereport&reptype=wos&id=' . $id), 'mccm_wo_print');

    echo '<div class="mccm-imp-layout"><aside class="mccm-imp-side"><div class="mccm-imp-sticky">';
    echo '<section class="mccm-card"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('card') . '</span><div><h2>How it works</h2><p>For this grave location.</p></div></header>';
    echo '<ol class="mccm-howto"><li><b>Add.</b> Fill in the form. Number, date and work are required.</li><li><b>Edit or delete.</b> Use the links in the table.</li><li><b>Print.</b> Print one work order, or all of them for this location.</li></ol>';
    echo '<div class="mccm-rules"><h3>Good to know</h3><ul><li>Work orders belong to the grave location, so everyone buried at this location sees the same list.</li></ul></div></section>';
    echo '</div></aside><div class="mccm-imp-main">';

    echo '<section class="mccm-card mccm-imp"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="mccm_wo_save"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="woid" value="' . ($edit ? (int) $edit->UID : 0) . '">';
    echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mccm_wo_save_' . $id)) . '">';
    echo '<header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('file') . '</span><div><h2>' . ($edit ? 'Edit work order' : 'Add work order') . '</h2><p>' . ($edit ? 'Change the details and save.' : 'A new work order for this location.') . '</p></div></header>';
    echo '<div class="mccm-grid">';
    echo mccm_wo_field('wo', 'Work order number', '<input type="text" id="wo" name="wo" maxlength="10" required value="' . esc_attr($edit ? stripslashes($edit->WO) : '') . '">');
    echo mccm_wo_field('wodate', 'Work order date', '<input type="date" id="wodate" name="wodate" min="1900-01-01" max="2200-12-31" required value="' . esc_attr($edit ? $date($edit->WODATE) : '') . '">');
    echo mccm_wo_field('complete', 'Completion date', '<input type="date" id="complete" name="complete" min="1900-01-01" max="2200-12-31" value="' . esc_attr($edit ? $date($edit->COMPLETE) : '') . '">');
    echo mccm_wo_field('work', 'Work requested', '<input type="text" id="work" name="work" maxlength="255" required value="' . esc_attr($edit ? stripslashes($edit->WORK) : '') . '">', true);
    echo mccm_wo_field('company', 'Company', '<input type="text" id="company" name="company" maxlength="100" value="' . esc_attr($edit ? stripslashes($edit->COMPANY) : '') . '">');
    echo '</div><div class="mccm-imp-foot mccm-rep-btns"><button type="submit" class="mccm-btn mccm-btn-main">' . mccm_icon('check') . '<span>' . ($edit ? 'Update work order' : 'Save work order') . '</span></button>';
    if ($edit) echo '<a class="mccm-btn mccm-btn-soft" href="' . esc_url(admin_url('admin.php?page=mccm&action=wos&id=' . $id)) . '"><span>Cancel</span></a>';
    echo '</div></form></section>';

    echo '<section class="mccm-card mccm-imp"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('users') . '</span><div><h2>Work orders at this location</h2><p>' . count($list) . ' on file, newest first.</p></div></header>';
    if (!$list) {
        echo '<p class="mccm-exp-facts">No work orders for this location yet.</p>';
    } else {
        echo '<div class="mccm-wo-wrap"><table class="mccm-wo-table"><thead><tr><th>Date</th><th>Number</th><th>Work requested</th><th>Completed</th><th>Company</th><th>Actions</th></tr></thead><tbody>';
        foreach ($list as $w) {
            $print = wp_nonce_url(admin_url('admin-ajax.php?action=generatereport&reptype=wo&woid=' . (int) $w->UID), 'mccm_wo_print');
            $editUrl = admin_url('admin.php?page=mccm&action=wos&id=' . $id . '&woid=' . (int) $w->UID);
            $delUrl = wp_nonce_url(admin_url('admin-post.php?action=mccm_wo_delete&id=' . $id . '&woid=' . (int) $w->UID), 'mccm_wodel_' . (int) $w->UID);
            echo '<tr><td>' . $h(mccm_s2h($w->WODATE)) . '</td><td>' . $h($w->WO) . '</td><td>' . $h($w->WORK) . '</td><td>' . $h(mccm_s2h($w->COMPLETE)) . '</td><td>' . $h($w->COMPANY) . '</td>';
            echo '<td class="mccm-wo-acts"><a href="' . esc_url($print) . '" target="_blank" rel="noopener">Print</a> <span>|</span> <a class="is-edit" href="' . esc_url($editUrl) . '">Edit</a> <span>|</span> <a class="is-del" href="' . esc_url($delUrl) . '" onclick="return confirm(\'Delete this work order? This cannot be undone.\');">Delete</a></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="mccm-imp-foot mccm-rep-btns"><a class="mccm-btn mccm-btn-soft" href="' . esc_url($printAll) . '" target="_blank" rel="noopener">' . mccm_icon('file') . '<span>Print all work orders</span></a></div>';
    }
    echo '</section>';
    echo '<p><a href="' . $home . '">&larr; Back to all records</a></p>';
    echo '</div></div></div>';
}
