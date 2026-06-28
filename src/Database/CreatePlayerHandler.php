<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class CreatePlayerHandler
{
    public function handle($request)
    {
        global $wpdb;

        $params = $request->get_json_params();

        if ($this->isBatchPayload($params)) {
            return $this->createBatch($params);
        }

        return $this->createSingle($wpdb, $params);
    }

    private function isBatchPayload($params): bool
    {
        if (!is_array($params)) {
            return false;
        }

        if (isset($params['players'])) {
            return true;
        }

        return array_is_list($params);
    }

    private function createBatch(array $params)
    {
        global $wpdb;

        $entries = isset($params['players']) ? $params['players'] : $params;

        if (!is_array($entries)) {
            return new \WP_Error('invalid_data', 'players must be an array', ['status' => 400]);
        }

        if (count($entries) === 0) {
            return new \WP_Error('invalid_data', 'At least one player is required', ['status' => 400]);
        }

        $responses = [];

        foreach ($entries as $playerEntry) {

            if (!is_array($playerEntry)) {
                return new \WP_Error('invalid_data', 'Each player must be an object', ['status' => 400]);
            }

            $playerResponse = $this->createSingle($wpdb, $playerEntry);

            if (is_wp_error($playerResponse)) {
                return $playerResponse;
            }

            $responses[] = $playerResponse;
        }

        return [
            'success' => true,
            'count'   => count($responses),
            'items'   => $responses,
        ];
    }

    private function createSingle($wpdb, array $params)
    {
        $forename = sanitize_text_field($params['forename'] ?? '');
        $surname  = sanitize_text_field($params['surname'] ?? '');

        if (empty($forename) || empty($surname)) {
            return new \WP_Error('invalid_data', 'Vorname und Nachname sind erforderlich', ['status' => 400]);
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_players 
                 WHERE forename = %s AND surname = %s",
                $forename,
                $surname
            )
        );

        if ($existing) {
            return [
                'success'   => true,
                'player_id' => $existing,
                'message'   => 'Spieler existiert bereits'
            ];
        }

        $wpdb->insert(
            $wpdb->prefix . 'monatsblitz_players',
            [
                'forename' => $forename,
                'surname'  => $surname
            ]
        );

        return [
            'success'   => true,
            'player_id' => $wpdb->insert_id
        ];
    }
}