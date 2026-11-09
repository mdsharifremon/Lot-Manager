<div align="center">

# Lot Manager

**Cemetery burial records for WordPress: manage, import, export, report, and let families find a grave online.**

![Version](https://img.shields.io/badge/version-2.0.0-2f6f5f)
![PHP](https://img.shields.io/badge/PHP-7.4%20to%208.5-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![License](https://img.shields.io/badge/license-GPL--2.0--only-blue)

</div>

---

## What it is

Lot Manager keeps a cemetery's burial register inside WordPress. Staff add and edit records in the dashboard. Visitors search by name on a public page and see where a person is buried, with a link to the section map.

It is a rewrite of the original *Meridian Consulting* Lot Manager (2011), rebuilt for modern PHP and WordPress. The database table is the same, so existing data keeps working with no migration.

> Plugin name: **Wyuka Cemetery Manager** · Folder: `lot-manager-v3/` · Main file: `mccm.php`

## Features

| Area | What you get |
|---|---|
| **Records** | Searchable, sortable list. Add, view, edit, delete. Quick side panel for a record. |
| **Validation** | Required fields, real dates, age range, choice lists, length limits read from the live table, duplicate warning, and a warning if someone else changed the record while you were editing. |
| **Import (CSV)** | 37 columns. Update existing records or add new ones. Test run first. Row by row report with the reason each row was skipped. Excel files in UTF-8 or Windows-1252 both work. |
| **Export (CSV)** | Full register, streamed in batches, so 60,000+ rows download without running out of memory. |
| **Reports** | Grave report (filter by date, cemetery, section, lot, space), duplicates report, contacts report. Screen, print, or CSV. |
| **Public grave locator** | `[mccm]` shortcode. Responsive result cards (4, 3, 2, 1 columns), 24 per page, exact count, section map PDF link. |

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer (tested on 8.5)
- MySQL or MariaDB

## Install

1. Copy the `lot-manager-v3` folder to `wp-content/plugins/`.
2. Activate **Wyuka Cemetery Manager** in the dashboard.
3. On a new site the records table is created automatically. If the table already exists it is left untouched.
4. Put `[mccm]` on the page where visitors should search.

> Deactivating or deleting the plugin never removes your data.

## Using it

**Dashboard menu: Lot Manager**

| Screen | Who | Purpose |
|---|---|---|
| All records | Editors and up (`add_users`) | Find, view, edit, delete |
| Add record | Editors and up | New burial record |
| Export | Administrators (`manage_options`) | Download the full register |
| Import | Administrators | Load or update records from CSV |
| Reports | Administrators | Grave, duplicates, contacts |

**Public page:** add `[mccm]` to any page.

### Required fields (new records)

Last name, First name, Interment #, Cemetery, Section, Space, Birth date, Gender.
When editing an old record, a field is only required if the record already had a value.

### Importing a CSV

1. Download the template on the Import screen. It has the 37 column headings in the right order.
2. Choose **Update existing** (matches on Interment #, Cemetery, Section, Lot, Space) or **Add as new**.
3. Tick **Test run** and import. Read the report. Nothing is saved.
4. Untick **Test run** and import for real.

The report shows rows read, would update or add, already up to date, skipped (with reasons), and duplicates.
Dates: `M/D/YYYY` or `YYYY-MM-DD`, 4-digit year. Partial dates like `1965-04-00` are kept.

## Safety and data quality

- Every action checks the user's capability and a nonce.
- All output is escaped. All database values are prepared.
- Tags and control characters are stripped from text on save and import.
- Text the database cannot store is refused on import instead of being damaged.
- Exports are written with a deny-all `.htaccess` in `exports/`.
- No work-order or staff-login features. They were removed in this version.

## Project structure

```
lot-manager-v3/
├── mccm.php                 Plugin entry point
├── includes/                Shared code: helpers, install, assets, list table
├── admin/
│   ├── menu.php, ajax.php, list-view.php
│   ├── form/                data, validate, save, render
│   └── screens/             records, import, export, reports
├── public/shortcodes.php    [mccm] grave locator
├── assets/                  admin and public CSS/JS, fonts
└── exports/                 Protected folder for export files
```

## Testing

Before release the plugin was checked against a copy of the real data:

- 57 save and validation cases (missing fields, bad dates, limits, special characters, duplicates, stale edits)
- 6 import files in both modes, as test runs and as rolled-back real runs
- Permission checks with no login, no nonce and a wrong nonce on every action
- Public search with abusive input (quotes, SQL text, script tags, huge page numbers)
- Reports, export row count against the database, template columns

## Credits and license

- Original plugin: Meridian Consulting, Inc. (2011)
- Rewrite and maintenance: [MD Sharif](https://sharifu.com) (2026)
- Fonts: Lato and Lora, SIL Open Font License (`assets/fonts/OFL.txt`)
- License: GPL-2.0-only, as stated in the plugin header
