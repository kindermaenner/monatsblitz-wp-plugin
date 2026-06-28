<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class CreateResultHandler
{
    public function handle($request)
    {
        global $wpdb;

        $params = $request->get_json_params();

        if (isset($params['results'])) {
            return $this->createBatch($params);
        }

        return $this->createSingle($wpdb, $params);
    }

    private function createBatch(array $params)
    {
        global $wpdb;

        if (!isset($params['tournament_id'])) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        if (!is_array($params['results'])) {
            return new \WP_Error('invalid_data', 'results must be an array', ['status' => 400]);
        }

        $responses = [];

        foreach ($params['results'] as $resultEntry) {

            if (!is_array($resultEntry)) {
                return new \WP_Error('invalid_data', 'Each result must be an object', ['status' => 400]);
            }

            $resultEntry['tournament_id'] = $params['tournament_id'];

            $resultResponse = $this->createSingle($wpdb, $resultEntry);

            if (is_wp_error($resultResponse)) {
                return $resultResponse;
            }

            $responses[] = $resultResponse;
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
        $player_id     = intval($params['player_id'] ?? 0);
        $points        = floatval($params['points'] ?? 0);
        $rank          = intval($params['rank'] ?? 0);

        // Validierung
        if (!$tournament_id || !$player_id) {
            return new \WP_Error('invalid_data', 'Turnier-ID und Spieler-ID erforderlich', ['status' => 400]);
        }

        // Turnier existiert?
        $tournament_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_tournaments WHERE id = %d",
                $tournament_id
            )
        );

        if (!$tournament_exists) {
            return new \WP_Error('invalid_tournament', 'Turnier existiert nicht', ['status' => 404]);
        }

        // Spieler existiert?
        $player_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_players WHERE id = %d",
                $player_id
            )
        );

        if (!$player_exists) {
            return new \WP_Error('invalid_player', 'Spieler existiert nicht', ['status' => 404]);
        }

        // Ergebnis existiert bereits?
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_results 
                 WHERE tournament_id = %d AND player_id = %d",
                $tournament_id,
                $player_id
            )
        );

        if ($existing) {
            // Update
            $wpdb->update(
                $wpdb->prefix . 'monatsblitz_results',
                [
                    'points' => $points,
                    'rank'   => $rank
                ],
                [
                    'tournament_id' => $tournament_id,
                    'player_id'     => $player_id
                ]
            );

            return [
                'success'   => true,
                'result_id' => $existing,
                'message'   => 'Ergebnis aktualisiert'
            ];
        }

        // Neu einfügen
        $wpdb->insert(
            $wpdb->prefix . 'monatsblitz_results',
            [
                'tournament_id' => $tournament_id,
                'player_id'     => $player_id,
                'points'        => $points,
                'rank'          => $rank
            ]
        );

        return [
            'success'   => true,
            'result_id' => $wpdb->insert_id
        ];
    }
}