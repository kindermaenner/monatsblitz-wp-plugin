<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetGamesHandler
{
    public function handle($request): array
    {
        global $wpdb;

        $tournament_id = intval($request['tournament_id']);
        $games_table   = $wpdb->prefix . 'monatsblitz_games';
        $players_table = $wpdb->prefix . 'monatsblitz_players';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    g.id,
                    g.tournament_id,
                    g.player1_id,
                    g.player2_id,
                    g.leg_type,
                    g.result,
                    p1.forename AS player1_forename,
                    p1.surname  AS player1_surname,
                    p2.forename AS player2_forename,
                    p2.surname  AS player2_surname
                FROM $games_table g
                LEFT JOIN $players_table p1 ON g.player1_id = p1.id
                LEFT JOIN $players_table p2 ON g.player2_id = p2.id
                WHERE g.tournament_id = %d
                ORDER BY g.id",
                $tournament_id
            ),
            ARRAY_A
        );

        return $results;
    }
}
