<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class TournamentPostManager
{
    public function createOrUpdatePost(
        array $postarr,
        string $slug,
        string $meta_key,
        string $tournament_meta_key,
        int $tournament_id,
        string $mode,
        string $iso_date
    ): array|\WP_Error {
        $existing_posts = get_posts([
            'post_type' => 'post',
            'meta_key' => $tournament_meta_key,
            'meta_value' => (string)$tournament_id,
            'numberposts' => 1,
        ]);

        if (empty($existing_posts)) {
            $existing_posts = get_posts([
                'post_type' => 'post',
                'name' => $slug,
                'numberposts' => 1,
            ]);
        }

        if (empty($existing_posts) && BlitzModeService::isBlitzMode($mode)) {
            $existing_posts = get_posts([
                'post_type' => 'post',
                'name' => 'monatsblitz-' . $iso_date,
                'numberposts' => 1,
            ]);
        }

        if (empty($existing_posts)) {
            $existing_posts = get_posts([
                'post_type' => 'post',
                'meta_key' => $meta_key,
                'meta_value' => '1',
                'numberposts' => 1,
            ]);
        }

        $updated = false;
        if (!empty($existing_posts)) {
            $postarr['ID'] = (int)$existing_posts[0]->ID;
            $post_id = wp_update_post($postarr);
            $updated = true;
        } else {
            $post_id = wp_insert_post($postarr);
        }

        if (is_wp_error($post_id)) {
            return new \WP_Error('post_error', 'Fehler beim Anlegen des Beitrags', ['status' => 500]);
        }

        return [
            'post_id' => (int)$post_id,
            'updated' => $updated,
        ];
    }

    public function copyTemplateMetaAndTaxonomies(int $template_post_id, int $target_post_id): void
    {
        $blacklist_meta = [
            '_thumbnail_id',
            '_edit_last',
            '_edit_lock',
            '_wp_old_slug',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
        ];

        $template_meta = get_post_meta($template_post_id);
        foreach ($template_meta as $meta_key => $meta_values) {
            if (in_array($meta_key, $blacklist_meta, true)) {
                continue;
            }

            foreach ($meta_values as $meta_value) {
                add_post_meta($target_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }

        $taxonomies = get_object_taxonomies('post', 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($template_post_id, $taxonomy, ['fields' => 'slugs']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($target_post_id, $terms, $taxonomy, false);
            }
        }
    }
}
