<?php
/**
 * Screen: Export. A page with one button per file; the CSV is streamed to the browser and never saved on the server.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_post_mccm_export', 'mccm_export_download');
add_action('admin_post_mccm_export_cleanup', 'mccm_export_cleanup_request');
add_filter('admin_body_class', function ($c) {
    return ($_GET['page'] ?? '') === 'mccm_export' ? $c . ' mccm-form-page' : $c;
});

/** Date columns: empty instead of 0000-00-00. */
function mccm_export_sql_col($col, $isDate) {
    return $isDate ? "IF(`$col` = '0000-00-00', '', `$col`)" : "`$col`";
}

/** All record columns except the ID: the 37 the Import reads, then the 7 contact columns (the Import ignores those). */
function mccm_export_record_columns() {
    $t = new MCCM_List_Table();
    return array_slice($t->get_columns(), 1, null, true);
}

/** What the file contains: heading row, SQL select list, row builder. Same column order as the Import. */
function mccm_export_spec() {
    global $wpdb;
    $dates = array('BIRTH', 'DEATH', 'BURIAL', 'DISINTERMENT');
    $sel = array();
    $all = mccm_export_record_columns();
    foreach (array_keys($all) as $c) $sel[] = mccm_export_sql_col($c, in_array($c, $dates, true)) . " AS `$c`";
    return array('table' => $wpdb->prefix . 'mccm', 'file' => 'records', 'head' => array_values($all), 'select' => $sel,
        'row' => function ($r) { return array_values($r); });
}

/** Stream the file in batches of 1000 rows (small memory, nothing written to disk). */
function mccm_export_download() {
    if (!current_user_can('manage_options')) wp_die('Not allowed', '', array('response' => 403));
    check_admin_referer('mccm_export');
    global $wpdb;
    $spec = mccm_export_spec();
    @set_time_limit(0);
    while (ob_get_level()) ob_end_clean();
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wyuka-' . $spec['file'] . '-' . wp_date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly
    fputcsv($out, $spec['head'], ',', '"', '');
    $last = 0;
    do {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT UID AS `__uid`, ' . implode(', ', $spec['select']) . ' FROM `' . $spec['table'] . '` WHERE UID > %d ORDER BY UID LIMIT 1000', $last), ARRAY_A);
        foreach ($rows as $r) {
            $last = (int) $r['__uid'];
            unset($r['__uid']);
            fputcsv($out, call_user_func($spec['row'], $r), ',', '"', '');
        }
        flush();
    } while (count($rows) === 1000);
    fclose($out);
    exit;
}

/* ---- old export files left by earlier versions ---- */

function mccm_export_old_files($dir = null) {
    $dir = $dir ?: MCCM_PLUGIN_DIR . 'exports/';
    $out = array();
    foreach ((array) glob($dir . 'export_*.csv') as $f) {
        if (preg_match('/^export_\d+_[A-Za-z0-9]+\.csv$/', basename($f)) && is_file($f)) $out[] = $f;
    }
    return $out;
}

/** Delete only the files the old exporter created. Returns how many were removed. */
function mccm_export_cleanup($dir = null) {
    $n = 0;
    foreach (mccm_export_old_files($dir) as $f) if (@unlink($f)) $n++;
    return $n;
}

function mccm_export_cleanup_request() {
    if (!current_user_can('manage_options')) wp_die('Not allowed', '', array('response' => 403));
    check_admin_referer('mccm_export_cleanup');
    $n = mccm_export_cleanup();
    wp_safe_redirect(admin_url('admin.php?page=mccm_export&mccm_cleaned=' . (int) $n));
    exit;
}

/* ---- the page ---- */

