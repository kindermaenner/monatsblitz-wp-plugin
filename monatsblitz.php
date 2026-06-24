<?php
/**
 * Plugin Name: Monatsblitz
 * Description: Verwaltung von Monatsblitz-Turnieren.
 * Version: 1.0
 * Author: Nina Kindermann
 */

if (!defined('ABSPATH')) {
    exit; // Direkten Zugriff verhindern
}

// 🔧 Konstanten
define('MB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MB_PLUGIN_URL', plugin_dir_url(__FILE__));

// 📦 Includes
require_once MB_PLUGIN_PATH . 'includes/MB_Database.php';
require_once MB_PLUGIN_PATH . 'api/MB_API.php';
require_once MB_PLUGIN_PATH . 'admin/MB_Admin.php';

// 🔌 Aktivierung (Tabellen anlegen)
register_activation_hook(__FILE__, [\monatsblitz\MB_Database::class, 'init']);

// 🚀 Initialisierung
add_action('plugins_loaded', function () {
    \monatsblitz\MB_API::init();
    \monatsblitz\MB_Admin::init();
});