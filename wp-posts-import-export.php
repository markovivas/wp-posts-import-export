<?php
/**
 * Plugin Name: WP Posts Import Export
 * Plugin URI:  https://github.com/anomalyco/wp-posts-import-export
 * Description: Exporte e importe posts do WordPress preservando todos os dados dos conteudos, imagens destacadas, categorias, tags, autores e datas de publicacao.
 * Version:     1.0.0
 * Author:      WP Posts Import Export
 * Author URI:  https://github.com/anomalyco
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-posts-import-export
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires WP:  6.0
 */

defined('ABSPATH') || exit;

define('WP_PIE_VERSION', '1.0.0');
define('WP_PIE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_PIE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_PIE_PLUGIN_BASENAME', plugin_basename(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'WPPostsImportExport\\';
    $base_dir = WP_PIE_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function wp_pie_init() {
    load_plugin_textdomain('wp-posts-import-export', false, dirname(WP_PIE_PLUGIN_BASENAME) . '/languages');

    if (is_admin()) {
        new \WPPostsImportExport\Admin();
    }
}
add_action('plugins_loaded', 'wp_pie_init');