function mccm_screen_export() {
    global $wpdb;
    $counts = array('records' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'mccm'));
    $cards = array(
        array('type' => 'records', 'icon' => 'users', 'title' => 'Burial records', 'desc' => 'Every burial record, with all columns.', 'noun' => 'records', 'cols' => count(mccm_export_record_columns())),
    );

    echo '<div class="wrap mccm-ui" id="mccm-ui">';
    echo '<nav class="mccm-crumbs" aria-label="Breadcrumb"><a href="' . esc_url(admin_url('admin.php?page=mccm')) . '">Lot Manager</a> <span>/</span> Export</nav>';
    echo '<h1 class="mccm-title">Export data</h1>';
    echo '<p class="mccm-sub">Download your data as a CSV file that opens in Excel.</p>';
    if (isset($_GET['mccm_cleaned'])) echo '<div class="mccm-summary" role="status"><strong>Done.</strong> ' . (int) $_GET['mccm_cleaned'] . ' old export file(s) deleted.</div>';

    echo '<div class="mccm-imp-layout"><aside class="mccm-imp-side"><div class="mccm-imp-sticky">';
    echo '<section class="mccm-card"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('file') . '</span><div><h2>How it works</h2><p>Three steps.</p></div></header>';
    echo '<ol class="mccm-howto">';
    echo '<li><b>Download.</b> Click the button. The file is made as you download it and is not kept on the server.</li>';
    echo '<li><b>Open in Excel.</b> It is a normal CSV with a header row.</li>';
    echo '<li><b>Change data?</b> Edit the file, then use <a href="' . esc_url(admin_url('admin.php?page=mccm_import')) . '">Import</a> with Update. It has the same columns.</li>';
    echo '</ol>';
    echo '<div class="mccm-rules"><h3>Good to know</h3><ul>';
    echo '<li class="is-danger"><b>Editing to import again?</b> Open the file with Data, then From Text/CSV, and set Interment #, Section, Lot and Space to Text. Otherwise Excel turns <b>007</b> into 7.</li>';
    echo '<li>A big file takes a few seconds to start. Wait for the download.</li>';
    echo '<li>Need a filtered or printable list? Use <a href="' . esc_url(admin_url('admin.php?page=mccmreports')) . '">Reports</a>.</li>';
    echo '</ul></div></section>';
    echo '</div></aside><div class="mccm-imp-main">';

    foreach ($cards as $c) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=mccm_export&type=' . $c['type']), 'mccm_export');
        echo '<section class="mccm-card mccm-imp" id="mccm-exp-' . esc_attr($c['type']) . '">';
        echo '<header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon($c['icon']) . '</span><div><h2>' . esc_html($c['title']) . '</h2><p>' . esc_html($c['desc']) . '</p></div></header>';
        echo '<p class="mccm-exp-facts"><b>' . esc_html(number_format_i18n($counts[$c['type']])) . '</b> ' . esc_html($c['noun']) . ' &middot; <b>' . (int) $c['cols'] . '</b> columns</p>';
        echo '<div class="mccm-imp-foot"><a class="mccm-btn mccm-btn-main mccm-imp-go" href="' . esc_url($url) . '">' . mccm_icon('download') . '<span>Download ' . esc_html($c['noun']) . ' (CSV)</span></a></div>';
        echo '</section>';
    }

    $old = mccm_export_old_files();
    if ($old) {
        $size = 0;
        foreach ($old as $f) $size += (int) @filesize($f);
        $del = wp_nonce_url(admin_url('admin-post.php?action=mccm_export_cleanup'), 'mccm_export_cleanup');
        echo '<section class="mccm-card mccm-imp"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('alert') . '</span><div><h2>Old export files</h2><p>Left on the server by an earlier version of this plugin.</p></div></header>';
        echo '<p class="mccm-exp-facts"><b>' . count($old) . '</b> file(s) &middot; <b>' . esc_html(size_format($size)) . '</b>. They are no longer needed.</p>';
        echo '<div class="mccm-imp-foot"><a class="mccm-btn mccm-btn-soft mccm-imp-go" href="' . esc_url($del) . '" data-mccm-title="Delete old export files?" data-mccm-confirm="This cannot be undone." data-mccm-btn="Delete files" data-mccm-danger="1">' . mccm_icon('alert') . '<span>Delete old export files</span></a></div></section>';
    }
    echo '</div></div></div>';
}
