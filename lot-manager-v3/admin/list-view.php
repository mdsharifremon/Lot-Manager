<?php
/**
 * Admin list view
 * List screen helpers: record details panel, screen options, body class.
 */
if (!defined('ABSPATH')) exit;

/** The list screen = no action, or the one that falls back to the list after a delete. */
function mccm_is_list_view() {
    return in_array($_REQUEST['action'] ?? '', array('', 'delete'), true);
}

add_filter('admin_body_class', function ($c) {
    if (($_GET['page'] ?? '') === 'mccm' && mccm_is_list_view()) $c .= ' mccm-list-page';
    return $c;
});

/** Value as a person reads it: dates m/d/yyyy, coded choices spelled out. */
function mccm_pretty($col, $type, $raw, $item) {
    $v = mccm_show_val($raw);
    if ($type === 'date') return $v === '' ? '' : mccm_s2h($v);
    if ($col === 'AGE') return (int) $v > 0 ? (string) (int) $v : '';
    $ch = mccm_choices();
    if (isset($ch[$col][$v])) return $ch[$col][$v];
    return $v;
}

/** The View button (Actions column). The record is fetched when it is clicked (see admin.js), so the page stays small. */
function mccm_row_details($item) {
    $uid = (int) $item['UID'];
    $int = mccm_show_val($item['INTERMENT'] ?? '');
    $who = trim(mccm_show_val($item['LAST'] ?? '') . ', ' . mccm_show_val($item['FIRST'] ?? '') . ' ' . mccm_show_val($item['MIDDLE'] ?? ''), ' ,');
    return '<button type="button" class="mccm-int" data-uid="' . $uid . '" data-edit="' . esc_url(admin_url('admin.php?page=mccm&action=edit&id=' . $uid)) . '" aria-label="View details for ' . esc_attr($who) . '">View</button>';
}

/** One empty panel per page, filled on click. */
function mccm_pop_shell() {
    echo '<div id="mccm-pop" popover class="mccm-pop" role="dialog" aria-label="Record details" data-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('mccm_row')) . '"></div>';
}

/** Panel content for one record. */
function mccm_row_panel($item) {
    $uid = (int) $item['UID'];
    $int = mccm_show_val($item['INTERMENT'] ?? '');
    $who = trim(mccm_show_val($item['LAST'] ?? '') . ', ' . mccm_show_val($item['FIRST'] ?? '') . ' ' . mccm_show_val($item['MIDDLE'] ?? ''), ' ,');
    $edit = esc_url(admin_url('admin.php?page=mccm&action=edit&id=' . $uid));
    $o = '<div class="mccm-pop-head"><div><p class="mccm-pop-kicker">Record ' . $uid . ' &middot; Interment ' . ($int !== '' ? esc_html($int) : 'not set') . '</p><h2>' . esc_html($who !== '' ? $who : 'No name') . '</h2></div>';
    $o .= '<button type="button" class="mccm-pop-x" popovertarget="mccm-pop" popovertargetaction="hide" aria-label="Close">&times;</button></div>';
    $o .= '<div class="mccm-pop-body">';
    foreach (mccm_form_layout() as $sec) {
        $rows = '';
        foreach ($sec['fields'] as $f) {
            $val = mccm_pretty($f[0], $f[2], $item[$f[0]] ?? '', $item);
            if ($val === '') continue;
            $rows .= '<div><dt>' . esc_html($f[1]) . '</dt><dd>' . esc_html($val) . '</dd></div>';
        }
        if ($rows === '') continue;
        $o .= '<section class="mccm-pop-sec"><h3>' . mccm_icon($sec['icon']) . esc_html($sec['title']) . '</h3><dl>' . $rows . '</dl></section>';
    }
    $o .= '</div>';
    $lc = (string) ($item['LASTCHANGE'] ?? '');
    $when = ($lc !== '' && strtotime($lc)) ? wp_date('M j, Y g:i a', strtotime($lc)) : '';
    $o .= '<div class="mccm-pop-foot"><span>' . ($when !== '' ? 'Last changed by ' . esc_html(mccm_show_val($item['CHANGER'] ?? '')) . ' &middot; ' . esc_html($when) : '') . '</span><span><a class="button button-primary" href="' . $edit . '">Edit record</a></span></div>';
    return $o;
}

/** Screen Options: records per page (WordPress standard). */
add_action('load-toplevel_page_mccm', function () {
    if (mccm_is_list_view()) add_screen_option('per_page', array('label' => 'Records per page', 'default' => 50, 'option' => 'mccm_per_page'));
});

add_filter('set_screen_option_mccm_per_page', function ($keep, $option, $value) { return max(10, min(150, (int) $value)); }, 10, 3);
