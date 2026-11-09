<?php
/**
 * Plugin Name: Wyuka Cemetery Manager
 * Plugin URI: http://meridianne.com
 * Description: Lot Manager for Wyuka
 * Author: Meridian Consulting, Inc.
 * Version: 1.0
 * Author URI: http://www.meridianne.com
 */
/**
 * Copyright 2011 Author Name
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
define('MCCM_VERSION', '1.1');
define('MCCM_PLUGIN_URL', plugin_dir_url(__FILE__));
add_action('admin_menu', 'mccm_admin_actions');

#add_action('admin_print_scripts','getdesc_javascript');
#add_action('wp_ajax_getcmdesc','getcmdesc');



add_action("wp_ajax_generatereport","generateReport");
add_action("wp_ajax_nopriv_generatereport","generateReport");

add_action("wp_ajax_exportdata","exportData");

add_action( 'wp_enqueue_scripts', 'mccm_enqueue_style' );
add_action( 'admin_enqueue_scripts', 'mccm_enqueue_style' );

function mccm_enqueue_style() {
    wp_enqueue_script('cmscript', plugin_dir_url(__FILE__). '/mccm.js');
    wp_register_style('cmstylesheet', plugin_dir_url(__FILE__) . '/mccm.css');
    wp_enqueue_style('cmstylesheet');
}

function mccm_admin_actions() {
    add_menu_page("Wyuka Lot Manager", "Wyuka Lot Manager", "add_users", "mccm", "mccmadmin");
    add_submenu_page("mccm","Export", "Export", "manage_options", "mccm_export", "mccmexport");
    add_submenu_page("mccm","Import", "Import", "manage_options", "mccm_import", "mccmimport");
    add_submenu_page("mccm", "Reports", "Reports","manage_options", "mccmreports", "mccmreports");
    add_submenu_page("mccm", "Utilities", "Utilities","manage_options", "mccmutils", "mccmutils");
}  //end mccm_admin_actions


function exportData() {
    if ($_POST['utility']=='exportdb') {
      global $wpdb;
      $tn=$wpdb->prefix."mccm";
          $file=sys_get_temp_dir()."/mccm".rand(10000,999999);
      $sql="SELECT * INTO OUTFILE '".$file."' FROM ".$tn;
      $wpdb->query($sql);
      header("Content-type: application/octet-stream");
      header('Content-Disposition: attachment; filename=Wyuka_db_dump.sql');
      echo file_get_contents($file);
    }
    die();
} //end exportData


function generateReport() {

	if (
    (isset($_POST['reporttype']) && $_POST['reporttype']=='CSV')
    ||
    (isset($_POST['outputtype']) && $_POST['outputtype']=='x')
    ) $export = true;
	else $export = false;

   if (!$export) echo "<html><head></head><body>";
   else {
	  $file=sys_get_temp_dir()."/mccm".rand(10000,999999);
	   }

   global $wpdb;
   $table_name=$wpdb->prefix.'mccm';
   $table_wo=$wpdb->prefix.'mccm_workorders';

   if (!empty($_REQUEST['reptype']) && $_REQUEST['reptype']=='lot') {
        $start=(int)$_POST['start_year']."-".(int)$_POST['start_month']."-".(int)$_POST['start_day'];
        $end=(int)$_POST['end_year']."-".(int)$_POST['end_month']."-".(int)$_POST['end_day'];
        $fullsql="SELECT LAST,FIRST, MIDDLE, CEMETERY, SECTION, LOT, SPACE, BIRTH, DEATH";
        if ($export) $fullsql .=" INTO OUTFILE '".$file."'";

        $countsql="SELECT COUNT(*)";
        $sql.=" FROM ".$table_name." WHERE DEATH>='".trim($start)."' AND DEATH<='".trim($end)."'";

        if ($_POST['cemetery']!='all') {
         $sql.=" AND CEMETERY='".htmlentities($_POST['cemetery'])."'";
        }
       if ($_POST['section']!='all') {
         $sql.=" AND SECTION='".htmlentities($_POST['section'])."'";
       }

       if ($_POST['lot']!='all') {
         $sql.=" AND LOT='".htmlentities($_POST['lot'])."'";
       }

       if ($_POST['space']!='all') {
         $sql.=" AND SPACE='".htmlentities($_POST['space'])."'";
       }

       $sql.=" ORDER BY ";
       foreach ($_POST['orderby'] as $ob) {
          if ($ob!='') $sql.=$ob.",";
       }
       $sql=rtrim($sql,",");



       if (!$export) {
           echo "<h2 style='display:inline'>Lot Report</h2>";
            $entries=$wpdb->get_var($countsql.$sql);
           echo "<div style='float:right'>".$entries." entries found.</div><hr />";
           $results=$wpdb->get_results($fullsql.$sql,ARRAY_A);
       }





      if ($export) {
          header("Content-type: application/octet-stream");
          header('Content-Disposition: attachment; filename=Wyuka_db_dump.sql');

          echo $fullsql.$sql;
          $wpdb->query($fullsql.$sql);
          echo file_get_contents($file);
          die();
      }
      else {
            echo "<table style='margin:0 auto;border-collapse:collapse;'><tr><th>LAST</th><th>FIRST</th><th>MIDDLE</th><th>CEMETERY</th><th>SECTION</th><th>LOT</th><th>SPACE</th><th>DOB</th><th>DOD</th></tr>";
      foreach ($results as $r) {
         echo "<tr>";
          foreach ($r as $col=>$data) {
                if ($col=="DEATH" || $col=="BIRTH") {
                    $data=s2h($data);
                }
            echo "<td style='border:1px solid black;padding:5px 20px'> ".stripslashes($data)."</td>";
          }
           echo "</tr>";

      }
      echo "</table>";
  } //end it being lot report
   }

   if (!empty($_REQUEST['reptype']) && $_REQUEST['reptype']=='wo') {
      $sql="SELECT * FROM ".$table_wo." WHERE UID=".(int)$_REQUEST['woid'];
      $data=$wpdb->get_row($sql);

      $sql="SELECT FIRST, MIDDLE, LAST FROM ".$table_name." WHERE SECTION='".$data->SECTION."' AND LOT='".$data->LOT."' AND SPACE='".$data->SPACE."'";
      $pdata=$wpdb->get_row($sql);

      echo "<h2>Work Order ". $data->WO ;
      echo " - ".stripslashes($pdata->FIRST)." ".stripslashes($pdata->MIDDLE)." ".stripslashes($pdata->LAST)."</h2>";
      echo "<h3>".stripslashes($data->CEMETERY)."  SEC: ".stripslashes($data->SECTION).",  LOT: ".stripslashes($data->SECTION).",  SPACE: ".stripslashes($data->SPACE)."</h3>";
      echo "<style>table {width:95%; border:1px solid black;margin:0 auto;border-collapse:collapse} td,th{border:1px solid black;padding:10px 20px}</style>";
      echo "<table><tr><th>Date</th><th>Work Requested</th><th>Date Completed</th><th>Company</th></tr>";
        echo "<tr><td>".s2h($data->WODATE)."</td><td>".stripslashes($data->WORK)."</td><td>".s2h($data->COMPLETE)."</td><td>".stripslashes($data->COMPANY)."</td></tr>";
      echo "</table>";


} //end wor report

 if (!empty($_REQUEST['reptype']) && $_REQUEST['reptype']=='wos') {
          $sql="SELECT * FROM ".$table_name." WHERE UID=".(int)$_REQUEST['id'];
          $pdata=$wpdb->get_row($sql);


      $sql="SELECT * FROM ".$table_wo." WHERE SECTION='".$pdata->SECTION."' AND LOT='".$pdata->LOT."' AND SPACE='".$pdata->SPACE."'";
      $data=$wpdb->get_results($sql);

      echo "<h2>All Work Orders ". $data->WO ;
      echo " - ".stripslashes($pdata->FIRST)." ".stripslashes($pdata->MIDDLE)." ".stripslashes($pdata->LAST)."</h2>";
      echo "<h3>".$pdata->CEMETERY."  SEC: ".$pdata->SECTION.",  LOT: ".$pdata->SECTION.",  SPACE: ".$pdata->SPACE."</h3>";
      echo "<style>table {width:95%; border:1px solid black;margin:0 auto;border-collapse:collapse} td,th{border:1px solid black;padding:10px 20px}</style>";
      echo "<table><tr><th>Date</th><th>Work Requested</th><th>Date Completed</th><th>Company</th></tr>";
      foreach ($data as $d)
        echo "<tr><td>".s2h($d->WODATE)."</td><td>".$d->WORK."</td><td>".s2h($d->COMPLETE)."</td><td>".stripslashes($d->COMPANY)."</td></tr>";
      echo "</table>";
 }

 if (!empty($_REQUEST['reptype']) && $_REQUEST['reptype']=='allwo') {
      $sql="SELECT * FROM ".$table_wo;
      $start=(int)$_POST['start_year']."-".(int)$_POST['start_month']."-".(int)$_POST['start_day'];
        $end=(int)$_POST['end_year']."-".(int)$_POST['end_month']."-".(int)$_POST['end_day'];
        $sql.=" WHERE WODATE>=' ".$start."' AND WODATE<='".$end."'";
       $sql.=" ORDER BY ";
       foreach ($_POST['orderby'] as $ob) {
          if ($ob!='') $sql.=$ob.",";
       }
       $sql=rtrim($sql,",");
      $data=$wpdb->get_results($sql);

      echo "<h2>All Work Orders ". $data->WO ;

      echo "<style>table {width:95%; border:1px solid black;margin:0 auto;border-collapse:collapse} td,th{border:1px solid black;padding:10px 20px}</style>";
      echo "<table><tr><th>Date</th><th>Cemetery</th><th>Section</th><th>Lot</th><th>Space</th><th>Work Requested</th><th>Date Completed</th><th>Company</th></tr>";
      foreach ($data as $d)
        echo "<tr><td>".s2h($d->WODATE)."</td><td>".$d->CEMETERY."</td><td>".$d->SECTION."</td><td>".$d->LOT."</td><td>".$d->SPACE."</td><td>".$d->WORK."</td><td>".s2h($d->COMPLETE)."</td><td>".stripslashes($d->COMPANY)."</td></tr>";
      echo "</table>";
 }

 if (!empty($_REQUEST['reptype']) && $_REQUEST['reptype']=='dupes') {
      $sql="SELECT * FROM (SELECT COUNT(*) as CNT,INTERMENT,CEMETERY,SECTION,LOT,SPACE,LAST, FIRST FROM ".$table_name." GROUP BY INTERMENT,CEMETERY,SECTION,LOT,SPACE,LAST,FIRST) as temptble WHERE CNT>1 ORDER BY INTERMENT,CEMETERY,SECTION,LOT,SPACE";
      $data=$wpdb->get_results($sql);

      echo "<h2>Duplicates";

      echo "<style>table {width:95%; border:1px solid black;margin:0 auto;border-collapse:collapse} td,th{border:1px solid black;padding:10px 20px}</style>";
      echo "<table><tr><th>Count</th><th>Interment</th><th>Cemetery</th><th>Section</th><th>Lot</th><th>Space</th><th>Last</th><th>First</th><tr>";
      foreach ($data as $d)
        echo "<tr><td>".$d->CNT."</td><td>".$d->INTERMENT."</td><td>".$d->CEMETERY."</td><td>".$d->SECTION."</td><td>".$d->LOT."</td><td>".$d->SPACE."</td><td>".stripslashes($d->LAST)."</td><td>".stripslashes($d->FIRST)."</td></tr>";
      echo "</table>";
 }



 if (!empty($_REQUEST['reptype']) && $_REQUEST['reptype']=='contacts') {

      if (empty($_REQUEST['contactage'])) $age=2;
      else $age = $_REQUEST['contactage'];
      $ageYear = date("Y",strtotime("-" . $age ." year"));
      $sql="SELECT * FROM ".$table_name." WHERE CONTACT_NAME IS NOT NULL AND CONTACT_YEAR>" . $ageYear;

      $data=$wpdb->get_results($sql);


      if ($_REQUEST['outputtype']=='x') {
            $cData = array();
            $cData[]=array("Name","Street1","Street2","City","State","Zip","Year");
            foreach ($data as $d) {
              $cData[] = array(
                $d->CONTACT_NAME,
                $d->CONTACT_STREET1,
                $d->CONTACT_STREET2,
                $d->CONTACT_CITY,
                $d->CONTACT_STATE,
                $d->CONTACT_ZIP,
                $d->CONTACT_YEAR
              );
            }

            header("Content-type: application/octet-stream");
            header('Content-Disposition: attachment; filename=wyuka_contact_export.csv');

            foreach ($cData as $l) {
              echo implode(",",$l)."\n";
            }
            die();
      } else {

      echo "<style>table {width:95%; border:1px solid black;margin:0 auto;border-collapse:collapse} td,th{border:1px solid black;padding:10px 20px}</style>";
      echo "<table><tr><th>Contact Name</th><th>Street1</th><th>Street2</th><th>City</th><th>State</th><th>Zip</th><th>Year added</th></tr>";
      foreach ($data as $d) {
        echo "<tr><td>".$d->CONTACT_NAME."</td><td>".$d->CONTACT_STREET1."</td>";
        echo "<td>".$d->CONTACT_STREET2."</td>";
        echo "<td>".$d->CONTACT_CITY."</td>";
        echo "<td>".$d->CONTACT_STATE."</td>";
        echo "<td>".$d->CONTACT_ZIP."</td>";
        echo "<td>".$d->CONTACT_YEAR."</td>";
        echo "</tr>";
      }
      echo "</table>";
    }
 }

  echo "<script>window.print();</script>";
  //echo "<script>window.close();</script>";
  echo "</body></html>";
  die();
} //end genrerateReport();

function mccmutils() {
   global $wpdb;
   $table_name=$wpdb->prefix.'mccm';
   if (isset($_POST['utility'])) {
    $sql='';
    switch ($_POST['utility']) {
      case 'cleansection':$sql="UPDATE `".$table_name."` SET SECTION=replace(SECTION,',','')";
            echo "Cleaning Sections...<br />";
          break;
      case 'cleanlot':$sql="UPDATE `".$table_name."` SET LOT=replace(LOT,',','')";
            echo "Cleaning Lots...<br />";
      break;
      case 'cleanspace': $sql="UPDATE `".$table_name."` SET SPACE=replace(SPACE,',','')";
        echo "Cleaning Spaces...<br />";
          break;
    }
    if ($sql!='') $wpdb->query($sql);
  }
?>
<style>
    form {display:inline;}
</style>
<fieldset style="border:1px solid black;display:inline-block;padding:10px">
  <legend>
    <h2>Clean Up Data</h2>
  </legend>
  <h3>This will clean up comma's in your Section, Lot, or Space data.  This is typically an artifact of the import</h3>
  <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST' name="utilityform" onsubmit="return confirm('This will permanently alter your data - are you sure you want to continue?\n\nDo you have a backup?');">
    <input type="hidden" name="utility" value="cleansection" />
    <input type="submit" value="Clean Section Data" />
  </form>

  <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST' name="utilityform" onsubmit="return confirm('This will permanently alter your data - are you sure you want to continue?\n\nDo you have a backup?');">
  <input type="hidden" name="utility" value="cleanlot" />
  <input type="submit" value="Clean Lot Data" />
</form>

  <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST' name="utilityform" onsubmit="return confirm('This will permanently alter your data - are you sure you want to continue?\n\nDo you have a backup?');">
  <input type="hidden" name="utility" value="cleanspace" />
  <input type="submit" value="Clean Space Data" />
</form>
</fieldset>
<br /><br />
<fieldset style="border:1px solid black;display:inline-block;padding:10px"><legend><h2>Export Data</h2></legend>
      <h3>This performs a raw database dump of the lot data.</h3>
  <form action='<?php echo admin_url('admin-ajax.php?action=exportdata'); ?>' target="blank" method='POST' name="exportdata">
  <input type="hidden" name="utility" value="exportdb" />
   <!-- LOAD DATA INFILE '$backup_file' INTO TABLE $table_name -->
  <input type="submit" value="Export Data" />
</form>
</fieldset>
<?php



}

function mccmreports() {
  global $wpdb;
  $table_name=$wpdb->prefix.'mccm';
  $table_wo=$wpdb->prefix.'mccm_workorders';
?>
<h1>Wyuka Reports</h1><hr />
<fieldset style="border:1px solid black;padding:10px;display:inline-block">
 <legend><h2>Grave Report</h2></legend>
<form action='<?php echo admin_url('admin-ajax.php?action=generatereport'); ?>' target="blank" method='POST' name="reportform">
<input type="hidden" name="reptype" value="lot" />
<div><b>Include graves where deceased died between:</b>
<?php
$sql="SELECT min(DEATH),max(DEATH) from ".$table_name." WHERE DEATH<>'0000-00-00'";
$firstDeath=$wpdb->get_var($sql);
$lastDeath=$wpdb->get_var($sql,1);
echo dateDropdown("start",$firstDeath,$lastDeath,$firstDeath);
echo " <b>and</b> ";
echo dateDropdown("end",$firstDeath,$lastDeath,$lastDeath);

$sql="SELECT DISTINCT CEMETERY FROM `".$table_name."` ORDER BY CEMETERY";
$cemeteries = $wpdb->get_col($sql);
$cemSelect="<select name='cemetery' id='cemetery'>\n";
$cemSelect.="<option value='all'>ALL CEMETERIES</option>\n";
foreach ($cemeteries as $cemetery) {
   $cemSelect.="<option value='".$cemetery."'>".stripslashes($cemetery)."</option>\n";
}
$cemSelect.="</select>";

$sql="SELECT DISTINCT SECTION FROM `".$table_name."` WHERE SECTION <> '' ORDER BY SECTION";
$sections = $wpdb->get_col($sql);
$secSelect="<select name='section' id='section'>\n";
$secSelect.="<option value='all'>ALL SECTIONS</option>\n";
foreach ($sections as $section) {
   $secSelect.="<option value='".$section."'>".$section."</option>\n";
}
$secSelect.="</select>";

$sql="SELECT DISTINCT LOT FROM `".$table_name."` WHERE LOT <> '' ORDER BY LOT";
$lots = $wpdb->get_col($sql);
$lotSelect="<select name='lot' id='lot'>\n";
$lotSelect.="<option value='all'>ALL LOTS</option>\n";
foreach ($lots as $lot) {
  $lotSelect.="<option value='".$lot."'>".$lot."</option>\n";
}
$lotSelect.="</select>";

$sql="SELECT DISTINCT SPACE FROM `".$table_name."` WHERE SPACE <> '' ORDER BY SPACE";
$spaces = $wpdb->get_col($sql);
$spaceSelect="<select name='space' id='space'>\n";
$spaceSelect.="<option value='all'>ALL SPACES</option>\n";
foreach ($spaces as $space) {
  $spaceSelect.="<option value='".$space."'>".$space."</option>\n";
}
$spaceSelect.="</select>";

?>
<br />
<em><small>Note: Defaults to ALL dates in database.</small></em>
</div><br /><br />
<div>
  <table>
    <tr>
      <th></th><th>Section</th><th>Lot</th><th>Space</th></tr>
   <tr><td><b>Include graves from:</b></td><td><?php echo $cemSelect;?></td><td><?php echo $secSelect;?></td><td><?php echo $lotSelect;?></td><td><?php echo $spaceSelect;?></td></tr>
 </table>
</div>
<br /><br />
<div>

<b>Order Report By </b>
<select name="orderby[]" id="orderby1">
    <option value="LAST">Last Name</option>
    <option value="FIRST">FIRST Name</option>
    <option value="SECTION">Section</option>
    <option value="LOT">Lot</option>
    <option value="SPACE">Space</option>
    <option value="DEATH">Date of Death</option>
 </select>, <b>then</b>
 <select name="orderby[]" id="orderby2">
        <option value=''></option>
    <option value="LAST">Last Name</option>
    <option value="FIRST" selected="SELECTED">FIRST Name</option>
    <option value="SECTION">Section</option>
    <option value="LOT">Lot</option>
    <option value="SPACE">Space</option>
    <option value="DEATH">Date of Death</option>
 </select>, <b>then </b>
<select name="orderby[]" id="orderby3">
        <option value=''></option>
    <option value="LAST">Last Name</option>
    <option value="FIRST">FIRST Name</option>
    <option value="SECTION">Section</option>
    <option value="LOT">Lot</option>
    <option value="SPACE">Space</option>
    <option value="DEATH">Date of Death</option>
 </select>
</div>

<br />

<br />
<input type="submit" value="Generate Report" />
<!-- input type="submit" name="reporttype" value="CSV" / -->
</form>
</fieldset>

<br />
<br />
<br />

<fieldset style="border:1px solid black;padding:10px;display:inline-block">
 <legend><h2>Work Order Report</h2></legend>
<form action='<?php echo admin_url('admin-ajax.php?action=generatereport'); ?>' target="blank" method='POST' name="reportform">
<input type="hidden" name="reptype" value="allwo" />
<div><b>Include work orders between:</b>
<?php
$sql="SELECT min(WODATE),max(WODATE) from ".$table_wo." WHERE WODATE<>'0000-00-00'";
$first=$wpdb->get_var($sql);
$last=$wpdb->get_var($sql,1);
echo dateDropdown("start",$first,$last,$first,$table_wo,"WODATE");
echo " <b>and</b> ";
echo dateDropdown("end",$first,$last,$last,$table_wo,"WODATE");


/*$sql="SELECT DISTINCT SECTION FROM `".$table_name."` WHERE SECTION <> '' ORDER BY SECTION";
$sections = $wpdb->get_col($sql);
$secSelect="<select name='section' id='section'>\n";
$secSelect.="<option value='all'>ALL SECTIONS</option>\n";
foreach ($sections as $section) {
   $secSelect.="<option value='".$section."'>".$section."</option>\n";
}
$secSelect.="</select>";

$sql="SELECT DISTINCT LOT FROM `".$table_name."` WHERE LOT <> '' ORDER BY LOT";
$lots = $wpdb->get_col($sql);
$lotSelect="<select name='lot' id='lot'>\n";
$lotSelect.="<option value='all'>ALL LOTS</option>\n";
foreach ($lots as $lot) {
  $lotSelect.="<option value='".$lot."'>".$lot."</option>\n";
}
$lotSelect.="</select>";

$sql="SELECT DISTINCT SPACE FROM `".$table_name."` WHERE SPACE <> '' ORDER BY SPACE";
$spaces = $wpdb->get_col($sql);
$spaceSelect="<select name='space' id='space'>\n";
$spaceSelect.="<option value='all'>ALL SPACES</option>\n";
foreach ($spaces as $space) {
  $spaceSelect.="<option value='".$space."'>".$space."</option>\n";
}
$spaceSelect.="</select>";


<br />
<em><small>Note: Defaults to ALL dates in database.</small></em>
</div><br /><br />
<div>
  <table>
    <tr>
      <th></th><th>Section</th><th>Lot</th><th>Space</th></tr>
   <tr><td><b>Include graves from:</b></td><td><?php echo $secSelect;?></td><td><?php echo $lotSelect;?></td><td><?php echo $spaceSelect;?></td></tr>
 </table>
</div>
<br /><br />
*/ ?>
<div>
<br />
<b>Order Report By </b>
<select name="orderby[]" id="orderby1">
    <option value="WODATE">Work Order Date</option>
    <option value="WO">Work Order Number</option>
    <option value="COMPANY">Company</option>
 </select>, <b>then</b>
 <select name="orderby[]" id="orderby2">
        <option value=''></option>
    <option value="WODATE">Work Order Date</option>
    <option value="WO">Work Order Number</option>
    <option value="COMPANY">Company</option>
 </select>, <b>then </b>
