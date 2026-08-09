<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class TournamentPostFactory
{
    public static function buildPostData(string $mode, string $iso_date, int $post_author_id, string $content): array
    {
        if (BlitzModeService::isBlitzMode($mode)) {
            $post_title = 'Monatsblitz ' . $iso_date;
            $slug = 'blitz-' . $iso_date;
        } else {
            $post_title = 'Turnier ' . $iso_date;
            $slug = 'turnier-' . $iso_date;
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
