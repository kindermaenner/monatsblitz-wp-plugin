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

        return $results;
    }
}
