<?php

if (!defined('ABSPATH')) {
    exit;
}

class MB_Admin {

    public static function init() {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', function() {
            register_setting('monatsblitz_settings', 'monatsblitz_author');
            register_setting('monatsblitz_settings', 'monatsblitz_template');
            register_setting('monatsblitz_settings', 'monatsblitz_api_key');
        });
    }

    public static function register_menu() {
        add_menu_page(
            'Monatsblitz',
            'Monatsblitz',
            'manage_options',
            'monatsblitz',
            [self::class, 'render_page'],
            'dashicons-chart-bar',
            26
        );

        // 👉 Unterpunkt: Einstellungen
        add_submenu_page(
            'monatsblitz',
            'Monatsblitz Einstellungen',
            'Einstellungen',
            'manage_options',
            'monatsblitz-settings',
            [self::class, 'render_settings_page']
        );
    }

    public static function render_settings_page() { 

        // Aktuelle Werte laden
        $author    = get_option('monatsblitz_author', '');
        $template  = get_option('monatsblitz_template', '');
        $api_key   = get_option('monatsblitz_api_key', '');

        // Wenn "Neuen Key generieren" gedrückt wurde (eigenes Formular!)
        if (isset($_POST['monatsblitz_generate_key'])) {

            check_admin_referer('monatsblitz_generate_key_action');

            $new_key = wp_generate_password(32, false, false);
            update_option('monatsblitz_api_key', $new_key);
            $api_key = $new_key;

            echo '<div class="updated"><p>Neuer API‑Key wurde generiert.</p></div>';
        }

        // Userliste
        $users = get_users([
            'fields' => ['ID', 'user_login']
        ]);

        echo '<div class="wrap">';
        echo '<h1>Monatsblitz – Einstellungen</h1>';

        /*
        * FORMULAR 1: API‑Key generieren (NICHT über options.php)
        */
        echo '<form method="post">';
        wp_nonce_field('monatsblitz_generate_key_action');

        echo '<table class="form-table">';
        echo '<tr><th scope="row">API‑Key</th><td>';
        echo '<input type="text" value="' . esc_attr($api_key) . '" class="regular-text" style="width:350px;" readonly>';
        echo '<p class="description">Diesen Key muss die Blitzabend‑App im Header <code>X-MB-Key</code> mitsenden.</p>';
        echo '<p><input type="submit" name="monatsblitz_generate_key" class="button" value="Neuen API‑Key generieren"></p>';
        echo '</td></tr>';
        echo '</table>';

        echo '</form>';

        /*
        * FORMULAR 2: Normale Einstellungen (läuft über options.php)
        */
        echo '<form method="post" action="options.php">';
        settings_fields('monatsblitz_settings');

        echo '<table class="form-table">';

        // API‑Key auch hier einfügen, damit WordPress ihn speichert
        echo '<tr><th scope="row">API‑Key (gespeichert)</th><td>';
        echo '<input type="text" name="monatsblitz_api_key" value="' . esc_attr($api_key) . '" class="regular-text" style="width:350px;" readonly>';
        echo '<p class="description">Wird automatisch aktualisiert, wenn ein neuer Key generiert wird.</p>';
        echo '</td></tr>';

        // Autor-Auswahl
        echo '<tr><th scope="row">Autor des Beitrags</th><td>';
        echo '<select name="monatsblitz_author">';
        echo '<option value="">— Bitte wählen —</option>';

        foreach ($users as $u) {
            $selected = selected($author, $u->ID, false);
            echo "<option value='{$u->ID}' $selected>{$u->user_login}</option>";
        }

        echo '</select>';
        echo '</td></tr>';

        // Template-Name
        echo '<tr><th scope="row">Template‑Name</th><td>';
        echo '<input type="text" name="monatsblitz_template" value="' . esc_attr($template) . '" class="regular-text">';
        echo '</td></tr>';

        echo '</table>';

        submit_button();
        echo '</form>';

        echo '</div>';
    }

    public static function render_page() {
        global $wpdb;

        // 👉 Reset-Action verarbeiten
        self::handle_actions();

        $tables = [
            'players'     => $wpdb->prefix . 'monatsblitz_players',
            'tournaments' => $wpdb->prefix . 'monatsblitz_tournaments',
            'games'       => $wpdb->prefix . 'monatsblitz_games',
            'results'     => $wpdb->prefix . 'monatsblitz_results',
        ];

        echo '<div class="wrap">';
        echo '<h1>Monatsblitz Übersicht</h1>';

        // 👉 Reset-Button
        echo '<form method="post" style="margin-bottom:20px;">';
        wp_nonce_field('monatsblitz_reset_tables');
        echo '<input type="hidden" name="monatsblitz_action" value="reset_tables">';
        echo '<input type="submit" class="button button-danger" value="⚠️ Tabellen neu erstellen" 
              onclick="return confirm(\'Wirklich ALLE Tabellen löschen und neu erstellen?\');">';
        echo '</form>';

        // 👉 Wenn Tabelle ausgewählt → Detailansicht
        if (isset($_GET['table']) && isset($tables[$_GET['table']])) {
            self::render_table_detail($tables[$_GET['table']], $_GET['table']);
            echo '</div>';
            return;
        }

        // 👉 Übersicht
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Tabelle</th><th>Status</th><th>Einträge</th></tr></thead>';
        echo '<tbody>';

        foreach ($tables as $key => $table) {

            $exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table)
            );

            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                $status = '✅ vorhanden';

                $link = admin_url('admin.php?page=monatsblitz&table=' . $key);
                $label = "<a href='$link'><strong>$table</strong></a>";

            } else {
                $count = '-';
                $status = '❌ fehlt';
                $label = $table;
            }

            echo "<tr>
                    <td>$label</td>
                    <td>$status</td>
                    <td>$count</td>
                  </tr>";
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    private static function handle_actions() {

        if (
            isset($_POST['monatsblitz_action']) &&
            $_POST['monatsblitz_action'] === 'reset_tables'
        ) {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce($_POST['_wpnonce'], 'monatsblitz_reset_tables')
            ) {
                echo '<div class="error"><p>Security check failed</p></div>';
                return;
            }

            self::reset_tables();

            echo '<div class="updated"><p>Tabellen wurden neu erstellt</p></div>';
        }
    }

    private static function reset_tables() {
        global $wpdb;

        $prefix = $wpdb->prefix . 'monatsblitz_';

        $tables = [
            $prefix . 'games',
            $prefix . 'results',
            $prefix . 'tournaments',
            $prefix . 'players',
        ];

        // FK Checks aus
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // FK Checks an
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');

        // Tabellen neu erstellen
        MB_Database::reset_tables();
    }

    private static function render_table_detail($table, $key) {
        global $wpdb;

        echo "<h2>Detailansicht: $table</h2>";

        // Zurück-Link
        $back = admin_url('admin.php?page=monatsblitz');
        echo "<p><a href='$back'>← Zurück</a></p>";

        // Daten holen
        $rows = $wpdb->get_results("SELECT * FROM $table LIMIT 100", ARRAY_A);

        if (empty($rows)) {
            echo "<p>Keine Einträge vorhanden.</p>";
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';

        foreach (array_keys($rows[0]) as $column) {
            echo "<th>$column</th>";
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . esc_html($value) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}