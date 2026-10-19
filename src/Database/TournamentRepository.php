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
    public function create(string $date, array $tournamentData, array $playerIds)
    {
        global $wpdb;

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

    /**
     * @param object $wpdb
     * @param int[] $playerIds
     */
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
