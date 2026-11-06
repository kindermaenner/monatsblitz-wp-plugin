<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class TournamentPostFactory
{
    public static function buildPostData(
        string $mode,
        string $iso_date,
        int $post_author_id,
        string $content,
        bool $counts_for_ranking = true,
        int $daily_number = 1,
        string $label = ''
    ): array {
        $is_blitz = BlitzModeService::isBlitzMode($mode);
        $base_title = ($counts_for_ranking && $is_blitz) ? 'Monatsblitz' : 'Turnier';
        $base_slug = ($counts_for_ranking && $is_blitz) ? 'blitz' : 'turnier';

        $clean_label = trim($label);
        if ($clean_label !== '') {
            $post_title = "{$base_title} {$iso_date} - {$clean_label}";
            $slug_suffix = function_exists('sanitize_title')
                ? sanitize_title($clean_label)
                : strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $clean_label));
            $slug = "{$base_slug}-{$iso_date}-{$slug_suffix}";
        } elseif ($daily_number > 1) {
            $post_title = "{$base_title} {$iso_date} (#{$daily_number})";
            $slug = "{$base_slug}-{$iso_date}-{$daily_number}";
        } else {
            $post_title = "{$base_title} {$iso_date}";
            $slug = "{$base_slug}-{$iso_date}";
        }

        $meta_key = $slug;
        $tournament_meta_key = '_monatsblitz_tournament_id';
        $post_time = '23:30:00';
        $post_date_local = $iso_date . ' ' . $post_time;
        $post_date_gmt = get_gmt_from_date($post_date_local);

        $postarr = [
            'post_title'    => $post_title,
            'post_name'     => $slug,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_date'     => $post_date_local,
            'post_date_gmt' => $post_date_gmt,
            'post_author'   => $post_author_id,
        ];

        return [
            'postarr' => $postarr,
            'slug' => $slug,
            'meta_key' => $meta_key,
            'tournament_meta_key' => $tournament_meta_key,
            'post_date_local' => $post_date_local,
            'post_date_gmt' => $post_date_gmt,
        ];
    }
}
