<?php

declare(strict_types=1);

namespace Monatsblitz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsFormRenderer
{
    private const TAG_DIV_CLOSE = '</div>';
    private const TAG_SELECT_CLOSE = '</select>';
    private const TAG_TD_CLOSE = '</td>';
    private const TAG_ROW_CLOSE = '</td></tr>';
    private const TAG_FORM_CLOSE = '</form>';
    private const INPUT_REGULAR_CLASS = '" class="regular-text">';

    public static function render(array $config, array $users, array $modes): void
    {
        self::render_api_keys_card($config);
        self::render_modes_catalog($modes);
        self::render_options_form($config, $users, $modes);
    }

    private static function render_api_keys_card(array $config): void
    {
        $apiKey = (string)($config['apiKey'] ?? '');
        $maintenanceApiKey = (string)($config['maintenanceApiKey'] ?? '');

        echo '<div class="card" style="max-width:100%; margin-top:20px; padding:20px;">';
        echo '<h2 style="margin-top:0;">🔑 API-Schlüsselverwaltung</h2>';

        echo '<form method="post" style="margin-bottom:0;">';
        wp_nonce_field('monatsblitz_generate_key_action');
        echo '<table class="form-table" style="margin-bottom:0;">';

        echo '<tr><th scope="row">App-API-Key</th><td>';
        echo '<input type="text" value="' . esc_attr($apiKey) . '" class="regular-text" style="width:350px;" readonly> ';
        echo '<input type="submit" name="monatsblitz_generate_key" class="button" value="Neuen API-Key generieren">';
        echo '<p class="description">Diesen Key muss die Monatsblitz-App im Header <code>X-MB-Key</code> mitsenden (wird im QR-Code oben codiert).</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Wartungs-API-Key</th><td>';
        echo '<input type="text" value="' . esc_attr($maintenanceApiKey) . '" class="regular-text" style="width:350px;" readonly> ';
        echo '<input type="submit" name="monatsblitz_generate_maintenance_key" class="button" value="Neuen API-Key generieren">';
        echo '<p class="description">Diesen Key für Wartungs-Endpunkte (z. B. <code>/recreatePosts</code>) im Header <code>X-MB-Maintenance-Key</code> mitsenden. <strong>Nicht an App-Nutzer weitergeben</strong>; darf nicht identisch mit dem App-Key sein.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '</table>';
        echo self::TAG_FORM_CLOSE;
        echo self::TAG_DIV_CLOSE;
    }