<select name="orderby[]" id="orderby3">
        <option value=''></option>
    <option value="WODATE">Work Order Date</option>
    <option value="WO">Work Order Number</option>
    <option value="COMPANY">Company</option>
 </select>
</div>

<br />

<br />
<input type="submit" value="Generate Report" />
</form>
</fieldset>
<br /><br /><br />
<fieldset style="border:1px solid black;padding:10px;display:inline-block">
<legend><h2>List Duplicates Report</h2></legend>
<form action='<?php echo admin_url('admin-ajax.php?action=generatereport'); ?>' target="blank" method='POST' name="reportform">
Generate a report listing duplicates based on Interment #, Cemetery, Section, Lot, Space, Last name, and First Name.<br /><br />
<input type="hidden" name="reptype" value="dupes" />

<input type="submit" value="Generate Report" />
</fieldset>



<br /><br /><br />
<fieldset style="border:1px solid black;padding:10px;display:inline-block">
<legend><h2>Contacts Report</h2></legend>
<form action='<?php echo admin_url('admin-ajax.php?action=generatereport'); ?>' target="blank" method='POST' name="reportform">
Generate a report of contacts.  <br />
Retrieve contacts added in the last <select name='contactage' id='contactage'>
  <option value='1'>1 Year</option>
  <option value='2' selected="SELECTED">2 Years</option>
  <option value='5'>5 Years</option>
  <option value='99'>Any Time</option>
