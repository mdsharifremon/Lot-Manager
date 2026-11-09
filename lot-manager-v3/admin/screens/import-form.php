<?php
/**
 * Import screen: form UI, report wrapper and the CSV template download.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_post_mccm_import_template', 'mccm_import_template');
add_filter('admin_body_class', function ($c) {
    return ($_GET['page'] ?? '') === 'mccm_import' ? $c . ' mccm-form-page' : $c;
});

/** The 37 columns the importer reads: same order as the list table, without the ID. */
function mccm_import_record_keys() {
    $t = new MCCM_List_Table();
    return array_slice(array_keys($t->get_columns()), 1, 37);
}

function mccm_import_record_columns() {
    $t = new MCCM_List_Table();
    return array_slice(array_values($t->get_columns()), 1, 37);
}

function mccm_import_template() {
    if (!current_user_can('manage_options')) wp_die('Not allowed', '', array('response' => 403));
    check_admin_referer('mccm_import_template');
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wyuka-records-template.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly
    fputcsv($out, mccm_import_record_columns(), ',', '"', '\\');
    fclose($out);
    exit;
}

/* ---- report (after a run) ---- */

function mccm_import_crumbs($last) {
    echo '<nav class="mccm-crumbs" aria-label="Breadcrumb"><a href="' . esc_url(admin_url('admin.php?page=mccm')) . '">Lot Manager</a> <span>/</span> ';
    echo $last ? '<a href="' . esc_url(admin_url('admin.php?page=mccm_import')) . '">Import</a> <span>/</span> ' . esc_html($last) : 'Import';
    echo '</nav>';
}

function mccm_import_report_open() {
    $dry = isset($_REQUEST['dryrun']);
    echo '<div class="wrap mccm-ui" id="mccm-ui">';
    mccm_import_crumbs('Report');
    echo '<h1 class="mccm-title">' . ($dry ? 'Test run' : 'Import') . ' report</h1>';
    echo '<p class="mccm-sub">' . ($dry ? 'This was a test. Nothing was saved.' : 'The import is running. Keep this page open until you see Finished.') . '</p>';
    echo '<section class="mccm-card"><div class="mccm-report">';
}

function mccm_import_report_close($stat, $dry) {
    echo '</div></section>';
    if (!$stat['rows']) echo '<div class="mccm-summary" role="alert"><strong>Nothing to import.</strong> No data rows were found. If the file has data, lower the number in "Rows to skip".</div>';
    $tiles = array(
        'rows'    => 'Rows read',
        'update'  => $dry ? 'Would update' : 'Updated',
        'add'     => $dry ? 'Would add' : 'Added',
        'same'    => 'Already up to date',
        'skipped' => 'Skipped',
        'dup'     => 'Duplicates, not changed',
        'failed'  => 'Refused by database',
    );
    echo '<section class="mccm-card"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('check') . '</span><div><h2>' . ($dry ? 'Test finished. Nothing was saved.' : 'Import finished.') . '</h2>';
    echo '<p>' . ($dry ? 'If the numbers look right, run the import again with the test box unticked.' : 'The numbers below are what was changed.') . '</p></div></header>';
    echo '<dl class="mccm-stats">';
    foreach ($tiles as $k => $label) echo '<div class="' . (in_array($k, array('skipped', 'dup', 'failed'), true) && $stat[$k] ? 'is-warn' : '') . '"><dt>' . esc_html($label) . '</dt><dd>' . (int) $stat[$k] . '</dd></div>';
    echo '</dl>';
    if ($stat['converted']) echo '<p class="mccm-stats-note">' . (int) $stat['converted'] . ' row(s) were not UTF-8 and were converted from Windows-1252. Check accented names.</p>';
    if ($stat['dup']) echo '<p class="mccm-stats-note">Duplicate rows are listed in the log above. Each matched more than one existing record, so nothing was changed.</p>';
    echo '</section>';
    mccm_import_problems_card($stat, $dry);
    echo '<p><a class="mccm-btn mccm-btn-soft" href="' . esc_url(admin_url('admin.php?page=mccm_import')) . '">Back to import</a></p></div>';
}

