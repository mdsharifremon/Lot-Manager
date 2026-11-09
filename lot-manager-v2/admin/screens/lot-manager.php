<?php
/**
 * Screen: Lot Manager
 * Main screen router (list, add/edit, delete, work orders) and the list page markup.
 */
if (!defined('ABSPATH')) exit;

function mccm_screen_lots() {
    global $wpdb;
    global $current_user;
    $table_name = $wpdb->prefix . "mccm";
    $table=new MCCM_List_Table();
    $colnames=$table->get_columns();
    $myAction = isset($_REQUEST['action'])?$_REQUEST['action']:'';
    do { $mccm_redo = false;
    switch ($myAction) {
        case "wos":
            mccm_wo_screen();
            break;
        case "edit":
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE UID=%d", (int) ($_REQUEST['id'] ?? 0)));
        case "addentry":
            // the form is drawn by admin/form/render.php; $mccm_errors is set when a save was refused
            mccm_render_entry_form(isset($data) ? $data : null, isset($mccm_errors) ? $mccm_errors : array());
            break;
        case "saveentry": check_admin_referer('mccm_saveentry');
             // normally mccm_early_save() (admin_init) already saved and redirected; we only get here when it was refused
             $mccm_res = isset($GLOBALS['mccm_save_result']) ? $GLOBALS['mccm_save_result'] : mccm_process_save();
             if ($mccm_res['ok']) { mccm_showEntrylist(); break; }
             $mccm_errors = $mccm_res['errors'];
             $data = (int) ($_REQUEST['id'] ?? 0) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE UID=%d", (int) $_REQUEST['id'])) : null;
             $myAction = $data ? 'edit' : 'addentry';
             $mccm_redo = true;
       break;
    case "delete":
         if (!empty($_REQUEST['id']) && wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'mccm_delete_'.(int) $_REQUEST['id'])) {
                $gone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE UID = %d", (int) $_REQUEST['id']), ARRAY_A);
                if ($gone) error_log('[wyuka-mccm] record deleted by ' . wp_get_current_user()->user_login . ': ' . wp_json_encode($gone)); // the only copy if it was a mistake
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
    } while ($mccm_redo);
}

function mccm_showEntrylist() {
    $entriesListTable = new MCCM_List_Table();
    $entriesListTable->prepare_items();
    $total = (int) $entriesListTable->get_pagination_arg('total_items');
    ?>
    <div class="wrap<?php echo is_admin() ? ' mccm-list-ui' : ''; ?>">
        <?php mccm_flash(); ?>
<?php if (is_admin()) { ?>
        <nav class="mccm-crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url(admin_url('admin.php?page=mccm')); ?>">Lot Manager</a> <span>/</span> All records</nav>
        <h1 class="mccm-title">Lot Information</h1>
        <p class="mccm-count"><?php echo number_format_i18n($total); ?> <?php echo !empty($_REQUEST['s']) ? 'matching' : (!empty($_GET['mccm_uid']) ? 'shown' : 'burial'); ?> records</p>
<?php } else { ?>
        <div id="icon-users" class="icon32"></div>
        <h2>Lot Information</h2>
        <em>Mousing over Interment # will show extended information</em>
<?php } ?>

        <form id="position" method="post">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page'] ?? '') ?>" />
            <?php if (is_admin()) { ?>
            <div class="mccm-toolbar">
                <?php $entriesListTable->search_box('Search','mccdsearch'); ?>
                <a class="button button-primary mccm-add" href="<?php echo esc_url(admin_url('admin.php?page=mccm&action=addentry')); ?>">+ Add record</a>
                <p class="mccm-tip">Separate words with a comma to narrow the search, for example: Smith, Wyuka</p>
            </div>
            <?php }
            else {
                ?>
                <input type="text" id="mccdsearch-search-input" name="sfe" value="">
                <input type="submit" name="" id="search_femmcm-submit" class="button" value="search">
                <?php
            } ?>
            <?php $entriesListTable->display(); ?>
            <?php if (is_admin()) mccm_pop_shell(); ?>

        </form>
    </div>
    <?php
}
