<?php
/**
 * Screen: Import (CSV). One engine for burial records and work orders; the form UI is in import-form.php.
 * Reads the file row by row, checks every row, and reports what really happened.
 */
if (!defined('ABSPATH')) exit;

/** One CSV date cell. Returns array(status, iso): empty | ok | partial (kept as exported) | bad. A 4-digit year is required. */
function mccm_import_date_parse($d) {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') return array('empty', '');
    if (preg_match('#^(\d{1,2})\s*/\s*(\d{1,2})\s*/\s*(\d{4})$#', $d, $m)) { $mo = (int) $m[1]; $da = (int) $m[2]; $y = (int) $m[3]; }
    elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $d, $m)) {
        $y = (int) $m[1]; $mo = (int) $m[2]; $da = (int) $m[3];
        if ($y > 0 && ($mo === 0 || $da === 0) && $mo <= 12 && $da <= 31) return array('partial', $d);
    } else return array('bad', '');
    if (!checkdate($mo, $da, $y)) return array('bad', '');
    return array('ok', sprintf('%04d-%02d-%02d', $y, $mo, $da));
}

/** Cell text cleaned like the add/edit form does (no tags, no control characters). Backslashes are kept as stored. */
function mccm_import_text($v) {
    return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_strip_all_tags((string) $v)));
}

/** varchar limits of a table (column => max length). */
function mccm_import_limits($table) {
    global $wpdb;
    $lim = array();
    foreach ((array) $wpdb->get_results('SHOW COLUMNS FROM `' . $table . '`') as $c) {
        if (preg_match('/^varchar\((\d+)\)/i', $c->Type, $m)) $lim[$c->Field] = (int) $m[1];
    }
    return $lim;
}

/** What one kind of file maps to: table, column names by file position, labels, dates, the update match key. */
function mccm_import_spec($type) {
    global $wpdb;
    if ($type === 'wo') {
        $cols = array('WODATE', 'CEMETERY', 'SECTION', 'LOT', 'SPACE', null, null, 'WORK', 'COMPLETE', 'WO', 'COMPANY'); // the two name columns are ignored
        $labels = mccm_import_wo_columns();
        $table = $wpdb->prefix . 'mccm_workorders';
        $spec = array('type' => 'wo', 'table' => $table, 'cols' => $cols, 'dates' => array('WODATE', 'COMPLETE'), 'match' => array('WO', 'CEMETERY', 'SECTION', 'LOT', 'SPACE'), 'min' => 11, 'probe' => 'WORK', 'stamp' => current_time('mysql'));
    } else {
        $cols = mccm_import_record_keys();
        $labels = mccm_import_record_columns();
        $table = $wpdb->prefix . 'mccm';
        $spec = array('type' => 'records', 'table' => $table, 'cols' => $cols, 'dates' => array('BIRTH', 'DEATH', 'BURIAL', 'DISINTERMENT'), 'match' => array('INTERMENT', 'CEMETERY', 'SECTION', 'LOT', 'SPACE'), 'min' => 37, 'probe' => 'LAST', 'stamp' => current_time('mysql'));
    }
    $spec['labels'] = array();
    foreach ($cols as $k => $c) if ($c) $spec['labels'][$c] = $labels[$k];
    $spec['limits'] = mccm_import_limits($table);
    $cs = $wpdb->get_col_charset($table, $spec['probe']);
    $spec['latin'] = is_string($cs) && stripos($cs, 'latin1') === 0;
    return $spec;
}

/** One row -> array(values by column, problems with the cell itself). Dates come back as ISO text, ages as numbers. */
function mccm_import_row($spec, $data) {
    $vals = array();
    $bad = array();
    foreach ($spec['cols'] as $k => $col) {
        if ($col === null) continue;
        $raw = (string) ($data[$k] ?? '');
        if (in_array($col, $spec['dates'], true)) {
            list($st, $iso) = mccm_import_date_parse($raw);
            if ($st === 'bad') $bad[$col] = 'not a valid date (use M/D/YYYY or YYYY-MM-DD, 4-digit year)';
            $vals[$col] = $st === 'empty' ? ($col === 'DISINTERMENT' ? '0000-00-00' : null) : $iso; // DISINTERMENT cannot be NULL in the table
        } elseif ($col === 'AGE') {
            $t = trim($raw);
            if ($t !== '' && !ctype_digit($t)) $bad[$col] = 'not a whole number';
            $vals[$col] = ($t !== '' && ctype_digit($t)) ? (int) $t : 0;
        } else {
            $vals[$col] = mccm_import_text($raw);
        }
    }
    return array($vals, $bad);
}

