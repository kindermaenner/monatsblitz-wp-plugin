<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetPlayersHandler
{
    public function handle(): array
    {
        global $wpdb;

        $players_table            = $wpdb->prefix . 'monatsblitz_players';
        $tournaments_table        = $wpdb->prefix . 'monatsblitz_tournaments';
        $tournament_players_table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $results_table            = $wpdb->prefix . 'monatsblitz_results';

        $results = $wpdb->get_results(
            "SELECT
                p.id,
                p.forename,
                p.surname,
                MAX(CONCAT(t.year, '-', LPAD(t.month, 2, '0'), '-', LPAD(t.day, 2, '0'))) AS last_participated
             FROM $players_table p
             LEFT JOIN (
                 SELECT tournament_id, player_id FROM $tournament_players_table
                 UNION
                 SELECT tournament_id, player_id FROM $results_table
             ) tp ON p.id = tp.player_id
             LEFT JOIN $tournaments_table t ON tp.tournament_id = t.id
             GROUP BY p.id, p.forename, p.surname
             ORDER BY p.surname, p.forename",
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id'                => (int) $row['id'],
                'forename'          => (string) ($row['forename'] ?? ''),
                'surname'           => (string) ($row['surname'] ?? ''),
                'last_participated' => !empty($row['last_participated']) ? (string) $row['last_participated'] : null,
            ];
        }, $results);
    }
}
