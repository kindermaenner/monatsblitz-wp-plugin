<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

class WithdrawPlayerHandler
{
    public function handle(array $params)
    {
        global $wpdb;

        $tournament_id = intval($params['tournament_id'] ?? 0);
        $player_id = intval($params['player_id'] ?? 0);
        $has_left = 1;
        if (isset($params['has_left'])) {
            $has_left = !empty($params['has_left']) ? 1 : 0;
        }

        if ($tournament_id <= 0 || $player_id <= 0) {
            return new \WP_Error('invalid_data', 'Fehlende Daten', ['status' => 400]);
        }

        $table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $wpdb->update(
            $table,
            ['has_left' => $has_left],
            ['tournament_id' => $tournament_id, 'player_id' => $player_id],
            ['%d'],
            ['%d', '%d']
        );

        return [
            'success'       => true,
            'tournament_id' => $tournament_id,
            'player_id'     => $player_id,
            'has_left'      => $has_left,
        ];
    }
}
