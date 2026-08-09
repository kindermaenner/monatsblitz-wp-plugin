<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentContentBuilder
{
    public static function buildCrossTable(array $players, array $games, bool $include_totals, ?int $round = null): string
    {
        $game_map = self::buildGameMap($games, $round);
        $table_html = self::buildCrossTableStyle();
        $table_html .= self::buildCrossTableHeader(count($players), $include_totals);
        $table_html .= '<tbody>';

        foreach ($players as $index => $row_player) {
            $table_html .= self::buildCrossTableRow($index, $row_player, $players, $game_map, $include_totals);
        }

        $table_html .= '</tbody></table></div>';
        return $table_html;
    }

    private static function buildGameMap(array $games, ?int $round): array
    {
        $game_map = [];

        foreach ($games as $game) {
            $leg_type = intval($game['leg_type'] ?? 1);
            if ($round !== null && $leg_type !== $round) {
                continue;
            }

            $p1 = intval($game['player1_id']);
            $p2 = intval($game['player2_id']);
            $game_map[$p1][$p2] = $game['result'];
        }

        return $game_map;
    }

    private static function buildCrossTableStyle(): string
    {
        $css_file = MONATSBLITZ_PLUGIN_PATH . 'assets/css/monatsblitz-cross-table.css';
        $css = file_get_contents($css_file);

        return "<style>\n" . $css . "\n</style>\n" . '<div class="mb-crosstable-scroll">' . '<table class="monatsblitz-crosstable">';
    }

    private static function buildCrossTableHeader(int $player_count, bool $include_totals): string
    {
        $header = '<thead><tr><th>Nr.</th><th>Spieler</th>';
        for ($c = 1; $c <= $player_count; $c++) {
            $header .= '<th>' . $c . '</th>';
        }

        if ($include_totals) {
            $header .= '<th>Punkte</th><th>Platz</th>';
        }

        return $header . '</tr></thead>';
    }

    private static function buildCrossTableRow(int $row_index, array $row_player, array $players, array $game_map, bool $include_totals): string
    {
        $row = '<tr>';
        $row .= '<td>' . ($row_index + 1) . '</td>';
        $row .= '<td>' . $row_player['name'] . '</td>';

        foreach ($players as $column_index => $column_player) {
            $row .= self::buildCrossTableCell($row_index, $column_index, $row_player, $column_player, $game_map);
        }

        if ($include_totals) {
            $row .= '<td>' . $row_player['points'] . '</td>';
            $row .= '<td>' . $row_player['rank'] . '</td>';
        }

        return $row . '</tr>';
    }

    private static function buildCrossTableCell(int $row_index, int $column_index, array $row_player, array $column_player, array $game_map): string
    {
        if ($row_index === $column_index) {
            return '<td class="mb-cell-empty mb-cell-diagonal" style="background-color:#eeeeee !important; color:#666666 !important;">&nbsp;</td>';
        }

        $p_i = $row_player['id'];
        $p_j = $column_player['id'];

        if (isset($game_map[$p_i][$p_j])) {
            $cell = self::normalizeResultCell($game_map[$p_i][$p_j], false);
        } elseif (isset($game_map[$p_j][$p_i])) {
            $cell = self::normalizeResultCell($game_map[$p_j][$p_i], true);
        } else {
            return '<td class="mb-cell-empty mb-cell-pending" style="background-color:#eeeeee !important; color:#666666 !important;">&nbsp;</td>';
        }

        return '<td>' . $cell . '</td>';
    }

    public static function buildSummaryTable(array $players): string
    {
        $summary = '<h3>Gesamtergebnis</h3>';
        $summary .= '<table class="monatsblitz">';
        $summary .= '<thead><tr><th>Spieler</th><th>Gesamtpunkte</th><th>Platz</th></tr></thead><tbody>';

        foreach ($players as $player) {
            $summary .= '<tr>';
            $summary .= '<td>' . $player['name'] . '</td>';
            $summary .= '<td>' . $player['points'] . '</td>';
            $summary .= '<td>' . $player['rank'] . '</td>';
            $summary .= '</tr>';
        }

        $summary .= '</tbody></table>';
        return $summary;
    }

    public static function buildResultsTable(array $results): string
    {
        $html = '<table class="monatsblitz">';
        $html .= '<thead><tr><th>Nr.</th><th>Spieler</th><th>Punkte</th><th>Platz</th></tr></thead><tbody>';

        $i = 1;
        foreach ($results as $result) {
            $name = esc_html(trim(((string)($result['forename'] ?? '')) . ' ' . ((string)($result['surname'] ?? ''))));
            $points = esc_html((string)($result['points'] ?? ''));
            $rank = esc_html((string)($result['rank'] ?? ''));

            $html .= '<tr>';
            $html .= '<td>' . $i . '</td>';
            $html .= '<td>' . $name . '</td>';
            $html .= '<td>' . $points . '</td>';
            $html .= '<td>' . $rank . '</td>';
            $html .= '</tr>';
            $i++;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public static function normalizeResultCell(string $result, bool $invert): string
    {
        $map = $invert ? self::getInverseResultMap() : self::getResultMap();
        if (isset($map[$result])) {
            return $map[$result];
        }

        if (self::isDrawResult($result)) {
            return '½';
        }

        return esc_html($result);
    }

    private static function getResultMap(): array
    {
        return [
            '1:0' => '1',
            '1-0' => '1',
            '0:1' => '0',
            '0-1' => '0',
            '+:-' => '+',
            '-:+' => '-',
        ];
    }

    private static function getInverseResultMap(): array
    {
        return [
            '1:0' => '0',
            '1-0' => '0',
            '0:1' => '1',
            '0-1' => '1',
            '+:-' => '-',
            '-:+' => '+',
        ];
    }

    private static function isDrawResult(string $result): bool
    {
        return in_array($result, ['0.5:0.5', '0.5-0.5', '½'], true);
    }
}
