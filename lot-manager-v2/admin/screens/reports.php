<?php
/**
 * Screen: Reports. Filtered lists to read on screen, print or download as CSV.
 * Row ids are read first, then the rows in batches of 1000, so a big report starts at once and uses little memory.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_post_mccm_report', 'mccm_report_run');
add_filter('admin_body_class', function ($c) {
    return ($_GET['page'] ?? '') === 'mccmreports' ? $c . ' mccm-form-page' : $c;
});

/** Sort choices (column => label). The first one is the default. */
function mccm_report_sorts($rpt) {
    if ($rpt === 'allwo') return array('WODATE' => 'Work order date', 'WO' => 'Work order #', 'COMPANY' => 'Company');
    return array('LAST' => 'Last name', 'FIRST' => 'First name', 'SECTION' => 'Section', 'LOT' => 'Lot', 'SPACE' => 'Space', 'DEATH' => 'Date of death');
}

/** A YYYY-MM-DD date or ''. */
function mccm_report_date($v) {
    $v = is_string($v) ? trim($v) : '';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return '';
    return $v;
}

/** Up to three allowed sort columns, no repeats. */
function mccm_report_order($rpt, $in) {
    $ok = array_keys(mccm_report_sorts($rpt));
    $cols = array();
    foreach ((array) $in as $c) {
        if (is_string($c) && in_array($c, $ok, true) && !in_array($c, $cols, true)) $cols[] = $c;
    }
    return $cols ? array_slice($cols, 0, 3) : array($ok[0]);
}