</select>
<br />
Output as: <select name='outputtype'><option value='x'>Excel</option><option value='p'>On Screen</option></select>
<input type="hidden" name="reptype" value="contacts" />
<br /><br />
<input type="submit" value="Generate Report" />
</fieldset>

<?php
}

function dateDropdown($n, $date, $maxdate, $selectedDate, $tn='', $field="DEATH") {
  $dArr=explode("-",$date);
  $minYear=$dArr[0];
  $minMonth=ltrim($dArr[1],"0");
  $minDay=ltrim($dArr[2],"0");
  $dArr=explode("-",$maxdate);
  $maxYear=$dArr[0];
  $maxMonth=ltrim($dArr[1],"0");
  $maxDay=ltrim($dArr[2],"0");
  $dArr=explode("-",$selectedDate);
  $selYear=$dArr[0];
  $selMonth=ltrim($dArr[1],"0");
  $selDay=ltrim($dArr[2],"0");


  global $wpdb;
  if ($tn=='') $tn = $wpdb->prefix.'mccm';
  $sql="SELECT DISTINCT LEFT(".$field.",4) FROM `".$tn."` WHERE ".$field." IS NOT NULL AND ".$field."<>'' AND ".$field."<>'0000-00-00' AND ".$field."<>'' ORDER BY LEFT(".$field.",4)";
  $years=$wpdb->get_col($sql);
  $out="<select name='".$n."_month' id='".$n."_month'>\n";
  $out.="<option value='0'></option>\n";
  for ($i=1;$i<13;$i++) {
//        $mName=date("F",mktime(0,0,0,$i,10));
        $selected=($selMonth==$i?" selected='SELECTED' ":"");
    $out.="<option value='".$i."'".$selected.">".$i." </option>\n";
    }
  $out.="</select>/\n ";
  $out.="<select name='".$n."_day' id='".$n."_day'>\n";
  $out.="<option value='0'></option>\n";
  for ($i=1;$i<32;$i++) {
        $selected=($selDay==$i?" selected='SELECTED' ":"");
    $out.="<option value='".$i."'".$selected.">".$i." </option>\n";
    }
  $out.="</select>/\n ";
  $out.="<select name='".$n."_year' id='".$n."_year'>\n";
  $out.="<option value='0'></option>\n";
  foreach ($years as $year) {
        $selected=($selYear==$year?" selected='SELECTED' ":"");
    $out.="<option value='".$year."'".$selected.">".$year." </option>\n";
    }
  $out.="</select>\n ";
  return $out;


}

function mccmexport() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mccm';
    $expDir = dirname(__FILE__)."/"."exports/";
    $temp=$expDir.'export_'.time().".csv";
    $head=$fhead=array();

    foreach ( $wpdb->get_col( "DESC " . $table_name, 0 ) as $column_name ) {
          if (in_array($column_name,array('DISINTERMENT','BURIAL','BIRTH','DEATH'))) $head[]= "IF(".$column_name." = '0000-00-00', '', ".$column_name." ) ".$column_name;
          else
          if (!in_array($column_name,array('UID','LASTCHANGE','CHANGER'))) {$head[]=$column_name;}
          if (!in_array($column_name,array('UID','LASTCHANGE','CHANGER'))) {$fhead[]=$column_name;}

    }


    $sql="SELECT ".trim(implode(',',$head))." FROM ".$table_name;

    $lines = $wpdb->get_results( $sql, ARRAY_A );

    file_put_contents($temp,implode(",",$fhead)."\n");

    if ( $lines ) {
        foreach ( $lines as $row ) {
            $line=array();
            foreach ($row as $r) {
                $line[]='"'.$r.'"';
            }


            file_put_contents($temp,implode(",",$line)."\n",FILE_APPEND);
        }
    }
    /*
    forseach ($lines as $l) {
        file_put_contents($temp,implode(",",$l)."\n",FILE_APPEND);
    }*/
    echo "<h1>You should be prompted to download your file...</h1>";
   echo "<iframe style='width:0;height:0' src='".plugin_dir_url(__FILE__)."exports/".basename($temp)."' ></iframe>\n";
    }

