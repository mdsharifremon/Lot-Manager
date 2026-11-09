<?php
/**
 * Form render
 * Add/edit form markup.
 */
if (!defined('ABSPATH')) exit;

/** Draw one field. */
function mccm_render_field($f, $vals, $errors, $lim, $sugg, $isRequired = false) {
    $name = $f[0]; $label = $f[1]; $type = $f[2];
    $req = $isRequired; $hint = $f['hint'] ?? ''; $ph = $f['ph'] ?? '';
    $max = $lim[$name] ?? 0;
    $id = 'f_' . $name;
    $val = (string) ($vals[$name] ?? '');
    $err = $errors[$name] ?? '';
    $opts = mccm_choices()[$name] ?? array();
    $focus = $err && empty($GLOBALS['mccm_af']); if ($focus) $GLOBALS['mccm_af'] = true; // first flagged field gets the cursor, no JS needed
    echo '<div class="mccm-field' . ($err ? ' has-error' : '') . '" data-field="' . esc_attr($name) . '">';
    echo '<div class="mccm-label-row"><label for="' . esc_attr($id) . '">' . esc_html($label) . ($req ? ' <span class="mccm-req" title="Required">*</span>' : '') . '</label>';
    echo '</div>';
    $common = ' id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . ($req ? ' data-required="1" required aria-required="true"' : '') . ' data-label="' . esc_attr($label) . '"' . ($err ? ' aria-invalid="true"' : '') . ($focus ? ' autofocus' : '') . ' aria-describedby="e_' . esc_attr($name) . '"';
    if ($type === 'select' || $type === 'selother') {
        $isOther = ($type === 'selother') && ($vals[$name . '__sel'] ?? '') === '__other';
        $sel = $isOther ? '__other' : $val;
        echo '<select' . $common . ($type === 'selother' ? ' data-other="1"' : '') . '><option value="">Select ' . esc_html(function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label)) . '</option>';
        $found = ($sel === '' || $sel === '__other');
        foreach ($opts as $k => $l) {
            if ((string) $k === $sel) $found = true;
            echo '<option value="' . esc_attr($k) . '"' . selected((string) $k === $sel, true, false) . '>' . esc_html($l) . '</option>';
        }
        if (!$found && $type === 'select') echo '<option value="' . esc_attr($sel) . '" selected>' . esc_html($sel) . '</option>';
        if ($type === 'selother') echo '<option value="__other"' . selected($sel, '__other', false) . '>Other…</option>';
        echo '</select>';
        if ($type === 'selother') {
            echo '<input type="text" class="mccm-other" id="' . esc_attr($id) . '__other" name="' . esc_attr($name) . '__other" placeholder="Type it here" value="' . esc_attr($isOther ? (string) ($vals[$name . '__txt'] ?? '') : '') . '"' . ($max ? ' maxlength="' . (int) $max . '"' : '') . ($isOther ? '' : ' hidden') . ' aria-label="' . esc_attr($label) . ' (other)">';
        }
    } elseif ($type === 'date') {
        echo '<input type="date"' . $common . ' value="' . esc_attr($val) . '" min="1700-01-01" max="2200-12-31">';
    } elseif ($type === 'number') {
        echo '<input type="number" inputmode="numeric"' . $common . ' value="' . esc_attr($val) . '" min="0" max="130" step="1" placeholder="Calculated from the dates">';
    } elseif ($type === 'year') {
        echo '<input type="text" inputmode="numeric"' . $common . ' value="' . esc_attr($val) . '" pattern="\d{4}" maxlength="4" placeholder="yyyy">';
    } elseif ($type === 'zip') {
        echo '<input type="text" inputmode="numeric"' . $common . ' value="' . esc_attr($val) . '" pattern="\d{5}(-\d{4})?" maxlength="10" placeholder="68502" data-pattern-msg="ZIP must be 5 digits, or 5+4.">';
    } else {
        $combo = ($type === 'suggest' && !empty($sugg[$name]['values'])) ? ' data-combo="1" data-top="' . (int) $sugg[$name]['top'] . '" data-options="' . esc_attr(wp_json_encode(array_values($sugg[$name]['values']))) . '"' : '';
        echo '<input type="text"' . $common . ' value="' . esc_attr($val) . '"' . ($max ? ' maxlength="' . (int) $max . '"' : '') . ($ph ? ' placeholder="' . esc_attr($ph) . '"' : '') . $combo . ' autocomplete="off">';
    }
    if ($hint) echo '<p class="mccm-hint">' . esc_html($hint) . '</p>';
    echo '<p class="mccm-err" id="e_' . esc_attr($name) . '" role="alert"' . ($err ? '' : ' hidden') . '>' . esc_html($err) . '</p>';
    echo '</div>';
}

