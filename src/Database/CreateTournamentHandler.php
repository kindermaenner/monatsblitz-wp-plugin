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
        $dateParts = $this->parseDate($date);
        if ($dateParts instanceof \WP_Error) {
            return $dateParts;
        }

        $settings = $this->parseTournamentSettings($params);
        if ($settings instanceof \WP_Error) {
            return $settings;
        }

        $tournamentData = array_merge($dateParts, [
            'mode'        => $settings['mode'],
            'round_count' => $settings['round_count'],
            'status'      => 'open',
        ]);

        return $this->createTournament($wpdb, $date, $tournamentData, $settings['player_ids']);
    }

    private function parseDate($date)
    {
        if (!is_string($date)) {
            return new \WP_Error('invalid_date', 'Invalid date format', ['status' => 400]);
        }

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

        $dateParts = [
            'year' => (int) $year,
            'month' => (int) $month,
            'day' => (int) $day,
        ];

        if (!checkdate($dateParts['month'], $dateParts['day'], $dateParts['year'])) {
            return new \WP_Error(
                'invalid_date',
                'Invalid calendar date',
                [
                    'status' => 400,
                    'developer_message' => 'checkdate failed for provided date.',
                    'input_date' => $date,
                    'parsed' => $dateParts,
                ]
            );
        }

        return $dateParts;
    }

    private function parseTournamentSettings(array $params)
    {
        $mode = sanitize_text_field($params['mode'] ?? '');
        if ($mode === '') {
            return new \WP_Error('invalid_data', 'Mode is required', ['status' => 400]);
        }

        $roundCount = intval($params['round_count'] ?? 1);
        if ($roundCount < 1) {
            return new \WP_Error('invalid_data', 'round_count must be >= 1', ['status' => 400]);
        }

        return [
            'mode'        => $mode,
            'round_count' => $roundCount,
            'player_ids'  => $this->extractPlayerIds($params['player_ids'] ?? []),
        ];
    }

    private function createTournament($wpdb, string $date, array $tournamentData, array $playerIds)
    {
        $table = $wpdb->prefix . 'monatsblitz_tournaments';
        $insertResult = $wpdb->insert(
            $table,
            $tournamentData,
            ['%d', '%d', '%d', '%s', '%d', '%s']
        );

        if ($insertResult === false) {
            return $this->handleInsertFailure($wpdb, $table, $date, $tournamentData, $playerIds);
        }

        $insertId = (int) $wpdb->insert_id;
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

        $this->insertTournamentPlayers($wpdb, $insertId, $playerIds);

        return $this->tournamentSuccess($insertId);
    }

    private function handleInsertFailure($wpdb, string $table, string $date, array $tournamentData, array $playerIds)
    {
        $dbError = $wpdb->last_error;
        if (stripos($dbError, 'Duplicate entry') !== false) {
            $existingId = $this->findOpenTournamentId($wpdb, $table, $tournamentData);
            if ($existingId !== null) {
                $this->resetExistingTournament($wpdb, $table, $existingId, $tournamentData, $playerIds);

                return $this->tournamentSuccess($existingId);
            }

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
                'payload' => $tournamentData,
            ]
        );
    }

    private function findOpenTournamentId($wpdb, string $table, array $tournamentData): ?int
    {
        if (!method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM {$table} WHERE year = %d AND month = %d AND day = %d",
                $tournamentData['year'],
                $tournamentData['month'],
                $tournamentData['day']
            )
        );

        if (!$existing || !isset($existing->id) || ($existing->status ?? '') !== 'open') {
            return null;
        }

        return (int) $existing->id;
    }

    private function resetExistingTournament($wpdb, string $table, int $tournamentId, array $tournamentData, array $playerIds): void
    {
        if (method_exists($wpdb, 'update')) {
            $wpdb->update(
                $table,
                [
                    'mode'        => $tournamentData['mode'],
                    'round_count' => $tournamentData['round_count'],
                ],
                ['id' => $tournamentId],
                ['%s', '%d'],
                ['%d']
            );
        }

        if (method_exists($wpdb, 'delete')) {
            $wpdb->delete($wpdb->prefix . 'monatsblitz_tournament_players', ['tournament_id' => $tournamentId], ['%d']);
            $wpdb->delete($wpdb->prefix . 'monatsblitz_games', ['tournament_id' => $tournamentId], ['%d']);
            $wpdb->delete($wpdb->prefix . 'monatsblitz_results', ['tournament_id' => $tournamentId], ['%d']);
        }

        $this->insertTournamentPlayers($wpdb, $tournamentId, $playerIds);
    }

    private function tournamentSuccess(int $tournamentId): array
    {
        return [
            'success'       => true,
            'tournament_id' => $tournamentId,
        ];
    }

    /**
     * @param mixed $rawPlayerIds
     * @return int[]
     */
    private function extractPlayerIds($rawPlayerIds): array
    {
        if (!is_array($rawPlayerIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawPlayerIds as $rawId) {
            $id = intval($rawId);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param object $wpdb
     * @param int $tournamentId
     * @param int[] $playerIds
     */
    private function insertTournamentPlayers($wpdb, int $tournamentId, array $playerIds): void
    {
        if (empty($playerIds) || !method_exists($wpdb, 'insert')) {
            return;
        }

        $playersTable = $wpdb->prefix . 'monatsblitz_tournament_players';
        foreach ($playerIds as $playerId) {
            $wpdb->insert(
                $playersTable,
                [
                    'tournament_id' => $tournamentId,
                    'player_id'     => $playerId,
                ],
                ['%d', '%d']
            );
        }
    }
}
