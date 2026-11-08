<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class ReopenTournamentHandler
{
    public function handle($request)
    {
        $tournamentId = $this->extractTournamentId($request);
        if ($tournamentId <= 0) {
            return new \WP_Error('invalid_data', 'Gültige Turnier-ID erforderlich', ['status' => 400]);
        }

        return $this->reopen($tournamentId);
    }

    public function reopen(int $tournamentId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';
        $tournament = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, year, month, day, daily_number, label, mode, status FROM {$table} WHERE id = %d",
                $tournamentId
            )
        );

        if (!$tournament || !isset($tournament->id)) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        if (($tournament->status ?? '') === 'open') {
            return [
                'success'       => true,
                'tournament_id' => $tournamentId,
                'status'        => 'open',
                'already_open'  => true,
                'message'       => 'Turnier ist bereits offen',
            ];
        }

        $updated = $wpdb->update(
            $table,
            ['status' => 'open'],
            ['id' => $tournamentId],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            $errorMsg = !empty($wpdb->last_error) ? $wpdb->last_error : 'Fehler beim Aktualisieren des Turniers';
            return new \WP_Error('db_error', $errorMsg, ['status' => 500]);
        }

        return [
            'success'       => true,
            'tournament_id' => $tournamentId,
            'status'        => 'open',
            'already_open'  => false,
            'message'       => 'Turnier erfolgreich wieder geöffnet',
        ];
    }

    private function extractTournamentId($request): int
    {
        if (is_array($request)) {
            return intval($request['tournament_id'] ?? $request['id'] ?? 0);
        }

        if (is_object($request)) {
            if (method_exists($request, 'get_param')) {
                $id = $request->get_param('id') ?? $request->get_param('tournament_id');
                if ($id !== null) {
                    return intval($id);
                }
            }
            if (method_exists($request, 'get_json_params')) {
                $params = $request->get_json_params();
                if (is_array($params)) {
                    return intval($params['tournament_id'] ?? $params['id'] ?? 0);
                }
            }
        }

        return 0;
    }
}