/** Case- and space-insensitive key, like the database comparison. */
function mccm_import_key($vals, $match) {
    $p = array();
    foreach ($match as $c) $p[] = trim((string) ($vals[$c] ?? ''));
    return mb_strtolower(implode("\x1f", $p));
}

/** Everything wrong with a row, as short phrases. Empty array = fine. $old = the existing record (update) or null (new). */
function mccm_import_problems($spec, $vals, $bad, $old) {
    $p = array();
    $lab = $spec['labels'];
    foreach ($bad as $col => $msg) $p[] = $lab[$col] . ': ' . $msg;
    if ($spec['type'] === 'wo') {
        if ($vals['CEMETERY'] === '') $p[] = 'Missing ' . $lab['CEMETERY'];
        if ($vals['WORK'] === '') $p[] = 'Missing ' . $lab['WORK'];
    } else {
        $always = mccm_always_required();
        foreach (mccm_required_fields() as $k => $label) {
            if (isset($bad[$k])) continue;
            if ((string) $vals[$k] !== '') continue;
            if ($old !== null && !in_array($k, $always, true)) {
                $ov = trim((string) ($old[$k] ?? ''));
                if ($ov === '' || $ov === '0000-00-00') continue; // an old incomplete record stays updatable
            }
            $p[] = 'Missing ' . $label;
        }
        if ($old !== null && $vals['LAST'] === '' && $vals['FIRST'] === '') $p[] = 'Missing first or last name';
    }
    foreach ($spec['limits'] as $col => $max) {
        if (!isset($vals[$col])) continue;
        $len = mb_strlen((string) $vals[$col]);
        if ($len > $max) $p[] = $lab[$col] . ': too long (' . $len . ' of ' . $max . ' characters)';
    }
    if ($spec['latin']) {
        foreach ($vals as $col => $v) {
            if (!is_string($v) || $v === '' || isset($bad[$col]) || in_array($col, $spec['dates'], true)) continue;
            if (mb_convert_encoding(mb_convert_encoding($v, 'Windows-1252', 'UTF-8'), 'UTF-8', 'Windows-1252') !== $v) $p[] = $lab[$col] . ': has characters the database cannot store';
        }
    }
    return $p;
}

/** Remember a skipped row for the report (first 500 only) and count why. $row = the row number as Excel shows it. */
function mccm_import_bad(&$stat, $row, $who, $why) {
    $stat['skipped']++;
    if (count($stat['bad']) < 500) $stat['bad'][] = array($row, $who, implode(', ', $why));
    foreach ($why as $w) {
        $w = preg_replace('/ \(\d+ of \d+ characters\)$/', '', $w);
        $stat['why'][$w] = ($stat['why'][$w] ?? 0) + 1;
    }
}

/** A row the database refused: counted as failed, never as added. */
function mccm_import_fail(&$stat, $row, $who) {
    global $wpdb;
    $stat['failed']++;
    error_log('[wyuka-mccm] import row ' . $row . ' refused by the database: ' . $wpdb->last_error);
    mccm_import_bad($stat, $row, $who, array('Database refused it: ' . substr(preg_replace('/\s+/', ' ', (string) $wpdb->last_error), 0, 120)));
}

/** An SQL value: NULL, a whole number, or a safely quoted string. */
function mccm_import_sql($v) {
    global $wpdb;
    if ($v === null) return 'NULL';
    if (is_int($v)) return (string) $v;
    return $wpdb->prepare('%s', $v);
}

