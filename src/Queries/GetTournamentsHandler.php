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

        if (!is_array($results)) {
            return [];
        }

        $formatted = [];
        foreach ($results as $row) {
            $year = (int) $row['year'];
            $month = (int) $row['month'];
            $day = (int) $row['day'];
            $date = $row['date'] ?? sprintf('%04d-%02d-%02d', $year, $month, $day);

            $formatted[] = [
                'id'             => (int) $row['id'],
                'year'           => $year,
                'month'          => $month,
                'day'            => $day,
                'mode'           => (string) ($row['mode'] ?? ''),
                'round_count'    => (int) ($row['round_count'] ?? 1),
                'status'         => (string) ($row['status'] ?? 'open'),
                'date'           => $date,
                'date_formatted' => (string) ($row['date_formatted'] ?? sprintf('%d.%d.%04d', $day, $month, $year)),
            ];
        }

        return $formatted;
    }
}