function mccmimport() {
    global $wpdb;
    global $current_user;
    $table_name = $wpdb->prefix . "mccm";
    $table_wo = $wpdb->prefix . "mccm_workorders";
    $wocolnames=array("Date","Cemetery","Sec","Lot","Space","Last Name","First Name","Work Requested", "Date Completed","Work Order #","Company");
    switch ($_REQUEST['action']) {
    case "importwo":
// ---------------------------------------------------------------------------------------------
        echo "IMPORTING WORK ORDERS<br />";
        $dr=isset($_REQUEST['dryrun']);
        if ($dr) echo "THIS WILL BE A DRY RUN - NO CHANGES MADE!<br />";
        $file=$_FILES['import']['tmp_name'];
        $colheaders=$_REQUEST['colrow'];
        echo "SKIPPING FIRST ".$colheaders." line(s) as header information<br />";
        $action=$_REQUEST['impact'];
        if (empty($file)) die("INVALID FILE");
        $handle = fopen($file, "r");
         while(!feof($handle)) {
           $lines[] = fgets($handle,4096);
         }
        fclose($handle);
        if ($action=='replace') {
            echo "DELETING OLD ENTRIES...<br />";
            if (!$dr) {
                $wpdb->query('DELETE FROM '.$table_wo);
                $wpdb->query('TRUNCATE '.$table_wo);//reset increment counter
            }
        }
        echo "ATTEMPTING TO IMPORT ".(count($lines) - $colheaders)." entries...";
        echo "<div style='height:350px;overflow:auto;border:1px solid #ccc;padding:5px;'>";
        $sqlArr=array();
        $wpdb->show_errors();
        $i=0;
        echo "STARTING... ";
        foreach ($lines as $line_num => $line) {
            if ($line_num >=$colheaders) {
//                echo "<br />LINE ".$line_num." ";
                $data=str_getcsv($line, ',', '"', '\\');
//                echo "VERIFYING COLUMN COUNT...";
                if (count($data)<count($wocolnames)) { //verify column count is correct
                    echo "<hr />INVALID COLUMN COUNT (".count($data)." vs ".count($wocolnames).") ON LINE ".($line_num+1).": SKIPPING LINE CAUSING PROBLEM:<br />";
                    print_r($line);
                    echo "<br />";
                    foreach ($data as $k=>$d) echo "(".$k.")".$d." , ";
                    echo "<hr />";
                } else {
                    $found=false;
                    if ($action=="update") {
                        $checksql="SELECT UID FROM ".$table_wo." WHERE WO='".$data[9]."' AND CEMETERY='".$data[1]."' AND SECTION='".$wpdb->escape(trim($data[2]))."' AND LOT='".$wpdb->escape(trim($data[3]))."' AND SPACE='".$wpdb->escape(trim($data[4]))."'";
                        $match=$wpdb->get_col($checksql);
                        if (count($match)>1) {
                            $found=true;
                           echo "<br />DUPLICATE MATCHES FOUND, NOT CHANGING!<br />";
                           echo "LINE: ". implode(", ", $data);
                           echo "<br />";
                           }
                        elseif (count($match)==1) {
                            $found=true;
                            echo "<br />UPDATING UID ".$match[0]." ($data[9])";
                            $uid=$match[0];
                            $sql="UPDATE ".$table_wo." SET ";
                            $UPDATEDATA=array();

                            foreach ($data as $k=>$d) {
                            if ($k<count($wocolnames)) {//only use the first 36 columns, with no entry for ID, CHANGER, or LAST CHANGED
                                $col=$columns[$k+1];
                                if (!in_array($k,array(5,6))) //don't get first/last name
                                if (in_array($k,array(0,8))) {
                                    if ($d!='') {
                                        $t=explode("/",$d);
                                        if (checkdate($t[0],$t[1],$t[2]))
                                           $UPDATEDATA[]=$col."='".$t[2]."-".($t[0]<10?"0":'').$t[0]."-".($t[1]<10?"0":'').$t[1]."'";
                                        else $UPDATEDATA[]=$col.'=NULL';
                                    }
                                    else $UPDATEDATA[]=$col.'=NULL';
                                }
                                elseif (is_numeric($d)) $UPDATEDATA[]=$col."=".$wpdb->escape(trim($d));
                                else $UPDATEDATA[]=$col.'="'.$wpdb->escape(trim($d)).'"';
                            }
                        }

                            $sql.=implode(",",$UPDATEDATA). " WHERE UID=".$uid;
                            if ( ! $dr ) $wpdb->query($sql);
                            $sql='';
                        }
                    } //end updating

                if (!$found) { //inserts if update not found, or always when doing an add to
                    echo "<br />NOT FOUND - ADDING:" . print_r($data,true)."<hr />";
                   $INSDATA=array();
                   $INSDATA[]='NULL';
                   foreach ($data as $k=>$d) {
                   if ($k<count($wocolnames)) {
                    if (!in_array($k,array(5,6))) //don't get first/last name
                     if (in_array($k,array(0,8))) {
                         if ($d!='') {
                             $t=explode("/",$d);
                             if (checkdate($t[0],$t[1],$t[2]))
                                 $INSDATA[]="'".$t[2]."-".($t[0]<10?"0":'').$t[0]."-".($t[1]<10?"0":'').$t[1]."'";
                             else $INSDATA[]='NULL';
                         }
                         else $INSDATA[]='NULL';
                     }
                    elseif (is_numeric($d))
                        $INSDATA[]=$wpdb->escape(trim($d));
                        else
                   $INSDATA[]='"'.$wpdb->escape(trim($d)).'"';
                   }
                  }
                  $INSDATA[]='"'.date ("Y-m-d H:i:s").'"';
                  $INSDATA[]='"'.$current_user->user_login.'"';
                  $sqlArr[]="(".implode(",",$INSDATA).")";
                 }
                } //no insert data here

                if ($line_num % 25===0) //attempt to streamline import process
                {if(!empty($sqlArr)) {
                    if ($line_num % 25===0) echo "...".$line_num;
                    if ($line_num % 800===0) echo "<br />";
                    $sql="INSERT INTO ".$table_wo." VALUES " . implode(",",$sqlArr);
                    if ($dr) echo $sql."<hr />";
                    if (!$dr) if (!$wpdb->query($sql)) { echo "<hr />";$wpdb->print_error();}
                    $sqlArr=array(); //reset sql arr
                    }
                   }
            }
        }
          if(!empty($sqlArr)) {
                    echo "...".($line_num-$colheaders);
                    $sql="INSERT INTO ".$table_wo." VALUES " . implode(",",$sqlArr);
                    //echo $sql;
                    if ($dr) echo $sql."<hr />";
                    if (!$dr) if(!$wpdb->query($sql)) {echo "<hr />";$wpdb->print_error();}
                   }
        echo "<br /><br />FINISHED.</div>.";
        $wpdb->hide_errors();
        break;

// ---------------------------------------------------------------------------------------------
    break;
    case "import":
// ---------------------------------------------------------------------------------------------
        $dr=isset($_REQUEST['dryrun']);
        if ($dr) echo "THIS WILL BE A DRY RUN - NO CHANGES MADE!<br />";
        $file=$_FILES['import']['tmp_name'];
        $colheaders=$_REQUEST['colrow'];
        echo "SKIPPING FIRST ".$colheaders." line(s) as header information<br />";
        $action=$_REQUEST['impact'];
        if (empty($file)) die("INVALID FILE");
        $handle = fopen($file, "r");
         while(!feof($handle)) {
           $lines[] = fgets($handle,4096);
         }
        fclose($handle);
        if ($action=='replace') {
            echo "DELETING OLD ENTRIES...<br />";
            if (!$dr) {
                $wpdb->query('DELETE FROM '.$table_name);
                $wpdb->query('TRUNCATE '.$table_name);//reset increment counter
            }
        }
        echo "ATTEMPTING TO IMPORT ".(count($lines) - $colheaders)." entries...";
        echo "<div style='height:350px;overflow:auto;border:1px solid #ccc;padding:5px;'>";
        $sqlArr=array();
        $wpdb->show_errors();
        $i=0;
        $colcount=37;
        echo "STARTING... ";
        foreach ($lines as $line_num => $line) {
            if ($line_num >=$colheaders) {
//                echo "<br />LINE ".$line_num." ";
                $data=str_getcsv($line, ',', '"', '\\');
//                echo "VERIFYING COLUMN COUNT...";
                if (count($data)<$colcount) { //verify column count is correct
                    echo "<hr />INVALID COLUMN COUNT (".count($data).") ON LINE ".($line_num+1).": SKIPPING LINE CAUSING PROBLEM:<br />";
                    print_r($line);
//                    foreach ($data as $k=>$d) echo "(".$k.")".$d." , ";
                    echo "<hr />";
                } else {
                    $found=false;
                    if ($action=="update") {
                        $checksql="SELECT UID FROM ".$table_name." WHERE INTERMENT='".$data[0]."' AND SECTION='".$data[6]."' AND LOT='".$data[7]."' and SPACE='".$data[8]."'";

                        $match=$wpdb->get_col($checksql);
                        if (count($match)>1) {
                            $found=true;
                           echo "<br />DUPLICATE MATCHES FOUND, NOT CHANGING!<br />";
                           echo "LINE: ". implode(", ", $data);
                           echo "<br /><br />";
                           }
                        elseif (count($match)==1) {
                            $found=true;
                            echo "<br />UPDATING UID ".$match[0]." ($data[1],$data[2])";
                            $mmcm = new MCCM_List_Table();
                            $columns=$mmcm->get_columns();
                            $columns=array_keys($columns);

                            $uid=$match[0];
                            $sql="UPDATE ".$table_name." SET ";
                            $UPDATEDATA=array();

                            foreach ($data as $k=>$d) {
                            if ($k<$colcount) {//only use the first 36 columns, with no entry for ID, CHANGER, or LAST CHANGED
                                $col=$columns[$k+1];
                                if (in_array($k,array(11,9,10))) {
                                    if ($d!='') {
                                        $t=explode("/",$d);
                                        if (checkdate($t[0],$t[1],$t[2]))
                                           $UPDATEDATA[]=$col."='".$t[2]."-".($t[0]<10?"0":'').$t[0]."-".($t[1]<10?"0":'').$t[1]."'";
                                        else $UPDATEDATA[]=$col.'=NULL';
                                    }
                                    else $UPDATEDATA[]=$col.'=NULL';
                                }
                                elseif (is_numeric($d)) $UPDATEDATA[]=$col."=".$wpdb->escape(trim($d));
                                else $UPDATEDATA[]=$col.'="'.$wpdb->escape(trim($d)).'"';
                            }
                        }

                            $sql.=implode(",",$UPDATEDATA). " WHERE UID=".$uid;
                            if ( ! $dr ) $wpdb->query($sql);
                            $sql='';
                        }
                    }
                    if (!$found) {
                  $INSDATA=array();
                  $INSDATA[]='NULL';
                  foreach ($data as $k=>$d) {
                   if ($k<$colcount) {//only use the first 36 columns, with no entry for ID, CHANGER, or LAST CHANGED
                     if (in_array($k,array(11,9,10))) {
                         if ($d!='') {
                             $t=explode("/",$d);
                             if (checkdate($t[0],$t[1],$t[2]))
                                 $INSDATA[]="'".$t[2]."-".($t[0]<10?"0":'').$t[0]."-".($t[1]<10?"0":'').$t[1]."'";
                             else $INSDATA[]='NULL';
                         }
                         else $INSDATA[]='NULL';
                     }
                    elseif (is_numeric($d))
                        $INSDATA[]=$wpdb->escape(trim($d));
                        else
                   $INSDATA[]='"'.$wpdb->escape(trim($d)).'"';
                   }
                  }
                  $INSDATA[]='"'.date ("Y-m-d H:i:s").'"';
                  $INSDATA[]='"'.$current_user->user_login.'"';
                  //if ($dr) echo "<hr />".count($INSDATA)." CELLS<hr />";
                  $sqlArr[]="(".implode(",",$INSDATA).")";
                 }
                } //no insert data here

                if ($line_num % 25===0) //attempt to streamline import process
                {if(!empty($sqlArr)) {
                    if ($line_num % 25===0) echo "...".$line_num;
                    if ($line_num % 800===0) echo "<br />";
                    $sql="INSERT INTO ".$table_name." VALUES " . implode(",",$sqlArr);
                    //if ($dr) echo "<hr />".$sql."<hr />";
                       if (!$dr) if (!$wpdb->query($sql)) { echo "<hr />";$wpdb->print_error();}
                       $sqlArr=array(); //reset sql arr
                    }
                   }
            }
        }

                   if(!empty($sqlArr)) {
                    echo "...".($line_num-$colheaders);
                    $sql="INSERT INTO ".$table_name." VALUES " . implode(",",$sqlArr);
                    //if ($dr) echo "<hr />".$sql."<hr />";
                    if (!$dr) if(!$wpdb->query($sql)) {echo "<hr />";$wpdb->print_error();}
                   }
        echo "<br /><br />FINISHED.</div>.";
        $wpdb->hide_errors();
        break;
// ---------------------------------------------------------------------------------------------
        default:
            ?>
            <h1>Import Data</h1>
            <fieldset class='mccmfs'><legend>Import Lot Data</legend>
             REMEMBER: This file needs to be in a comma delimited list (CSV) with the column order and count the same as those in an export: <br /><p><b>
    <?php
    $table=new MCCM_List_Table();
    $colnames=$table->get_columns();
    foreach ($colnames as $cn) {
       if ($cn!='ID') $tempArray[]= $cn;
    }
    echo implode(",",$tempArray);

?>
</b></p>

            <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST' enctype="multipart/form-data" name="entryform" onsubmit="return confirm('This will permanently alter your data - are you sure you want to continue?\n\nDo you have a backup?');">
                <input type='hidden' name='page' id='page' value='mccm_import' />
                <input type='hidden' name='action' value='import' />
                The first <input size='2' type='text' name='colrow' id='colrow' value='<?php echo (empty($_REQUEST['colrow'])?'3':$_REQUEST['colrow']); ?>' /> columns contain header information.
                <br />
                I want to <select name='impact'><option value='update'>update</option><option value='replace'>replace</option><option value='addto'>add to</option></select> the existing data.
                <br />
                <em>PLEASE NOTE: Updating can take a long time.  It is based on matching Interment #, Section, Lot and Space.  If ANY of these 4 have changed, place edit manually.</em>
                <br />
                File Name: <input type="file" name="import" value="" />
                <br />
                <input type="checkbox" name="dryrun" id="dryrun" value="YES" checked="CHECKED"> PERFORM A DRY RUN AND DO NOT ACTUALLY CHANGE DATA
                <br />
                <input type='submit' name='addentry' id='addentry' value='Run Import' />
                <?php $max_upload = (int)(ini_get('upload_max_filesize'));
                      $max_post = (int)(ini_get('post_max_size'));
                      $memory_limit = (int)(ini_get('memory_limit'));
                      $upload_mb = min($max_upload, $max_post, $memory_limit);
                      echo "Max file size: ". $upload_mb."MB";
?>
            </form>
            </fieldset>
            <br /><br /><br />
            <fieldset  class='mccmfs'><legend>Import Work Orders</legend>
            REMEMBER: This file needs to be in a comma delimited list (CSV) with the column order and count the same as those in an export: <br /><p><b>
            <?php echo implode(",",$wocolnames); ?>
            </b></p>
            <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST' enctype="multipart/form-data" name="entryform" onsubmit="return confirm('This will permanently alter your data - are you sure you want to continue?\n\nDo you have a backup?');">
                <input type='hidden' name='page' id='page' value='mccm_import' />
                <input type='hidden' name='action' value='importwo' />
                The first <input size='2' type='text' name='colrow' id='colrow' value='<?php echo (empty($_REQUEST['colrow'])?'2':$_REQUEST['colrow']); ?>' /> columns contain header information.
                <br />
                I want to <select name='impact'><option value='update'>update</option><option value='replace'>replace</option><option value='addto'>add to</option></select> the existing data.
                <br />
                <em>PLEASE NOTE: Updating can take a long time.  It is based on matching WO NUMBER, SECTION, LOT, and SPACE.  If these have changed, place edit manually.</em>
                <br />
                File Name: <input type="file" name="import" value="" /><br />
                <input type="checkbox" name="dryrun" id="dryrun" value="YES" checked="CHECKED"> PERFORM A DRY RUN AND DO NOT ACTUALLY CHANGE DATA<br />
                <input type='submit' name='addentry' id='addentry' value='Run Import' />
                <?php $max_upload = (int)(ini_get('upload_max_filesize'));
                      $max_post = (int)(ini_get('post_max_size'));
                      $memory_limit = (int)(ini_get('memory_limit'));
                      $upload_mb = min($max_upload, $max_post, $memory_limit);
                      echo "Max file size: ". $upload_mb."MB";
?>
            </form>








<?php
    } //end switch
}

