<?php

declare(strict_types=1);

namespace Monatsblitz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsFormRenderer
{
    private const TAG_SELECT_CLOSE = '</select>';
    private const TAG_TD_CLOSE = '</td>';
    private const TAG_ROW_CLOSE = '</td></tr>';
    private const TAG_FORM_CLOSE = '</form>';
    private const INPUT_REGULAR_CLASS = '" class="regular-text">';

    public static function render(array $config, array $users, array $modes): void
    {
        self::render_settings_form($config, $users, $modes);
        self::render_modes_catalog($modes);
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
        echo self::TAG_ROW_CLOSE;
        echo '</table>';
        echo self::TAG_FORM_CLOSE;

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
        echo self::TAG_FORM_CLOSE;
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
        string $templateStaticPage,
        bool $hideJanuaryOverview,
        string $blitzModes
    ): void {
        echo '<tr><th scope="row">Template-Name</th><td>';
        echo '<input type="text" name="monatsblitz_template" value="' . esc_attr($template) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Platzhalter: <code>{{month_name}}</code>, <code>{{year}}</code>, <code>{{date}}</code>, <code>{{mode}}</code>, <code>{{round_count}}</code>, <code>{{table}}</code>, <code>{{winner_name}}</code>, <code>{{winner_points}}</code>, <code>{{winner_games}}</code>, <code>{{winner_verb}}</code>, <code>{{winner_text}}</code>.<br>Singular/Plural: <code>{{plural:siegt|siegen}}</code> oder <code>{{#singular}}...{{/singular}}</code> / <code>{{#plural}}...{{/plural}}</code>.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Template: Statische Jahresseite</th><td>';
        echo '<input type="text" name="monatsblitz_template_static_page" value="' . esc_attr($templateStaticPage) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Enthalt Platzhalter {{blitz_monthly_overview}} und {{blitz_ranking_year}}.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Teilnehmer-Ubersicht: Januar ausblenden</th><td>';
        echo '<label>';
        echo '<input type="checkbox" name="monatsblitz_hide_january_overview" value="1" ' . ($hideJanuaryOverview ? 'checked="checked"' : '') . '>';
        echo ' Januar-Spalte in {{blitz_monthly_overview}} ausblenden';
        echo '</label>';
        echo '<p class="description">Empfohlen, wenn im Januar grundsatzlich kein Monatsblitz stattfindet.</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Zusatzliche Blitz-Modi</th><td>';
        echo '<input type="text" name="monatsblitz_blitz_modes" value="' . esc_attr($blitzModes) . self::INPUT_REGULAR_CLASS;
        echo '<p class="description">Komma-Liste, z. B. Handicap, Armageddon. Standardregel bleibt aktiv: alle Modi <= 5+X zahlen als Blitz.</p>';
        echo self::TAG_ROW_CLOSE;
    }

    private static function render_mode_preselection_settings(
        string $defaultModeType,
        string $defaultFixedMode,
        array $defaultRotationModes,
        array $modes
    ): void {
        echo '<tr><th scope="row">Standard-Turniermodus</th><td>';
        echo '<select name="monatsblitz_default_mode_type">';
        echo '<option value="none" ' . selected($defaultModeType, 'none', false) . '>Keine automatische Vorauswahl</option>';
        echo '<option value="fixed" ' . selected($defaultModeType, 'fixed', false) . '>Fester Modus</option>';
        echo '<option value="rotation" ' . selected($defaultModeType, 'rotation', false) . '>Rotierender Modus</option>';
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Steuert die Vorauswahl bei neuen Turnieren in der App (wird auch im QR-Code übermittelt).</p>';
        echo self::TAG_ROW_CLOSE;

        echo '<tr><th scope="row">Fester Standardmodus</th><td>';
        echo '<select name="monatsblitz_default_fixed_mode">';
        echo '<option value="">— Bitte wählen —</option>';
        foreach ($modes as $m) {
            $selected = selected($defaultFixedMode, $m['name'], false);
            echo "<option value='" . esc_attr($m['name']) . "' $selected>" . esc_html($m['name']) . "</option>";
        }
        echo self::TAG_SELECT_CLOSE;
        echo '<p class="description">Wird verwendet, wenn oben <em>Fester Modus</em> gewählt ist.</p>';
        echo self::TAG_ROW_CLOSE;

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
        echo self::TAG_ROW_CLOSE;
    }

    private static function render_modes_catalog(array $modes): void
    {
        echo '<h2 style="margin-top:30px;">⏱️ Turniermodi (Katalog)</h2>';
        echo '<p class="description">Diese Modi stehen in der App bei der Turniererstellung zur Auswahl und können für feste Vorgaben oder Rotationen verwendet werden.</p>';

        echo '<table class="widefat fixed striped" style="max-width:600px; margin-bottom:15px;">';
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
    }
}
