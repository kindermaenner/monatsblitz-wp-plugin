<?php

/**
 * Plugin Name: Monatsblitz
 * Plugin URI: https://github.com/kindermaenner/monatsblitz-wp-plugin
 * Description: Verwaltung von Monatsblitz-Turnieren.
 * Version: 1.0
 * Requires PHP: 8.1
 * Author: Nina Kindermann
 * Author URI: https://kindermaenner.de
 * License: GPL-2.0-or-later
 * License URI: https://github.com/kindermaenner/monatsblitz-wp-plugin/blob/main/LICENSE
 * Update URI: https://kindermaenner.de/plugin-updates/monatsblitz/
 */

if (!defined('ABSPATH')) {
    exit; // Direkten Zugriff verhindern
}

// 🔧 Konstanten
define('MB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MB_PLUGIN_URL', plugin_dir_url(__FILE__));

// 📦 Includes
require_once __DIR__ . '/vendor/autoload.php';

define('MONATSBLITZ_PLUGIN_PATH', plugin_dir_path(__FILE__));

$pluginData = get_file_data(__FILE__, ['Version' => 'Version']);
define('MONATSBLITZ_VERSION', $pluginData['Version'] ?? '1.0');
new \Monatsblitz\Update\PluginUpdater(
    __FILE__,
    MONATSBLITZ_VERSION,
    'https://kindermaenner.de/plugin-updates/monatsblitz/manifest.json'
);

// 🔌 Aktivierung (Tabellen anlegen)
register_activation_hook(__FILE__, [\Monatsblitz\Database\DatabaseSchema::class, 'init']);

// 🚀 Initialisierung
add_action('plugins_loaded', function () {
    \Monatsblitz\Database\DatabaseSchema::maybe_upgrade();
    \Monatsblitz\Api\Api::init();
    \Monatsblitz\Admin\Admin::init();

    add_action('wp_enqueue_scripts', static function (): void {
        wp_enqueue_style(
            'monatsblitz-tables',
            MB_PLUGIN_URL . 'assets/css/monatsblitz-tables.css',
            [],
            '1.0'
        );
        wp_enqueue_style(
            'monatsblitz-cross-table',
            MB_PLUGIN_URL . 'assets/css/monatsblitz-cross-table.css',
            ['monatsblitz-tables'],
            '1.0'
        );
        wp_enqueue_style(
            'monatsblitz-year-overview',
            MB_PLUGIN_URL . 'assets/css/monatsblitz-year-overview.css',
            ['monatsblitz-tables'],
            '1.0'
        );
        wp_enqueue_style(
            'monatsblitz-year-ranking',
            MB_PLUGIN_URL . 'assets/css/monatsblitz-year-ranking.css',
            ['monatsblitz-tables'],
            '1.0'
        );
    });
});
