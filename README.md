# WP Posts Import Export

Contributors: anomalyco
Tags: export, import, posts, migration, wordpress
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Export and import WordPress posts preserving all content data, featured images, categories, tags, authors, and publication dates.

## Description

WP Posts Import Export allows you to export WordPress posts into a ZIP file containing a structured JSON file and all featured images, and import them back into any WordPress installation with full data preservation.

### Features

- Export posts with filters by category and date range
- Preserves title, content, slug, date (local and GMT), status, author, categories, and tags
- Automatically downloads and renames featured images using post date (dd-mm-YYYY format)
- Handles duplicate dates with incremental suffixes
- Imports posts preserving original publication dates
- Creates missing categories and tags during import
- Falls back to current admin when original author doesn't exist
- Imports featured images into the WordPress Media Library
- Progress bar with detailed import report
- Full i18n support
- PHP 8.1+ compatible
- Multisite compatible

## Installation

1. Upload the `wp-posts-import-export` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Tools > Import/Export Posts to use the plugin

## Usage

### Exporting

1. Go to Tools > Import/Export Posts
2. Select the "Export" tab
3. Optionally filter by category or date range
4. Click "Export Posts"
5. Download the generated ZIP file

### Importing

1. Go to Tools > Import/Export Posts
2. Select the "Import" tab
3. Upload the ZIP file
4. Click "Import"
5. Wait for the progress bar to complete
6. Review the import report

## Frequently Asked Questions

### Does this plugin support custom post types?

Currently it only exports/imports standard WordPress posts. Custom post type support may be added in future versions.

### Are attachments (other than featured images) exported?

No, only featured images are exported and imported. Other media attached to post content is not included.

### What happens if an author doesn't exist on the target site?

The post is assigned to the current administrator user.

### Is the plugin multisite compatible?

Yes, the plugin works on WordPress multisite installations.

## Changelog

### 1.0.0

- Initial release

## Upgrade Notice

### 1.0.0

Initial release.