function s2h ($d) {
            $d = (string) $d;
            if ($d=="0000-00-00" || $d==='') return "";
            $temp=explode("-",$d); //try to explode on - since no /
            if (count($temp)!==3) return $d;
            $year=$temp[0];
            $month=ltrim($temp[1],"0");
            $day=ltrim($temp[2],"0");
            return $month."/".$day."/".$year;
}

function h2s ($d) {
            $d = (string) $d;
            $temp=explode("/",$d);
            if (count($temp)!==3) $temp=explode("-",$d); //try to explode on - since no /
            if (count($temp)!==3) return $d;
            $year=$temp[2];
            $month=($temp[0]<10?"0".$temp[0]:$temp[0]);
            $day=($temp[1]<10?"0".$temp[1]:$temp[1]);
            return $year."-".$month."-".$day;
}


function mccmadmin() {
    global $wpdb;
    global $current_user;
    $table_name = $wpdb->prefix . "mccm";
    $table_wo = $wpdb->prefix."mccm_workorders";
    $table=new MCCM_List_Table();
    $colnames=$table->get_columns();
    $myAction = isset($_REQUEST['action'])?$_REQUEST['action']:'';
    switch ($myAction) {
        case "savewo":
           $wodata=array("CEMETERY"=>$_REQUEST['cemetery'],"SECTION"=>$_REQUEST['section'],"LOT"=>$_REQUEST['lot'],"SPACE"=>$_REQUEST['space'],"WODATE"=>h2s($_REQUEST['wodate']),"COMPLETE"=>h2s($_REQUEST['complete']),"WORK"=>$_REQUEST['work'],"WO"=>$_REQUEST['wo'],"COMPANY"=>$_REQUEST['company']);
           $wodata['LASTCHANGE']=date ("Y-m-d H:i:s");
           $wodata['CHANGER']=$current_user->user_login;
           if (!empty($_REQUEST['woid'])) {
               //attempt to update
               $wpdb->UPDATE(
                   $table_wo,
                   $wodata,
                   array('UID' => (int) $_REQUEST['woid']),
                   array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
               );
               echo "<center>Workorder Updated...</center><br />";
               }
             else {
                 //atempt to save
                 $wpdb->insert(
                    $table_wo,
                    $wodata,
                    array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                    );

                echo "<center>Workorder Saved...</center><br />";
                 }

        case "wos":
            if (!empty($_REQUEST['task']) && $_REQUEST['task']=='del' && !empty($_REQUEST['woid'])) {
              $sql="DELETE FROM ".$table_wo." WHERE UID=".(int)$_REQUEST['woid']." LIMIT 1";
              $wpdb->query($sql);
            }
           if (!empty($_REQUEST['woid'])) $wodata = $wpdb->get_row("SELECT * FROM " . $table_wo . " WHERE UID=" . (int) $_REQUEST['woid']);
           $sql="SELECT UID,LAST,FIRST,MIDDLE,CEMETERY, SECTION,LOT,SPACE FROM ".$table_name." WHERE UID='".(int)$_REQUEST['id']."'";
           $info=$wpdb->get_row($sql);
           $wosql="SELECT * FROM ".$table_wo." WHERE CEMETERY='".$info->CEMETERY."' AND SECTION='".$info->SECTION."' AND LOT='".$info->LOT."' and SPACE='".$info->SPACE."' ORDER BY WODATE DESC;";
           $workorders=$wpdb->get_results($wosql);
              ?>
                <h2>Work Orders</h2>
                <h3>&nbsp;&nbsp;&nbsp;<?php echo $info->LAST.", ".$info->FIRST." ".$info->MIDDLE;?><br />
                &nbsp;&nbsp;&nbsp;<?php echo $info->CEMETERY." - SEC:".$info->SECTION." - LOT:".$info->LOT." - SPACE:".$info->SPACE;?></h3>
                  <hr />
                  <button type="button" onclick="jQuery('#woeditform').slideToggle();">Add/Edit Work Order</button>
                  <button type="button" onclick="window.open('<?php echo admin_url('admin-ajax.php?action=generatereport&reptype=wos&id='.$info->UID);?>');" >Print All Work Orders</button>

                  <div id="woeditform" style="<?php if (empty($wodata)) { ?>display:none<?php } ?>">
                  <fieldset id="woeditformfs">
                  <legend><h3>Quick Add Work Order</h3></legend>
                  <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST' name="entryform" onsubmit=" return validateWO();" >
                    <input type='hidden' name='action' value='savewo' />
                    <input type='hidden' name='id' value='<?php echo (int)$_REQUEST['id'];?>' />
                    <input type='hidden' name='cemetery' value='<?php echo $info->CEMETERY;?>' />
                    <input type='hidden' name='section' value='<?php echo $info->SECTION;?>' />
                    <input type='hidden' name='lot' value='<?php echo $info->LOT;?>' />
                    <input type='hidden' name='space' value='<?php echo $info->SPACE;?>' />
                    <input type='hidden' name='woid' value='<?php if (!empty($wodata)) echo $wodata->UID;?>' />
                    <label for="wo">Work Order Number:</label><input type="text" name="wo" id="wo" value="<?php if (!empty($wodata)) echo $wodata->WO;?>" /><br />
                    <label for="wodate">Work Order Date:</label><input type="text" name="wodate" id="wodate" value="<?php if (!empty($wodata)) echo s2h($wodata->WODATE);?>" /><br />
                    <label for="work">Work Requested:</label><input type="text" name="work" id="work" size="100" value="<?php if (!empty($wodata)) echo $wodata->WORK;?>" /><br />
                    <label for="complete">Completion Date:</label><input type="text" name="complete" value="<?php if (!empty($wodata)) echo s2h($wodata->COMPLETE);?>" /><br />
                    <label for="company">Company:</label><input type="text" name="company" value="<?php if (!empty($wodata)) echo $wodata->COMPANY;?>" /><br />
                    <input type="submit" name="savewo" value="Save/Update Work Order" />
                  </form>
                  </fieldset>
                </div>
                  <h3>Work Orders</h3>
                  <table class='wotable'>
                    <tr><th>Action</th><th>WO Date</th><th>WO Number</th><th>Work Requested</th><th>Completion</th><th>Company</th></tr>
                  <?php
                      if (!empty($workorders)) foreach ($workorders as $wo) {
                          echo "<tr>";
                          echo "<td>";
                          echo "<a href='".admin_url('admin-ajax.php?action=generatereport&reptype=wo&woid='.$wo->UID)."</a>' target='blank'>print</a> | ";
                          echo "<a href='admin.php?page=mccm&action=wos&id=".(int)$_REQUEST['id']."&woid=".$wo->UID."'>edit</a> |";
                          echo " <a href='admin.php?page=mccm&action=wos&id=".(int)$_REQUEST['id']."&task=del&woid=".$wo->UID."' onclick=\"return confirm('Are you sure?')\">delete</a></td>";
                          echo "<td>".s2h($wo->WODATE)."</td>";
                          echo "<td>".$wo->WO."</td>";
                          echo "<td>".$wo->WORK."</td>";
                          echo "<td>".s2h($wo->COMPLETE)."</td>";
                          echo "<td>".$wo->COMPANY."</td>";
                          echo "</tr>\n";
                      }
           else echo "<tr><td colspan='6'><center>NO WORK ORDERS FOUND</center></td></tr>";
                          ?>

                  </table>
                  <?php if (!empty($_GET['action']) && $_GET['action']=='wos') { ?>
                    <input type="button" value="Back" onclick="window.history.back()">
                  <?php } else { ?>

                <button type='button' onclick='parent.location="<?php echo admin_url();?>admin.php?page=mccm"'>Back</button>

              <?php
             }
          break;
        case "edit":
            $data = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE UID=" . (int) $_REQUEST['id']);
        case "addentry":
            ?>
            <h1>Add/Edit Information</h1>
            <form action='<?php admin_url(); ?>admin.php?page=<?php echo $_REQUEST['page']; ?>' method='POST'>
                <input type='hidden' name='page' id='page' value='mccm' />
                <input type='hidden' name='action' value='saveentry' />
                <input type='hidden' name='id' value='<?php if (isset($_REQUEST['id'])) echo (int) $_REQUEST['id']; ?>' />
                <div class='mccmedit'>
                <?php
                    $cols = $wpdb->get_col( "DESC " . $table_name, 0 );
                    foreach ($cols as $k=>$col) {
                        if (!in_array($col,array("UID","CHANGER","LASTCHANGE"))) {
                            if ($k % 20===0 || $col=="CONTACT_NAME") {
                                if ($k!==0) echo "</div>\n";

                                if ($col=="CONTACT_NAME") {
                                  echo "<div class='mccmedit' style='clear:both;padding:10px;border:1px solid black;margin-bottom:20px'>\n<h3>CONTACT INFORMATION</h3>\n";
                                } else echo "<div class='mccmedit'>\n";

                            }
                            if(!empty($colnames[$col])) $cn=$colnames[$col]; else $cn=$col;
                          ?>
                <label for="<?php echo $col;?>"><?php echo $cn;?>:</label>
                <input type='text' name='<?php echo $col;?>' id="<?php echo $col;?>" value="<?php
                      if (in_array($col,array("BIRTH","DEATH","BURIAL","DISINTERMENT")) && !empty($data->$col)) {echo s2h($data->$col);}
                      elseif ($col=="AGE") {
                          if (isset($data) && $data->$col==0) $date->$col='';
                      }
                      else if (!empty($data)) echo stripslashes($data->$col);
                      else if ($col=='CONTACT_YEAR' && !isset($_REQUEST['id'])) {
                          echo date("Y");
                      }
                ?>" />
                <?php if (in_array($col,array("BIRTH","DEATH","BURIAL","DISINTERMENT"))) echo "<em>m/d/yyyy</em>" ?>
                <br />
             <?php } }?>
                </div>
                <div style="clear:both">
                <input type='submit' name='addentry' id='addentry' value='Save Entry' />
                </div>
            </form>
            <?php
            break;
        case "saveentry":
             foreach ($colnames as $cn=>$ct) {
                if ($cn!='UID') {
                   if (in_array($cn,array('AGE'))) {
					   $insArr[$cn]=(int) $_REQUEST[$cn];
					   $insType[]='%d';
					   }
                   else {
					   $insArr[$cn]=$_REQUEST[$cn];
					   $insType[]='%s';
				   }
                }
                $dateFields = array("BIRTH","DEATH","BURIAL","DISINTERMENT");
                foreach ($dateFields as $df) {
                    $insArr[$df]=h2s($_REQUEST[$df]);
                }


            }
            $insArr['LASTCHANGE']=date ("Y-m-d H:i:s");
			$insType[]='%s';
            $insArr['CHANGER']=$current_user->user_login;
			$insType[]='%s';
            if ($_REQUEST['id'] == 0) {
				//var_dump($table_name);
				//var_dump($insArr);
				//var_dump($insType);
				$wpdb->INSERT($table_name, $insArr,$insType);
			}
            else $wpdb->UPDATE($table_name, $insArr, array('UID' => (int) $_REQUEST['id']), $insType);
            mccm_showEntrylist();
       break;
    case "delete":
         if ($_REQUEST['id'] != '') {
                $wpdb->query(
                        $wpdb->prepare(
                                "
                            DELETE FROM $table_name
                             WHERE UID = %d
                            ", (int) $_REQUEST['id']
                        )
                );
            }
            mccm_showEntrylist();
     break;
        default:
            mccm_showEntrylist();
    }
}

