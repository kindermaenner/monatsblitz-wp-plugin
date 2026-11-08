<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class TournamentRepository
{
    /**
     * @param int[] $playerIds
     * @return array|\WP_Error
     */
    public function create(string $date, array $tournamentData, array $playerIds, bool $confirmParallel = false)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        if (!$confirmParallel && empty($tournamentData['daily_number'])) {
            $openTournament = $this->findOpenTournament($wpdb, $table, $tournamentData);
            if ($openTournament !== null) {
                return new \WP_Error(
                    'open_tournament_exists',
                    'An open tournament already exists for this date. Confirm to create a parallel tournament.',
                    [
                        'status' => 409,
                        'open_tournament_id' => (int) $openTournament->id,
                        'input_date' => $date,
                    ]
                );
            }
        }

        if (empty($tournamentData['daily_number'])) {
            $tournamentData['daily_number'] = $this->getNextDailyNumber($wpdb, $table, $tournamentData);
        }

        if (empty($tournamentData['start_time'])) {
            $tournamentData['start_time'] = function_exists('current_time')
                ? current_time('H:i:s')
                : date('H:i:s');
        }

        $rowToInsert = [
            'year'               => (int) $tournamentData['year'],
            'month'              => (int) $tournamentData['month'],
            'day'                => (int) $tournamentData['day'],
            'daily_number'       => (int) $tournamentData['daily_number'],
            'label'              => (string) ($tournamentData['label'] ?? ''),
            'counts_for_ranking' => (int) ($tournamentData['counts_for_ranking'] ?? 1),
            'start_time'         => $tournamentData['start_time'],
            'mode'               => (string) $tournamentData['mode'],
            'round_count'        => (int) $tournamentData['round_count'],
            'status'             => (string) ($tournamentData['status'] ?? 'open'),
        ];

        $insertResult = $wpdb->insert(
            $table,
            $rowToInsert,
            ['%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s']
        );

        if ($insertResult === false) {
            return $this->handleInsertFailure($wpdb, $table, $date, $rowToInsert, $playerIds);
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

    /**
     * @param object $wpdb
     * @param int[] $playerIds
     * @return array|\WP_Error
     */
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

    private function findOpenTournament($wpdb, string $table, array $tournamentData): ?object
    {
        if (!method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, daily_number, label, mode, status FROM {$table} WHERE year = %d AND month = %d AND day = %d AND status = 'open' LIMIT 1",
                $tournamentData['year'],
                $tournamentData['month'],
                $tournamentData['day']
            )
        );

        if (!$row || !isset($row->id)) {
            return null;
        }

        return $row;
    }

    private function getNextDailyNumber($wpdb, string $table, array $tournamentData): int
    {
        if (!method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_var')) {
            return 1;
        }

        $max = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(daily_number), 0) FROM {$table} WHERE year = %d AND month = %d AND day = %d",
                $tournamentData['year'],
                $tournamentData['month'],
                $tournamentData['day']
            )
        );

        return max(1, (int)$max + 1);
    }

    private function findOpenTournamentId($wpdb, string $table, array $tournamentData): ?int
    {
        if (!method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $dailyNumber = (int) ($tournamentData['daily_number'] ?? 1);
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM {$table} WHERE year = %d AND month = %d AND day = %d AND daily_number = %d",
                $tournamentData['year'],
                $tournamentData['month'],
                $tournamentData['day'],
                $dailyNumber
            )
        );

        if (!$existing || !isset($existing->id) || ($existing->status ?? '') !== 'open') {
            return null;
        }

        return (int) $existing->id;
    }

    /**
     * @param object $wpdb
     * @param int[] $playerIds
     */
    private function resetExistingTournament($wpdb, string $table, int $tournamentId, array $tournamentData, array $playerIds): void
    {
        $roundCount = max(1, intval($tournamentData['round_count'] ?? 1));

        if (method_exists($wpdb, 'update')) {
            $wpdb->update(
                $table,
                [
                    'mode'               => $tournamentData['mode'],
                    'round_count'        => $roundCount,
                    'counts_for_ranking' => (int) ($tournamentData['counts_for_ranking'] ?? 1),
                    'label'              => (string) ($tournamentData['label'] ?? ''),
                ],
                ['id' => $tournamentId],
                ['%s', '%d', '%d', '%s'],
                ['%d']
            );
        }

        // Delete only games exceeding the new round count (if round count was reduced)
        if (method_exists($wpdb, 'query') && method_exists($wpdb, 'prepare')) {
            $gamesTable = $wpdb->prefix . 'monatsblitz_games';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$gamesTable} WHERE tournament_id = %d AND leg_type > %d",
                    $tournamentId,
                    $roundCount
                )
            );
        }

        if (!empty($playerIds)) {
            if (method_exists($wpdb, 'delete')) {
                $wpdb->delete($wpdb->prefix . 'monatsblitz_tournament_players', ['tournament_id' => $tournamentId], ['%d']);
            }
            $this->insertTournamentPlayers($wpdb, $tournamentId, $playerIds);
        }
    }

    /**
     * @param object $wpdb
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

    private function tournamentSuccess(int $tournamentId): array
    {
        return [
            'success'       => true,
            'tournament_id' => $tournamentId,
        ];
    }
}
