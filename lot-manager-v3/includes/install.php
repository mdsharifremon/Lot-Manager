<?php
/**
 * Install / uninstall
 * Creates the table on activation if missing. Uninstall never drops data.
 */
if (!defined('ABSPATH')) exit;

function mccm_install() {
    global $wpdb;

    // Create default options if needed
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
                  `CONTACT_NAME` varchar(255) DEFAULT NULL,
                  `CONTACT_STREET1` varchar(255) DEFAULT NULL,
                  `CONTACT_STREET2` varchar(255) DEFAULT NULL,
                  `CONTACT_CITY` varchar(255) DEFAULT NULL,
                  `CONTACT_STATE` varchar(255) DEFAULT NULL,
                  `CONTACT_ZIP` varchar(255) DEFAULT NULL,
                  `CONTACT_YEAR` year(4) DEFAULT NULL,
                  `LASTCHANGE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `CHANGER` varchar(255) NOT NULL,
                  PRIMARY KEY (`UID`),
                  KEY `UID` (`UID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $wpdb->query($sql);
        }

    }

/** Runs on "delete plugin". Intentionally keeps all data. */
function mccm_uninstall() {
    // Burial records are never dropped when the plugin is deleted.
}
