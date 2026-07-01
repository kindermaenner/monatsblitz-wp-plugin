<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class RecreatePostsHandler
{
    public function handle()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $tournaments = $wpdb->get_results(
            "SELECT id FROM {$table} ORDER BY year ASC, month ASC, day ASC, id ASC",
            ARRAY_A
        );

        if (!is_array($tournaments)) {
            return new \WP_Error('db_error', 'Turniere konnten nicht geladen werden', ['status' => 500]);
        }

        $finalizeHandler = $this->createFinalizeHandler();
        $processed = 0;
        $succeeded = 0;
        $errors = [];

        foreach ($tournaments as $tournament) {
            $tournamentId = intval($tournament['id'] ?? 0);
            if ($tournamentId <= 0) {
                continue;
            }

            $processed++;

            $request = new class($tournamentId) {
                private array $params;

                public function __construct(int $tournamentId)
                {
                    $this->params = ['tournament_id' => $tournamentId];
                }

                public function get_json_params(): array
                {
                    return $this->params;
                }
            };

            $result = $finalizeHandler->handle($request);

            if (is_wp_error($result)) {
                $errorCode = method_exists($result, 'get_error_code') ? $result->get_error_code() : ($result->code ?? 'error');
                $errorMessage = method_exists($result, 'get_error_message') ? $result->get_error_message() : ($result->message ?? 'Unknown error');
                $errorStatus = method_exists($result, 'get_error_data') ? $result->get_error_data('status') : ($result->data['status'] ?? null);

                $errors[] = [
                    'tournament_id' => $tournamentId,
                    'code' => (string)$errorCode,
                    'message' => (string)$errorMessage,
                    'status' => (int)($errorStatus ?: 500),
                ];
                continue;
            }

            $succeeded++;
        }

        return [
            'success' => empty($errors),
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    protected function createFinalizeHandler(): object
    {
        return new FinalizeTournamentHandler();
    }
}
