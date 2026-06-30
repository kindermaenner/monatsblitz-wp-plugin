<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class YearStaticPageHandler
{
    public function createOrUpdate(int $year): array|\WP_Error
    {
        global $wpdb;

        if ($year < 2000 || $year > 2100) {
            return new \WP_Error('invalid_data',
                                 sprintf('Ungultiges Jahr: "%d".', $year),
                                 ['status' => 400]);
        }

        $templateName = sanitize_text_field((string)get_option('monatsblitz_template_static_page', ''));
        if ($templateName === '') {
            return new \WP_Error('no_template', 'Kein Template fur statische Jahresseite konfiguriert', ['status' => 400]);
        }

        $templatePage = get_page_by_title($templateName, OBJECT, 'page');
        if (!$templatePage || is_wp_error($templatePage)) {
            return new \WP_Error('template_missing', 'Template-Seite nicht gefunden', ['status' => 404]);
        }

        $slug = sprintf('blitzmeisterschaft-%04d', $year);
        $metaKey = sprintf('_blitzmeisterschaft_%04d', $year);

        $tablePrefix = $wpdb->prefix . 'monatsblitz_';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id AS tournament_id, t.month, t.mode, r.player_id, r.rank, p.forename, p.surname
                 FROM {$tablePrefix}tournaments t
                 INNER JOIN {$tablePrefix}results r ON r.tournament_id = t.id
                 INNER JOIN {$tablePrefix}players p ON p.id = r.player_id
                 WHERE t.year = %d
                 ORDER BY t.month ASC, t.day ASC, t.id ASC, r.rank ASC",
                $year
            ),
            ARRAY_A
        );

        $monthly = [];
        $pointsPerTournament = [];

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

        $hideJanuary = (bool)get_option('monatsblitz_hide_january_overview', false);

        $overviewHtml = $this->buildOverviewTable($monthly, $hideJanuary);
        $rankingHtml = $this->buildYearRankingTable($monthly, $pointsPerTournament);

        $content = str_replace(
            ['{{year}}', '{{blitz_monthly_overview}}', '{{blitz_ranking_year}}'],
            [(string)$year, $overviewHtml, $rankingHtml],
            (string)$templatePage->post_content
        );

        $existing = get_posts([
            'post_type' => 'page',
            'meta_key' => $metaKey,
            'meta_value' => '1',
            'numberposts' => 1,
        ]);

        $pageId = 0;
        $updated = false;

        $post_title = sprintf('Blitzmeisterschaft %04d', $year);
        if (!empty($existing)) {
            $pageId = (int)$existing[0]->ID;
            wp_update_post([
                'ID' => $pageId,
                'post_title' => $post_title,
                'post_name' => $slug,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
            $this->copyAllMeta((int)$templatePage->ID, $pageId);
            $updated = true;
        } else {
            $pageId = (int)wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $post_title,
                'post_name' => $slug,
                'post_content' => $content,
                'post_author' => (int)get_option('monatsblitz_author') ?: 1,
            ]);

            if (is_wp_error($pageId) || $pageId <= 0) {
                return new \WP_Error('page_error', 'Fehler beim Anlegen der Jahresseite', ['status' => 500]);
            }

            $this->copyAllMeta((int)$templatePage->ID, $pageId);
        }

        update_post_meta($pageId, $metaKey, '1');
        update_post_meta($pageId, '_blitzmeisterschaft_year', $year);

        $this->removePageFromMenus((int)$pageId);

        return [
            'success' => true,
            'year' => $year,
            'page_id' => $pageId,
            'updated' => $updated,
        ];
    }

    private function copyAllMeta(int $templatePageId, int $targetPageId): void
    {
        $blacklistMeta = [
            '_thumbnail_id',
            '_edit_last',
            '_edit_lock',
            '_wp_old_slug',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
        ];

        $templateMeta = get_post_meta($templatePageId);
        foreach ($templateMeta as $metaKey => $metaValues) {
            if (in_array($metaKey, $blacklistMeta, true)) {
                continue;
            }

            foreach ($metaValues as $metaValue) {
                add_post_meta($targetPageId, $metaKey, maybe_unserialize($metaValue));
            }
        }
    }

    private function pointsFromRank(int $rank): int
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

    private function buildOverviewTable(array $monthly, bool $hideJanuary): string
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

    private function buildYearRankingTable(array $monthly, array $pointsPerTournament): string
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

    private function removePageFromMenus(int $pageId): void
    {
        if (!function_exists('wp_get_nav_menus')
            || !function_exists('wp_get_nav_menu_items')
            || !function_exists('wp_delete_post')) {
            return;
        }

        $menus = wp_get_nav_menus();

        if (empty($menus)) {
            return;
        }

        foreach ($menus as $menu) {
            $menuId = (int)($menu->term_id ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $items = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
            if (empty($items)) {
                continue;
            }

            foreach ($items as $item) {
                $objectId = (int)($item->object_id ?? 0);
                $itemId = (int)($item->ID ?? 0);
                $itemObject = (string)($item->object ?? '');

                if ($itemId > 0 && $objectId === $pageId && $itemObject === 'page') {
                    wp_delete_post($itemId, true);
                }
            }
        }
    }

}
