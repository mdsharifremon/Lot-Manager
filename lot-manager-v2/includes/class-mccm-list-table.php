<?php
/**
 * List table
 * The records list (WP_List_Table). Shared by the admin list and the front end.
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
        //Build row actions
        if (is_admin()) {
            $pg = esc_attr(sanitize_key($_REQUEST['page'] ?? 'mccm'));
            $actions = array(
                'wo' => sprintf('<a href="?page=%s&action=%s&id=%d">Work orders</a>', $pg, 'wos', (int) $item['UID']),
                'edit' => sprintf('<a href="?page=%s&action=%s&id=%d">Edit</a>', $pg, 'edit', (int) $item['UID']),
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d&_wpnonce=%s" onclick="%s">Delete</a>', $pg, 'delete', (int) $item['UID'], wp_create_nonce('mccm_delete_'.$item['UID']), mccm_delete_confirm($item)),
            );
        } else {
            $actions = array('wo' => sprintf('<a href="?page_id=%d&action=%s&id=%d">Work Orders</a>', (int) ($_REQUEST['page_id'] ?? 0), 'wos', (int) $item['UID']));
        }

        if (is_admin()) { // WordPress style: ID always shown, links appear on hover (CSS); View opens the details panel
            $actions = array_slice($actions, 0, 1, true) + array('view' => mccm_row_details($item)) + array_slice($actions, 1, null, true);
            return (int) $item['UID'] . $this->row_actions($actions);
        }

        //Return the title contents
        return sprintf('<span style="color:silver">(id:%1$s)</span>%2$s',
                        /* $2%s */ $item['UID'],
                        /* $3%s */ $this->row_actions($actions)
        );
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
        if (is_admin()) { $n = mccm_show_val($item['INTERMENT'] ?? ''); return $n !== '' ? esc_html($n) : '&ndash;&ndash;'; } // admin: plain number, the View button is in Actions
        global $wpdb;
        $ctext=$this->get_columns();
        $ctext['LASTCHANGE']="Last Changed On";
        $ctext['CHANGER']="Changed By";
        $extrainfo=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."mccm WHERE UID=".(int)$item['UID'],ARRAY_A);
        $uid=(int)$item['UID'];
        $out="<span class='mccm_interment' onmouseover='mccm_show(".$uid.");' onmouseout='mccm_hide(".$uid.");'>".($item['INTERMENT']!=''?esc_html(stripslashes((string)$item['INTERMENT'])):"--")."";
        $out.="</span><div class='mccmextra' id='mccmextra_".$uid."'><table><tr>";
        $rcount=0;
        foreach ($extrainfo as $cn=>$ei) {
                     if (strpos($cn,'CONTACT_')===0) continue; // contact details stay off the front end
                     $isage=false;
                     if ($cn=="AGE") {
                         if ($ei==0) {
                             $birth=$extrainfo['BIRTH'];
                             $death=$extrainfo['DEATH'];
                             if (mccm_s2h($birth)!='' && mccm_s2h($death)!='') {
                                $bd=explode("-",$birth);
                                $dd=explode("-",$death);
                                $age=$dd[0] - $bd[0];
                                if (($dd[1] < $bd[1]) || $dd[1]==$bd[1] && $dd[2]<$bd[2]) $age--;
                                $ei="<i><u>".(int)$age."</u></i>"; $isage=true;
                             }
                             else $ei='';
                         }
                     }
                     if (in_array($cn,array("BIRTH","DEATH","BURIAL","DISINTERMENT"))) {
                         $ei=mccm_s2h($ei);
                     }
            $out.="<td class='eilabel'>".esc_html($ctext[$cn]).":</td><td class='eiinfo'> ".($isage?$ei:esc_html(stripslashes((string) $ei))) . "</td>\n";
            if (in_array($rcount++,array(0,1,5,9,12,15,19,22,23,26,30,33,36))) $out.= "</tr><tr>\n";

        }
        $out.="</tr></table></div>";
        return $out;
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

        //if (!is_admin()) {$columns[]="UID";}
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
        $per_page = is_admin() ? max(10, min(150, (int) $this->get_items_per_page('mccm_per_page', 50))) : 150; // admin: set in Screen Options
        $columns = $this->get_columns();
        $hidden = $this->get_hidden();
                if (is_admin()) { $columns['LAST'] = 'Last name'; $columns['FIRST'] = 'First name'; $columns['INTERMENT'] = 'Interment'; $columns['BIRTH'] = 'Birth date'; $columns['DEATH'] = 'Death date'; }
        if (is_admin()) { $columns = array_diff_key($columns, array_flip($hidden), array_flip(array('CONTACT_NAME','CONTACT_STREET1','CONTACT_STREET2','CONTACT_CITY','CONTACT_STATE','CONTACT_ZIP','CONTACT_YEAR'))); $hidden = array(); } // admin: don't send hidden columns at all
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $table_name = $wpdb->prefix . "mccm";
        if (is_admin()) { // only the columns the list shows (the full record loads when its Interment # is clicked)
            $need = array_unique(array_merge(array('UID','LAST','FIRST','MIDDLE','INTERMENT'), array_diff(array_keys($columns), $hidden)));
            $need = array_intersect($need, $wpdb->get_col("DESC " . $table_name, 0));
            $query = "SELECT `" . implode('`,`', $need) . "` FROM " . $table_name;
        } else $query = "SELECT * FROM " . $table_name;


        $term = '';
        foreach (array('s', 'sfe') as $sk) if ($term === '' && isset($_REQUEST[$sk]) && is_scalar($_REQUEST[$sk])) $term = trim(mb_substr(wp_unslash((string) $_REQUEST[$sk]), 0, 80));
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

        $orderby = (!empty($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array('UID','LAST','FIRST','SECTION','LOT','INTERMENT','SPACE','BIRTH','DEATH','CEMETERY'), true)) ? $_REQUEST['orderby'] : (is_admin() ? 'UID' : 'LAST'); // admin default: newest first
        if ($term !== '' && empty($_REQUEST['orderby'])) $orderby="FIRST"; // an explicit column click wins
        $dir = strtolower((string) ($_REQUEST['order'] ?? ''));
        $order = ($dir === 'desc' || ($dir !== 'asc' && $orderby === 'UID' && is_admin())) ? 'desc' : 'asc';
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

    if (!is_admin()) {
        require_once( ABSPATH . 'wp-admin/includes/template.php' );
        require_once( ABSPATH . 'wp-admin/includes/screen.php' );
}
