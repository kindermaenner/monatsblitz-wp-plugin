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

            $request = new class ($tournamentId) {
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
                $errors[] = $this->formatWpError($result, $tournamentId);
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

    /**
     * Normalize a WP_Error into structured array used by the handler.
     *
     * @param \WP_Error $error
     * @param int $tournamentId
     * @return array
     */
    private function formatWpError($error, int $tournamentId): array
    {
        $errorCode = method_exists($error, 'get_error_code') ? $error->get_error_code() : ($error->code ?? 'error');
        $errorMessage = method_exists($error, 'get_error_message') ? $error->get_error_message() : ($error->message ?? 'Unknown error');
        $errorStatus = method_exists($error, 'get_error_data') ? $error->get_error_data('status') : ($error->data['status'] ?? null);

        return [
            'tournament_id' => $tournamentId,
            'code' => (string)$errorCode,
            'message' => (string)$errorMessage,
            'status' => (int)($errorStatus ?: 500),
        ];
    }
}
