<?php

declare(strict_types=1);

namespace Monatsblitz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    private const TAG_SELECT_CLOSE = '</select>';
    private const TAG_TD_CLOSE = '</td>';

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
        self::render_qr_section($apiKey, $defaultModeType, $defaultFixedMode, $defaultRotationModes);
        self::render_settings_form(
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
        self::render_modes_catalog($modes);

        echo '</div>';
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
            echo '<div class="notice notice-error"><p>Der Name des Modus darf nicht leer sein.</p></div>';
            return;
        }

        $handler = new \Monatsblitz\Database\CreateModeHandler();
        $result = $handler->handle(['name' => $modeName]);
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Modus <strong>' . esc_html($modeName) . '</strong> erfolgreich angelegt.</p></div>';
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
            echo '<div class="notice notice-success"><p>Modus wurde gelöscht.</p></div>';
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

        echo '<div class="updated"><p>Neuer API-Key wurde generiert.</p></div>';
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
                echo '<div class="notice notice-warning"><p><strong>🚀 Neue Version ' . esc_html($updateResult['latest_version'] ?? '') . ' verfügbar!</strong> Aktuell installiert: ' . esc_html($updateResult['current_version']) . '. <a href="' . esc_url($updateUrl) . '">Jetzt auf der Plugin-Seite aktualisieren &rarr;</a></p></div>';
            } else {
                $displayVersion = $updateResult['latest_version'] ?? $updateResult['current_version'];
                echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Das Plugin ist auf dem neuesten Stand</strong> (Version ' . esc_html($displayVersion) . ').</p></div>';
            }
        } elseif ($cachePurgedNotice) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Update-Cache wurde erfolgreich geleert.</strong></p></div>';
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
        echo '</form>';
        echo '</div>';
    }

    private static function render_qr_section(string $apiKey, string $defaultModeType, string $defaultFixedMode, array $defaultRotationModes): void
    {
        echo '<div class="card" style="max-width:600px; margin:20px 0; padding:16px 20px;">';
        echo '<h2 style="margin-top:0;">📱 App-Einrichtung per QR-Code</h2>';
        if (empty($apiKey)) {
            echo '<p style="color:#d63638;"><em>Bitte generiere zuerst unten unter Einstellungen einen API-Key, damit der QR-Code für die App erzeugt werden kann.</em></p>';
            echo '</div>';
            return;
        }

        $payload = self::build_qr_payload($apiKey, $defaultModeType, $defaultFixedMode, $defaultRotationModes);
        $qrPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        self::render_qr_markup($apiKey, $defaultModeType, $defaultFixedMode, $defaultRotationModes);
        self::render_qr_script($qrPayload);
        echo '</div>';
    }

    private static function build_qr_payload(string $apiKey, string $defaultModeType, string $defaultFixedMode, array $defaultRotationModes): array
    {
        $payload = [
            'url' => home_url(),
            'key' => $apiKey,
        ];
        if (in_array($defaultModeType, ['fixed', 'rotation', 'none'], true)) {
            $payload['mode_type'] = $defaultModeType;
            if ($defaultModeType === 'fixed' && !empty($defaultFixedMode)) {
                $payload['fixed_mode'] = $defaultFixedMode;
            } elseif ($defaultModeType === 'rotation' && !empty($defaultRotationModes)) {
                $payload['rotation_modes'] = array_values($defaultRotationModes);
            }
        }
        return $payload;
    }

    private static function render_qr_markup(string $apiKey, string $defaultModeType, string $defaultFixedMode, array $defaultRotationModes): void
    {
        echo '<p>Scanne diesen QR-Code mit der <strong>Monatsblitz-App</strong> (unter <em>Einstellungen &rarr; QR-Code scannen</em>), um Server-Adresse und API-Key automatisch einzurichten:</p>';
        echo '<div style="margin:16px 0; display:flex; align-items:center; gap:20px; flex-wrap:wrap;">';
        echo '<div id="monatsblitz-qrcode" style="display:inline-block; padding:12px; background:#fff; border:1px solid #ccd0d4; border-radius:4px; min-width:180px; min-height:180px;"></div>';
        echo '<div style="font-size:13px; color:#555;">';
        echo '<p style="margin-top:0;"><strong>Enthaltene Konfiguration:</strong></p>';
        echo '<ul style="list-style:disc; margin-left:20px;">';
        echo '<li><strong>URL:</strong> <code>' . esc_html(home_url()) . '</code></li>';
        echo '<li><strong>API-Key:</strong> <code>' . esc_html(substr($apiKey, 0, 6) . '...' . substr($apiKey, -4)) . '</code></li>';
        if ($defaultModeType === 'fixed' && !empty($defaultFixedMode)) {
            echo '<li><strong>Turniermodus:</strong> Fest (<code>' . esc_html($defaultFixedMode) . '</code>)</li>';
        } elseif ($defaultModeType === 'rotation' && !empty($defaultRotationModes)) {
            echo '<li><strong>Turniermodus:</strong> Rotation (<code>' . esc_html(implode(', ', $defaultRotationModes)) . '</code>)</li>';
        } else {
            echo '<li><strong>Turniermodus:</strong> Keine automatische Vorauswahl</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';
    }

    private static function render_qr_script(string|false $qrPayload): void
    {
        echo '<script type="text/javascript">' . Admin::get_qrcode_js() . '</script>';
        echo '<script type="text/javascript">
        (function() {
            function renderQr() {
                var container = document.getElementById("monatsblitz-qrcode");
                if (!container || typeof QRCode === "undefined") {
                    return;
                }
                if (container.querySelector("canvas, img")) {
                    return;
                }
                container.innerHTML = "";
                new QRCode(container, {
                    text: ' . wp_json_encode($qrPayload) . ',
                    width: 180,
                    height: 180,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            renderQr();
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", renderQr);
            }
        })();
        </script>';
    }

    private static function render_settings_form(array $config, array $users, array $modes): void
    {
        $apiKey = (string)($config['apiKey'] ?? '');
        $author = $config['author'] ?? '';
        $template = (string)($config['template'] ?? '');
        $templateStaticPage = (string)($config['templateStaticPage'] ?? '');
        $hideJanuaryOverview = (bool)($config['hideJanuaryOverview'] ?? false);
        $blitzModes = (string)($config['blitzModes'] ?? '');
        $defaultModeType = (string)($config['defaultModeType'] ?? 'none');
        $defaultFixedMode = (string)($config['defaultFixedMode'] ?? '');
        $defaultRotationModes = (array)($config['defaultRotationModes'] ?? []);

        echo '<h2 style="margin-top:30px;">⚙️ Einstellungen</h2>';

        // Formular 1: API-Key generieren
        echo '<form method="post" style="margin-bottom:0;">';
        wp_nonce_field('monatsblitz_generate_key_action');
        echo '<table class="form-table" style="margin-bottom:0;">';
        echo '<tr><th scope="row">API-Key</th><td>';
        echo '<input type="text" value="' . esc_attr($apiKey) . '" class="regular-text" style="width:350px;" readonly> ';
        echo '<input type="submit" name="monatsblitz_generate_key" class="button" value="Neuen API-Key generieren">';
        echo '<p class="description">Diesen Key muss die Monatsblitz-App im Header <code>X-MB-Key</code> mitsenden.</p>';
        echo '</td></tr>';
        echo '</table>';
        echo '</form>';

        // Formular 2: Normale Einstellungen (läuft über options.php)
        echo '<form method="post" action="options.php">';
        settings_fields('monatsblitz_settings');
        echo '<input type="hidden" name="monatsblitz_api_key" value="' . esc_attr($apiKey) . '">';

        echo '<table class="form-table">';
        self::render_author_setting($author, $users);
        self::render_template_settings($template, $templateStaticPage, $hideJanuaryOverview, $blitzModes);
        self::render_mode_preselection_settings($defaultModeType, $defaultFixedMode, $defaultRotationModes, $modes);
        echo '</table>';

        submit_button();
        echo '</form>';
    }

    private static function render_author_setting($author, array $users): void
    {
        echo '<tr><th scope="row">Autor des Beitrags</th><td>';
        echo '<select name="monatsblitz_author">';
        echo '<option value="">— Bitte wählen —</option>';
        foreach ($users as $u) {
            $selected = selected($author, $u->ID, false);
            echo "<option value='{$u->ID}' $selected>{$u->user_login}</option>";
        }
        echo self::TAG_SELECT_CLOSE;
        echo '</td></tr>';
    }

    private static function render_template_settings(string $template, string $templateStaticPage, bool $hideJanuaryOverview, string $blitzModes): void
    {
        echo '<tr><th scope="row">Template-Name</th><td>';
        echo '<input type="text" name="monatsblitz_template" value="' . esc_attr($template) . '" class="regular-text">';
        echo '<p class="description">Platzhalter: <code>{{month_name}}</code>, <code>{{year}}</code>, <code>{{date}}</code>, <code>{{mode}}</code>, <code>{{round_count}}</code>, <code>{{table}}</code>, <code>{{winner_name}}</code>, <code>{{winner_points}}</code>, <code>{{winner_games}}</code>, <code>{{winner_verb}}</code>, <code>{{winner_text}}</code>.<br>Singular/Plural: <code>{{plural:siegt|siegen}}</code> oder <code>{{#singular}}...{{/singular}}</code> / <code>{{#plural}}...{{/plural}}</code>.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Template: Statische Jahresseite</th><td>';
        echo '<input type="text" name="monatsblitz_template_static_page" value="' . esc_attr($templateStaticPage) . '" class="regular-text">';
        echo '<p class="description">Enthalt Platzhalter {{blitz_monthly_overview}} und {{blitz_ranking_year}}.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Teilnehmer-Ubersicht: Januar ausblenden</th><td>';
        echo '<label>';
        echo '<input type="checkbox" name="monatsblitz_hide_january_overview" value="1" ' . ($hideJanuaryOverview ? 'checked="checked"' : '') . '>';
        echo ' Januar-Spalte in {{blitz_monthly_overview}} ausblenden';
        echo '</label>';
        echo '<p class="description">Empfohlen, wenn im Januar grundsatzlich kein Monatsblitz stattfindet.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Zusatzliche Blitz-Modi</th><td>';
        echo '<input type="text" name="monatsblitz_blitz_modes" value="' . esc_attr($blitzModes) . '" class="regular-text">';
        echo '<p class="description">Komma-Liste, z. B. Handicap, Armageddon. Standardregel bleibt aktiv: alle Modi <= 5+X zahlen als Blitz.</p>';
        echo '</td></tr>';
    }

    private static function render_mode_preselection_settings(string $defaultModeType, string $defaultFixedMode, array $defaultRotationModes, array $modes): void
    {
        echo '<tr><th scope="row">Standard-Turniermodus</th><td>';
        echo '<select name="monatsblitz_default_mode_type">';
        echo '<option value="none" ' . selected($defaultModeType, 'none', false) . '>Keine automatische Vorauswahl</option>';
        echo '<option value="fixed" ' . selected($defaultModeType, 'fixed', false) . '>Fester Modus</option>';
        echo '<option value="rotation" ' . selected($defaultModeType, 'rotation', false) . '>Rotierender Modus</option>';
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Steuert die Vorauswahl bei neuen Turnieren in der App (wird auch im QR-Code übermittelt).</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Fester Standardmodus</th><td>';
        echo '<select name="monatsblitz_default_fixed_mode">';
        echo '<option value="">— Bitte wählen —</option>';
        foreach ($modes as $m) {
            $selected = selected($defaultFixedMode, $m['name'], false);
            echo "<option value='" . esc_attr($m['name']) . "' $selected>" . esc_html($m['name']) . "</option>";
        }
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Wird verwendet, wenn oben <em>Fester Modus</em> gewählt ist.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Modus-Rotation</th><td>';
        echo '<input type="hidden" name="monatsblitz_default_rotation_modes[]" value="">';
        if (empty($modes)) {
            echo '<p class="description">Keine Modi im Katalog vorhanden. Bitte lege weiter unten Modi an.</p>';
        } else {
            foreach ($modes as $m) {
                $checked = in_array($m['name'], $defaultRotationModes, true) ? 'checked="checked"' : '';
                echo '<label style="display:inline-block; margin-right:15px; margin-bottom:5px;">';
                echo '<input type="checkbox" name="monatsblitz_default_rotation_modes[]" value="' . esc_attr($m['name']) . '" ' . $checked . '> ';
                echo esc_html($m['name']);
                echo '</label>';
            }
            echo '<p class="description">Wird verwendet, wenn oben <em>Rotierender Modus</em> gewählt ist. Die App wählt dann basierend auf den vergangenen Turnieren den nächsten Modus dieser Reihenfolge.</p>';
        }
        echo '</td></tr>';
    }

    private static function render_modes_catalog(array $modes): void
    {
        echo '<h2 style="margin-top:30px;">⏱️ Turniermodi (Katalog)</h2>';
        echo '<p class="description">Diese Modi stehen in der App bei der Turniererstellung zur Auswahl und können für feste Vorgaben oder Rotationen verwendet werden.</p>';

        echo '<table class="widefat fixed striped" style="max-width:600px; margin-bottom:15px;">';
        echo '<thead><tr><th style="width:60px;">ID</th><th>Modus-Name</th><th style="width:100px;">Aktion</th></tr></thead>';
        echo '<tbody>';

        if (empty($modes)) {
            echo '<tr><td colspan="3">Keine Modi hinterlegt.</td></tr>';
        } else {
            foreach ($modes as $m) {
                echo '<tr>';
                echo '<td>' . esc_html((string)$m['id']) . self::TAG_TD_CLOSE;
                echo '<td><strong>' . esc_html($m['name']) . '</strong>' . self::TAG_TD_CLOSE;
                echo '<td>';
                echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Modus wirklich l&ouml;schen?\');">';
                wp_nonce_field('monatsblitz_delete_mode_action');
                echo '<input type="hidden" name="monatsblitz_delete_mode" value="1">';
                echo '<input type="hidden" name="monatsblitz_delete_mode_id" value="' . esc_attr((string)$m['id']) . '">';
                echo '<input type="submit" class="button button-small button-link-delete" value="Löschen">';
                echo '</form>';
                echo self::TAG_TD_CLOSE;
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<form method="post" style="display:flex; align-items:center; gap:10px; max-width:600px;">';
        wp_nonce_field('monatsblitz_add_mode_action');
        echo '<input type="text" name="monatsblitz_new_mode_name" placeholder="z. B. 10+0 oder 3+0" class="regular-text" style="width:250px;" required>';
        echo '<input type="submit" name="monatsblitz_add_mode" class="button" value="Modus hinzufügen">';
        echo '</form>';
    }
}
