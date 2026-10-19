<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class CreateTournamentHandler
{
    public function handle($request)
    {
        $params = $request->get_json_params();

        if (empty($params['date'])) {
            return new \WP_Error('invalid_data', 'Date is required', ['status' => 400]);
        }

        $date = $params['date'];
        $dateParts = $this->parseDate($date);
        if ($dateParts instanceof \WP_Error) {
            return $dateParts;
        }

        $settings = $this->parseTournamentSettings($params);
        if ($settings instanceof \WP_Error) {
            return $settings;
        }

        $tournamentData = array_merge($dateParts, [
            'mode'        => $settings['mode'],
            'round_count' => $settings['round_count'],
            'status'      => 'open',
        ]);

        return (new TournamentRepository())->create($date, $tournamentData, $settings['player_ids']);
    }

    private function parseDate($date)
    {
        if (!is_string($date)) {
            return new \WP_Error('invalid_date', 'Invalid date format', ['status' => 400]);
        }

        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return new \WP_Error('invalid_date', 'Invalid date format', ['status' => 400]);
        }

        list($year, $month, $day) = $parts;
        if (!ctype_digit($year) || !ctype_digit($month) || !ctype_digit($day)) {
            return new \WP_Error(
                'invalid_date',
                'Date must contain numeric year, month and day',
                [
                    'status' => 400,
                    'developer_message' => 'Expected date format YYYY-MM-DD with numeric parts only.',
                    'input_date' => $date,
                ]
            );
        }

        $dateParts = [
            'year' => (int) $year,
            'month' => (int) $month,
            'day' => (int) $day,
        ];

        if (!checkdate($dateParts['month'], $dateParts['day'], $dateParts['year'])) {
            return new \WP_Error(
                'invalid_date',
                'Invalid calendar date',
                [
                    'status' => 400,
                    'developer_message' => 'checkdate failed for provided date.',
                    'input_date' => $date,
                    'parsed' => $dateParts,
                ]
            );
        }

        return $dateParts;
    }

    private function parseTournamentSettings(array $params)
    {
        $mode = sanitize_text_field($params['mode'] ?? '');
        if ($mode === '') {
            return new \WP_Error('invalid_data', 'Mode is required', ['status' => 400]);
        }

        $roundCount = intval($params['round_count'] ?? 1);
        if ($roundCount < 1) {
            return new \WP_Error('invalid_data', 'round_count must be >= 1', ['status' => 400]);
        }

        return [
            'mode'        => $mode,
            'round_count' => $roundCount,
            'player_ids'  => $this->extractPlayerIds($params['player_ids'] ?? []),
        ];
    }

    /**
     * @param mixed $rawPlayerIds
     * @return int[]
     */
    private function extractPlayerIds($rawPlayerIds): array
    {
        if (!is_array($rawPlayerIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawPlayerIds as $rawId) {
            $id = intval($rawId);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