/** Everything one report needs: title, columns, the matching row ids (or the rows) and a line describing the filters. */
function mccm_report_spec($rpt, $q) {
    global $wpdb;
    $t = $wpdb->prefix . 'mccm';
    $tw = $wpdb->prefix . 'mccm_workorders';
    $get = function ($k) use ($q) { return isset($q[$k]) && is_string($q[$k]) ? $q[$k] : ''; };

    if ($rpt === 'lot' || $rpt === 'allwo') {
        $isLot = $rpt === 'lot';
        $dcol = $isLot ? 'DEATH' : 'WODATE';
        $table = $isLot ? $t : $tw;
        $where = array('1=1');
        $filters = array();
        $from = mccm_report_date($get('from'));
        $to = mccm_report_date($get('to'));
        if ($from && $to && $from > $to) list($from, $to) = array($to, $from);
        if ($from) $where[] = $wpdb->prepare("`$dcol` >= %s", $from);
        if ($to) $where[] = $wpdb->prepare("`$dcol` <= %s", $to);
        if ($from || $to) $filters[] = ($isLot ? 'Died' : 'Work orders') . ': ' . ($from ? mccm_s2h($from) : 'any') . ' to ' . ($to ? mccm_s2h($to) : 'any');
        if ($isLot) {
            foreach (array('cemetery' => 'CEMETERY', 'section' => 'SECTION', 'lot' => 'LOT', 'space' => 'SPACE') as $k => $col) {
                $v = $get($k);
                if ($v === '' || $v === 'all') continue;
                $where[] = $wpdb->prepare("`$col` = %s", $v);
                $filters[] = ucfirst($k) . ': ' . stripslashes($v);
            }
        }
        $order = mccm_report_order($rpt, $q['orderby'] ?? array());
        $sorts = mccm_report_sorts($rpt);
        $filters[] = 'Sorted by ' . implode(', ', array_map(function ($c) use ($sorts) { return strtolower($sorts[$c]); }, $order));
        $ids = $wpdb->get_col("SELECT UID FROM `$table` WHERE " . implode(' AND ', $where) . ' ORDER BY `' . implode('`, `', $order) . '`, UID');
        $wpdb->flush(); // drop the result set; only the ids are kept
        if ($isLot) {
            $head = array('LAST' => 'Last name', 'FIRST' => 'First name', 'MIDDLE' => 'Middle', 'CEMETERY' => 'Cemetery', 'SECTION' => 'Section', 'LOT' => 'Lot', 'SPACE' => 'Space', 'BIRTH' => 'Born', 'DEATH' => 'Died');
            return array('title' => 'Grave report', 'file' => 'grave-report', 'table' => $table, 'head' => $head, 'dates' => array('BIRTH', 'DEATH'), 'filters' => $filters, 'ids' => $ids, 'count' => count($ids));
        }
        $head = array('WODATE' => 'Date', 'WO' => 'Work order #', 'CEMETERY' => 'Cemetery', 'SECTION' => 'Section', 'LOT' => 'Lot', 'SPACE' => 'Space', 'WORK' => 'Work requested', 'COMPLETE' => 'Completed', 'COMPANY' => 'Company');
        return array('title' => 'Work order report', 'file' => 'work-order-report', 'table' => $table, 'head' => $head, 'dates' => array('WODATE', 'COMPLETE'), 'filters' => $filters, 'ids' => $ids, 'count' => count($ids));
    }

    if ($rpt === 'dupes') {
        $rows = $wpdb->get_results("SELECT * FROM (SELECT COUNT(*) as CNT,INTERMENT,CEMETERY,SECTION,LOT,SPACE,LAST, FIRST FROM `$t` GROUP BY INTERMENT,CEMETERY,SECTION,LOT,SPACE,LAST,FIRST) as temptble WHERE CNT>1 ORDER BY INTERMENT,CEMETERY,SECTION,LOT,SPACE", ARRAY_A);
        $head = array('CNT' => 'Count', 'INTERMENT' => 'Interment #', 'CEMETERY' => 'Cemetery', 'SECTION' => 'Section', 'LOT' => 'Lot', 'SPACE' => 'Space', 'LAST' => 'Last name', 'FIRST' => 'First name');
        return array('title' => 'Duplicates report', 'file' => 'duplicates-report', 'head' => $head, 'dates' => array(), 'filters' => array('Same interment #, cemetery, section, lot, space, last and first name'), 'rows' => (array) $rows, 'count' => count((array) $rows));
    }

    $age = (int) $get('contactage');
    if (!in_array($age, array(1, 2, 5, 99), true)) $age = 2;
    $year = (int) gmdate('Y', strtotime('-' . $age . ' year'));
    $ids = $wpdb->get_col($wpdb->prepare("SELECT UID FROM `$t` WHERE CONTACT_NAME IS NOT NULL AND CONTACT_YEAR > %d ORDER BY UID", $year));
    $wpdb->flush();
    $head = array('CONTACT_NAME' => 'Name', 'CONTACT_STREET1' => 'Street 1', 'CONTACT_STREET2' => 'Street 2', 'CONTACT_CITY' => 'City', 'CONTACT_STATE' => 'State', 'CONTACT_ZIP' => 'Zip', 'CONTACT_YEAR' => 'Year added');
    return array('title' => 'Contacts report', 'file' => 'contacts-report', 'table' => $t, 'head' => $head, 'dates' => array(), 'filters' => array($age === 99 ? 'Contacts added in any year' : 'Contacts added in the last ' . $age . ($age === 1 ? ' year' : ' years')), 'ids' => $ids, 'count' => count($ids));
}

/** Yield the rows one at a time, reading 1000 from the database per query. */
function mccm_report_rows($spec) {
    global $wpdb;
    if (isset($spec['rows'])) {
        foreach ($spec['rows'] as $r) yield $r;
        return;
    }
    $cols = '`' . implode('`, `', array_keys($spec['head'])) . '`';
    foreach (array_chunk($spec['ids'], 1000) as $chunk) {
        $rows = $wpdb->get_results('SELECT UID, ' . $cols . ' FROM `' . $spec['table'] . '` WHERE UID IN (' . implode(',', array_map('intval', $chunk)) . ')', OBJECT_K);
        foreach ($chunk as $id) {
            if (isset($rows[$id])) yield (array) $rows[$id];
        }
    }
}

/** Plain text for one cell: legacy backslashes removed, dates as m/d/Y. */
function mccm_report_cell($col, $v, $dates) {
    $v = stripslashes((string) $v);
    return in_array($col, $dates, true) ? mccm_s2h($v) : $v;
}

