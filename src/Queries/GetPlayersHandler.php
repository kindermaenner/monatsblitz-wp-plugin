<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetPlayersHandler
{
    public function handle(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_players';

        return $wpdb->get_results(
            "SELECT
                id,
                forename,
                surname
             FROM $table
             ORDER BY surname, forename",
            ARRAY_A
        );
    }
}
