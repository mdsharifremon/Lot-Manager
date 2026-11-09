<?php
/**
 * Front end shortcodes
 * [mccm] (public grave locator).
 */
if (!defined('ABSPATH')) exit;

/** Map PDF for a cemetery section, or '' when there is none. */
function mccm_pub_map_url($cemetery, $section) {
    $base = 'https://wyuka.com/wp-content/uploads/';
    $maps = array(
        'wyuka' => array(
            '1 A' => '2010/08/Space-1A-Graves-1-1303.pdf',
            '1 B' => '2010/08/Space-1B.pdf',
            '2 A' => '2010/08/Section-2A.pdf',
            '1' => '2011/08/wyuka-map-section-1.pdf',
            '2' => '2010/08/Section-2.pdf',
            '3' => '2010/08/Section-3.pdf',
            '3 A' => '2010/08/Space-3.pdf',
            '4' => '2010/08/Space-4.pdf',
            '4 A' => '2010/08/Space-4A.pdf',
            '4 FG' => '2020/04/Copier-scan_20200411_115542-dragged-2.pdf',
            '5' => '2010/08/Space-5.pdf',
            '6' => '2010/08/Space-6.pdf',
            '7' => '2010/08/Space-7.pdf',
            '8' => '2010/08/Space-8.pdf',
            '9' => '2010/08/Space-9.pdf',
            '10' => '2010/08/Space-10.pdf',
            '11' => '2010/08/Space-11.pdf',
            '11 HOF' => '2020/04/Copier-scan_20200411_115542-dragged.pdf',
            '12' => '2010/08/Space-12.pdf',
            '13' => '2010/08/Space-13.pdf',
            '14' => '2010/08/Space-14.pdf',
            '15' => '2010/08/Space-15.pdf',
            '15 GAR' => '2010/08/G.A.R.pdf',
            '16' => '2010/08/Section-16.pdf',
            '17' => '2010/08/Section-17.pdf',
            '18' => '2010/08/Section-18.pdf',
            '19' => '2013/09/wyuka.pdf',
            '20' => '2010/08/Section-20.pdf',
            '21' => '2010/08/Section-21.pdf',
            '22' => '2010/08/Section-22.pdf',
            '23' => '2010/08/Section-23.pdf',
            '24' => '2010/08/Section-24.pdf',
            '24 SC' => '2020/04/Copier-scan_20200411_115542-dragged-3.pdf',
            '24 WC' => '2020/04/Copier-scan_20200411_115542-dragged-3.pdf',
            '25' => '2010/07/Section-Map-25.pdf',
            '26' => '2010/08/Section-26.pdf',
            '27' => '2010/08/Section-27.pdf',
            '28' => '2010/08/Section-28.pdf',
            '28 Est' => '2020/04/Copier-scan_20200411_115542-dragged-5.pdf',
            '28 BL' => '2020/04/Copier-scan_20200411_115542-dragged-4.pdf',
            '29' => '2010/08/Section-29.pdf',
            '30' => '2018/05/Copier-scan_20180511_152553.pdf',
            '31' => '2010/08/Section-31.pdf',
            '31 COL' => '2020/04/Copier-scan_20200411_115542-dragged-6.pdf',
            '32' => '2010/08/Section-32.pdf',
            '33' => '2010/08/Section-33.pdf',
            '34' => '2011/08/wyuka-map-section-34.pdf',
            '35' => '2010/08/Section-35.pdf',
            '36' => '2010/08/Section-36.pdf',
            '37' => '2010/08/Section-37.pdf',
            '38' => '2010/08/Section-38.pdf',
            '39' => '2010/08/Section-39.pdf',
            '40' => '2010/08/Section-40.pdf',
            '41' => '2010/08/Section-41-and-41FF.pdf',
            '41 FF' => '2010/08/Section-41-and-41FF.pdf',
            '42' => '2010/08/Section-42.pdf',
            '43' => '2010/08/Section-43.pdf',
            '43 SV' => '2010/08/Section-43SV.pdf',
            '44' => '2010/08/Section-44.pdf',
            '47' => '2010/08/Section-471.pdf',
            '47 LC' => '2020/04/Copier-scan_20200411_115542-dragged-7.pdf',
        ),
        'fairview' => array(
            '1' => '2010/08/Section-11.pdf',
            '2' => '2010/08/Section-25.pdf',
            '3' => '2010/08/Section-34.pdf',
            '4' => '2010/08/Section-4.pdf',
            '5' => '2010/08/Section-5.pdf',
            '6' => '2010/08/Section-6.pdf',
            '7' => '2010/08/Section-7.pdf',
            '8' => '2010/08/Section-8.pdf',
            '9' => '2010/08/Section-9.pdf',
            '10' => '2010/08/Section-10.pdf',
            '11' => '2010/08/Section-111.pdf',
            '12' => '2010/08/Section-12.pdf',
            '13' => '2010/08/Section-13.pdf',
            '14' => '2010/08/Section-14.pdf',
            '15' => '2010/08/Section-15.pdf',
            '16' => '2010/08/Section-161.pdf',
            '17' => '2010/08/Section-17-and-18.pdf',
            '18' => '2010/08/Section-17-and-18.pdf',
            '19' => '2010/08/Section-191.pdf',
            '20' => '2010/08/Section-201.pdf',
            '21' => '2010/08/Section-211.pdf',
            '22' => '2010/08/Section-221.pdf',
            '23' => '2010/08/Section-221.pdf',
            '24' => '2010/08/Section-241.pdf',
            '25' => '2010/08/Section-251.pdf',
            '26' => '2010/08/Section-261.pdf',
        ),
    );
    $cem = strtolower(trim((string) $cemetery));
    $sec = (string) $section; // exact match, same as before
    return isset($maps[$cem][$sec]) ? $base . $maps[$cem][$sec] : '';
}

