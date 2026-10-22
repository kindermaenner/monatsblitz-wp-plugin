<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentPageContentBuilder
{
    private const PLACEHOLDER_WINNER_COUNT = '{{winner_count}}';

    public function buildContent(string $template_content, array $replacements, array $winner_data = []): string
    {
        $template_content = preg_replace(
            '~(?:<style\b[^>]*>\s*)?table\.monatsblitz\b(?:[^{}]*\{[^{}]*\}\s*)+(?:</style\s*>)?~i',
            '',
            $template_content
        );
        if ($template_content === null) {
            throw new TemplateProcessingException('Could not remove table styles from tournament template.');
        }

        $is_plural = $winner_data['is_plural'] ?? null;
        if ($is_plural === null) {
            $is_plural = isset($replacements[self::PLACEHOLDER_WINNER_COUNT]) && intval($replacements[self::PLACEHOLDER_WINNER_COUNT]) > 1;
        }

        if ($is_plural) {
            // Remove singular blocks
            $template_content = (string) preg_replace('~\{{#(?:singular|single_winner)\}}[\s\S]*?\{{/(?:singular|single_winner)\}}~i', '', $template_content);
            // Unwrap plural blocks
            $template_content = (string) preg_replace('~\{{/(?:plural|multiple_winners)\}}~i', '', (string) preg_replace('~\{{#(?:plural|multiple_winners)\}}~i', '', $template_content));
        } else {
            // Remove plural blocks
            $template_content = (string) preg_replace('~\{{#(?:plural|multiple_winners)\}}[\s\S]*?\{{/(?:plural|multiple_winners)\}}~i', '', $template_content);
            // Unwrap singular blocks
            $template_content = (string) preg_replace('~\{{/(?:singular|single_winner)\}}~i', '', (string) preg_replace('~\{{#(?:singular|single_winner)\}}~i', '', $template_content));
        }

        // Process inline helper {{plural:singular|plural}}
        $template_content = (string) preg_replace_callback(
            '~\{{plural:([^|}]*)\|([^|}]*)\}}~i',
            function ($matches) use ($is_plural) {
                return $is_plural ? $matches[2] : $matches[1];
            },
            $template_content
        );

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template_content
        );
    }

    public function buildMetadata(array $results, int $winner_games): array
    {
        $winner_data = $this->extractWinnerData($results);
        $games_placeholder = $winner_games > 0
            ? ('aus ' . esc_html((string)$winner_games))
            : '';
        $winner_data['games_placeholder'] = $games_placeholder;

        $name = $winner_data['name'];
        $points = $winner_data['points'];
        $count = $winner_data['count'];

        $points_str = $points !== '' ? $points : '0';
        $full_points = $games_placeholder !== ''
            ? "{$points_str} {$games_placeholder}"
            : $points_str;

        if ($count > 1) {
            $winner_data['text'] = "{$name} siegen mit jeweils {$full_points} Punkten.";
        } else {
            $winner_data['text'] = "{$name} siegt mit {$full_points} Punkten.";
        }

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
        $count = $winner_data['count'] ?? 1;
        $is_plural = ($winner_data['is_plural'] ?? false) || ($count > 1);

        return [
            '{{month_name}}' => esc_html($month_name),
            '{{year}}' => esc_html($year),
            '{{date}}' => esc_html($date_str),
            '{{winner_name}}' => $winner_data['name'] ?? '',
            '{{winner_names}}' => $winner_data['name'] ?? '',
            '{{winner_games}}' => $winner_data['games_placeholder'] ?? '',
            '{{winner_points}}' => $winner_data['points'] ?? '',
            self::PLACEHOLDER_WINNER_COUNT => (string)$count,
            '{{winner_verb}}' => $is_plural ? 'siegen' : 'siegt',
            '{{winner_text}}' => $winner_data['text'] ?? '',
            '{{ranking_rows}}' => $ranking_rows,
            '{{games_list}}' => $games_list,
            '{{table}}' => $table_html,
            '{{mode}}' => $mode,
            '{{round_count}}' => esc_html($round_count),
        ];
    }

    private function extractWinnerData(array $results): array
    {
        $winner_names = [];
        $winner_points = '';
        $winner_player_ids = [];

        $winners = $this->extractWinners($results);
        foreach ($winners as $w) {
            $forename = trim((string)($w['forename'] ?? ''));
            $surname = trim((string)($w['surname'] ?? ''));
            $full_name = trim($forename . ' ' . $surname);
            if ($full_name !== '') {
                $winner_names[] = esc_html($full_name);
            }
            $winner_player_ids[] = intval($w['player_id'] ?? 0);
        }

        if (!empty($winners)) {
            $winner_points = esc_html((string)($winners[0]['points'] ?? ''));
        }

        $count = count($winner_names);

        return [
            'name' => $this->formatWinnerNames($winner_names),
            'names' => $winner_names,
            'count' => max(1, $count),
            'is_plural' => $count > 1,
            'points' => $winner_points,
            'player_id' => $winner_player_ids[0] ?? 0,
            'player_ids' => $winner_player_ids,
            'games_placeholder' => '',
            'text' => '',
        ];
    }

    private function extractWinners(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        if (isset($results[0]['rank'])) {
            $min_rank = intval($results[0]['rank']);
            $winners = array_values(array_filter($results, function ($r) use ($min_rank) {
                return isset($r['rank']) && intval($r['rank']) === $min_rank;
            }));
            if (!empty($winners)) {
                return $winners;
            }
        }

        return [$results[0]];
    }

    private function formatWinnerNames(array $winner_names): string
    {
        $count = count($winner_names);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $winner_names[0];
        }
        if ($count === 2) {
            return $winner_names[0] . ' und ' . $winner_names[1];
        }

        $names_copy = $winner_names;
        $last = array_pop($names_copy);
        return implode(', ', $names_copy) . ' und ' . $last;
    }
}
