<?php
/**
 * Plugin Name: Wyuka Cemetery Manager
 * Plugin URI: https://sharifu.com
 * Description:  Description: Burial records, reports, import and export, and the public grave locator. <strong>Main data store of the site. Do not deactivate: the records will not show on the website or in the dashboard.</strong>
 * Version: 3.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.8
 * Author: Sharif
 * Author URI: mailto:sharifwds@gmail.com
 * Update URI: false
 * License: GPL-2.0-only
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: mccm
 */
/**
 * Copyright 2011 Meridian Consulting, Inc. (original plugin)
 * Copyright 2026 MD Sharif (https://sharifu.com) (restructured and rewritten)
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
if (!defined('ABSPATH')) exit; // no direct access

define('MCCM_VERSION', '3.0.0');
define('MCCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MCCM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCCM_AUTHOR_NAME', 'Sharif'); // shown on printed reports
define('MCCM_AUTHOR_URI', 'https://sharifu.com');

/* Structure:  includes/ shared code  |  admin/ dashboard code  |  public/ front end  |  assets/ css, js, fonts */
require_once MCCM_PLUGIN_DIR . 'includes/helpers.php';
require_once MCCM_PLUGIN_DIR . 'includes/class-mccm-list-table.php';
require_once MCCM_PLUGIN_DIR . 'includes/install.php';
require_once MCCM_PLUGIN_DIR . 'includes/assets.php';
require_once MCCM_PLUGIN_DIR . 'admin/menu.php';
require_once MCCM_PLUGIN_DIR . 'admin/ajax.php';
require_once MCCM_PLUGIN_DIR . 'admin/plugin-screen.php';
require_once MCCM_PLUGIN_DIR . 'admin/list-view.php';
require_once MCCM_PLUGIN_DIR . 'admin/form/data.php';
require_once MCCM_PLUGIN_DIR . 'admin/form/validate.php';
require_once MCCM_PLUGIN_DIR . 'admin/form/save.php';
require_once MCCM_PLUGIN_DIR . 'admin/form/render.php';
require_once MCCM_PLUGIN_DIR . 'admin/screens/lot-manager.php';
require_once MCCM_PLUGIN_DIR . 'admin/screens/export.php';
require_once MCCM_PLUGIN_DIR . 'admin/screens/import.php';
require_once MCCM_PLUGIN_DIR . 'admin/screens/import-form.php';
require_once MCCM_PLUGIN_DIR . 'admin/screens/reports.php';
require_once MCCM_PLUGIN_DIR . 'public/shortcodes.php';

register_activation_hook(__FILE__, 'mccm_install');
register_uninstall_hook(__FILE__, 'mccm_uninstall');
