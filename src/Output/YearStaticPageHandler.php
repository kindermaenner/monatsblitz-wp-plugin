<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class YearStaticPageHandler
{
    private YearStaticContentBuilder $contentBuilder;

    public function __construct(?YearStaticContentBuilder $contentBuilder = null)
    {
        $this->contentBuilder = $contentBuilder ?? new YearStaticContentBuilder();
    }

    public function createOrUpdate(int $year, bool $force = false): array|\WP_Error
    {
        global $wpdb;

        if ($year < 2000 || $year > 2100) {
            return new \WP_Error(
                'invalid_data',
                sprintf('Ungultiges Jahr: "%d".', $year),
                ['status' => 400]
            );
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

        [$monthly, $pointsPerTournament] = $this->contentBuilder->aggregateMonthlyStats($rows);

        $hideJanuary = (bool)get_option('monatsblitz_hide_january_overview', false);

        $content = $this->contentBuilder->buildContent(
            (string)$templatePage->post_content,
            $year,
            $monthly,
            $pointsPerTournament,
            $hideJanuary
        );

        return $this->upsertYearPage($templatePage, $slug, $metaKey, $content, $year);
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

    /**
     * Create or update the yearly page and return result array.
     */
    private function upsertYearPage($templatePage, string $slug, string $metaKey, string $content, int $year): array|\WP_Error
    {
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

    private function removePageFromMenus(int $pageId): void
    {
        if (
            !function_exists('wp_get_nav_menus')
            || !function_exists('wp_get_nav_menu_items')
            || !function_exists('wp_delete_post')
        ) {
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