function mccm_report_run() {
    if (!current_user_can('manage_options')) wp_die('Not allowed', '', array('response' => 403));
    check_admin_referer('mccm_report');
    $q = wp_unslash($_GET);
    $rpt = isset($q['rpt']) && is_string($q['rpt']) ? $q['rpt'] : '';
    if (!in_array($rpt, array('lot', 'allwo', 'dupes', 'contacts'), true)) wp_die('Unknown report', '', array('response' => 400));
    $spec = mccm_report_spec($rpt, $q);
    @set_time_limit(0);
    while (ob_get_level()) ob_end_clean();
    nocache_headers();
    if (($q['format'] ?? '') === 'csv') mccm_report_csv($spec);
    else mccm_report_page($spec, $q);
    exit;
}

/** Stops Excel from running a cell as a formula (= + - @ at the start). Reports only; the Export stays exact for re-import. */
function mccm_report_csv_safe($v) {
    $v = (string) $v;
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false && !is_numeric($v)) ? "'" . $v : $v;
}

function mccm_report_csv($spec) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wyuka-' . $spec['file'] . '-' . wp_date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly
    fputcsv($out, array_values($spec['head']), ',', '"', '');
    $n = 0;
    foreach (mccm_report_rows($spec) as $r) {
        $line = array();
        foreach ($spec['head'] as $c => $label) $line[] = mccm_report_csv_safe(mccm_report_cell($c, $r[$c] ?? '', $spec['dates']));
        fputcsv($out, $line, ',', '"', '');
        if (++$n % 1000 === 0) flush();
    }
    fclose($out);
}

