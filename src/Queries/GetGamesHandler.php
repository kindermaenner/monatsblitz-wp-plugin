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

        if (!is_array($results)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id'               => (int) ($row['id'] ?? 0),
                'game_id'          => (int) ($row['id'] ?? 0),
                'tournament_id'    => (int) ($row['tournament_id'] ?? 0),
                'player1_id'       => (int) ($row['player1_id'] ?? 0),
                'player2_id'       => (int) ($row['player2_id'] ?? 0),
                'leg_type'         => (int) ($row['leg_type'] ?? 1),
                'result'           => (string) ($row['result'] ?? ''),
                'player1_forename' => (string) ($row['player1_forename'] ?? ''),
                'player1_surname'  => (string) ($row['player1_surname'] ?? ''),
                'player2_forename' => (string) ($row['player2_forename'] ?? ''),
                'player2_surname'  => (string) ($row['player2_surname'] ?? ''),
            ];
        }, $results);
    }
}