function mccm_showEntrylist() {
    $entriesListTable = new MCCM_List_Table();
    $entriesListTable->prepare_items();
    ?>
    <div class="wrap">
        <div id="icon-users" class="icon32"></div>
        <h2>Lot Information</h2>
        <em>Mousing over Interment # will show extended information</em>

<?php if (is_admin()) { ?>
        <form action='<?php echo admin_url();?>admin.php' method='get'>
                <input type='hidden' name='page' id='page' value='mccm' />
                <input type='hidden' name='action' value='addentry' />
                <input type='submit' name='addentry' id='addentry' value='Add a Entry' />
                </form>
<?php } ?>


        <form id="position" method="post">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
            <?php if (is_admin()) { ?> <?php $entriesListTable->search_box('search','mccdsearch');?>
            <?php }
            else {
                ?>
                <input type="text" id="mccdsearch-search-input" name="sfe" value="">
                <input type="submit" name="" id="search_femmcm-submit" class="button" value="search">
                <?php
            } ?>
            <?php $entriesListTable->display(); ?>

        </form>
    </div>
    <?php
    if (is_admin())
    echo "<form action='" . admin_url() . "admin.php' method='get'>
                <input type='hidden' name='page' id='page' value='mccm' />
                <input type='hidden' name='action' value='addentry' />
                <input type='submit' name='addentry' id='addentry' value='Add a Entry' />
                </form>";
}

function mccm_install() {
    global $wpdb;

    // Create default options if needed
    add_option('mccm_option', 'the option');
    // Create a table if necessary (only if can't use options or postmeta tables)
    $table_name = $wpdb->prefix . "mccm";
    // Create mccm table if not existing
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        $sql = "
                CREATE TABLE IF NOT EXISTS `".$table_name."` (
                  `UID` int(11) NOT NULL AUTO_INCREMENT,
                  `INTERMENT` varchar(15) NOT NULL,
                  `LAST` varchar(255) NOT NULL,
                  `FIRST` varchar(255) NOT NULL,
                  `MIDDLE` varchar(255) NOT NULL,
                  `MAIDEN` varchar(255) NOT NULL,
                  `CEMETERY` varchar(25) DEFAULT 'Wyuka',
                  `SECTION` varchar(20) NOT NULL,
                  `LOT` varchar(20) NOT NULL,
                  `SPACE` varchar(20) NOT NULL,
                  `BIRTH` date DEFAULT NULL,
                  `DEATH` date DEFAULT NULL,
                  `BURIAL` date DEFAULT NULL,
                  `DISINTERMENT` date DEFAULT NULL,
                  `VAULT` varchar(40) NOT NULL,
                  `MARKER` varchar(255) NOT NULL,
                  `LEADIN` varchar(255) NOT NULL,
                  `AGE` int(11) NOT NULL,
                  `GENDER` varchar(1) NOT NULL,
                  `MARITAL` varchar(50) NOT NULL,
                  `SPOUSE` varchar(255) NOT NULL,
                  `POB` varchar(255) NOT NULL,
                  `POD` varchar(255) NOT NULL,
                  `COD` varchar(255) NOT NULL,
                  `MILITARY` varchar(255) NOT NULL,
                  `LOLAST` varchar(255) NOT NULL,
                  `LOFIRST` varchar(255) NOT NULL,
                  `LORELATION` varchar(255) NOT NULL,
                  `FATHERLAST` varchar(255) NOT NULL,
                  `FATHERFIRST` varchar(255) NOT NULL,
                  `MOTHERLAST` varchar(255) NOT NULL,
                  `MOTHERFIRST` varchar(255) NOT NULL,
                  `FUNERALHOME` varchar(255) NOT NULL,
                  `DIRECTOR` varchar(255) NOT NULL,
                  `CHARGES` varchar(255) NOT NULL,
                  `FAMILY` varchar(255) NOT NULL,
                  `MORTUARY` varchar(255) NOT NULL,
                  `DEED` varchar(100) NOT NULL,
                  `LASTCHANGE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `CHANGER` varchar(255) NOT NULL
                  PRIMARY KEY (`UID`),
                  KEY `UID` (`UID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $wpdb->query($sql);
        }


    $table_name = $wpdb->prefix . "mccm_workorders";
    // Create mccm table if not existing
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        $sql = "
                CREATE TABLE IF NOT EXISTS `".$table_name."` (
                  `UID` int(11) NOT NULL AUTO_INCREMENT,
                  `WODATE` date DEFAULT NULL,
                  `CEMETERY` varchar(25) DEFAULT 'Wyuka',
                  `SECTION` varchar(20) NOT NULL,
                  `LOT` varchar(20) NOT NULL,
                  `SPACE` varchar(20) NOT NULL,
                  `WORK` varchar(255) NOT NULL,
                  `COMPLETE` date DEFAULT NULL,
                  `WO` varchar(10) NOT NULL,
                  `COMPANY` varchar(100) NOT NULL,
                  `LASTCHANGE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `CHANGER` varchar(255) NOT NULL,
                  PRIMARY KEY (`UID`),
                  KEY `UID` (`UID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $wpdb->query($sql);
        }

    }


