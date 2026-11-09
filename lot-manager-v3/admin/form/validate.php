<?php
/**
 * Form validation
 * Server-side validation (the real check).
 */
if (!defined('ABSPATH')) exit;

/** ISO date or m/d/yyyy -> Y-m-d, or '' when empty/invalid. */
function mccm_date_to_iso($v) {
    $v = trim((string) $v);
    if ($v === '' || $v === '0000-00-00') return '';
    if (preg_match('~^(\d{4})-(\d{1,2})-(\d{1,2})$~', $v, $m)) { $y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3]; }
    elseif (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $v, $m)) { $mo = (int) $m[1]; $d = (int) $m[2]; $y = (int) $m[3]; }
    else return '';
    if (!checkdate($mo, $d, $y)) return '';
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/** Turn the "Other..." choice into the typed text, so the stored value is a normal plain value. */
function mccm_normalize_other($req) {
    foreach (mccm_other_fields() as $f) {
        if (($req[$f] ?? '') === '__other') $req[$f] = trim((string) ($req[$f . '__other'] ?? ''));
        unset($req[$f . '__other']);
    }
    return $req;
}

/**
 * Real validation. $req is already unslashed and "Other" already resolved.
 * $old = existing row (edit) or null. Returns array(field => message).
 */
function mccm_validate_entry_clean($req, $old = null, $otherEmpty = array()) {
    $errors = array();
    $lim = mccm_col_limits();
    foreach ($lim as $col => $max) {
        $v = trim((string) ($req[$col] ?? ''));
        $len = function_exists('mb_strlen') ? mb_strlen($v) : strlen($v);
        if ($len > $max) $errors[$col] = 'Too long: ' . $len . ' characters, the most allowed is ' . $max . '.';
    }
    foreach (mccm_required_fields() as $rf => $rl) {
        if ($old && ($rf === 'LAST' || $rf === 'FIRST')) continue; // edits: handled below as "at least one name"
        if ($old && !in_array($rf, mccm_always_required(), true)) { $ov = trim((string) $old->$rf); if ($ov === '' || $ov === '0000-00-00' || (in_array($rf, array('BIRTH', 'DEATH', 'BURIAL'), true) && mccm_date_to_iso($ov) === '')) continue; } // old incomplete records stay editable, except the always-required ones
        if (trim((string) ($req[$rf] ?? '')) === '' && !isset($otherEmpty[$rf])) $errors[$rf] = ($rf === 'CEMETERY') ? 'Choose a cemetery.' : $rl . ' is required.';
    }
    if ($old && trim((string) ($req['LAST'] ?? '')) === '' && trim((string) ($req['FIRST'] ?? '')) === '') $errors['LAST'] = 'Enter a last name or a first name.'; // edits: some old records only have one name
    foreach ($otherEmpty as $f => $_) $errors[$f] = 'Type the value for "Other".';
    foreach (mccm_choices() as $col => $opts) {
        if (in_array($col, mccm_other_fields(), true) || $col === 'CONTACT_STATE') continue;
        $v = (string) ($req[$col] ?? '');
        $was = $old ? (string) $old->$col : '';
        if ($v !== '' && !isset($opts[$v]) && $v !== $was) $errors[$col] = 'Choose one of the listed options.';
    }
    $dates = array();
    foreach (array('BIRTH' => 'Birth date', 'DEATH' => 'Death date', 'BURIAL' => 'Burial date', 'DISINTERMENT' => 'Disinterment date') as $df => $lab) {
        $v = trim((string) ($req[$df] ?? ''));
        if ($v === '') continue;
        $iso = mccm_date_to_iso($v);
        if ($iso === '') $errors[$df] = $lab . ' is not a real date.';
        else $dates[$df] = $iso;
    }
    $datesUnchanged = $old && ($dates['BIRTH'] ?? '') === mccm_date_to_iso((string) $old->BIRTH) && ($dates['DEATH'] ?? '') === mccm_date_to_iso((string) $old->DEATH); // old records that already had odd dates stay editable
    if (!$datesUnchanged && isset($dates['BIRTH'], $dates['DEATH']) && $dates['DEATH'] < $dates['BIRTH']) $errors['DEATH'] = 'Death date is before the birth date.';
    $age = trim((string) ($req['AGE'] ?? ''));
    if ($age !== '' && !($old && (string) (int) $old->AGE === $age) && (!ctype_digit($age) || (int) $age > 130)) $errors['AGE'] = 'Age must be a whole number from 0 to 130.';
    $cy = trim((string) ($req['CONTACT_YEAR'] ?? ''));
    if ($cy !== '' && (!ctype_digit($cy) || strlen($cy) !== 4)) $errors['CONTACT_YEAR'] = 'Year must be 4 digits.';
    return $errors;
}

