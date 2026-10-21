<?php

declare(strict_types=1);

namespace Monatsblitz\Queries;

class GetModesHandler
{
    public function handle(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_modes';

        $results = $wpdb->get_results(
            "SELECT id, name FROM {$table} ORDER BY id ASC",
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id'   => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
            ];
        }, $results);
    }
}