function mccmemp_shortcode($atts = array(), $content = null, $code = "") {
     global $wpdb;
     $table_wo = $wpdb->prefix."mccm_workorders";
     $table_name = $wpdb->prefix."mccm";


       switch ($_REQUEST['action']) {
           case "wos":
            if (!empty($_REQUEST['task']) && $_REQUEST['task']=='del' && !empty($_REQUEST['woid'])) {
              $sql="DELETE FROM ".$table_wo." WHERE UID=".(int)$_REQUEST['woid']." LIMIT 1";
              $wpdb->query($sql);
            }
           if (!empty($_REQUEST['woid'])) $wodata = $wpdb->get_row("SELECT * FROM " . $table_wo . " WHERE UID=" . (int) $_REQUEST['woid']);
           $sql="SELECT UID,LAST,FIRST,MIDDLE,CEMETERY, SECTION,LOT,SPACE FROM ".$table_name." WHERE UID='".(int)$_REQUEST['id']."'";
           $info=$wpdb->get_row($sql);
           $wosql="SELECT * FROM ".$table_wo." WHERE CEMETERY='".$info->CEMETERY."' AND SECTION='".$info->SECTION."' AND LOT='".$info->LOT."' and SPACE='".$info->SPACE."' ORDER BY WODATE DESC;";
           $workorders=$wpdb->get_results($wosql);
              ?>
                <h2>Work Orders</h2>
                <h3>&nbsp;&nbsp;&nbsp;<?php echo $info->LAST.", ".$info->FIRST." ".$info->MIDDLE;?><br />
                &nbsp;&nbsp;&nbsp;<?php echo $info->CEMETERY." - SEC:".$info->SECTION." - LOT:".$info->LOT." - SPACE:".$info->SPACE;?></h3>
                  <hr />
                  <button type="button" onclick="window.open('<?php echo admin_url('admin-ajax.php?action=generatereport&reptype=wos&id='.$info->UID);?>');" >Print All Work Orders</button>
                  <h3>Work Orders</h3>
                  <table class='wotable'>
                    <tr><th>Action</th><th>WO Date</th><th>WO Number</th><th>Work Requested</th><th>Completion</th><th>Company</th></tr>
                  <?php
                      if (!empty($workorders)) foreach ($workorders as $wo) {
                          echo "<tr>";
                          echo "<td>";
                          echo "<a href='".admin_url('admin-ajax.php?action=generatereport&reptype=wo&woid='.$wo->UID)."</a>' target='blank'>print</a>";
                          echo "<td>".s2h($wo->WODATE)."</td>";
                          echo "<td>".$wo->WO."</td>";
                          echo "<td>".$wo->WORK."</td>";
                          echo "<td>".s2h($wo->COMPLETE)."</td>";
                          echo "<td>".$wo->COMPANY."</td>";
                          echo "</tr>\n";
                      }
           else echo "<tr><td colspan='6'><center>NO WORK ORDERS FOUND</center></td></tr>";
                          ?>

                  </table>
                  <?php if (!empty($_GET['action']) && $_GET['action']=='wos') { ?>
                    <input type="button" value="Back" onclick="window.history.back()">
                  <?php } else { ?>

                <button type='button' onclick='parent.location="<?php echo admin_url();?>admin.php?page=mccm"'>Back</button>

              <?php
             }
          break;
            default:mccm_showEntrylist();
       }









}



/**
 * Replaces shortcode "[mccm]" with plugin stuff
 * Can be called anywhere in template with
 * <?php echo do_shortcode('[mccm]'); ?>
 * @param array $atts
 * @param string $content
 * @param string $code
 */
function mccm_shortcode($atts = array(), $content = null, $code = "") {
    // have to return, not echo

    $out='<div class="lotform"><form method="post" action="'.get_permalink().'">
         <span class="lotform-label">Enter Last Name or Last Name, First Name (ex: Smith or Smith, John)</span>
         <input type="text" name="SearchString" placeholder="Last Name"><input type="submit" name="" value="Search"></form></div>';

 if (!empty($_REQUEST['SearchString'])) {
      $collimit=4;
      global $wpdb;
      $term=htmlentities(stripslashes($_REQUEST['SearchString']));
      $term=stripslashes($_REQUEST['SearchString']);
      $out.="<!--MCCM START-->\n<table style='border-spacing:5px'><tr>\n";
      $table_name = $wpdb->prefix . "mccm";
      $cols=array('LAST','FIRST','MIDDLE','MAIDEN','SECTION','LOT','SPACE','BIRTH','DEATH','CEMETERY','POB','POD','FATHERFIRST','FATHERLAST','MOTHERFIRST','MOTHERLAST','GENDER','DEED','AGE');
      $searchcols=array('LAST','FIRST','MIDDLE','MAIDEN','SECTION','LOT','SPACE','BIRTH','DEATH','CEMETERY');
      $query = "SELECT DISTINCT ".implode(',',$cols)." FROM " . $table_name;
      $tArr=explode(",",$term);
         foreach ($tArr as $t) {
           foreach ($searchcols as $column_name ) {
               $wArr[$t][] =$column_name." LIKE \"%".trim($t)."%\"";
               $wArr[$t][] =$column_name." LIKE \"%".addslashes(addslashes(addslashes(trim($t))))."%\"";
              }
          }
          $where=" WHERE ";
          $fl=true;
          foreach ($wArr as $h) {
            if (!$fl) $where.=" AND ";
              $where .= "(" . implode(" OR ",$h).")";
              $fl=false;
            }
          $query.=$where;
          $query.= " ORDER BY LAST,FIRST ASC";
          $perPage = 64;
          if (isset($_REQUEST['page'])) $offset = (int)$_REQUEST['page'] * $perPage;
          else $offset = 0;

          $query.=" LIMIT " . $offset.','.($perPage+1);
          //if ($_SERVER['REMOTE_ADDR']=='98.19.49.65') {$out.=$term;$out.=$query;}
          $data = $wpdb->get_results($query, ARRAY_A);
          if (empty($data)) {
             $out.=makeCell('NO RESULTS FOUND');
             $out.="<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></table>";
             if ($offset != 0) { //not on the first page, so we can go back!
                 $out.="<div class='cemeteryBack'><form method='post'><input type='hidden'name='SearchString' value='".$_POST['SearchString']."'><input type='hidden' name='page' value='".( (int) $_REQUEST['page'] - 1)."' /><button>Previous</button></form></div>";
             }
             return $out;
          }
          else {
            $colcount=1;
            $hasMore=false;
            foreach ($data as $index=>$r) {
              if ($perPage <= $index) {
                $hasMore=true;
                continue;
              }

              $lotCem = ($r['CEMETERY']!=''?$r['CEMETERY']:' ');
              $lotCem2 = strtolower($lotCem);
              $lotSec = "Lot: Sec-".($r['SECTION']!=''?$r['SECTION']:' - ');

              
               if($lotCem2 == 'wyuka') { // WYUKA

                switch ($lotSec) {

                  case 'Lot: Sec-1 A':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-1A-Graves-1-1303.pdf';
                    break;

                  case 'Lot: Sec-1 B':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-1B.pdf';
                    break;

                  case 'Lot: Sec-2 A':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-2A.pdf';
                    break;

                  case 'Lot: Sec-1':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2011/08/wyuka-map-section-1.pdf';
                    break;

                  case 'Lot: Sec-2':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-2.pdf';
                    break;

                  case 'Lot: Sec-3':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-3.pdf';
                    break;

                  case 'Lot: Sec-3 A':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-3.pdf';
                    break;

                  case 'Lot: Sec-4':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-4.pdf';
                    break;

                  case 'Lot: Sec-4 A':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-4A.pdf';
                    break;

                  case 'Lot: Sec-4 FG':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-2.pdf';
                    break;

                  case 'Lot: Sec-5':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-5.pdf';
                    break;

                  case 'Lot: Sec-6':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-6.pdf';
                    break;

                  case 'Lot: Sec-7':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-7.pdf';
                    break;

                  case 'Lot: Sec-8':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-8.pdf';
                    break;

                  case 'Lot: Sec-9':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-9.pdf';
                    break;

                  case 'Lot: Sec-10':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-10.pdf';
                    break;

                  case 'Lot: Sec-11':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-11.pdf';
                    break;

                  case 'Lot: Sec-11 HOF':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged.pdf';
                    break;

                  case 'Lot: Sec-12':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-12.pdf';
                    break;

                  case 'Lot: Sec-13':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-13.pdf';
                    break;

                  case 'Lot: Sec-14':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-14.pdf';
                    break;

                  case 'Lot: Sec-15':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Space-15.pdf';
                    break;

                  case 'Lot: Sec-15 GAR':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/G.A.R.pdf';
                    break;

                  case 'Lot: Sec-16':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-16.pdf';
                    break;

                  case 'Lot: Sec-17':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-17.pdf';
                    break;

                  case 'Lot: Sec-18':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-18.pdf';
                    break;

                  case 'Lot: Sec-19':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2013/09/wyuka.pdf';
                    break;

                  case 'Lot: Sec-20':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-20.pdf';
                    break;

                  case 'Lot: Sec-21':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-21.pdf';
                    break;

                  case 'Lot: Sec-22':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-22.pdf';
                    break;

                  case 'Lot: Sec-23':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-23.pdf';
                    break;

                  case 'Lot: Sec-24':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-24.pdf';
                    break;

                  case 'Lot: Sec-24 SC':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-3.pdf';
                    break;

                  case 'Lot: Sec-24 WC':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-3.pdf';
                    break;

                  case 'Lot: Sec-25':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/07/Section-Map-25.pdf';
                    break;

                  case 'Lot: Sec-26':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-26.pdf';
                    break;

                  case 'Lot: Sec-27':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-27.pdf';
                    break;

                  case 'Lot: Sec-28':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-28.pdf';
                    break;

                  case 'Lot: Sec-28 Est':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-5.pdf';
                    break;

                  case 'Lot: Sec-28 BL':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-4.pdf';
                    break;

                  case 'Lot: Sec-29':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-29.pdf';
                    break;

                  case 'Lot: Sec-30':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2018/05/Copier-scan_20180511_152553.pdf';
                    break;

                  case 'Lot: Sec-31':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-31.pdf';
                    break;

                  case 'Lot: Sec-31 COL':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-6.pdf';
                    break;

                  case 'Lot: Sec-32':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-32.pdf';
                    break;

                  case 'Lot: Sec-33':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-33.pdf';
                    break;

                  case 'Lot: Sec-34':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2011/08/wyuka-map-section-34.pdf';
                    break;

                  case 'Lot: Sec-35':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-35.pdf';
                    break;

                  case 'Lot: Sec-36':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-36.pdf';
                    break;

                  case 'Lot: Sec-37':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-37.pdf';
                    break;

                  case 'Lot: Sec-38':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-38.pdf';
                    break;

                  case 'Lot: Sec-39':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-39.pdf';
                    break;

                  case 'Lot: Sec-40':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-40.pdf';
                    break;

                  case 'Lot: Sec-41':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-41-and-41FF.pdf';
                    break;

                  case 'Lot: Sec-41 FF':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-41-and-41FF.pdf';
                    break;

                  case 'Lot: Sec-42':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-42.pdf';
                    break;

                  case 'Lot: Sec-43':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-43.pdf';
                    break;

                  case 'Lot: Sec-43 SV':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-43SV.pdf';
                    break;

                  case 'Lot: Sec-44':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-44.pdf';
                    break;

                  case 'Lot: Sec-47':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-471.pdf';
                    break;

                  case 'Lot: Sec-47 LC':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2020/04/Copier-scan_20200411_115542-dragged-7.pdf';
                    break;
                  
                  default:
                   $lotLink = '#';
                    break;


                }

               } elseif ($lotCem2 == 'fairview') { // FAIRVIEW

                switch ($lotSec) {

                  case 'Lot: Sec-1':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-11.pdf';
                    break;

                  case 'Lot: Sec-2':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-25.pdf';
                    break;

                  case 'Lot: Sec-3':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-34.pdf';
                    break;

                  case 'Lot: Sec-4':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-4.pdf';
                    break;

                  case 'Lot: Sec-5':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-5.pdf';
                    break;

                  case 'Lot: Sec-6':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-6.pdf';
                    break;

                  case 'Lot: Sec-7':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-7.pdf';
                    break;

                  case 'Lot: Sec-8':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-8.pdf';
                    break;

                  case 'Lot: Sec-9':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-9.pdf';
                    break;

                  case 'Lot: Sec-10':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-10.pdf';
                    break;

                  case 'Lot: Sec-11':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-111.pdf';
                    break;

                  case 'Lot: Sec-12':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-12.pdf';
                    break;

                  case 'Lot: Sec-13':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-13.pdf';
                    break;

                  case 'Lot: Sec-14':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-14.pdf';
                    break;

                  case 'Lot: Sec-15':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-15.pdf';
                    break;

                  case 'Lot: Sec-16':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-161.pdf';
                    break;

                  case 'Lot: Sec-17':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-17-and-18.pdf';
                    break;

                  case 'Lot: Sec-18':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-17-and-18.pdf';
                    break;

                  case 'Lot: Sec-19':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-191.pdf';
                    break;

                  case 'Lot: Sec-20':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-201.pdf';
                    break;

                  case 'Lot: Sec-21':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-211.pdf';
                    break;

                  case 'Lot: Sec-22':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-221.pdf';
                    break;

                  case 'Lot: Sec-23':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-221.pdf';
                    break;

                  case 'Lot: Sec-24':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-241.pdf';
                    break;

                  case 'Lot: Sec-25':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-251.pdf';
                    break;

                  case 'Lot: Sec-26':
                    $lotLink = 'https://wyuka.com/wp-content/uploads/2010/08/Section-261.pdf';
                    break;
                  
                  default:
                   $lotLink = '#';
                    break;
                }

               } else {

                  $lotLink = '#';

               }

                // txt
               
                $lotInfo = " <a href=\"".$lotLink."\" class=\"lot-link\">".$lotSec." Lot-".($r['LOT']!=''?$r['LOT']:' - ');
                

               $info='<b>'.stripslashes((string) ($r['LAST'] ?? '')).', '.stripslashes((string) ($r['FIRST'] ?? '')).' '.stripslashes((string) ($r['MIDDLE'] ?? ''));
               if (!empty($r['MAIDEN'])) $info.=' ('.stripslashes($r['MAIDEN']).')';

               


               $info.='</b><br />'."\n";
               $info.=$lotInfo;
               $info.=" Sp-".($r['SPACE']!=''?$r['SPACE']:' - ')."</a>";
               $info.="<br />";
               $info.="Birth: ".s2h($r['BIRTH'])."<br>";
               $info.="Death: ".s2h($r['DEATH']);
               $info.="<br />\n";
               $info.="Cemetery: ".$lotCem;
               $out.=makeCell($info);
               if ($colcount % $collimit === 0) {$out.="</tr><tr>";$colcount=0;}
               $colcount++;
           }
        }

      for ($i=$colcount;$i>=0;$i--) {
         $out.="<td class=\"no-td-data\">&nbsp;</td>";
      }
      $out.="\n</tr></table>\n<div class='cemeteryNav'>";
 }

 if ( ! empty( $offset ) ) { //not on the first page, so we can go back!
     $out.="<div class='cemeteryBack'><form method='post'><input type='hidden'name='SearchString' value='".( $_POST['SearchString'] ?? '' )."'><input type='hidden' name='page' value='".( (int) ( $_REQUEST['page'] ?? 0 ) - 1)."' /><button>Previous</button></form></div>";
 }

   if ( ! empty( $hasMore ) ) {
   $out.="<div class='cemeteryNext'><form method='post'><input type='hidden'name='SearchString' value='".$_POST['SearchString']."'><input type='hidden' name='page' value='".( (int) $_REQUEST['page'] + 1)."' /><button>Next</button></form></div>";
   }

   $out.="</div>";

    return $out;
}


