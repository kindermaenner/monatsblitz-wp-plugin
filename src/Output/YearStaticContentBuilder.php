<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class YearStaticContentBuilder
{
    /**
     * Aggregate monthly stats and points per tournament from DB rows.
     *
     * @param array|null $rows
     * @return array [monthly, pointsPerTournament]
     */
    public function aggregateMonthlyStats(?array $rows): array
    {
        $monthly = [];
        $pointsPerTournament = [];

        if (empty($rows) || !is_array($rows)) {
            return [$monthly, $pointsPerTournament];
        }

        foreach ($rows as $row) {
            $mode = (string)($row['mode'] ?? '');
            if (!BlitzModeService::isBlitzMode($mode)) {
                continue;
            }

            $playerId = (int)$row['player_id'];
            $month = (int)$row['month'];
            if ($month < 1 || $month > 12) {
                continue;
            }

            $playerName = trim((string)$row['forename'] . ' ' . (string)$row['surname']);
            if (!isset($monthly[$playerId])) {
                $monthly[$playerId] = [
                    'name' => esc_html($playerName),
                    'months' => array_fill(1, 12, ['participations' => 0, 'points' => 0]),
                ];
            }

            $rankPoints = $this->pointsFromRank((int)$row['rank']);
            $monthly[$playerId]['months'][$month]['participations'] += 1;
            $monthly[$playerId]['months'][$month]['points'] += $rankPoints;

            if ($rankPoints > 0) {
                $pointsPerTournament[$playerId][] = $rankPoints;
            }
        }

        uasort(
            $monthly,
            static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name'])
        );

        return [$monthly, $pointsPerTournament];
    }

    public function pointsFromRank(int $rank): int
    {
        return match ($rank) {
            1 => 5,
            2 => 4,
            3 => 3,
            4 => 2,
            5 => 1,
            default => 0,
        };
    }

    public function buildOverviewTable(array $monthly, bool $hideJanuary): string
    {
        $startMonth = $hideJanuary ? 2 : 1;
        $html = '<div class="mb-year-scroll">';
        $html .= '<table class="monatsblitz-year-overview">';
        $html .= '<thead><tr><th>Spieler</th>';
        for ($month = $startMonth; $month <= 12; $month++) {
            $html .= '<th>' . $month . '</th>';
        }
        $html .= '<th>Teiln.</th><th>Punkte</th></tr></thead><tbody>';

        foreach ($monthly as $playerData) {
            $totalParticipations = 0;
            $totalPoints = 0;

            $html .= '<tr>';
            $html .= '<td>' . $playerData['name'] . '</td>';

            for ($month = $startMonth; $month <= 12; $month++) {
                $cell = $playerData['months'][$month] ?? ['participations' => 0, 'points' => 0];
                $part = (int)$cell['participations'];
                $points = (int)$cell['points'];
                $totalParticipations += $part;
                $totalPoints += $points;

                $html .= '<td>' . ($part > 0 ? (string)$points : '') . '</td>';
            }

            $html .= '<td>' . $totalParticipations . '</td>';
            $html .= '<td>' . $totalPoints . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    public function buildYearRankingTable(array $monthly, array $pointsPerTournament): string
    {
        $ranking = [];

        foreach ($monthly as $playerId => $playerData) {
            $points = $pointsPerTournament[(int)$playerId] ?? [];
            rsort($points, SORT_NUMERIC);
            $counted = array_slice($points, 0, 7);

            $ranking[] = [
                'name' => $playerData['name'],
                'points' => array_sum($counted),
                'counted' => count($counted),
            ];
        }

        usort($ranking, static function (array $a, array $b): int {
            if ((int)$a['points'] === (int)$b['points']) {
                return strcmp((string)$a['name'], (string)$b['name']);
            }

            return (int)$b['points'] <=> (int)$a['points'];
        });

        $html = '<table class="monatsblitz-year-ranking">';
        $html .= '<thead><tr>';
        $html .= '<th><span class="full">Platz</span><span class="short">Pl.</span></th>';
        $html .= '<th>Spieler</th>';
        $html .= '<th><span class="full">Wertungspunkte</span><span class="short">Punkte</span></th>';
        $html .= '<th><span class="full">Gewertete Teilnahmen</span><span class="short">Teiln.</span></th>';
        $html .= '</tr></thead><tbody>';

        $pos = 1;
        foreach ($ranking as $row) {
            $html .= '<tr>';
            $html .= '<td>' . $pos . '</td>';
            $html .= '<td>' . $row['name'] . '</td>';
            $html .= '<td>' . (int)$row['points'] . '</td>';
            $html .= '<td>' . (int)$row['counted'] . '</td>';
            $html .= '</tr>';
            $pos++;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function buildContent(
        string $templateContent,
        int $year,
        array $monthly,
        array $pointsPerTournament,
        bool $hideJanuary
    ): string {
        $overviewHtml = $this->buildOverviewTable($monthly, $hideJanuary);
        $rankingHtml = $this->buildYearRankingTable($monthly, $pointsPerTournament);

        return str_replace(
            ['{{year}}', '{{blitz_monthly_overview}}', '{{blitz_ranking_year}}'],
            [(string)$year, $overviewHtml, $rankingHtml],
            $templateContent
        );
    }
}
