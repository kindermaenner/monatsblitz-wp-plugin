<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentContentBuilder
{
    public static function buildCrossTable(array $players, array $games, bool $include_totals, ?int $round = null): string
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

        $n = count($players);
        $css_file = MONATSBLITZ_PLUGIN_PATH . 'assets/css/monatsblitz-cross-table.css';
        $css = file_get_contents($css_file);

        $table_html = "<style>\n" . $css . "\n</style>\n";
        $table_html .= '<div class="mb-crosstable-scroll">';
        $table_html .= '<table class="monatsblitz-crosstable">';
        $table_html .= '<thead><tr><th>Nr.</th><th>Spieler</th>';

        for ($c = 1; $c <= $n; $c++) {
            $table_html .= '<th>' . $c . '</th>';
        }

        if ($include_totals) {
            $table_html .= '<th>Punkte</th><th>Platz</th>';
        }

        $table_html .= '</tr></thead>';
        $table_html .= '<tbody>';

        for ($i = 0; $i < $n; $i++) {
            $row_player = $players[$i];
            $table_html .= '<tr>';
            $table_html .= '<td>' . ($i + 1) . '</td>';
            $table_html .= '<td>' . $row_player['name'] . '</td>';

            for ($j = 0; $j < $n; $j++) {
                $cell_attr = '';
                if ($i === $j) {
                    $cell = '&nbsp;';
                    $cell_attr = ' class="mb-cell-empty mb-cell-diagonal" style="background-color:#eeeeee !important; color:#666666 !important;"';
                } else {
                    $p_i = $row_player['id'];
                    $p_j = $players[$j]['id'];
                    if (isset($game_map[$p_i][$p_j])) {
                        $cell = self::normalizeResultCell($game_map[$p_i][$p_j], false);
                    } elseif (isset($game_map[$p_j][$p_i])) {
                        $cell = self::normalizeResultCell($game_map[$p_j][$p_i], true);
                    } else {
                        $cell = '&nbsp;';
                        $cell_attr = ' class="mb-cell-empty mb-cell-pending" style="background-color:#eeeeee !important; color:#666666 !important;"';
                    }
                }
                $table_html .= '<td' . $cell_attr . '>' . $cell . '</td>';
            }

            if ($include_totals) {
                $table_html .= '<td>' . $row_player['points'] . '</td>';
                $table_html .= '<td>' . $row_player['rank'] . '</td>';
            }

            $table_html .= '</tr>';
        }

        $table_html .= '</tbody></table></div>';
        return $table_html;
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
        if (!$invert) {
            if ($result === '1:0' || $result === '1-0') { return '1'; }
            if ($result === '0:1' || $result === '0-1') { return '0'; }
            if ($result === '+:-') { return '+'; }
            if ($result === '-:+') { return '-'; }
        } else {
            if ($result === '1:0' || $result === '1-0') { return '0'; }
            if ($result === '0:1' || $result === '0-1') { return '1'; }
            if ($result === '+:-') { return '-'; }
            if ($result === '-:+') { return '+'; }
        }

        if ($result === '0.5:0.5' || $result === '0.5-0.5' || $result === '½') {
            return '½';
        }

        return esc_html($result);
    }
}