function makeCell($t) {
   return '<td class="cemeteryLot"><div>'.$t.'</div></td>';
}


/**
 * Drops the table and deletes the options
 * when user clicks "delete plugin"
 */
function mccm_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . "mccm";

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        $qry = "
            DROP TABLE `{$table_name}`
        ";
        $wpdb->query($qry);
    }
}

register_activation_hook(__FILE__, 'mccm_install');
add_shortcode('mccm', 'mccm_shortcode');
add_shortcode('mccm_emp', 'mccmemp_shortcode');
register_uninstall_hook(__FILE__, 'mccm_uninstall');

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
                return stripslashes( (string) ( $item[ $column_name ] ?? '' ) );
//                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function column_UID($item) {

        //Build row actions
        $actions = array(
            'wo' => sprintf('<a href="?page=%s&action=%s&id=%s">Work Orders</a>', $_REQUEST['page'], 'wos', $item['UID']),
            'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">Edit</a>', $_REQUEST['page'], 'edit', $item['UID']),
            'delete' => sprintf('<a href="?page=%s&action=%s&id=%s" onclick="return confirm(\'Are you sure??\');">Delete</a>', $_REQUEST['page'], 'delete', $item['UID']),
        );



        if (!is_admin()) {
            $actions=array('wo' => sprintf('<a href="?page_id=%s&action=%s&id=%s">Work Orders</a>', $_REQUEST['page_id'], 'wos', $item['UID']));
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
               return s2h($item['BIRTH']);
    }

    function column_DISINTERMENT($item) {
               return s2h($item['DISINTERMENT']);
    }

    function column_DEATH($item) {
               return s2h($item['DEATH']);
    }

    function column_CONTACT_STREET1($item) {
      if ($item['CONTACT_NAME']!='') {
        $fa =  $item['CONTACT_STREET1'] . '<br /> ' . (!empty($item['CONTACT_STREET2'])?$item['CONTACT_STREET2']."<br /> ":'') . $item['CONTACT_CITY']. ' ' . $item['CONTACT_STATE']. ', ' . $item['CONTACT_ZIP'];
        return $fa;
      }else return "";

    }

    function column_BURIAL($item) {
               return s2h($item['BURIAL']);
    }


    function column_INTERMENT($item) {
        global $wpdb;
        $ctext=$this->get_columns();
        $ctext['LASTCHANGE']="Last Changed On";
        $ctext['CHANGER']="Changed By";
        $extrainfo=$wpdb->get_row("SELECT * FROM ".$wpdb->prefix."mccm WHERE UID=".(int)$item['UID'],ARRAY_A);
        $out="<span class='mccm_interment' onmouseover='mccm_show(".$item['UID'].");' onmouseout='mccm_hide(".$item['UID'].");'>".($item['INTERMENT']!=''?$item['INTERMENT']:"--")."";
        $out.="</span><div class='mccmextra' id='mccmextra_".$item['UID']."'><table><tr>";
        $rcount=0;
        foreach ($extrainfo as $cn=>$ei) {
                     if ($cn=="AGE") {
                         if ($ei==0) {
                             $birth=$extrainfo['BIRTH'];
                             $death=$extrainfo['DEATH'];
                             if (s2h($birth)!='' && s2h($death)!='') {
                                $bd=explode("-",$birth);
                                $dd=explode("-",$death);
                                $age=$dd[0] - $bd[0];
                                if (($dd[1] < $bd[1]) || $dd[1]==$bd[1] && $dd[2]<$bd[2]) $age--;
                                $ei="<i><u>".$age."</u></i>";
                             }
                             else $ei='';
                         }
                     }
                     if (in_array($cn,array("BIRTH","DEATH","BURIAL","DISINTERMENT"))) {
                         $ei=s2h($ei);
                     }
            $out.="<td class='eilabel'>".$ctext[$cn].":</td><td class='eiinfo'> ".stripslashes( (string) $ei ) . "</td>\n";
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
        $per_page = 150;
        $columns = $this->get_columns();
        $hidden = $this->get_hidden();
        $columns['CONTACT_NAME']="Contact";
        $columns['CONTACT_STREET1']="Address";
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $table_name = $wpdb->prefix . "mccm";
        $query = "SELECT * FROM " . $table_name;


        if (!empty($_REQUEST['s']) || !empty($_REQUEST['sfe'])) {
            if (!empty($_REQUEST['s'])) $term=htmlentities($_REQUEST['s']);
            else $term=htmlentities($_REQUEST['sfe']);
            $cols = $wpdb->get_col( "DESC " . $table_name, 0 );
            $tArr=explode(",",$term);
            foreach ($tArr as $t) {
                foreach ($cols as $column_name ) {
                    $wArr[$t][] =$column_name." LIKE '%".trim($t)."%'";
                    $wArr[$t][] =$column_name." LIKE '%".addslashes(addslashes(trim($t)))."%'";
                }
            }
            $where=" WHERE ";
            $fl=true;
            foreach ($wArr as $h) {
                if (!$fl) $where.=" AND ";
                    $where .= "(" . implode(" OR ",$h).")";
                $fl=false;
            }
            $query.=$where;
        }


        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'LAST'; //If no sort, default to title
        if (!empty($term)) $orderby="FIRST";
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
        $query.= " ORDER BY " . $orderby . " " . $order;
        $current_page = $this->get_pagenum();
        $data = $wpdb->get_results($query." LIMIT ".($per_page*($current_page-1)).",".$per_page, ARRAY_A);
        //        $total_items = count($data);
        $total_items=$wpdb->get_var("SELECT count(*) FROM ".$table_name);
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


if (!function_exists('str_getcsv')) {
    function str_getcsv($string) {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $string);
        rewind($fh);
        $row = fgetcsv($fh);
        fclose($fh);
       return $row;
    }

}



/*

ALTER TABLE `wp_mccm`
ADD `CONTACT_NAME` varchar(255) NULL,
ADD `CONTACT_STREET1` varchar(255) NULL AFTER `CONTACT_NAME`,
ADD `CONTACT_STREET2` varchar(255) NULL AFTER `CONTACT_STREET1`,
ADD `CONTACT_CITY` varchar(255) NULL AFTER `CONTACT_STREET2`,
ADD `CONTACT_STATE` varchar(255) NULL AFTER `CONTACT_CITY`,
ADD `CONTACT_ZIP` varchar(255) NULL AFTER `CONTACT_STATE`;
ADD `CONTACT_YEAR` year NULL AFTER `CONTACT_ZIP`;

*/
