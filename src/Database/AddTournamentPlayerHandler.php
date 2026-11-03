<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class AddTournamentPlayerHandler
{
    public function handle(array $params)
    {
        global $wpdb;

        $tournament_id = intval($params['tournament_id'] ?? 0);
        $player_id = intval($params['player_id'] ?? 0);

        if ($tournament_id <= 0 || $player_id <= 0) {
            return new \WP_Error('invalid_data', 'Fehlende Daten', ['status' => 400]);
        }

        $tournament_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}monatsblitz_tournaments WHERE id = %d",
                $tournament_id
            )
        );

        if ($tournament_status === null) {
            return new \WP_Error('invalid_tournament', 'Turnier existiert nicht', ['status' => 404]);
        }

        if ($tournament_status === 'finalized') {
            return new \WP_Error('tournament_finalized', 'Turnier ist bereits finalisiert', ['status' => 403]);
        }

        $player_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_players WHERE id = %d",
                $player_id
            )
        );

        if (!$player_exists) {
            return new \WP_Error('invalid_player', 'Spieler existiert nicht', ['status' => 404]);
        }

        $table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, has_left FROM {$table} WHERE tournament_id = %d AND player_id = %d",
                $tournament_id,
                $player_id
            )
        );

        if ($existing) {
            if (!empty($existing->has_left)) {
                $updated = $wpdb->update(
                    $table,
                    ['has_left' => 0],
                    ['tournament_id' => $tournament_id, 'player_id' => $player_id],
                    ['%d'],
                    ['%d', '%d']
                );
                if ($updated === false) {
                    $errorMsg = !empty($wpdb->last_error) ? $wpdb->last_error : 'Fehler beim Aktualisieren des Spielers in der Datenbank';
                    return new \WP_Error('db_error', $errorMsg, ['status' => 500]);
                }
            }
        } else {
            $inserted = $wpdb->insert(
                $table,
                [
                    'tournament_id' => $tournament_id,
                    'player_id'     => $player_id,
                    'has_left'      => 0,
                ],
                ['%d', '%d', '%d']
            );

            if ($inserted === false) {
                $errorMsg = !empty($wpdb->last_error) ? $wpdb->last_error : 'Fehler beim Hinzufügen des Spielers zum Turnier';
                return new \WP_Error('db_error', $errorMsg, ['status' => 500]);
            }
        }

        return [
            'success'       => true,
            'tournament_id' => $tournament_id,
            'player_id'     => $player_id,
        ];
    }
}
