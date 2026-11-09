<?php
/**
 * Admin AJAX
 * Report and export endpoints (staff only), plus the record details panel endpoint.
 */
if (!defined('ABSPATH')) exit;




add_action("wp_ajax_generatereport","mccm_generate_report");

// Staff only: there is deliberately no wp_ajax_nopriv_ hook for this.


/** Print views of work orders, opened from the Lot Manager list. Staff only, read-only. The filtered reports live in screens/reports.php. */
function mccm_generate_report() {
	if (!current_user_can('add_users')) wp_die('Not allowed', '', array('response' => 403));
	if (!wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'mccm_wo_print')) wp_die('This link has expired. Go back, reload the page and try again.', '', array('response' => 403));
	global $wpdb;
	$t  = $wpdb->prefix . 'mccm';
	$tw = $wpdb->prefix . 'mccm_workorders';
	$type = $_REQUEST['reptype'] ?? '';
	$h = function ($v) { return esc_html(stripslashes((string) $v)); };
	$row = function ($d) use ($h) {
		return '<tr><td>' . $h(mccm_s2h($d->WODATE)) . '</td><td>' . $h($d->WORK) . '</td><td>' . $h(mccm_s2h($d->COMPLETE)) . '</td><td>' . $h($d->COMPANY) . '</td></tr>';
	};
	$head = '<table><tr><th>Date</th><th>Work Requested</th><th>Date Completed</th><th>Company</th></tr>';
	echo '<html><head><meta charset="utf-8"><style>table{width:95%;border:1px solid black;margin:0 auto;border-collapse:collapse}td,th{border:1px solid black;padding:10px 20px}</style></head><body>';

	if ($type === 'wo') {
		$data = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tw` WHERE UID = %d", (int) ($_REQUEST['woid'] ?? 0)));
		if (!$data) wp_die('Work order not found', '', array('response' => 404));
		$p = $wpdb->get_row($wpdb->prepare("SELECT FIRST, MIDDLE, LAST FROM `$t` WHERE CEMETERY = %s AND SECTION = %s AND LOT = %s AND SPACE = %s", $data->CEMETERY, $data->SECTION, $data->LOT, $data->SPACE));
		echo '<h2>Work Order ' . $h($data->WO) . ($p ? ' - ' . $h($p->FIRST) . ' ' . $h($p->MIDDLE) . ' ' . $h($p->LAST) : '') . '</h2>';
		echo '<h3>' . $h($data->CEMETERY) . '  SEC: ' . $h($data->SECTION) . ',  LOT: ' . $h($data->LOT) . ',  SPACE: ' . $h($data->SPACE) . '</h3>';
		echo $head . $row($data) . '</table>';
	} elseif ($type === 'wos') {
		$p = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$t` WHERE UID = %d", (int) ($_REQUEST['id'] ?? 0)));
		if (!$p) wp_die('Record not found', '', array('response' => 404));
		$data = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$tw` WHERE CEMETERY = %s AND SECTION = %s AND LOT = %s AND SPACE = %s", $p->CEMETERY, $p->SECTION, $p->LOT, $p->SPACE));
		echo '<h2>All Work Orders - ' . $h($p->FIRST) . ' ' . $h($p->MIDDLE) . ' ' . $h($p->LAST) . '</h2>';
		echo '<h3>' . $h($p->CEMETERY) . '  SEC: ' . $h($p->SECTION) . ',  LOT: ' . $h($p->LOT) . ',  SPACE: ' . $h($p->SPACE) . '</h3>';
		echo $head;
		foreach ($data as $d) echo $row($d);
		echo '</table>';
	}

	echo '<script>window.print();</script></body></html>';
	die();
}

/** AJAX: one record's panel. Staff only, nonce-checked, read-only, one query by primary key. */
add_action('wp_ajax_mccm_row', function () {
    if (!current_user_can('add_users') || !check_ajax_referer('mccm_row', '_wpnonce', false)) wp_die('Not allowed', '', array('response' => 403));
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'mccm WHERE UID = %d', (int) ($_GET['id'] ?? 0)), ARRAY_A);
    if (!$row) wp_die('Record not found', '', array('response' => 404));
    echo mccm_row_panel($row);
    wp_die();
});
