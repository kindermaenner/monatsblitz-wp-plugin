<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentRepository
{
    public function loadTournament(int $tournament_id)
    {
        global $wpdb;

        $t = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, year, month, day, mode, round_count
                 FROM {$wpdb->prefix}monatsblitz_tournaments
                 WHERE id = %d",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!$t) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        return $t;
    }

    public function loadTournamentResults(int $tournament_id)
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.player_id, r.points, r.rank, p.forename, p.surname
                 FROM {$wpdb->prefix}monatsblitz_results r
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p ON r.player_id = p.id
                 WHERE r.tournament_id = %d
                 ORDER BY r.rank ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return new \WP_Error('no_results', 'Keine Ergebnisse vorhanden', ['status' => 400]);
        }

        return $results;
    }

    public function loadTournamentGames(int $tournament_id): array
    {
        global $wpdb;

        $games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT g.player1_id, g.player2_id, g.leg_type, g.result,
                        p1.forename as p1_forename, p1.surname as p1_surname,
                        p2.forename as p2_forename, p2.surname as p2_surname
                 FROM {$wpdb->prefix}monatsblitz_games g
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p1 ON g.player1_id = p1.id
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p2 ON g.player2_id = p2.id
                 WHERE g.tournament_id = %d
                 ORDER BY g.id",
                $tournament_id
            ),
            ARRAY_A
        );

        return is_array($games) ? $games : [];
    }

    public function countWinnerGames(int $tournament_id, int $winner_player_id): int
    {
        global $wpdb;

        return intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->prefix}monatsblitz_games
                     WHERE tournament_id = %d
                       AND (player1_id = %d OR player2_id = %d)",
                    $tournament_id,
                    $winner_player_id,
                    $winner_player_id
                )
            )
        );
    }
}
