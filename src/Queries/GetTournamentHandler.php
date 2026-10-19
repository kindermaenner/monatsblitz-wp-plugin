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

        if (!isset($result['status'])) {
            $result['status'] = 'open';
        }
        if (isset($result['year'], $result['month'], $result['day']) && !isset($result['date'])) {
            $result['date'] = sprintf('%04d-%02d-%02d', (int)$result['year'], (int)$result['month'], (int)$result['day']);
        }

        $players_table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $player_rows = method_exists($wpdb, 'get_results')
            ? $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT player_id FROM {$players_table} WHERE tournament_id = %d ORDER BY id ASC",
                    $tournament_id
                ),
                ARRAY_A
            )
            : [];

        $player_ids = [];
        if (is_array($player_rows)) {
            foreach ($player_rows as $row) {
                $pid = is_array($row) ? intval($row['player_id'] ?? 0) : intval($row->player_id ?? 0);
                if ($pid > 0) {
                    $player_ids[] = $pid;
                }
            }
        }
        $result['player_ids'] = $player_ids;

        return $result;
    }
}
