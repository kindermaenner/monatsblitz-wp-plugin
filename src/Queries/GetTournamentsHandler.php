<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetTournamentsHandler
{
    public function handle(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $results = $wpdb->get_results(
            "SELECT 
                id,
                year,
                month,
                day,
                mode,
                round_count,
                CONCAT(day, '.', month, '.', year) AS date_formatted
             FROM $table
             ORDER BY year DESC, month DESC, day DESC",
            ARRAY_A
        );

        return $results;
    }
}
