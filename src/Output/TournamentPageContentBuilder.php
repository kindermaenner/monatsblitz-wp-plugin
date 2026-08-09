<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentPageContentBuilder
{
    public function buildContent(string $template_content, array $replacements): string
    {
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template_content
        );
    }

    public function buildMetadata(array $results, int $winner_games): array
    {
        $winner_data = $this->extractWinnerData($results);
        $winner_data['games_placeholder'] = $winner_games > 0
            ? ('aus ' . esc_html((string)$winner_games))
            : '';

        return $winner_data;
    }

    public function buildPlaceholders(
        string $month_name,
        string $date_str,
        string $year,
        string $mode,
        string $round_count,
        string $ranking_rows,
        string $games_list,
        string $table_html,
        array $winner_data
    ): array {
        return [
            '{{month_name}}' => esc_html($month_name),
            '{{year}}' => esc_html($year),
            '{{date}}' => esc_html($date_str),
            '{{winner_name}}' => $winner_data['name'],
            '{{winner_games}}' => $winner_data['games_placeholder'],
            '{{winner_points}}' => $winner_data['points'],
            '{{ranking_rows}}' => $ranking_rows,
            '{{games_list}}' => $games_list,
            '{{table}}' => $table_html,
            '{{mode}}' => $mode,
            '{{round_count}}' => esc_html($round_count),
        ];
    }

    private function extractWinnerData(array $results): array
    {
        $winner_name = '';
        $winner_points = '';
        $winner_player_id = 0;

        if (!empty($results)) {
            $winner = $results[0];
            $winner_name = esc_html(trim($winner['forename'] . ' ' . $winner['surname']));
            $winner_points = esc_html($winner['points']);
            $winner_player_id = intval($winner['player_id']);
        }

        return [
            'name' => $winner_name,
            'points' => $winner_points,
            'player_id' => $winner_player_id,
            'games_placeholder' => '',
        ];
    }
}