/** Skipped rows: first 10 shown, the rest behind one click. */
function mccm_import_problems_card($stat, $dry) {
    if (!$stat['bad']) return;
    $rows = $stat['bad'];
    $row = function ($r) { return '<tr><td>' . (int) $r[0] . '</td><td>' . esc_html($r[1]) . '</td><td>' . esc_html($r[2]) . '</td></tr>'; };
    echo '<section class="mccm-card"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('alert') . '</span><div><h2>Rows with problems</h2>';
    echo '<p>' . ($dry ? 'These ' . (int) $stat['skipped'] . ' rows would not be imported.' : 'These ' . (int) $stat['skipped'] . ' rows were not imported.') . ' Fix them in your file and upload only these rows. Line numbers match the rows in Excel.</p></div></header>';
    if ($stat['why']) {
        arsort($stat['why']);
        $top = array();
        foreach (array_slice($stat['why'], 0, 4, true) as $w => $n) $top[] = esc_html($w) . ' (' . (int) $n . ')';
        echo '<p class="mccm-stats-note"><b>Most often missing:</b> ' . implode(', ', $top) . '</p>';
    }
    $one = 0;
    foreach ($stat['bad'] as $b) if (strpos($b[2], 'Only 1 of ') === 0) $one++;
    if ($stat['rows'] && $one >= $stat['rows'] * 0.9) echo '<p class="mccm-stats-note"><b>The columns look joined together.</b> They may be separated by semicolons or tabs. In Excel, use Save As and choose CSV UTF-8 (Comma delimited).</p>';
    echo '<table class="mccm-table"><thead><tr><th>Line</th><th>Record</th><th>Problem</th></tr></thead><tbody>';
    foreach (array_slice($rows, 0, 10) as $r) echo $row($r);
    echo '</tbody></table>';
    if (count($rows) > 10) {
        echo '<details class="mccm-more"><summary>Show ' . (count($rows) - 10) . ' more' . ($stat['skipped'] > count($rows) ? ' (first 500 of ' . (int) $stat['skipped'] . ')' : '') . '</summary><table class="mccm-table"><tbody>';
        foreach (array_slice($rows, 10) as $r) echo $row($r);
        echo '</tbody></table></details>';
    }
    echo '</section>';
}

/** Stop with a message inside the page frame. Used instead of die(). */
function mccm_import_stop($msg) {
    echo '</div></section><div class="mccm-summary" role="alert"><strong>Stopped.</strong> ' . esc_html($msg) . '</div>';
    echo '<p><a class="mccm-btn mccm-btn-soft" href="' . esc_url(admin_url('admin.php?page=mccm_import')) . '">Back to import</a></p></div>';
    require_once ABSPATH . 'wp-admin/admin-footer.php';
    exit;
}

/* ---- the form page ---- */

function mccm_import_page() {
    $max = array_filter(array_map(function ($k) { return (int) ini_get($k); }, array('upload_max_filesize', 'post_max_size', 'memory_limit')), function ($v) { return $v > 0; });
    $mb = $max ? min($max) : 0;
    $keys = mccm_import_record_keys();
    $req = array_keys(mccm_required_fields());

    echo '<div class="wrap mccm-ui" id="mccm-ui">';
    mccm_import_crumbs('');
    echo '<h1 class="mccm-title">Import data</h1>';
    echo '<p class="mccm-sub">Load many records at once from an Excel or CSV file. Always run a test first.</p>';
    echo '<div class="mccm-imp-layout"><aside class="mccm-imp-side"><div class="mccm-imp-sticky">';

    echo '<section class="mccm-card"><header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon('file') . '</span><div><h2>How it works</h2><p>Three steps.</p></div></header>';
    echo '<ol class="mccm-howto">';
    echo '<li><b>Get the file.</b> Download the blank template and open it in Excel.</li>';
    echo '<li><b>Fill it in.</b> One row per record. Save as <em>CSV UTF-8 (Comma delimited)</em>.</li>';
    echo '<li><b>Test, then import.</b> Upload with the test box ticked and read the report. If it looks right, run it again with the box unticked.</li>';
    echo '</ol>';
    echo '<div class="mccm-rules"><h3>Rules for the file</h3><ul>';
    echo '<li class="is-danger"><b>A blank cell erases the saved value</b> when you update a record. Keep the existing value in the file if you want to keep it.</li>';
    echo '<li>Do not add, remove or move columns. Keep the header row. Extra columns after the last one are ignored.</li>';
    echo '<li>Columns marked <b>*</b> must be filled in. A row missing one is skipped, and the report says why.</li>';
    echo '<li>Dates: <b>8/7/1873</b> or <b>1873-08-07</b>. Age: a number.</li>';
    echo '<li>Excel turns <b>007</b> into 7 and <b>3-4</b> into a date. Before you type, select the Interment #, Section, Lot and Space columns, then Format Cells, then Text.</li>';
    echo '</ul></div></section>';
    echo '</div></aside><div class="mccm-imp-main">';

    mccm_import_card(array(
        'type' => 'records', 'action' => 'import', 'title' => 'Burial records', 'icon' => 'users', 'skip' => 1,
        'desc' => 'Add or update records in the Lot Manager.',
        'noun' => 'records', 'what' => 'burial records', 'match' => 'Interment #, Section, Lot and Space',
        'cols' => mccm_import_record_columns(), 'stars' => array_keys(array_intersect($keys, $req)), 'mb' => $mb,
    ));
    echo '</div></div></div>';
}

