<?php

declare(strict_types=1);

namespace Monatsblitz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class TableDetailPage
{
    private const TAG_SELECT_CLOSE = '</select>';
    private const TAG_TD_CLOSE = '</td>';

    public static function render(string $tableKey, string $table): void
    {
        echo "<h2>Detailansicht: $table</h2>";

        $back = admin_url('admin.php?page=monatsblitz-tables');
        echo "<p><a href='$back'>← Zurück</a></p>";

        $filterTournamentId = self::handle_tournament_filter($tableKey);
        $rows = self::fetch_table_rows($tableKey, $table, $filterTournamentId);

        if (empty($rows)) {
            echo "<p>Keine Einträge vorhanden.</p>";
            return;
        }

        self::render_rows_table($rows);
    }

    private static function handle_tournament_filter(string $tableKey): int|string|null
    {
        if (!in_array($tableKey, ['games', 'results', 'tournament_players'], true)) {
            return null;
        }

        global $wpdb;
        $tournamentsTable = $wpdb->prefix . 'monatsblitz_tournaments';
        $tournaments = $wpdb->get_results(
            "SELECT id, year, month, day, mode, status FROM {$tournamentsTable} ORDER BY id DESC"
        );
        $tournamentsList = is_array($tournaments) ? $tournaments : [];

        $openTournamentId = self::find_open_tournament_id($tournamentsList);
        $filterTournamentId = self::get_selected_tournament_filter($openTournamentId);

        self::render_tournament_filter_form($tableKey, $tournamentsList, $filterTournamentId);

        return $filterTournamentId;
    }

    private static function find_open_tournament_id(array $tournaments): ?int
    {
        foreach ($tournaments as $t) {
            $status = is_object($t) ? ($t->status ?? '') : ($t['status'] ?? '');
            if ($status === 'open') {
                return is_object($t) ? (int)$t->id : (int)$t['id'];
            }
        }
        return null;
    }

    private static function get_selected_tournament_filter(?int $openTournamentId): int|string
    {
        if (isset($_GET['tournament_id'])) {
            return $_GET['tournament_id'] === 'all' ? 'all' : (int)$_GET['tournament_id'];
        }
        return $openTournamentId ?? 'all';
    }

    private static function render_tournament_filter_form(string $tableKey, array $tournaments, int|string $filterTournamentId): void
    {
        $currentPageUrl = admin_url('admin.php');
        echo '<form method="get" action="' . esc_url($currentPageUrl) . '" style="margin: 15px 0; display:flex; align-items:center; gap:10px;">';
        echo '<input type="hidden" name="page" value="monatsblitz-tables">';
        echo '<input type="hidden" name="table" value="' . esc_attr($tableKey) . '">';
        echo '<label for="mb_filter_tournament"><strong>Turnier filtern:</strong></label>';
        echo '<select name="tournament_id" id="mb_filter_tournament" onchange="this.form.submit()">';
        echo '<option value="all"' . selected($filterTournamentId, 'all', false) . '>— Alle Turniere (neueste zuerst) —</option>';
        foreach ($tournaments as $t) {
            self::render_tournament_filter_option($t, $filterTournamentId);
        }
        echo self::TAG_SELECT_CLOSE;
        echo '<noscript><input type="submit" class="button" value="Filtern"></noscript>';
        echo '</form>';
    }

    private static function render_tournament_filter_option(object|array $t, int|string $filterTournamentId): void
    {
        $tId = is_object($t) ? (int)$t->id : (int)$t['id'];
        $tYear = is_object($t) ? (int)$t->year : (int)$t['year'];
        $tMonth = is_object($t) ? (int)$t->month : (int)$t['month'];
        $tDay = is_object($t) ? (int)$t->day : (int)$t['day'];
        $tMode = is_object($t) ? ($t->mode ?? '') : ($t['mode'] ?? '');
        $tStatus = is_object($t) ? ($t->status ?? '') : ($t['status'] ?? '');
        $isOpen = $tStatus === 'open';
        $label = sprintf('%04d-%02d-%02d (%s)%s', $tYear, $tMonth, $tDay, $tMode, $isOpen ? ' [OFFEN]' : '');
        echo '<option value="' . $tId . '"' . selected($filterTournamentId, $tId, false) . '>' . esc_html($label) . '</option>';
    }

