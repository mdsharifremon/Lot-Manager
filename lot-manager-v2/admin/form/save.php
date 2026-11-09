<?php
/**
 * Form save
 * Early save (post/redirect/get) and the saved banner.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'mccm_early_save');

/**
 * Validate + write one entry. Returns array(ok, errors, uid, new, name).
 * Same columns and same storage formats as the original plugin.
 */
function mccm_process_save() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $table = $wpdb->prefix . 'mccm';
    $raw = wp_unslash($_POST);
    foreach ($raw as $k => $v) if (is_array($v)) return array('ok' => false, 'errors' => array('_form' => 'The form data was not valid. Reload the page and try again.'), 'uid' => 0, 'new' => false, 'name' => ''); // fields are single values
    $id = (int) ($raw['id'] ?? 0);
    $old = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE UID = %d", $id)) : null;
    if ($id && !$old) return array('ok' => false, 'errors' => array('_form' => 'That record no longer exists.'), 'uid' => 0, 'new' => false, 'name' => '');

    $otherEmpty = array();
    foreach (mccm_other_fields() as $f) {
        if (($raw[$f] ?? '') === '__other' && trim((string) ($raw[$f . '__other'] ?? '')) === '') $otherEmpty[$f] = true;
    }
    $req = mccm_normalize_other($raw);
    $errors = mccm_validate_entry_clean($req, $old, $otherEmpty);
    $cy = trim((string) ($req['CONTACT_ZIP'] ?? '')); $cs = trim((string) ($req['CONTACT_STATE'] ?? ''));
    if ($cy !== '' && !($old && trim((string) $old->CONTACT_ZIP) === $cy) && !preg_match('/^\d{5}(-\d{4})?$/', $cy)) $errors['CONTACT_ZIP'] = 'ZIP must be 5 digits (or 5+4).';
    if ($cs !== '' && !($old && strcasecmp(trim((string) $old->CONTACT_STATE), $cs) === 0) && !preg_match('/^[A-Za-z]{2}$/', $cs)) $errors['CONTACT_STATE'] = 'State must be 2 letters.';
    if ($errors) return array('ok' => false, 'errors' => $errors, 'uid' => $id, 'new' => !$id, 'name' => '');

    // Warnings the editor can accept by ticking the box on the form (nothing is blocked for good).
    if (($raw['mccm_confirm'] ?? '') !== '1') {
        $warn = array();
        $loc = array('CEMETERY', 'SECTION', 'LOT', 'SPACE');
        if (!$old) {
            $dup = (int) $wpdb->get_var($wpdb->prepare("SELECT UID FROM `$table` WHERE LAST = %s AND FIRST = %s AND CEMETERY = %s AND SECTION = %s AND LOT = %s AND SPACE = %s LIMIT 1", trim((string) ($req['LAST'] ?? '')), trim((string) ($req['FIRST'] ?? '')), trim((string) ($req['CEMETERY'] ?? '')), trim((string) ($req['SECTION'] ?? '')), trim((string) ($req['LOT'] ?? '')), trim((string) ($req['SPACE'] ?? ''))));
            if ($dup) $warn[] = 'A record with the same name and space already exists (record ' . $dup . ').';
        } else {
            $moved = false;
            foreach ($loc as $c) if (trim(stripslashes((string) $old->$c)) !== trim((string) ($req[$c] ?? ''))) $moved = true;
            if ($moved) {
                $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}mccm_workorders` WHERE CEMETERY = %s AND SECTION = %s AND LOT = %s AND SPACE = %s", $old->CEMETERY, $old->SECTION, $old->LOT, $old->SPACE));
                if ($n) $warn[] = $n . ' work order(s) belong to the old location. They stay with that location and will no longer show on this record.';
            }
            $lc = (string) ($raw['mccm_lc'] ?? '');
            if ($lc !== '' && $lc !== (string) $old->LASTCHANGE) $warn[] = 'This record was changed by ' . ($old->CHANGER !== '' ? $old->CHANGER : 'someone') . ' (' . $old->LASTCHANGE . ') after you opened it. Saving will overwrite their changes.';
        }
        if ($warn) return array('ok' => false, 'errors' => array('_confirm' => implode(' ', $warn)), 'uid' => $id, 'new' => !$id, 'name' => '');
    }

    $row = array();
    $fmt = array();
    foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM `$table`") as $c) {
        $col = $c->Field;
        if (in_array($col, array('UID', 'LASTCHANGE', 'CHANGER'), true)) continue;
        $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_strip_all_tags((string) ($req[$col] ?? ''))));
        $oldRaw = $old ? $old->$col : null;
        // Saving must change only what the editor changed. A value that still matches what the form showed
        // (ignoring stray spaces and old backslash-escaping) is written back exactly as it was stored.
        if ($old && !in_array($col, array('BIRTH', 'DEATH', 'BURIAL', 'DISINTERMENT', 'AGE'), true)
            && (trim(stripslashes((string) $oldRaw)) === $v || ($col === 'CONTACT_STATE' && strcasecmp(trim((string) $oldRaw), $v) === 0))) {
            $row[$col] = $oldRaw; $fmt[] = '%s'; continue;
        }
        if (in_array($col, array('BIRTH', 'DEATH', 'BURIAL', 'DISINTERMENT'), true)) {
            $iso = mccm_date_to_iso($v);
            if ($old && $iso === mccm_date_to_iso((string) $oldRaw)) { $row[$col] = $oldRaw; $fmt[] = '%s'; continue; } // unchanged (incl. an empty 0000-00-00 or NULL)
            // DISINTERMENT is NOT NULL in the live table; empty is stored as 0000-00-00 exactly like the original plugin did
            $row[$col] = $iso === '' ? ($c->Null === 'NO' ? '0000-00-00' : null) : $iso; $fmt[] = '%s';
        } elseif ($col === 'AGE') {
            $row[$col] = $v === '' ? 0 : (int) $v; $fmt[] = '%d';
        } elseif (strpos($col, 'CONTACT_') === 0) {
            $row[$col] = $v === '' ? null : $v; $fmt[] = '%s';
        } else {
            $row[$col] = $v; $fmt[] = '%s';
        }
    }
    $row['LASTCHANGE'] = current_time('mysql'); $fmt[] = '%s';
    $row['CHANGER'] = $current_user->user_login; $fmt[] = '%s';

    if (!$id) {
        $ok = $wpdb->insert($table, $row, $fmt);
        $uid = $ok ? (int) $wpdb->insert_id : 0;
    } else {
        $ok = $wpdb->update($table, $row, array('UID' => $id), $fmt);
        $uid = $id;
    }
    if ($ok === false) error_log('[wyuka-mccm] save failed (record ' . ($id ?: 'new') . ', user ' . $current_user->user_login . '): ' . $wpdb->last_error);
    if ($ok === false) return array('ok' => false, 'errors' => array('_form' => 'The database refused the save, so nothing was saved. Your entries are still on this page. Try Save again; if it keeps failing, send this to your developer: ' . substr(preg_replace('/\s+/', ' ', $wpdb->last_error), 0, 160)), 'uid' => $id, 'new' => !$id, 'name' => '');
    delete_transient('mccm_suggest_v2');
    return array('ok' => true, 'errors' => array(), 'uid' => $uid, 'new' => !$id, 'name' => trim(($row['FIRST'] ?? '') . ' ' . ($row['LAST'] ?? '')));
}

/** Runs before any page output, so we can redirect after a successful save. */
function mccm_early_save() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if (($_REQUEST['page'] ?? '') !== 'mccm' || ($_REQUEST['action'] ?? '') !== 'saveentry') return;
    if (!current_user_can('add_users')) wp_die('Not allowed', '', array('response' => 403));
    check_admin_referer('mccm_saveentry');
    $r = mccm_process_save();
    $GLOBALS['mccm_save_result'] = $r;
    if ($r['ok']) {
        wp_safe_redirect(add_query_arg(array('page' => 'mccm', 'mccm_msg' => $r['new'] ? 'added' : 'saved', 'mccm_uid' => $r['uid']), admin_url('admin.php')));
        exit;
    }
}

/** Green "saved" banner on the list page after redirect. */
function mccm_flash() {
    if (!is_admin() || !in_array($_GET['mccm_msg'] ?? '', array('saved', 'added'), true)) return;
    $uid = (int) ($_GET['mccm_uid'] ?? 0);
    echo '<div class="notice notice-success is-dismissible mccm-flash"><p><strong>' . ($_GET['mccm_msg'] === 'added' ? 'Record added.' : 'Record saved.') . '</strong> Showing record number ' . $uid . ' below. <a href="' . esc_url(admin_url('admin.php?page=mccm')) . '">Show all records</a></p></div>';
}