function mccm_import_card($c) {
    $id = 'mccm-imp-' . $c['type'];
    $tpl = wp_nonce_url(admin_url('admin-post.php?action=mccm_import_template&type=' . $c['type']), 'mccm_import_template');
    echo '<section class="mccm-card mccm-imp" id="' . esc_attr($id) . '">';
    echo '<header class="mccm-card-head"><span class="mccm-card-ico">' . mccm_icon($c['icon']) . '</span><div><h2>' . esc_html($c['title']) . '</h2><p>' . esc_html($c['desc']) . '</p></div></header>';
    echo '<form class="mccm-imp-form" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin.php?page=mccm_import')) . '">';
    wp_nonce_field('mccm_import');
    echo '<input type="hidden" name="page" value="mccm_import"><input type="hidden" name="action" value="' . esc_attr($c['action']) . '">';
    echo '<ol class="mccm-steps">';

    echo '<li><h3><b>1</b> Get the file</h3><p><a class="mccm-btn mccm-btn-soft" href="' . $tpl . '">' . mccm_icon('download') . '<span>Download blank template</span></a></p>';
    echo '<details class="mccm-cols"><summary><span><span class="main">' . count($c['cols']) . ' columns in ' . esc_html($c['what']) . '</span><span class="short">Required fields are marked with a star (*)</span></span></summary><ol>';
    foreach ($c['cols'] as $i => $label) echo '<li' . (in_array($i, $c['stars'], true) ? ' class="is-req"' : '') . '>' . esc_html($label) . '</li>';
    echo '</ol></details></li>';

    echo '<li><h3><b>2</b> Choose your file</h3><input type="file" name="import" accept=".csv,text/csv" required>';
    echo '<p class="mccm-hint">CSV only.' . ($c['mb'] ? ' Up to ' . (int) $c['mb'] . ' MB.' : '') . '</p></li>';

    $modes = array(
        'update' => array('Update ' . $c['noun'], 'Finds each one by ' . $c['match'] . ' and changes it. Not found: added as new. More than one found: left alone and reported.'),
        'addto'  => array('Add as new', 'Adds every row as a new entry. Nothing that exists is changed. Run it once: running the same file again adds the rows again.'),
    );
    echo '<li><h3><b>3</b> What should happen?</h3><div class="mccm-modes">';
    foreach ($modes as $v => $m) {
        echo '<label class="mccm-mode"><input type="radio" name="impact" value="' . esc_attr($v) . '"' . ($v === 'update' ? ' checked' : '') . '><span><b>' . esc_html($m[0]) . '</b>' . esc_html($m[1]) . '</span></label>';
    }
    echo '</div></li>';

    echo '<li><h3><b>4</b> Header rows</h3><div class="mccm-field"><label for="' . esc_attr($id) . '-skip">Rows to skip at the top of the file</label>';
    echo '<input type="number" id="' . esc_attr($id) . '-skip" name="colrow" min="0" max="20" required value="' . (int) $c['skip'] . '">';
    echo '<p class="mccm-hint">The template has 1 header row.</p></div></li>';

    echo '<li><h3><b>5</b> Test first</h3><label class="mccm-switch"><input type="checkbox" name="dryrun" value="YES" checked><span><b>Test only.</b> Read the file and show what would happen. Nothing is saved.</span></label></li>';
    echo '</ol>';
    echo '<div class="mccm-imp-foot"><button type="submit" class="mccm-btn mccm-btn-main mccm-imp-go">' . mccm_icon('upload') . '<span data-test="Run test" data-live="Import now">Run test</span></button></div>';
    echo '</form></section>';
}
