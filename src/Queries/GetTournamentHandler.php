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

        return $result;
    }
}