/** Open the uploaded file or stop with the real reason. */
function mccm_import_open_upload() {
    $f = $_FILES['import'] ?? array();
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) mccm_import_stop('The file is too big for this server (limit ' . size_format(wp_max_upload_size()) . '). Split it into smaller files.');
    if ($err !== UPLOAD_ERR_OK || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) mccm_import_stop('No file was uploaded.');
    if (!in_array(strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION)), array('csv', 'txt'), true)) mccm_import_stop('This is not a CSV file. In Excel, use Save As and choose CSV UTF-8 (Comma delimited).');
    $h = fopen($f['tmp_name'], 'rb');
    if (!$h) mccm_import_stop('The file could not be read.');
    return $h;
}

/** The import itself (type: records | wo). Update = find by the match key and change what differs; Add = every row is new. */
function mccm_import_run($type, &$stat) {
    global $wpdb;
    $spec = mccm_import_spec($type);
    $dr = isset($_REQUEST['dryrun']);
    $action = $_REQUEST['impact'] ?? '';
    $skip = max(0, (int) ($_REQUEST['colrow'] ?? 0));
    $user = wp_get_current_user()->user_login;
    if ($type === 'wo') echo 'IMPORTING WORK ORDERS<br />';
    if ($dr) echo 'THIS WILL BE A DRY RUN - NO CHANGES MADE!<br />';
    echo 'SKIPPING FIRST ' . $skip . ' row(s) as header information<br />';
    if (!in_array($action, array('update', 'addto'), true)) mccm_import_stop('Unknown import mode.');
    $h = mccm_import_open_upload();
    $t = $spec['table'];
    $cols = array_values(array_filter($spec['cols']));
    $colList = '`' . implode('`,`', $cols) . '`,`LASTCHANGE`,`CHANGER`';

    // Update mode: read the match keys once (in chunks), so each file row is a lookup, not a table scan.
    $map = array();
    if ($action === 'update') {
        $last = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT `UID`,`' . implode('`,`', $spec['match']) . '` FROM `' . $t . '` WHERE `UID` > %d ORDER BY `UID` LIMIT 5000', $last), ARRAY_A);
            foreach ($rows as $r) {
                $last = (int) $r['UID'];
                $k = mccm_import_key($r, $spec['match']);
                $map[$k] = isset($map[$k]) ? array_merge((array) $map[$k], array($last)) : $last;
            }
        } while (count($rows) === 5000);
        unset($rows);
    }

    $wpdb->suppress_errors(true);
    $pend = array();
    $flush = function () use (&$pend, &$stat, $wpdb, $t, $colList, $dr) {
        if (!$pend) return;
        if (!$dr) {
            $head = 'INSERT INTO `' . $t . '` (' . $colList . ') VALUES ';
            $tuples = array();
            foreach ($pend as $p) $tuples[] = $p[2];
            if (false === $wpdb->query($head . implode(',', $tuples))) { // one bad row must not sink the batch: retry row by row
                foreach ($pend as $p) {
                    if (false === $wpdb->query($head . $p[2])) mccm_import_fail($stat, $p[0], $p[1]);
                    else $stat['add']++;
                }
                $pend = array();
                return;
            }
        }
        $stat['add'] += count($pend);
        $pend = array();
    };

    echo "<div class='mccm-log'>STARTING... ";
    $n = 0;
    $logged = 0;
    while (($data = fgetcsv($h, 0, ',', '"', '')) !== false) {
        $n++;
        if ($n === 1 && isset($data[0])) $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]); // Excel's UTF-8 marker
        if ($n <= $skip) continue;
        if (trim(implode('', array_map('strval', $data))) === '') continue; // empty row
        $stat['rows']++;
        if ($stat['rows'] % 1000 === 0) { echo '...' . $stat['rows']; flush(); }
        $joined = implode("\x1e", array_map('strval', $data));
        if (!mb_check_encoding($joined, 'UTF-8')) { // saved as plain "CSV (Comma delimited)" = Windows-1252
            $data = array_map(function ($c) { return mb_convert_encoding((string) $c, 'UTF-8', 'Windows-1252'); }, $data);
            $stat['converted']++;
        }
        $who = $type === 'wo' ? (trim((string) ($data[9] ?? '')) !== '' ? $data[9] : ($data[7] ?? '')) : trim(($data[2] ?? '') . ' ' . ($data[1] ?? ''));
        $who = trim((string) $who);
        if (count($data) < $spec['min']) {
            mccm_import_bad($stat, $n, $who, array('Only ' . count($data) . ' of ' . $spec['min'] . ' cells'));
            continue;
        }
        list($vals, $bad) = mccm_import_row($spec, $data);
        $key = $action === 'update' ? mccm_import_key($vals, $spec['match']) : null;
        $hit = $key !== null ? ($map[$key] ?? null) : null;

        if (is_array($hit)) { // more than one existing record has this key: never guess
            $stat['dup']++;
            if ($logged++ < 200) echo '<br />DUPLICATE MATCHES FOUND, NOT CHANGING! Row ' . $n . ': ' . esc_html(implode(', ', array_slice($data, 0, 9)));
            continue;
        }
        if ($hit === -1) { // the same new row twice in this file
            mccm_import_bad($stat, $n, $who, array('Same row appears earlier in this file'));
            continue;
        }

        if ($hit !== null) { // update an existing record
            $old = $wpdb->get_row($wpdb->prepare('SELECT * FROM `' . $t . '` WHERE `UID` = %d', $hit), ARRAY_A);
            if (!$old) { mccm_import_bad($stat, $n, $who, array('The matching record disappeared')); continue; }
            if ($why = mccm_import_problems($spec, $vals, $bad, $old)) {
                mccm_import_bad($stat, $n, $who, $why);
                if ($logged++ < 200) echo '<br />SKIPPED row ' . $n . ': ' . esc_html(implode(', ', $why));
                continue;
            }
            $set = array();
            foreach ($vals as $col => $v) {
                $o = $old[$col] ?? null;
                if (in_array($col, $spec['dates'], true)) { $same = ($o === null || $o === '0000-00-00' ? '' : (string) $o) === ($v === null || $v === '0000-00-00' ? '' : (string) $v); }
                elseif (is_int($v)) { $same = (int) $o === $v; }
                else { $same = trim((string) $o) === (string) $v; }
                if (!$same) $set[] = '`' . $col . '` = ' . mccm_import_sql($v);
            }
            if (!$set) { $stat['same']++; continue; }
            $set[] = '`LASTCHANGE` = ' . mccm_import_sql($spec['stamp']);
            $set[] = '`CHANGER` = ' . mccm_import_sql($user);
            if (!$dr && false === $wpdb->query('UPDATE `' . $t . '` SET ' . implode(', ', $set) . ' WHERE `UID` = ' . (int) $hit)) { mccm_import_fail($stat, $n, $who); continue; }
            $stat['update']++;
            continue;
        }

        // a new record
        if ($why = mccm_import_problems($spec, $vals, $bad, null)) {
            mccm_import_bad($stat, $n, $who, $why);
            if ($logged++ < 200) echo '<br />SKIPPED row ' . $n . ': ' . esc_html(implode(', ', $why));
            continue;
        }
        $tuple = array();
        foreach ($cols as $c) $tuple[] = mccm_import_sql($vals[$c]);
        $tuple[] = mccm_import_sql($spec['stamp']);
        $tuple[] = mccm_import_sql($user);
        $pend[] = array($n, $who, '(' . implode(',', $tuple) . ')');
        if ($key !== null) $map[$key] = -1;
        if (count($pend) >= 100) $flush();
    }
    fclose($h);
    $flush();
    $wpdb->suppress_errors(false);
    echo '<br /><br />FINISHED.</div>';
}

function mccm_screen_import() {
    $act = $_REQUEST['action'] ?? '';
    if (!in_array($act, array('import', 'importwo'), true)) { mccm_import_page(); return; }
    if (!current_user_can('manage_options')) wp_die('Not allowed', '', array('response' => 403));
    check_admin_referer('mccm_import');
    @set_time_limit(0);
    ignore_user_abort(true); // a closed browser tab must not leave an import half done
    $stat = array('rows' => 0, 'skipped' => 0, 'dup' => 0, 'update' => 0, 'same' => 0, 'add' => 0, 'failed' => 0, 'converted' => 0, 'bad' => array(), 'why' => array());
    mccm_import_report_open();
    mccm_import_run($act === 'importwo' ? 'wo' : 'records', $stat);
    mccm_import_report_close($stat, isset($_REQUEST['dryrun']));
}