    private static function fetch_table_rows(string $tableKey, string $table, int|string|null $filterTournamentId): array
    {
        global $wpdb;
        $playersTable = $wpdb->prefix . 'monatsblitz_players';

        return match ($tableKey) {
            'players' => (array) $wpdb->get_results("SELECT id, forename, surname FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A),
            'tournaments' => (array) $wpdb->get_results("SELECT id, year, month, day, mode, round_count, status FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A),
            'tournament_players' => self::fetch_tournament_players($table, $playersTable, $filterTournamentId),
            'games' => self::fetch_games($table, $playersTable, $filterTournamentId),
            'results' => self::fetch_results($table, $playersTable, $filterTournamentId),
            default => (array) $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A),
        };
    }

    private static function fetch_tournament_players(string $table, string $playersTable, int|string|null $filterTournamentId): array
    {
        global $wpdb;
        if ($filterTournamentId !== null && $filterTournamentId !== 'all') {
            $query = $wpdb->prepare(
                "SELECT tp.id, tp.tournament_id, tp.player_id,
                        COALESCE(CONCAT(p.surname, ', ', p.forename), '-') AS player_name
                 FROM {$table} tp
                 LEFT JOIN {$playersTable} p ON p.id = tp.player_id
                 WHERE tp.tournament_id = %d
                 ORDER BY tp.id DESC",
                (int)$filterTournamentId
            );
        } else {
            $query = "SELECT tp.id, tp.tournament_id, tp.player_id,
                             COALESCE(CONCAT(p.surname, ', ', p.forename), '-') AS player_name
                      FROM {$table} tp
                      LEFT JOIN {$playersTable} p ON p.id = tp.player_id
                      ORDER BY tp.id DESC LIMIT 100";
        }
        return (array) $wpdb->get_results($query, ARRAY_A);
    }

    private static function fetch_games(string $table, string $playersTable, int|string|null $filterTournamentId): array
    {
        global $wpdb;
        if ($filterTournamentId !== null && $filterTournamentId !== 'all') {
            $query = $wpdb->prepare(
                "SELECT g.id, g.tournament_id,
                        g.player1_id, COALESCE(CONCAT(p1.surname, ', ', p1.forename), '-') AS player1_name,
                        g.player2_id, COALESCE(CONCAT(p2.surname, ', ', p2.forename), '-') AS player2_name,
                        g.leg_type, g.result
                 FROM {$table} g
                 LEFT JOIN {$playersTable} p1 ON p1.id = g.player1_id
                 LEFT JOIN {$playersTable} p2 ON p2.id = g.player2_id
                 WHERE g.tournament_id = %d
                 ORDER BY g.id DESC",
                (int)$filterTournamentId
            );
        } else {
            $query = "SELECT g.id, g.tournament_id,
                             g.player1_id, COALESCE(CONCAT(p1.surname, ', ', p1.forename), '-') AS player1_name,
                             g.player2_id, COALESCE(CONCAT(p2.surname, ', ', p2.forename), '-') AS player2_name,
                             g.leg_type, g.result
                      FROM {$table} g
                      LEFT JOIN {$playersTable} p1 ON p1.id = g.player1_id
                      LEFT JOIN {$playersTable} p2 ON p2.id = g.player2_id
                      ORDER BY g.id DESC LIMIT 100";
        }
        return (array) $wpdb->get_results($query, ARRAY_A);
    }

    private static function fetch_results(string $table, string $playersTable, int|string|null $filterTournamentId): array
    {
        global $wpdb;
        if ($filterTournamentId !== null && $filterTournamentId !== 'all') {
            $query = $wpdb->prepare(
                "SELECT r.id, r.tournament_id,
                        r.player_id, COALESCE(CONCAT(p.surname, ', ', p.forename), '-') AS player_name,
                        r.points, r.rank
                 FROM {$table} r
                 LEFT JOIN {$playersTable} p ON p.id = r.player_id
                 WHERE r.tournament_id = %d
                 ORDER BY r.rank ASC, r.id DESC",
                (int)$filterTournamentId
            );
        } else {
            $query = "SELECT r.id, r.tournament_id,
                             r.player_id, COALESCE(CONCAT(p.surname, ', ', p.forename), '-') AS player_name,
                             r.points, r.rank
                       FROM {$table} r
                       LEFT JOIN {$playersTable} p ON p.id = r.player_id
                       ORDER BY r.rank ASC, r.id DESC LIMIT 100";
        }
        return (array) $wpdb->get_results($query, ARRAY_A);
    }

    private static function render_rows_table(array $rows): void
    {
        echo '<p style="color:#666; font-size:13px; margin: 10px 0;">Einträge: ' . count($rows) . '</p>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';

        foreach (array_keys($rows[0]) as $column) {
            echo '<th>' . esc_html($column) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . esc_html((string)($value ?? '')) . self::TAG_TD_CLOSE;
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
