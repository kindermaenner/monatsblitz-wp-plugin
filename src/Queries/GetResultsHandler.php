<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetResultsHandler
{
    public function handle($request): array
    {
        global $wpdb;

        $tournament_id = intval($request['tournament_id']);
        $results_table = $wpdb->prefix . 'monatsblitz_results';
        $players_table = $wpdb->prefix . 'monatsblitz_players';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    r.id,
                    r.tournament_id,
                    r.player_id,
                    r.points,
                    r.rank,
                    p.forename,
                    p.surname
                FROM $results_table r
                LEFT JOIN $players_table p ON r.player_id = p.id
                WHERE r.tournament_id = %d
                ORDER BY r.rank ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id'            => (int) ($row['id'] ?? 0),
                'tournament_id' => (int) ($row['tournament_id'] ?? 0),
                'player_id'     => (int) ($row['player_id'] ?? 0),
                'points'        => (float) ($row['points'] ?? 0.0),
                'rank'          => (int) ($row['rank'] ?? 0),
                'forename'      => (string) ($row['forename'] ?? ''),
                'surname'       => (string) ($row['surname'] ?? ''),
            ];
        }, $results);
    }
}