/** Small inline SVG icons for the result cards. */
function mccm_pub_icon($name, $size = 16) {
    $p = array(
        'pin'      => '<path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/>',
        'born'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        'died'     => '<path d="M12 3v18M7 8h10"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
    );
    return '<svg class="mccm-ico" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$name] . '</svg>';
}

/** One result card. Every value is escaped. */
function mccm_pub_card($r) {
    $v   = function ($k) use ($r) { return trim(stripslashes((string) ($r[$k] ?? ''))); };
    $name = trim(preg_replace('/\s+/', ' ', $v('FIRST') . ' ' . $v('MIDDLE') . ' ' . $v('LAST')));
    $cem  = $v('CEMETERY');
    $dash = '&ndash;';
    $chip = function ($label, $val) use ($dash) {
        return '<span>' . $label . '<b>' . ($val !== '' ? esc_html($val) : $dash) . '</b></span>';
    };
    $born = mccm_s2h($r['BIRTH'] ?? '');
    $died = mccm_s2h($r['DEATH'] ?? '');
    $map  = mccm_pub_map_url($cem, $r['SECTION'] ?? '');

    $o  = '<article class="mccm-card">';
    $o .= '<h3>' . esc_html($name) . ($v('MAIDEN') !== '' ? '<small>(' . esc_html($v('MAIDEN')) . ')</small>' : '') . '</h3>';
    $o .= '<div class="mccm-chips">' . $chip('Section', $v('SECTION')) . $chip('Lot', $v('LOT')) . $chip('Space', $v('SPACE')) . '</div>';
    $o .= '<ul class="mccm-dates"><li><i>' . mccm_pub_icon('born') . '</i><span>Born</span><b>' . ($born !== '' ? esc_html($born) : $dash) . '</b></li>';
    $o .= '<li><i>' . mccm_pub_icon('died') . '</i><span>Died</span><b>' . ($died !== '' ? esc_html($died) : $dash) . '</b></li>';
    if ($cem !== '') $o .= '<li><i>' . mccm_pub_icon('pin') . '</i><span>Cemetery</span><b class="mccm-cem mccm-cem-' . esc_attr(sanitize_html_class(strtolower($cem))) . '">' . esc_html($cem) . '</b></li>';
    $o .= '</ul>';
    if ($map !== '') $o .= '<a class="mccm-btn" href="' . esc_url($map) . '" target="_blank" rel="noopener">' . mccm_pub_icon('download') . 'Download map (PDF)</a>';
    return $o . '</article>';
}