/** Draw the whole add/edit page. $data = existing row or null. */
function mccm_render_entry_form($data = null, $errors = array()) {
    $GLOBALS['mccm_af'] = false;
    $lim = mccm_col_limits();
    $sugg = mccm_suggestions();
    $layout = mccm_form_layout();
    $reqFields = mccm_required_fields();
    $isEdit = !empty($data) && !empty($data->UID);
    $posted = !empty($errors);
    $vals = array();
    if (!$isEdit) $vals['CONTACT_YEAR'] = wp_date('Y');
    $post = $posted ? array_filter(wp_unslash($_POST), 'is_scalar') : array(); // arrays are refused on save, never shown
    foreach ($layout as $sec) foreach ($sec['fields'] as $f) {
        $n = $f[0]; $type = $f[2];
        if ($posted) {
            $v = (string) ($post[$n] ?? '');
            if ($type === 'selother') { $vals[$n . '__sel'] = $v; $vals[$n . '__txt'] = (string) ($post[$n . '__other'] ?? ''); $vals[$n] = $v === '__other' ? '' : $v; }
            else $vals[$n] = $v;
        } elseif ($data) {
            $v = isset($data->$n) ? stripslashes((string) $data->$n) : '';
            if ($type === 'date') $v = mccm_date_to_iso($v);
            elseif ($n === 'AGE') $v = ((int) $v) ? (string) (int) $v : '';
            elseif ($n === 'CONTACT_STATE') { $up = strtoupper(trim($v)); if (isset(mccm_us_states()[$up])) $v = $up; }
            elseif ($type === 'selother') {
                $opts = mccm_choices()[$n]; $match = '';
                foreach ($opts as $k => $_) if (strcasecmp((string) $k, trim($v)) === 0) $match = (string) $k;
                if (trim($v) === '' || $match !== '') { $vals[$n . '__sel'] = $match; $v = $match; } // a whitespace-only old value counts as empty
                else { $vals[$n . '__sel'] = '__other'; $vals[$n . '__txt'] = $v; }
            }
            $vals[$n] = $v;
        } else {
            $vals[$n] = ($n === 'CONTACT_YEAR') ? date('Y') : '';
        }
    }
    $title = $isEdit ? 'Edit burial record' : 'Add burial record';
    $who = $isEdit ? trim(stripslashes((string) $data->FIRST) . ' ' . stripslashes((string) $data->LAST)) : '';
    $formErrors = $errors;
    $general = $formErrors['_form'] ?? '';
    $confirmMsg = $formErrors['_confirm'] ?? '';
    if ($confirmMsg !== '') $general = $confirmMsg;
    unset($formErrors['_form'], $formErrors['_confirm']);
    echo '<div class="wrap mccm-ui" id="mccm-ui">';
    echo '<nav class="mccm-crumbs" aria-label="Breadcrumb"><a href="' . esc_url(admin_url('admin.php?page=mccm')) . '">Lot Manager</a> <span>/</span> ' . ($isEdit ? 'Edit record' : 'Add record') . '</nav>';
    echo '<h1 class="mccm-title">' . esc_html($title) . '</h1>';
    echo '<p class="mccm-sub">' . ($isEdit ? 'Editing ' . esc_html($who) . ' (record ' . (int) $data->UID . '). ' : '') . ($isEdit ? 'A name, cemetery, section and space are always needed. Fields marked <span class="mccm-req">*</span> are needed too. Everything else is optional.' : 'Fields marked <span class="mccm-req">*</span> are required. Everything else is optional.') . '</p>';
    if ($general || $formErrors) {
        echo '<div class="mccm-summary" role="alert" data-server-errors="' . count($formErrors) . '"><strong>Not saved.</strong> ' . ($general ? esc_html($general) : 'Please fix the ' . count($formErrors) . ' highlighted field' . (count($formErrors) > 1 ? 's' : '') . ':');
        if ($formErrors) { echo '<ul>'; foreach ($formErrors as $k => $m) echo '<li><a href="#f_' . esc_attr($k) . '">' . esc_html($m) . '</a></li>'; echo '</ul>'; }
        echo '</div>';
    }
    echo '<div class="mccm-chips" role="navigation" aria-label="Jump to section">';
    foreach ($layout as $i => $sec) echo '<a href="#mccm-sec-' . esc_attr($sec['id']) . '" data-chip="' . esc_attr($sec['id']) . '"><span>' . sprintf('%02d', $i + 1) . '</span> ' . esc_html($sec['nav']) . '</a>';
    echo '</div>';
    echo '<form method="post" id="mccm-entry-form" action="' . esc_url(admin_url('admin.php?page=mccm')) . '">';
    echo '<input type="hidden" name="page" value="mccm"><input type="hidden" name="action" value="saveentry">';
    wp_nonce_field('mccm_saveentry');
    echo '<input type="hidden" name="id" value="' . ($isEdit ? (int) $data->UID : '') . '">';
    $lc = isset($_POST['mccm_lc']) ? wp_unslash((string) $_POST['mccm_lc']) : ($isEdit ? (string) $data->LASTCHANGE : ''); // what the editor first opened
    echo '<input type="hidden" name="mccm_lc" value="' . esc_attr($lc) . '">';
    if ($confirmMsg !== '') echo '<p class="mccm-summary"><label><input type="checkbox" name="mccm_confirm" value="1"> I have read the warning above. Save anyway.</label></p>';
    foreach ($layout as $sec) {
        echo '<section class="mccm-card" id="mccm-sec-' . esc_attr($sec['id']) . '" data-section="' . esc_attr($sec['id']) . '">';
        echo '<header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon($sec['icon']) . '</span><div><h2>' . esc_html($sec['title']) . '</h2><p>' . esc_html($sec['desc']) . '</p></div></header>';
        echo '<div class="mccm-grid">';
        foreach ($sec['fields'] as $f) {
            $isReq = false;
            if (isset($reqFields[$f[0]])) { $isReq = true; if ($isEdit && ($f[0] === 'LAST' || $f[0] === 'FIRST')) $isReq = false; else if ($isEdit && !in_array($f[0], mccm_always_required(), true)) { $ov = trim((string) $data->{$f[0]}); if ($ov === '' || $ov === '0000-00-00') $isReq = false; } }
            // partial dates in old records (e.g. 1903-00-00) cannot be shown in a date picker: keep them, say so, and do not demand a new date
            if ($isEdit && $f[2] === 'date') {
                $stored = trim((string) ($data->{$f[0]} ?? ''));
                if ($stored !== '' && $stored !== '0000-00-00' && mccm_date_to_iso($stored) === '') { $isReq = false; $f['hint'] = 'Stored as ' . $stored . ' (a partial date). Leave empty to keep it, or pick a full date to replace it.'; }
            }
            mccm_render_field($f, $vals, $formErrors, $lim, $sugg, $isReq);
        }
        echo '</div></section>';
    }
    echo '<div class="mccm-bar"><div class="mccm-bar-in"><span class="mccm-status" id="mccm-status">';
    if ($isEdit && !empty($data->CHANGER)) echo 'Last changed by ' . esc_html($data->CHANGER) . ' · ' . esc_html(mysql2date('M j, Y g:i a', $data->LASTCHANGE));
    else echo $isEdit ? '' : 'New record, not saved yet';
    echo '</span><span class="mccm-bar-btns"><a class="mccm-btn mccm-btn-soft" href="' . esc_url(admin_url('admin.php?page=mccm')) . '">Cancel</a>';
    echo '<button type="submit" class="mccm-btn mccm-btn-main" id="mccm-save">' . mccm_icon('check') . '<span>Save record</span></button></span></div></div>';
    echo '</form></div>';
}

/** Page background for the form pages comes from a body class set here, not from JS. */
add_filter('admin_body_class', function ($c) {
    $pg = $_GET['page'] ?? '';
    if ($pg === 'mccm_add' || ($pg === 'mccm' && in_array($_REQUEST['action'] ?? '', array('addentry', 'edit', 'saveentry'), true))) $c .= ' mccm-form-page';
    return $c;
});
