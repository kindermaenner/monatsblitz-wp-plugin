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
                status,
                CONCAT(year, '-', LPAD(month, 2, '0'), '-', LPAD(day, 2, '0')) AS date,
                CONCAT(day, '.', month, '.', year) AS date_formatted
             FROM $table
             ORDER BY year DESC, month DESC, day DESC",
            ARRAY_A
        );

        if (is_array($results)) {
            foreach ($results as &$row) {
                if (!isset($row['status'])) {
                    $row['status'] = 'open';
                }
                if (isset($row['year'], $row['month'], $row['day']) && !isset($row['date'])) {
                    $row['date'] = sprintf('%04d-%02d-%02d', (int)$row['year'], (int)$row['month'], (int)$row['day']);
                }
            }
        }

        return is_array($results) ? $results : [];
    }
}
