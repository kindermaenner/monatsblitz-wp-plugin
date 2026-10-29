<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetTournamentHandler
{
    public function handle($request)
    {
        global $wpdb;

        $tournament_id = intval($request['id']);
        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $result = $wpdb->get_row(
            $wpdb->prepare(
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
                 WHERE id = %d",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!$result) {
            return new \WP_Error(
                'not_found',
                'Turnier nicht gefunden',
                ['status' => 404]
            );
        }

        $year = (int) ($result['year'] ?? 0);
        $month = (int) ($result['month'] ?? 0);
        $day = (int) ($result['day'] ?? 0);
        $date = $result['date'] ?? sprintf('%04d-%02d-%02d', $year, $month, $day);

        [$player_ids, $withdrawn_player_ids] = $this->fetchTournamentPlayerIds($tournament_id);

        return [
            'id'                   => (int) ($result['id'] ?? 0),
            'year'                 => $year,
            'month'                => $month,
            'day'                  => $day,
            'mode'                 => (string) ($result['mode'] ?? ''),
            'round_count'          => (int) ($result['round_count'] ?? 1),
            'status'               => (string) ($result['status'] ?? 'open'),
            'date'                 => $date,
            'date_formatted'       => (string) ($result['date_formatted'] ?? sprintf('%d.%d.%04d', $day, $month, $year)),
            'player_ids'           => $player_ids,
            'withdrawn_player_ids' => $withdrawn_player_ids,
        ];
    }

    /**
     * @return array{0: array<int>, 1: array<int>}
     */
    private function fetchTournamentPlayerIds(int $tournament_id): array
    {
        global $wpdb;
        $players_table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $player_rows = method_exists($wpdb, 'get_results')
            ? $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT player_id, has_left FROM {$players_table} WHERE tournament_id = %d ORDER BY id ASC",
                    $tournament_id
                ),
                ARRAY_A
            )
            : [];

        $player_ids = [];
        $withdrawn_player_ids = [];
        if (is_array($player_rows)) {
            foreach ($player_rows as $row) {
                $pid = $this->extractRowField($row, 'player_id');
                $has_left = $this->extractRowField($row, 'has_left');
                if ($pid > 0) {
                    $player_ids[] = $pid;
                    if ($has_left === 1) {
                        $withdrawn_player_ids[] = $pid;
                    }
                }
            }
        }

        if (empty($player_ids) && method_exists($wpdb, 'get_results') && method_exists($wpdb, 'prepare')) {
            $games_table = $wpdb->prefix . 'monatsblitz_games';
            $game_players = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT player1_id AS pid FROM {$games_table} WHERE tournament_id = %d
                     UNION
                     SELECT DISTINCT player2_id AS pid FROM {$games_table} WHERE tournament_id = %d",
                    $tournament_id,
                    $tournament_id
                ),
                ARRAY_A
            );
            if (is_array($game_players)) {
                foreach ($game_players as $gp) {
                    $pid = intval($gp['pid'] ?? 0);
                    if ($pid > 0 && !in_array($pid, $player_ids, true)) {
                        $player_ids[] = $pid;
                    }
                }
            }
        }

        return [$player_ids, $withdrawn_player_ids];
    }

    private function extractRowField(mixed $row, string $field): int
    {
        if (is_array($row)) {
            return intval($row[$field] ?? 0);
        }

        if (is_object($row)) {
            return intval($row->{$field} ?? 0);
        }

        return 0;
    }
}
