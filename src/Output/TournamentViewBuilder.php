<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentViewBuilder
{
    public function buildRankingRows(array $results): string
    {
        $collisionMap = PlayerNameFormatter::buildCollisionMap($results);
        $ranking_rows = '';
        $position = 1;

        foreach ($results as $result) {
            $playerId = intval($result['player_id'] ?? 0);
            $name = esc_html(PlayerNameFormatter::format(
                (string)($result['forename'] ?? ''),
                (string)($result['surname'] ?? ''),
                (string)($result['rufname'] ?? ''),
                null,
                $collisionMap[$playerId] ?? false
            ));
            $ranking_rows .= "<tr>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$position}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$name}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">" . esc_html($result['points']) . "</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">" . esc_html($result['rank']) . "</td>
            </tr>";
            $position++;
        }

        return $ranking_rows;
    }

    public function buildGamesList(array $games): string
    {
        $games_list = '';

        foreach ($games as $game) {
            $p1 = esc_html(PlayerNameFormatter::format(
                (string)($game['p1_forename'] ?? ''),
                (string)($game['p1_surname'] ?? ''),
                (string)($game['p1_rufname'] ?? '')
            ));
            $p2 = esc_html(PlayerNameFormatter::format(
                (string)($game['p2_forename'] ?? ''),
                (string)($game['p2_surname'] ?? ''),
                (string)($game['p2_rufname'] ?? '')
            ));
            $res = esc_html($game['result']);
            $games_list .= "<li>{$p1} vs {$p2}: {$res}</li>";
        }

        return $games_list;
    }

    public function buildPlayersFromResults(array $results): array
    {
        $collisionMap = PlayerNameFormatter::buildCollisionMap($results);
        $players = [];

        foreach ($results as $result) {
            $playerId = intval($result['player_id'] ?? 0);
            $formattedName = PlayerNameFormatter::format(
                (string)($result['forename'] ?? ''),
                (string)($result['surname'] ?? ''),
                (string)($result['rufname'] ?? ''),
                null,
                $collisionMap[$playerId] ?? false
            );
            $players[] = [
                'id' => $playerId,
                'name' => esc_html($formattedName),
                'points' => esc_html($result['points']),
                'rank' => esc_html($result['rank']),
            ];
        }

        return $players;
    }

    public function buildTableHtml(array $players, array $games, int $round_count): string
    {
        if (empty($games)) {
            return '';
        }

        if ($round_count === 1) {
            return TournamentContentBuilder::buildCrossTable($players, $games, true);
        }

        $table_html = '';
        for ($round = 1; $round <= $round_count; $round++) {
            $table_html .= '<h3>Runde ' . $round . '</h3>';
            $table_html .= TournamentContentBuilder::buildCrossTable($players, $games, false, $round);
        }
        $table_html .= TournamentContentBuilder::buildSummaryTable($players);

        return $table_html;
    }
}
