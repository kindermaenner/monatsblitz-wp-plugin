<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class CreateTournamentHandler
{
    public function handle($request)
    {
        global $wpdb;

        $params = $request->get_json_params();

        if (empty($params['date'])) {
            return new \WP_Error('invalid_data', 'Date is required', ['status' => 400]);
        }

        // 📅 Datum zerlegen
        $date = $params['date'];
        $parts = explode('-', $date);

        if (count($parts) !== 3) {
            return new \WP_Error('invalid_date', 'Invalid date format', ['status' => 400]);
        }

        list($year, $month, $day) = $parts;

        $mode        = sanitize_text_field($params['mode'] ?? '');
        $round_count = intval($params['round_count'] ?? 1);

        if ($mode === '') {
            return new \WP_Error('invalid_data', 'Mode is required', ['status' => 400]);
        }

        if ($round_count < 1) {
            return new \WP_Error('invalid_data', 'round_count must be >= 1', ['status' => 400]);
        }

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $wpdb->insert(
            $table,
            [
                'year'        => (int)$year,
                'month'       => (int)$month,
                'day'         => (int)$day,
                'mode'        => $mode,
                'round_count' => $round_count
            ],
            ['%d', '%d', '%d', '%s', '%d']
        );

        return [
            'success'        => true,
            'tournament_id'  => $wpdb->insert_id
        ];
    }
}