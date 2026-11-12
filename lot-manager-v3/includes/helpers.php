<?php
/**
 * Shared helpers
 * Date helpers and small utilities used by admin and front end.
 */
if (!defined('ABSPATH')) exit;

/** Stored date (YYYY-MM-DD) to M/D/YYYY. Partial dates show what is known: 4/1965 or 1965. */
function mccm_s2h($d) {
    $d = (string) $d;
    if ($d === '0000-00-00' || $d === '') return '';
    $t = explode('-', $d);
    if (count($t) !== 3) return $d;
    $m = (int) $t[1]; $da = (int) $t[2];
    if ($m === 0) return $t[0];
    return $da === 0 ? $m . '/' . $t[0] : $m . '/' . $da . '/' . $t[0];
}

/** Plain text of a stored value for display (old data may still carry backslashes). */
function mccm_show_val($v) {
    return trim(stripslashes((string) $v));
}

/** Delete link attributes for the confirm box; the text names the record, so nobody deletes the wrong one. */
function mccm_delete_confirm($item) {
    $who = trim(mccm_show_val($item['LAST'] ?? '') . ', ' . mccm_show_val($item['FIRST'] ?? ''), ' ,');
    $msg = 'Record ' . (int) $item['UID'] . ($who !== '' ? ' (' . $who . ')' : '') . ' will be removed. This cannot be undone.';
    return 'data-mccm-title="Delete this record?" data-mccm-confirm="' . esc_attr($msg) . '" data-mccm-btn="Delete record" data-mccm-danger="1"';
}
