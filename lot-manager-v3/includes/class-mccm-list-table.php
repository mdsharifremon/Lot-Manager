<?php
/**
 * List table
 * The records list (WP_List_Table) for the admin.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class MCCM_List_Table extends WP_List_Table {

    function __construct() {
        global $status, $page;

        //Set parent defaults
        parent::__construct(array(
            'singular' => 'Entry', //singular name of the listed records
            'plural' => 'Entrys', //plural name of the listed records
            'ajax' => false        //does this table support ajax?
        ));
    }

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'UID':
                return $item[$column_name];
            default:
                return esc_html(stripslashes((string) ($item[$column_name] ?? ''))); // escaped: imported text must never run as HTML
//                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function column_UID($item) {
        $pg = esc_attr(sanitize_key($_REQUEST['page'] ?? 'mccm'));
        $actions = array(
            'view' => mccm_row_details($item), // opens the details panel
            'edit' => sprintf('<a href="?page=%s&action=%s&id=%d">Edit</a>', $pg, 'edit', (int) $item['UID']),
            'delete' => sprintf('<a href="?page=%s&action=%s&id=%d&_wpnonce=%s" %s>Delete</a>', $pg, 'delete', (int) $item['UID'], wp_create_nonce('mccm_delete_'.$item['UID']), mccm_delete_confirm($item)),
        );
        return (int) $item['UID'] . $this->row_actions($actions); // WordPress style: ID always shown, links appear on hover (CSS)
    }

    function column_AGE($item) {
       if ($item['AGE']==0) return '';
    }

    function column_BIRTH($item) {
               return mccm_s2h($item['BIRTH']);
    }

    function column_DISINTERMENT($item) {
               return mccm_s2h($item['DISINTERMENT']);
    }

    function column_DEATH($item) {
               return mccm_s2h($item['DEATH']);
    }

    function column_CONTACT_STREET1($item) {
      if ($item['CONTACT_NAME']!='') {
        $fa =  $item['CONTACT_STREET1'] . '<br /> ' . (!empty($item['CONTACT_STREET2'])?$item['CONTACT_STREET2']."<br /> ":'') . $item['CONTACT_CITY']. ' ' . $item['CONTACT_STATE']. ', ' . $item['CONTACT_ZIP'];
        return $fa;
      }else return "";

    }

    function column_BURIAL($item) {
               return mccm_s2h($item['BURIAL']);
    }


    function column_INTERMENT($item) {
        $n = mccm_show_val($item['INTERMENT'] ?? '');
        return $n !== '' ? esc_html($n) : '&ndash;&ndash;'; // plain number; the View button is in Actions
    }

    function get_hidden() {
        $columns = array(
            'MIDDLE',
            'MAIDEN',
            'BURIAL',
            'VAULT',
            'MARKER',
            'LEADIN',
            'FAMILY',
            'AGE',
            'POB',
            'POD',
            'GENDER',
            'COD',
            'MILITARY',
            'MARITAL',
            'LOLAST',
            'LOFIRST',
            'LORELATION',
            'FATHERFIRST',
            'FATHERLAST',
            'MOTHERFIRST',
            'MOTHERLAST',
            'FUNERALHOME',
            'DIRECTOR',
            'CHARGES',
            'MORTUARY',
            'DEED',
            'SPOUSE',
            'DISINTERMENT',
            'CONTACT_STREET2',
            'CONTACT_STATE',
            'CONTACT_ZIP',
            'CONTACT_CITY',
            'CONTACT_YEAR'
        );

        return $columns;
    }

    function get_columns() {
        $columns = array(
            'UID' => 'ID',
            'INTERMENT' => 'Interment #',
            'LAST'=>'Decedent Last Name',
            'FIRST' => 'Decedent First Name',
            'MIDDLE'=>'Middle Name/Title',
            'MAIDEN'=>'Maiden Name/Title',
            'CEMETERY'=>'Cemetery',
            'SECTION' => 'Section',
            'LOT' => 'Lot',
            'SPACE' => 'Space',
            'BIRTH' => 'Birth Date',
            'DEATH' => 'Death Date',
            'BURIAL'=>'Burial Date',
			      'DISINTERMENT'=>'Disinterment',
            'VAULT'=>'Vault',
            'MARKER'=>'Marker Type',
            'LEADIN'=>'Lead-In',
            'AGE'=>'Age',
            'GENDER'=>'Gender',
            'MARITAL'=>'Marital Status',
            'SPOUSE'=>'Spouse',
            'POB'=>'Place of Birth',
            'POD'=>'Place of Death',
            'COD'=>'Cause of Death',
            'MILITARY'=>'Military Service',
            'LOLAST'=>'Lot Owner Last Name',
            'LOFIRST'=>'Lot Owner First Name',
            'LORELATION'=>'Relationship to Owner',
            'FATHERLAST'=>'Last Name of Father',
            'FATHERFIRST'=>'First Name of Father',
            'MOTHERLAST'=>'Last Name of Mother',
            'MOTHERFIRST'=>'First Name of Mother',
            'FUNERALHOME'=>'Funeral Home',
            'DIRECTOR'=>'Director',
            'CHARGES'=>'Interment Charges',
            'FAMILY'=>'Family',
            'MORTUARY'=>'Mortuary',
            'DEED'=>'Deed #',
            'CONTACT_NAME'=>'Contact Name',
            'CONTACT_STREET1'=>'Contact Street 1',
            'CONTACT_STREET2'=>'Contact Street 2',
            'CONTACT_CITY'=>'Contact City',
            'CONTACT_STATE'=>'Contact State',
            'CONTACT_ZIP'=>'Contact Zip',
            'CONTACT_YEAR'=>'Year Added'

        );
        return $columns;
    }

   function get_sortable_columns() {
        $sortable_columns = array(
            'UID' => array('UID', false), //true means it's already sorted
            'LAST' => array('LAST', false),
            'FIRST' => array('FIRST', false),
            'SECTION' => array('SECTION', false),
            'LOT' => array('LOT', false),
            'INTERMENT' => array('INTERMENT', false),
            'SPACE' => array('SPACE', false),
            'BIRTH' =>array('BIRTH',false),
            'DEATH' =>array('DEATH',false),
            'CEMETERY' =>array('CEMETERY',false)
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb; //This is used only if making any database queries
        $per_page = max(10, min(150, (int) $this->get_items_per_page('mccm_per_page', 50))); // set in Screen Options
        $columns = $this->get_columns();
        $hidden = $this->get_hidden();
        $columns['LAST'] = 'Last name'; $columns['FIRST'] = 'First name'; $columns['INTERMENT'] = 'Interment'; $columns['BIRTH'] = 'Birth date'; $columns['DEATH'] = 'Death date';
        $columns = array_diff_key($columns, array_flip($hidden), array_flip(array('CONTACT_NAME','CONTACT_STREET1','CONTACT_STREET2','CONTACT_CITY','CONTACT_STATE','CONTACT_ZIP','CONTACT_YEAR')));
        $hidden = array(); // don't send hidden columns at all
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $table_name = $wpdb->prefix . "mccm";
        // only the columns the list shows (the full record loads when its Interment # is clicked)
        $need = array_unique(array_merge(array('UID','LAST','FIRST','MIDDLE','INTERMENT'), array_diff(array_keys($columns), $hidden)));
        $need = array_intersect($need, $wpdb->get_col("DESC " . $table_name, 0));
        $query = "SELECT `" . implode('`,`', $need) . "` FROM " . $table_name;


        $term = '';
        if (isset($_REQUEST['s']) && is_scalar($_REQUEST['s'])) $term = trim(mb_substr(wp_unslash((string) $_REQUEST['s']), 0, 80));
        if ($term !== '') { // up to 3 comma-separated terms, all must match; "0" is a valid search
            $cols = $wpdb->get_col( "DESC " . $table_name, 0 );
            $wArr = array();
            foreach (array_slice(array_values(array_filter(array_map('trim', explode(",", $term)), 'strlen')), 0, 3) as $t) {
                foreach ($cols as $column_name ) {
                    $wArr[$t][] = $wpdb->prepare("`".$column_name."` LIKE %s", '%'.$wpdb->esc_like($t).'%');
                    if (addslashes($t) !== $t) $wArr[$t][] = $wpdb->prepare("`".$column_name."` LIKE %s", '%'.$wpdb->esc_like(addslashes($t)).'%'); // data saved with slashes
                }
            }
            if ($wArr) {
                $where = " WHERE (" . implode(") AND (", array_map(function ($h) { return implode(" OR ", $h); }, $wArr)) . ")";
                $query .= $where;
            }
        }


        // right after a save: show just that record (exact match on its record number), not a fuzzy search
        $only = (!empty($_GET['mccm_uid']) && !empty($_GET['mccm_msg']) && $term === '') ? (int) $_GET['mccm_uid'] : 0;
        if ($only) $query .= $wpdb->prepare(" WHERE UID = %d", $only);

        $orderby = (!empty($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array('UID','LAST','FIRST','SECTION','LOT','INTERMENT','SPACE','BIRTH','DEATH','CEMETERY'), true)) ? $_REQUEST['orderby'] : 'UID'; // default: newest first
        if ($term !== '' && empty($_REQUEST['orderby'])) $orderby="FIRST"; // an explicit column click wins
        $dir = strtolower((string) ($_REQUEST['order'] ?? ''));
        $order = ($dir === 'desc' || ($dir !== 'asc' && $orderby === 'UID')) ? 'desc' : 'asc';
        $query.= " ORDER BY " . $orderby . " " . $order;
        $current_page = $this->get_pagenum();
        $data = $wpdb->get_results($query." LIMIT ".($per_page*($current_page-1)).",".$per_page, ARRAY_A);
        //        $total_items = count($data);
        $total_items=$wpdb->get_var("SELECT count(*) FROM ".$table_name . (isset($where) ? $where : ''));
        if ($only) $total_items = count($data);
//        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        $this->items = $data;
        $this->set_pagination_args(array(
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page, //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //WE have to calculate the total number of pages
        ));
    }




}