/** [mccm] public grave search: form, result cards, paging. */
function mccm_shortcode($atts = array(), $content = null, $code = "") {
    global $wpdb;
    $perPage = 24;
    $term    = isset($_REQUEST['SearchString']) && is_scalar($_REQUEST['SearchString']) ? trim(mb_substr(wp_unslash((string) $_REQUEST['SearchString']), 0, 60)) : '';
    $page    = max(0, (int) ($_REQUEST['page'] ?? 0));

    $out  = '<div class="mccm-search"><h2>Find a loved one</h2><p>Last name, or Last name, First name (e.g. Smith, John)</p>';
    $out .= '<form method="post" action="' . esc_url(get_permalink()) . '"><input type="text" name="SearchString" placeholder="Last name" value="' . esc_attr($term) . '"><input type="submit" value="Search"></form></div>';

    $parts = array_slice(array_values(array_filter(array_map('trim', explode(',', $term)), function ($p) { return mb_strlen($p) >= 2; })), 0, 3); // max 3 terms, 2+ letters each
    if (!$parts) return $term === '' ? $out : $out . '<div class="mccm-none"><p>Please type at least 2 letters of the last name.</p></div>';

    $table = $wpdb->prefix . 'mccm';
    $cols  = array('LAST','FIRST','MIDDLE','MAIDEN','SECTION','LOT','SPACE','BIRTH','DEATH','CEMETERY','POB','POD','FATHERFIRST','FATHERLAST','MOTHERFIRST','MOTHERLAST','GENDER','DEED','AGE');
    $search = array('LAST','FIRST','MIDDLE','MAIDEN','SECTION','LOT','SPACE','BIRTH','DEATH','CEMETERY');
    $groups = array();
    foreach ($parts as $t) {
        $h = array();
        foreach ($search as $c) {
            $h[] = $wpdb->prepare($c . ' LIKE %s', '%' . $wpdb->esc_like($t) . '%');
            if (addslashes($t) !== $t) $h[] = $wpdb->prepare($c . ' LIKE %s', '%' . $wpdb->esc_like(addslashes($t)) . '%'); // data saved with slashes
        }
        $groups[] = '(' . implode(' OR ', $h) . ')';
    }
    $where = ' WHERE ' . implode(' AND ', $groups);
    $list  = 'SELECT DISTINCT ' . implode(',', $cols) . ' FROM ' . $table . $where;
    $ck    = 'mccm_pub_' . md5($where); // same search again within 5 min: reuse the count
    $total = get_transient($ck);
    if ($total === false) { $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM (' . $list . ') x'); set_transient($ck, $total, 300); }
    $total = (int) $total;
    $page  = min($page, max(0, (int) ceil($total / $perPage) - 1));
    $data  = $wpdb->get_results($list . ' ORDER BY LAST,FIRST ASC LIMIT ' . ($page * $perPage) . ',' . $perPage, ARRAY_A);

    $q = esc_html($term);
    if (!$data) {
        return $out . '<div class="mccm-none"><i>' . mccm_pub_icon('search', 30) . '</i><p>No records found for <b>&ldquo;' . $q . '&rdquo;</b></p><p>Please check the spelling or try the last name only.</p></div>';
    }

    $pages = max(1, (int) ceil($total / $perPage));
    $out .= '<p class="mccm-count"><b>' . number_format_i18n($total) . '</b> ' . ($total === 1 ? 'record' : 'records') . ' found for <b class="mccm-term">&ldquo;' . $q . '&rdquo;</b></p>';
    $out .= '<div class="mccm-results"><div class="mccm-grid">';
    foreach ($data as $r) $out .= mccm_pub_card($r);
    $out .= '</div>';

    if ($pages > 1) {
        $btn = function ($label, $to, $on) use ($term) {
            if (!$on) return '<span class="mccm-pbtn is-off">' . $label . '</span>';
            return '<form method="post"><input type="hidden" name="SearchString" value="' . esc_attr($term) . '"><input type="hidden" name="page" value="' . (int) $to . '"><button class="mccm-pbtn">' . $label . '</button></form>';
        };
        $out .= '<div class="mccm-pager">' . $btn('Previous', $page - 1, $page > 0) . '<span>Page ' . ($page + 1) . ' of ' . $pages . '</span>' . $btn('Next', $page + 1, $page + 1 < $pages) . '</div>';
    }
    return $out . '</div>';
}

add_shortcode('mccm', 'mccm_shortcode');
