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

        $date = $params['date'];
        $parts = explode('-', $date);

        if (count($parts) !== 3) {
            return new \WP_Error('invalid_date', 'Invalid date format', ['status' => 400]);
        }

        list($year, $month, $day) = $parts;

        if (!ctype_digit($year) || !ctype_digit($month) || !ctype_digit($day)) {
            return new \WP_Error(
                'invalid_date',
                'Date must contain numeric year, month and day',
                [
                    'status' => 400,
                    'developer_message' => 'Expected date format YYYY-MM-DD with numeric parts only.',
                    'input_date' => $date,
                ]
            );
        }

        $yearInt = (int)$year;
        $monthInt = (int)$month;
        $dayInt = (int)$day;

        if (!checkdate($monthInt, $dayInt, $yearInt)) {
            return new \WP_Error(
                'invalid_date',
                'Invalid calendar date',
                [
                    'status' => 400,
                    'developer_message' => 'checkdate failed for provided date.',
                    'input_date' => $date,
                    'parsed' => [
                        'year' => $yearInt,
                        'month' => $monthInt,
                        'day' => $dayInt,
                    ],
                ]
            );
        }

        $mode        = sanitize_text_field($params['mode'] ?? '');
        $round_count = intval($params['round_count'] ?? 1);

        if ($mode === '') {
            return new \WP_Error('invalid_data', 'Mode is required', ['status' => 400]);
        }

        if ($round_count < 1) {
            return new \WP_Error('invalid_data', 'round_count must be >= 1', ['status' => 400]);
        }

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $insertResult = $wpdb->insert(
            $table,
            [
                'year'        => $yearInt,
                'month'       => $monthInt,
                'day'         => $dayInt,
                'mode'        => $mode,
                'round_count' => $round_count
            ],
            ['%d', '%d', '%d', '%s', '%d']
        );

        if ($insertResult === false) {
            $dbError = $wpdb->last_error;

            if (stripos($dbError, 'Duplicate entry') !== false) {
                return new \WP_Error(
                    'duplicate_tournament',
                    'Tournament for this date already exists',
                    [
                        'status' => 409,
                        'developer_message' => $dbError,
                        'input_date' => $date,
                        'table' => $table,
                    ]
                );
            }

            return new \WP_Error(
                'db_insert_failed',
                'Tournament could not be created',
                [
                    'status' => 500,
                    'developer_message' => $dbError !== ''
                        ? $dbError
                        : 'wpdb->insert returned false without last_error.',
                    'table' => $table,
                    'payload' => [
                        'year' => $yearInt,
                        'month' => $monthInt,
                        'day' => $dayInt,
                        'mode' => $mode,
                        'round_count' => $round_count,
                    ],
                ]
            );
        }

        $insertId = (int)$wpdb->insert_id;

        if ($insertId <= 0) {
            return new \WP_Error(
                'db_insert_invalid_id',
                'Tournament created without valid ID',
                [
                    'status' => 500,
                    'developer_message' => 'Insert reported success, but insert_id is missing or 0.',
                    'insert_result' => $insertResult,
                    'insert_id' => $insertId,
                    'table' => $table,
                ]
            );
        }

        return [
            'success'        => true,
            'tournament_id'  => $insertId
        ];
    }
}