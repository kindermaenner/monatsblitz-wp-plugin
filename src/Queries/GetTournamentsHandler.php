<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetTournamentsHandler
{
    public function handle(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $results = $wpdb->get_results(
            "SELECT
                id,
                year,
                month,
                day,
                mode,
                round_count,
                status,
                CONCAT(year, '-', LPAD(month, 2, '0'), '-', LPAD(day, 2, '0')) AS date,
                CONCAT(day, '.', month, '.', year) AS date_formatted
             FROM $table
             ORDER BY year DESC, month DESC, day DESC",
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        $tp_table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $players_by_tournament = [];
        $withdrawn_by_tournament = [];

        $tp_rows = method_exists($wpdb, 'get_results')
            ? $wpdb->get_results(
                "SELECT tournament_id, player_id, has_left FROM $tp_table ORDER BY id ASC",
                ARRAY_A
            )
            : [];

        if (is_array($tp_rows)) {
            foreach ($tp_rows as $row) {
                $tid = (int) ($row['tournament_id'] ?? 0);
                $pid = (int) ($row['player_id'] ?? 0);
                $left = (int) ($row['has_left'] ?? 0);
                if ($tid > 0 && $pid > 0) {
                    $players_by_tournament[$tid][] = $pid;
                    if ($left === 1) {
                        $withdrawn_by_tournament[$tid][] = $pid;
                    }
                }
            }
        }

        $games_table = $wpdb->prefix . 'monatsblitz_games';
        $game_players = method_exists($wpdb, 'get_results')
            ? $wpdb->get_results(
                "SELECT DISTINCT tournament_id, player1_id AS pid FROM $games_table
                 UNION
                 SELECT DISTINCT tournament_id, player2_id AS pid FROM $games_table",
                ARRAY_A
            )
            : [];

        if (is_array($game_players)) {
            foreach ($game_players as $gp) {
                $tid = (int) ($gp['tournament_id'] ?? 0);
                $pid = (int) ($gp['pid'] ?? 0);
                if ($tid > 0 && $pid > 0) {
                    if (!isset($players_by_tournament[$tid])) {
                        $players_by_tournament[$tid] = [];
                    }
                    if (!in_array($pid, $players_by_tournament[$tid], true)) {
                        $players_by_tournament[$tid][] = $pid;
                    }
                }
            }
        }

        $formatted = [];
        foreach ($results as $row) {
            $id = (int) $row['id'];
            $year = (int) $row['year'];
            $month = (int) $row['month'];
            $day = (int) $row['day'];
            $date = $row['date'] ?? sprintf('%04d-%02d-%02d', $year, $month, $day);

            $formatted[] = [
                'id'                   => $id,
                'year'                 => $year,
                'month'                => $month,
                'day'                  => $day,
                'mode'                 => (string) ($row['mode'] ?? ''),
                'round_count'          => (int) ($row['round_count'] ?? 1),
                'status'               => (string) ($row['status'] ?? 'open'),
                'date'                 => $date,
                'date_formatted'       => (string) ($row['date_formatted'] ?? sprintf('%d.%d.%04d', $day, $month, $year)),
                'player_ids'           => $players_by_tournament[$id] ?? [],
                'withdrawn_player_ids' => $withdrawn_by_tournament[$id] ?? [],
            ];
        }

        return $formatted;
    }
}