    private static function render_modes_catalog(array $modes): void
    {
        echo '<div class="card" style="max-width:100%; margin-top:20px; padding:20px;">';
        echo '<h2 style="margin-top:0;">⏱️ Turniermodi (Katalog)</h2>';
        echo '<p class="description">Diese Modi stehen in der App bei der Turniererstellung zur Auswahl und können für feste Vorgaben oder Rotationen verwendet werden.</p>';

        echo '<table class="widefat fixed striped" style="max-width:600px; margin:15px 0;">';
        echo '<thead><tr><th style="width:60px;">ID</th><th>Modus-Name</th><th style="width:100px;">Aktion</th></tr></thead>';
        echo '<tbody>';

        if (empty($modes)) {
            echo '<tr><td colspan="3">Keine Modi hinterlegt.' . self::TAG_ROW_CLOSE;
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
                echo self::TAG_FORM_CLOSE;
                echo self::TAG_TD_CLOSE;
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<form method="post" style="display:flex; align-items:center; gap:10px; max-width:600px;">';
        wp_nonce_field('monatsblitz_add_mode_action');
        echo '<input type="text" name="monatsblitz_new_mode_name" placeholder="z. B. 10+0 oder 3+0" class="regular-text" style="width:250px;" required>';
        echo '<input type="submit" name="monatsblitz_add_mode" class="button" value="Modus hinzufügen">';
        echo self::TAG_FORM_CLOSE;
        echo self::TAG_DIV_CLOSE;
    }

    private static function render_options_form(array $config, array $users, array $modes): void
    {
        $apiKey = (string)($config['apiKey'] ?? '');
        $maintenanceApiKey = (string)($config['maintenanceApiKey'] ?? '');
        $author = $config['author'] ?? '';
        $template = (string)($config['template'] ?? '');
        $templateNonRanking = (string)($config['templateNonRanking'] ?? '');
        $templateStaticPage = (string)($config['templateStaticPage'] ?? '');
        $hideJanuaryOverview = (bool)($config['hideJanuaryOverview'] ?? false);
        $blitzModes = (string)($config['blitzModes'] ?? '');
        $defaultModeType = (string)($config['defaultModeType'] ?? 'none');
        $defaultFixedMode = (string)($config['defaultFixedMode'] ?? '');
        $defaultRotationModes = (array)($config['defaultRotationModes'] ?? []);
        $rufnameDisplay = (string)($config['rufnameDisplay'] ?? 'none');

        echo '<form method="post" action="options.php">';
        settings_fields('monatsblitz_settings');
        echo '<input type="hidden" name="monatsblitz_api_key" value="' . esc_attr($apiKey) . '">';
        echo '<input type="hidden" name="monatsblitz_maintenance_api_key" value="' . esc_attr($maintenanceApiKey) . '">';

        // Card 1: Spielmodus-Vorauswahl & Wertung
        echo '<div class="card" style="max-width:100%; margin-top:20px; padding:20px;">';
        echo '<h2 style="margin-top:0;">♟️ Spielmodus-Vorauswahl (App) &amp; Wertung</h2>';
        echo '<table class="form-table">';
        self::render_mode_preselection_settings($defaultModeType, $defaultFixedMode, $defaultRotationModes, $modes);
        self::render_blitz_modes_setting($blitzModes);
        echo '</table>';
        echo self::TAG_DIV_CLOSE;

        // Card 2: Beitrags-Templates & Darstellung
        echo '<div class="card" style="max-width:100%; margin-top:20px; padding:20px;">';
        echo '<h2 style="margin-top:0;">📝 Beitrags-Templates &amp; Darstellung</h2>';
        echo '<div class="notice notice-info inline" style="margin:12px 0 20px 0; padding:10px 15px;">';
        echo '<p>ℹ️ Eine Übersicht aller verfügbaren Template-Platzhalter (z.&nbsp;B. <code>{{winner_name}}</code>, <code>{{table}}</code>, Singular/Plural-Filter) und Beispiele findest du in der <a href="https://github.com/kindermaenner/monatsblitz-wp-plugin#template-placeholders" target="_blank" rel="noopener noreferrer"><strong>Template-Dokumentation im GitHub Readme ↗</strong></a>.</p>';
        echo self::TAG_DIV_CLOSE;

        echo '<table class="form-table">';
        self::render_author_setting($author, $users);
        self::render_rufname_setting($rufnameDisplay);
        self::render_template_settings($template, $templateNonRanking, $templateStaticPage, $hideJanuaryOverview);
        echo '</table>';

        submit_button('Einstellungen speichern');
        echo self::TAG_DIV_CLOSE;

        echo self::TAG_FORM_CLOSE;
    }

    private static function render_blitz_modes_setting(string $blitzModes): void
    {
        echo '<tr><th scope="row">Zusätzliche Blitz-Modi</th><td>';
        echo '<input type="text" name="monatsblitz_blitz_modes" value="' . esc_attr($blitzModes) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Komma-Liste, z. B. Handicap, Armageddon. Standardregel bleibt aktiv: alle Modi &le; 5+X zählen automatisch als Blitz für die Jahreswertung.</p>';
        echo self::TAG_ROW_CLOSE;
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
        echo self::TAG_ROW_CLOSE;
    }

    private static function render_template_settings(
        string $template,
        string $templateNonRanking,
        string $templateStaticPage,
        bool $hideJanuaryOverview
    ): void {
        echo '<tr><th colspan="2" style="padding-top:20px; padding-bottom:5px;"><h3 style="margin:0;">📰 Templates für News-Beiträge (Turnierberichte)</h3><p class="description">Wird beim Finalisieren eines Turniers kopiert und als neuer, veröffentlichter WordPress-<strong>Beitrag</strong> angelegt.</p></th></tr>';

        echo '<tr><th scope="row">Beitrags-Template<br><small style="font-weight:normal;color:#666;">Offizielles Monatsblitz</small></th><td>';
        echo '<input type="text" name="monatsblitz_template" value="' . esc_attr($template) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Titel des WordPress-<strong>Beitrags</strong> für Turniere mit Jahreswertung (z.&nbsp;B. <code>Template_Monatsblitz</code>).</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Beitrags-Template<br><small style="font-weight:normal;color:#666;">Freie Turniere (optional)</small></th><td>';
        echo '<input type="text" name="monatsblitz_template_non_ranking" value="' . esc_attr($templateNonRanking) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Titel des WordPress-<strong>Beitrags</strong> für Turniere ohne Jahreswertung (z.&nbsp;B. Nachblitz). Bleibt das Feld leer, wird das obige Monatsblitz-Template verwendet.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th colspan="2" style="padding-top:25px; padding-bottom:5px;"><h3 style="margin:0;">📄 Template für statische Jahresseite (Gesamtwertung)</h3><p class="description">Aktualisiert beim Finalisieren automatisch die Jahres-Übersichtsseite (Slug: <code>blitz-YYYY</code>).</p></th></tr>';

        echo '<tr><th scope="row">Seiten-Template<br><small style="font-weight:normal;color:#666;">Jahres-Gesamtwertung</small></th><td>';
        echo '<input type="text" name="monatsblitz_template_static_page" value="' . esc_attr($templateStaticPage) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Titel einer WordPress-<strong>Seite</strong> (nicht Beitrag!), die als Vorlage dient und die Tabellen <code>{{blitz_monthly_overview}}</code> und <code>{{blitz_ranking_year}}</code> enthält.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Teilnehmer-Übersicht:<br><small style="font-weight:normal;color:#666;">Januar ausblenden</small></th><td>';
        echo '<label>';
        echo '<input type="checkbox" name="monatsblitz_hide_january_overview" value="1" ' . ($hideJanuaryOverview ? 'checked="checked"' : '') . '>';
        echo ' Januar-Spalte in <code>{{blitz_monthly_overview}}</code> auf der Jahresseite ausblenden';
        echo '</label>';
        echo '<p class="description">Empfohlen, wenn im Januar grundsätzlich kein Monatsblitz stattfindet.</p>';
        echo self::TAG_ROW_CLOSE;
    }

    private static function render_rufname_setting(string $rufnameDisplay): void
    {
        echo '<tr><th scope="row">Rufnamen in Tabellen</th><td>';
        echo '<select name="monatsblitz_rufname_display">';
        echo '<option value="none" ' . selected($rufnameDisplay, 'none', false) . '>Keine Rufnamen (nur offizieller Name: Vorname Nachname)</option>';
        echo '<option value="collision" ' . selected($rufnameDisplay, 'collision', false) . '>Nur bei Namensgleichheit zur Unterscheidung (z. B. Michael Schmidt (Michi))</option>';
        echo '<option value="always" ' . selected($rufnameDisplay, 'always', false) . '>Immer wenn vorhanden (z. B. Pham Dai Thien Nguyen (Thien))</option>';
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Steuert, wie Spielernamen in den generierten Beiträgen und der Jahreswertung formatiert werden.</p>';
        echo self::TAG_ROW_CLOSE;
    }

    private static function render_mode_preselection_settings(
        string $defaultModeType,
        string $defaultFixedMode,
        array $defaultRotationModes,
        array $modes
    ): void {
        echo '<tr><th scope="row">Standard-Turniermodus</th><td>';
        echo '<select name="monatsblitz_default_mode_type" id="monatsblitz_default_mode_type">';
        echo '<option value="none" ' . selected($defaultModeType, 'none', false) . '>Keine automatische Vorauswahl</option>';
        echo '<option value="fixed" ' . selected($defaultModeType, 'fixed', false) . '>Fester Modus</option>';
        echo '<option value="rotation" ' . selected($defaultModeType, 'rotation', false) . '>Rotierender Modus</option>';
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Steuert die Vorauswahl bei neuen Turnieren in der App (wird auch im QR-Code übermittelt).</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr id="row_monatsblitz_default_fixed_mode"><th scope="row">Fester Standardmodus</th><td>';
        echo '<select name="monatsblitz_default_fixed_mode">';
        echo '<option value="">— Bitte wählen —</option>';
        foreach ($modes as $m) {
            $selected = selected($defaultFixedMode, $m['name'], false);
            echo "<option value='" . esc_attr($m['name']) . "' $selected>" . esc_html($m['name']) . "</option>";
        }
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Wird verwendet, wenn oben <em>Fester Modus</em> gewählt ist.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr id="row_monatsblitz_default_rotation_modes"><th scope="row">Modus-Rotation</th><td>';
        echo '<input type="hidden" name="monatsblitz_default_rotation_modes[]" value="">';
        if (empty($modes)) {
            echo '<p class="description">Keine Modi im Katalog vorhanden. Bitte lege im Turniermodi-Katalog oben Modi an.</p>';
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
        echo self::TAG_ROW_CLOSE;

        echo '<script type="text/javascript">
        (function() {
            function updateModePreselection() {
                var select = document.getElementById("monatsblitz_default_mode_type");
                var fixedRow = document.getElementById("row_monatsblitz_default_fixed_mode");
                var rotRow = document.getElementById("row_monatsblitz_default_rotation_modes");
                if (!select || !fixedRow || !rotRow) return;
                var mode = select.value;
                fixedRow.style.display = (mode === "fixed") ? "" : "none";
                rotRow.style.display = (mode === "rotation") ? "" : "none";
            }
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", updateModePreselection);
            } else {
                updateModePreselection();
            }
            var select = document.getElementById("monatsblitz_default_mode_type");
            if (select) {
                select.addEventListener("change", updateModePreselection);
            }
        })();
        </script>';
    }
}