/** A standalone page (no admin chrome) that fills in as the rows arrive. Print uses the browser's print window. */
function mccm_report_page($spec, $q) {
    $csv = add_query_arg(array_merge($q, array('format' => 'csv')), admin_url('admin-post.php'));
    $count = (int) $spec['count'];
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">';
    echo '<title>Wyuka - ' . esc_html($spec['title']) . '</title><style>
body{margin:0;padding:24px 28px 40px;background:#fff;color:#1d1d1f;font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.bar{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px}
.bar button,.bar a{display:inline-flex;align-items:center;height:38px;padding:0 18px;border:1px solid #8c9b95;border-radius:6px;background:#fff;color:#1d1d1f;font:700 12px/1 inherit;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;cursor:pointer}
.bar button:hover,.bar a:hover{background:#eef4f1;border-color:#2f6f5f}
h1{margin:0 0 4px;font-size:24px}
.meta{margin:0 0 18px;color:#555;font-size:13px}
table{width:100%;border-collapse:collapse}
th,td{padding:6px 10px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top;font-size:13px}
th{position:sticky;top:0;background:#f3f5f4;border-bottom:2px solid #b5beba;font-weight:700}
td.none{padding:24px 10px;color:#555}
.sp,.sp td,.sp th{display:none}
.foot{margin:28px 0 0;text-align:center;color:#777;font-size:12px}
.foot a{color:inherit}
@page{size:auto;margin:0}
@media print{
*{color:#000!important;background:none!important}
body{padding:0 12mm}
.bar{display:none}
h1{margin-top:12mm;font-size:20px}
.meta{font-size:11px;margin-bottom:10px}
th{position:static;border-bottom:1.5px solid #000}
td,th{padding:3px 6px;font-size:11px;border-bottom:1px solid #aaa}
thead{display:table-header-group}tfoot{display:table-footer-group}
tr{break-inside:avoid}
.sp,.sp td,.sp th{display:table-cell;height:12mm;padding:0;border:0}
tfoot .sp td{height:16mm}
.foot{position:fixed;left:0;right:0;bottom:6mm;margin:0;font-size:10px}
}
</style></head><body>';
    echo '<div class="bar"><button type="button" onclick="window.print()">Print</button><a href="' . esc_url($csv) . '">Download CSV</a></div>';
    $cols = count($spec['head']);
    echo '<h1>Wyuka &middot; ' . esc_html($spec['title']) . '</h1>';
    echo '<p class="meta">' . esc_html(implode(' · ', $spec['filters'])) . ($spec['filters'] ? ' · ' : '') . '<b>' . esc_html(number_format_i18n($count)) . '</b> ' . ($count === 1 ? 'entry' : 'entries') . ' · ' . esc_html(wp_date('F j, Y')) . '</p>';
    echo '<table><thead><tr class="sp"><td colspan="' . $cols . '"></td></tr><tr>';
    foreach ($spec['head'] as $label) echo '<th>' . esc_html($label) . '</th>';
    echo '</tr></thead><tfoot><tr class="sp"><td colspan="' . $cols . '"></td></tr></tfoot><tbody>';
    flush();
    $n = 0;
    foreach (mccm_report_rows($spec) as $r) {
        echo '<tr>';
        foreach ($spec['head'] as $c => $label) echo '<td>' . esc_html(mccm_report_cell($c, $r[$c] ?? '', $spec['dates'])) . '</td>';
        echo '</tr>';
        if (++$n % 500 === 0) flush();
    }
    if (!$n) echo '<tr><td class="none" colspan="' . $cols . '">No entries match these filters.</td></tr>';
    echo '</tbody></table>';
    echo '<p class="foot">Report created with Lot Manager by <a href="' . esc_url(MCCM_AUTHOR_URI) . '">' . esc_html(MCCM_AUTHOR_NAME) . '</a> &middot; ' . esc_html(preg_replace('#^https?://#', '', MCCM_AUTHOR_URI)) . '</p>';
    echo '</body></html>';
}

/* ---- the page ---- */

function mccm_report_field($id, $label, $control, $hint = '') {
    return '<div class="mccm-field"><div class="mccm-label-row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></div>' . $control . ($hint ? '<p class="mccm-hint">' . esc_html($hint) . '</p>' : '') . '</div>';
}

function mccm_report_date_field($id, $name, $label) {
    return mccm_report_field($id, $label, '<input type="date" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" min="1700-01-01" max="2200-12-31">');
}

/** A dropdown. $opts is value => label; the first option is the "all" or blank choice. */
function mccm_report_select($id, $label, $opts, $name = null, $selected = '') {
    $h = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name ?: $id) . '">';
    foreach ($opts as $v => $l) $h .= '<option value="' . esc_attr($v) . '"' . selected((string) $v, (string) $selected, false) . '>' . esc_html($l) . '</option>';
    return mccm_report_field($id, $label, $h . '</select>');
}

/** A text box that suggests the values in the database as you type. Empty means all. */
function mccm_report_suggest($id, $label, $col, $placeholder) {
    $vals = mccm_report_distinct($col, '');
    unset($vals[''], $vals['all']);
    $dl = '<datalist id="' . esc_attr($id) . '-list">';
    foreach (array_keys($vals) as $v) $dl .= '<option value="' . esc_attr($v) . '">';
    $in = '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($id) . '" list="' . esc_attr($id) . '-list" autocomplete="off" placeholder="' . esc_attr($placeholder) . '">';
    return mccm_report_field($id, $label, $in . $dl . '</datalist>');
}

/** Distinct values of a column as value => label, with an "all" choice first. */
function mccm_report_distinct($col, $all) {
    global $wpdb;
    $out = array('all' => $all);
    $vals = get_transient('mccm_distinct_' . $col); // 10 min cache: the full-table scan ran on every page load
    if (!is_array($vals)) {
        $vals = (array) $wpdb->get_col('SELECT DISTINCT `' . $col . '` FROM `' . $wpdb->prefix . 'mccm` WHERE `' . $col . "` <> '' ORDER BY `" . $col . '`');
        set_transient('mccm_distinct_' . $col, $vals, 600);
    }
    foreach ($vals as $v) $out[$v] = stripslashes($v);
    return $out;
}

function mccm_report_card($rpt, $icon, $title, $desc, $body) {
    echo '<section class="mccm-card mccm-imp" id="mccm-rep-' . esc_attr($rpt) . '">';
    echo '<form method="get" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="mccm_report"><input type="hidden" name="rpt" value="' . esc_attr($rpt) . '">';
    echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mccm_report')) . '">';
    echo '<header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon($icon) . '</span><div><h2>' . esc_html($title) . '</h2><p>' . esc_html($desc) . '</p></div></header>';
    echo $body;
    echo '<div class="mccm-imp-foot mccm-rep-btns">';
    echo '<button type="submit" name="format" value="html" formtarget="_blank" class="mccm-btn mccm-btn-main">' . mccm_icon('file') . '<span>Open report</span></button>';
    echo '<button type="submit" name="format" value="csv" formtarget="_self" class="mccm-btn mccm-btn-soft">' . mccm_icon('download') . '<span>Download CSV</span></button>';
    echo '</div></form></section>';
}

function mccm_screen_reports() {
    $sort3 = function ($rpt, $idBase) {
        $s = mccm_report_sorts($rpt);
        $first = array_keys($s);
        $h = mccm_report_select($idBase . '1', 'Sort by', $s, 'orderby[]', $first[0]);
        $h .= mccm_report_select($idBase . '2', 'Then by', array('' => 'None') + $s, 'orderby[]', $rpt === 'lot' ? 'FIRST' : '');
        $h .= mccm_report_select($idBase . '3', 'Then by', array('' => 'None') + $s, 'orderby[]');
        return $h;
    };

    echo '<div class="wrap mccm-ui" id="mccm-ui">';
    echo '<nav class="mccm-crumbs" aria-label="Breadcrumb"><a href="' . esc_url(admin_url('admin.php?page=mccm')) . '">Lot Manager</a> <span>/</span> Reports</nav>';
    echo '<h1 class="mccm-title">Reports</h1>';
    echo '<p class="mccm-sub">Filtered lists you can read, print or download as a CSV file.</p>';

    echo '<div class="mccm-imp-layout"><aside class="mccm-imp-side"><div class="mccm-imp-sticky">';
    echo '<section class="mccm-card"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('file') . '</span><div><h2>How it works</h2><p>Three steps.</p></div></header>';
    echo '<ol class="mccm-howto">';
    echo '<li><b>Choose filters.</b> Leave a field empty to include everything.</li>';
    echo '<li><b>Open report.</b> It opens in a new tab and fills in as the rows load. Use <b>Print</b> there to print it.</li>';
    echo '<li><b>Need Excel?</b> Click <b>Download CSV</b> instead.</li>';
    echo '</ol>';
    echo '<div class="mccm-rules"><h3>Good to know</h3><ul>';
    echo '<li>A big report takes a few seconds to start. Wait for the first rows.</li>';
    echo '<li>Want every record and every column? Use <a href="' . esc_url(admin_url('admin.php?page=mccm_export')) . '">Export</a>.</li>';
    echo '</ul></div></section>';
    echo '</div></aside><div class="mccm-imp-main">';

    $grid = function ($html) { return '<div class="mccm-grid">' . $html . '</div>'; };

    $lot = mccm_report_date_field('lot-from', 'from', 'Died from') . mccm_report_date_field('lot-to', 'to', 'Died to');
    $lot .= mccm_report_select('cemetery', 'Cemetery', mccm_report_distinct('CEMETERY', 'All cemeteries'));
    $lot .= mccm_report_select('section', 'Section', mccm_report_distinct('SECTION', 'All sections'));
    $lot .= mccm_report_suggest('lot', 'Lot', 'LOT', 'All lots');
    $lot .= mccm_report_suggest('space', 'Space', 'SPACE', 'All spaces');
    $lot .= $sort3('lot', 'orderby-lot-');
    mccm_report_card('lot', 'pin', 'Grave report', 'Burial records by date of death and location.', $grid($lot));

    $wo = mccm_report_date_field('wo-from', 'from', 'Work orders from') . mccm_report_date_field('wo-to', 'to', 'Work orders to') . $sort3('allwo', 'orderby-wo-');
    mccm_report_card('allwo', 'card', 'Work order report', 'All work orders in a date range.', $grid($wo));

    mccm_report_card('dupes', 'users', 'Duplicates report', 'Records that repeat the same interment #, cemetery, section, lot, space, last name and first name.', '<p class="mccm-exp-facts">No filters. Checks every record.</p>');

    $age = mccm_report_select('contactage', 'Added in the last', array(1 => '1 year', 2 => '2 years', 5 => '5 years', 99 => 'All years'), 'contactage', 2);
    mccm_report_card('contacts', 'user', 'Contacts report', 'Contact names and addresses from burial records.', $grid($age));

    echo '</div></div></div>';
}
