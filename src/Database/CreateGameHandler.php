<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class CreateGameHandler
{
    public function handle($request)
    {
        global $wpdb;

        $params = $request->get_json_params();

        if (isset($params['games'])) {
            return $this->createBatch($wpdb, $params);
        }

        return $this->createSingle($wpdb, $params);
    }

    private function createBatch($wpdb, array $params)
    {
        if (intval($params['tournament_id'] ?? 0) <= 0) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        if (!is_array($params['games'])) {
            return new \WP_Error('invalid_data', 'games must be an array', ['status' => 400]);
        }

        $responses = [];

        foreach ($params['games'] as $gameEntry) {

            if (!is_array($gameEntry)) {
                return new \WP_Error('invalid_data', 'Each game must be an object', ['status' => 400]);
            }

            $gameEntry['tournament_id'] = $params['tournament_id'];

            $gameResponse = $this->createSingle($wpdb, $gameEntry);

            if (is_wp_error($gameResponse)) {
                return $gameResponse;
            }

            $responses[] = $gameResponse;
        }

        return [
            'success' => true,
            'count'   => count($responses),
            'items'   => $responses,
        ];
    }

    private function createSingle($wpdb, array $params)
    {
        $tournament_id = intval($params['tournament_id'] ?? 0);
        $player1_id    = intval($params['player1_id'] ?? 0);
        $player2_id    = intval($params['player2_id'] ?? 0);
        $leg_type      = intval($params['leg_type'] ?? 1);
        $result        = sanitize_text_field($params['result'] ?? '');

        if ($tournament_id <= 0 || $player1_id <= 0 || $player2_id <= 0) {
            return new \WP_Error('invalid_data', 'Fehlende Daten', ['status' => 400]);
        }

        if ($leg_type <= 0) {
            return new \WP_Error('invalid_data', 'leg_type muss >= 1 sein', ['status' => 400]);
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_tournaments WHERE id = %d",
                $tournament_id
            )
        );

        if (!$exists) {
            return new \WP_Error('invalid_tournament', 'Turnier existiert nicht', ['status' => 404]);
        }

        $wpdb->insert(
            $wpdb->prefix . 'monatsblitz_games',
            [
                'tournament_id' => $tournament_id,
                'player1_id'    => $player1_id,
                'player2_id'    => $player2_id,
                'leg_type'      => $leg_type,
                'result'        => $result
            ],
            ['%d', '%d', '%d', '%d', '%s']
        );

        return [
            'success' => true,
            'game_id' => $wpdb->insert_id
        ];
    }
}