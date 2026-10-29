<?php

declare(strict_types=1);

namespace Monatsblitz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    private const TAG_DIV_CLOSE = '</div>';
    private const TAG_FORM_CLOSE = '</form>';

    public static function render(): void
    {
        $apiKey = (string)get_option('monatsblitz_api_key', '');
        $updateResult = null;
        $cachePurgedNotice = false;

        self::handle_settings_actions($apiKey, $updateResult, $cachePurgedNotice);

        $author = get_option('monatsblitz_author', '');
        $template = (string)get_option('monatsblitz_template', '');
        $templateStaticPage = (string)get_option('monatsblitz_template_static_page', '');
        $blitzModes = (string)get_option('monatsblitz_blitz_modes', '');
        $hideJanuaryOverview = (bool)get_option('monatsblitz_hide_january_overview', false);
        $defaultModeType = (string)get_option('monatsblitz_default_mode_type', 'none');
        $defaultFixedMode = (string)get_option('monatsblitz_default_fixed_mode', '');
        $defaultRotationModes = self::get_default_rotation_modes();

        $modes = (new \Monatsblitz\Queries\GetModesHandler())->handle();
        $users = get_users(['fields' => ['ID', 'user_login']]);

        echo '<div class="wrap">';
        echo '<h1>Monatsblitz – Einstellungen</h1>';

        self::render_update_notices($updateResult, $cachePurgedNotice);
        self::render_update_section();
        SettingsQrSection::render($apiKey, $defaultModeType, $defaultFixedMode, $defaultRotationModes);
        SettingsFormRenderer::render(
            [
                'apiKey' => $apiKey,
                'author' => $author,
                'template' => $template,
                'templateStaticPage' => $templateStaticPage,
                'hideJanuaryOverview' => $hideJanuaryOverview,
                'blitzModes' => $blitzModes,
                'defaultModeType' => $defaultModeType,
                'defaultFixedMode' => $defaultFixedMode,
                'defaultRotationModes' => $defaultRotationModes,
            ],
            $users,
            $modes
        );

        echo self::TAG_DIV_CLOSE;
    }

    private static function get_default_rotation_modes(): array
    {
        $modes = get_option('monatsblitz_default_rotation_modes', []);
        if (!is_array($modes)) {
            return array_values(array_filter(array_map('trim', explode(',', (string)$modes)), fn($v) => $v !== ''));
        }
        return array_values(array_filter($modes, fn($v) => !empty($v)));
    }

    private static function handle_settings_actions(string &$apiKey, ?array &$updateResult, bool &$cachePurgedNotice): void
    {
        self::handle_add_mode_action();
        self::handle_delete_mode_action();
        self::handle_generate_key_action($apiKey);
        self::handle_check_update_action($updateResult, $cachePurgedNotice);
    }

    private static function handle_add_mode_action(): void
    {
        if (!isset($_POST['monatsblitz_add_mode'])) {
            return;
        }
        check_admin_referer('monatsblitz_add_mode_action');
        $modeName = sanitize_text_field(trim((string)($_POST['monatsblitz_new_mode_name'] ?? '')));
        if ($modeName === '') {
            echo '<div class="notice notice-error"><p>Der Name des Modus darf nicht leer sein.</p>' . self::TAG_DIV_CLOSE;
            return;
        }

        $handler = new \Monatsblitz\Database\CreateModeHandler();
        $result = $handler->handle(['name' => $modeName]);
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p>' . self::TAG_DIV_CLOSE;
        } else {
            echo '<div class="notice notice-success"><p>Modus <strong>' . esc_html($modeName) . '</strong> erfolgreich angelegt.</p>' . self::TAG_DIV_CLOSE;
        }
    }

    private static function handle_delete_mode_action(): void
    {
        if (!isset($_POST['monatsblitz_delete_mode'])) {
            return;
        }
        check_admin_referer('monatsblitz_delete_mode_action');
        $deleteId = intval($_POST['monatsblitz_delete_mode_id'] ?? 0);
        if ($deleteId > 0) {
            global $wpdb;
            $modesTable = $wpdb->prefix . 'monatsblitz_modes';
            $wpdb->delete($modesTable, ['id' => $deleteId], ['%d']);
            echo '<div class="notice notice-success"><p>Modus wurde gelöscht.</p>' . self::TAG_DIV_CLOSE;
        }
    }

    private static function handle_generate_key_action(string &$apiKey): void
    {
        if (!isset($_POST['monatsblitz_generate_key'])) {
            return;
        }
        check_admin_referer('monatsblitz_generate_key_action');

        $newKey = wp_generate_password(32, false, false);
        update_option('monatsblitz_api_key', $newKey);
        $apiKey = $newKey;

        echo '<div class="updated"><p>Neuer API-Key wurde generiert.</p>' . self::TAG_DIV_CLOSE;
    }

    private static function handle_check_update_action(?array &$updateResult, bool &$cachePurgedNotice): void
    {
        if (!isset($_POST['monatsblitz_check_update'])) {
            return;
        }
        check_admin_referer('monatsblitz_check_update_action');

        if (!class_exists(\Monatsblitz\Update\PluginUpdater::class)) {
            return;
        }
        $updater = \Monatsblitz\Update\PluginUpdater::getInstance();
        if ($updater !== null) {
            $updateResult = $updater->checkUpdateNow();
        } else {
            \Monatsblitz\Update\PluginUpdater::purge();
            $cachePurgedNotice = true;
        }
    }

    private static function render_update_notices(?array $updateResult, bool $cachePurgedNotice): void
    {
        if ($updateResult !== null) {
            if ($updateResult['has_update']) {
                $updateUrl = function_exists('admin_url') ? admin_url('plugins.php') : 'plugins.php';
                echo '<div class="notice notice-warning"><p><strong>🚀 Neue Version ' . esc_html($updateResult['latest_version'] ?? '') . ' verfügbar!</strong> Aktuell installiert: ' . esc_html($updateResult['current_version']) . '. <a href="' . esc_url($updateUrl) . '">Jetzt auf der Plugin-Seite aktualisieren &rarr;</a></p>' . self::TAG_DIV_CLOSE;
            } else {
                $displayVersion = $updateResult['latest_version'] ?? $updateResult['current_version'];
                echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Das Plugin ist auf dem neuesten Stand</strong> (Version ' . esc_html($displayVersion) . ').</p>' . self::TAG_DIV_CLOSE;
            }
        } elseif ($cachePurgedNotice) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Update-Cache wurde erfolgreich geleert.</strong></p>' . self::TAG_DIV_CLOSE;
        }
    }

    private static function render_update_section(): void
    {
        $installedVersion = defined('MONATSBLITZ_VERSION') ? MONATSBLITZ_VERSION : '0.9.3';
        if (class_exists(\Monatsblitz\Update\PluginUpdater::class)) {
            $updater = \Monatsblitz\Update\PluginUpdater::getInstance();
            if ($updater !== null) {
                $installedVersion = $updater->getCurrentVersion();
            }
        }

        echo '<div class="card" style="max-width:600px; margin:20px 0; padding:16px 20px;">';
        echo '<h2 style="margin-top:0;">🔄 Plugin-Aktualisierung</h2>';
        echo '<p>Installierte Version: <strong>' . esc_html($installedVersion) . '</strong></p>';
        echo '<form method="post" style="margin-top:12px;">';
        wp_nonce_field('monatsblitz_check_update_action');
        echo '<p><input type="submit" name="monatsblitz_check_update" class="button button-secondary" value="🔄 Auf Aktualisierungen prüfen"></p>';
        echo '<p class="description">Leert den internen 6-Stunden-Cache und prüft sofort beim Update-Server auf neue Releases.</p>';
        echo self::TAG_FORM_CLOSE;
        echo self::TAG_DIV_CLOSE;
    }
}
