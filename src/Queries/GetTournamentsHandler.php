<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetTournamentsHandler
{
    public function handle($request = null): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $whereClause = '';
        if ($request instanceof \WP_REST_Request) {
            $rankingFilter = $request->get_param('counts_for_ranking');
            if ($rankingFilter !== null) {
                $val = filter_var($rankingFilter, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $whereClause = $wpdb->prepare(" WHERE counts_for_ranking = %d", $val);
            }
        }

        $results = $wpdb->get_results(
            "SELECT
                id,
                year,
                month,
                day,
                daily_number,
                label,
                counts_for_ranking,
                start_time,
                mode,
                round_count,
                status,
                CONCAT(year, '-', LPAD(month, 2, '0'), '-', LPAD(day, 2, '0')) AS date,
                CONCAT(day, '.', month, '.', year) AS date_formatted
             FROM $table{$whereClause}
             ORDER BY year DESC, month DESC, day DESC, daily_number DESC, id DESC",
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        [$players_by_tournament, $withdrawn_by_tournament] = $this->fetchTournamentPlayerData();

        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = $this->formatTournament($row, $players_by_tournament, $withdrawn_by_tournament);
        }

        return $formatted;
    }

    /**
     * @return array{array<int, array<int>>, array<int, array<int>>}
     */
    private function fetchTournamentPlayerData(): array
    {
        global $wpdb;

        $tp_table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $players_by_tournament = [];
        $withdrawn_by_tournament = [];

        $tp_rows = method_exists($wpdb, 'get_results')
            ? $wpdb->get_results(
                "SELECT tournament_id, player_id, has_left FROM $tp_table ORDER BY id ASC",
                ARRAY_A
            )
            : [];

        $this->addRegisteredPlayers($tp_rows, $players_by_tournament, $withdrawn_by_tournament);

        $games_table = $wpdb->prefix . 'monatsblitz_games';
        $game_players = method_exists($wpdb, 'get_results')
            ? $wpdb->get_results(
                "SELECT DISTINCT tournament_id, player1_id AS pid FROM $games_table
                 UNION
                 SELECT DISTINCT tournament_id, player2_id AS pid FROM $games_table",
                ARRAY_A
            )
            : [];

        $this->addGamePlayers($game_players, $players_by_tournament);

        return [$players_by_tournament, $withdrawn_by_tournament];
    }

    private function addRegisteredPlayers(mixed $rows, array &$players_by_tournament, array &$withdrawn_by_tournament): void
    {
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $tid = (int) ($row['tournament_id'] ?? 0);
            $pid = (int) ($row['player_id'] ?? 0);
            $left = (int) ($row['has_left'] ?? 0);
            if ($tid <= 0 || $pid <= 0) {
                continue;
            }

            $players_by_tournament[$tid][] = $pid;
            if ($left === 1) {
                $withdrawn_by_tournament[$tid][] = $pid;
            }
        }
    }

    private function addGamePlayers(mixed $game_players, array &$players_by_tournament): void
    {
        if (!is_array($game_players)) {
            return;
        }

        foreach ($game_players as $game_player) {
            $tid = (int) ($game_player['tournament_id'] ?? 0);
            $pid = (int) ($game_player['pid'] ?? 0);
            if ($tid <= 0 || $pid <= 0) {
                continue;
            }

            if (!isset($players_by_tournament[$tid])) {
                $players_by_tournament[$tid] = [];
            }
            if (!in_array($pid, $players_by_tournament[$tid], true)) {
                $players_by_tournament[$tid][] = $pid;
            }
        }
    }

    private function formatTournament(array $row, array $players_by_tournament, array $withdrawn_by_tournament): array
    {
        $id = (int) $row['id'];
        $year = (int) $row['year'];
        $month = (int) $row['month'];
        $day = (int) $row['day'];
        $date = $row['date'] ?? sprintf('%04d-%02d-%02d', $year, $month, $day);

        return [
            'id'                   => $id,
            'year'                 => $year,
            'month'                => $month,
            'day'                  => $day,
            'daily_number'         => (int) ($row['daily_number'] ?? 1),
            'label'                => (string) ($row['label'] ?? ''),
            'counts_for_ranking'   => !isset($row['counts_for_ranking']) || (bool) $row['counts_for_ranking'],
            'start_time'           => $row['start_time'] ?? null,
            'mode'                 => (string) ($row['mode'] ?? ''),
            'round_count'          => (int) ($row['round_count'] ?? 1),
            'status'               => (string) ($row['status'] ?? 'open'),
            'date'                 => $date,
            'date_formatted'       => (string) ($row['date_formatted'] ?? sprintf('%d.%d.%04d', $day, $month, $year)),
            'player_ids'           => $players_by_tournament[$id] ?? [],
            'withdrawn_player_ids' => $withdrawn_by_tournament[$id] ?? [],
        ];
    }
}
