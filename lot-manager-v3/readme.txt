=== Wyuka Cemetery Manager ===
Contributors: mdsharif
Tags: cemetery, burial, records, grave, genealogy
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Keep a cemetery's burial records in WordPress: add, import, export, report, and a public grave locator with map links.

== Description ==

Wyuka Cemetery Manager stores a cemetery's burial register in WordPress. Staff add and edit records in the dashboard. Visitors search by name on a public page and see where a person is buried.

**Dashboard**

* Searchable, sortable list of records, with a quick view panel
* Add and edit form with required fields, date and age checks, and a warning for duplicates or when someone else changed the record
* Import from CSV: update existing records or add new ones, with a test run and a row by row report
* Export the full register to CSV
* Reports: grave report with filters, duplicates report, contacts report (screen, print or CSV)

**Website**

* Add the `[mccm]` shortcode to any page for the public grave locator
* Result cards, 24 per page, with the section map link

Based on the original Lot Manager by Meridian Consulting, Inc. (2011), rewritten for current PHP and WordPress.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin. The records table is created if it does not exist; an existing table is left untouched.
3. Open **Lot Manager** in the dashboard.
4. Put `[mccm]` on the page where visitors should search.

== Frequently Asked Questions ==

= Is my data removed if I deactivate or delete the plugin? =

No. The records stay in the database table. While the plugin is deactivated the records are not shown on the website or in the dashboard.

= Who can use it? =

Editors and above can view, add, edit and delete records. Administrators can import, export and run reports.

= How do I import records? =

Open **Lot Manager > Import**, download the template, fill it in, and run a test first. The template has 37 columns.

= Can the grave locator link to my own section maps? =

The map links are currently set for Wyuka Cemetery and Fairview Cemetery. Other cemeteries need the map list changed in `public/shortcodes.php`.

== Changelog ==

= 3.0.0 =
* Work orders and the staff page were removed.
* Import now applies the same rules as the add and edit form.
* New card layout for the public grave locator, 24 results per page.
* Warning when the plugin is deactivated.
* Safer import, export and reports (permission and nonce checks on every action).

= 2.0.0 =
* Rewrite for current PHP and WordPress.

== Upgrade Notice ==

= 3.0.0 =
Work orders and the staff page are removed. Burial records and their data are not changed.
