<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class FinalizeTournamentService
{
    private TournamentRepository $repository;
    private TournamentPostManager $postManager;

    public function __construct()
    {
        $this->repository = new TournamentRepository();
        $this->postManager = new TournamentPostManager();
    }

    public function handle($request)
    {
        $tournament_id = $this->getTournamentId($request);
        if (is_wp_error($tournament_id)) {
            return $tournament_id;
        }

        $tournament = $this->repository->loadTournament($tournament_id);
        if (is_wp_error($tournament)) {
            return $tournament;
        }

        $post_author_id = intval(get_option('monatsblitz_author'));
        $template_name = sanitize_text_field(get_option('monatsblitz_template'));

        $date_str = sprintf('%02d.%02d.%04d', $tournament['day'], $tournament['month'], $tournament['year']);
        $iso_date = sprintf('%04d-%02d-%02d', $tournament['year'], $tournament['month'], $tournament['day']);
        $month_name = $this->getMonthName(intval($tournament['month'] ?? 0));

        $results = $this->repository->loadTournamentResults($tournament_id);
        if (is_wp_error($results)) {
            return $results;
        }

        $games = $this->repository->loadTournamentGames($tournament_id);
        $template_data = $this->loadTemplateContent($template_name);

        $template_content = $template_data['content'];
        $template_post = $template_data['post'];

        if ($template_content === '') {
            $template_content = "<h1>{{month_name}} {{year}}</h1>
                                 <p>Die Ergebnisse unseres Blitz-Abends vom {{date}}.</p>
                                 {{ranking_rows}}{{games_list}}";
        }

        $winner_data = $this->extractWinnerData($results);
        $winner_games = $winner_data['player_id']
            ? $this->repository->countWinnerGames($tournament_id, $winner_data['player_id'])
            : 0;

        $winner_data['games_placeholder'] = $winner_games > 0
            ? ('aus ' . esc_html((string)$winner_games))
            : '';

        $ranking_rows = $this->buildRankingRows($results);
        $games_list = $this->buildGamesList($games);
        $players = $this->buildPlayersFromResults($results);

        $mode = esc_html((string)($tournament['mode'] ?? ''));
        $round_count = max(1, intval($tournament['round_count'] ?? 1));
        $table_html = $this->buildTableHtml($players, $games, $round_count);
        if ($table_html === '') {
            $table_html = TournamentContentBuilder::buildResultsTable($results);
        }

        $content = str_replace(
            [
                '{{month_name}}','{{year}}','{{date}}','{{winner_name}}',
                '{{winner_games}}','{{winner_points}}','{{ranking_rows}}',
                '{{games_list}}','{{table}}','{{mode}}','{{round_count}}'
            ],
            [
                esc_html($month_name), esc_html($tournament['year']), esc_html($date_str),
                $winner_data['name'], $winner_data['games_placeholder'], $winner_data['points'],
                $ranking_rows, $games_list, $table_html, $mode,
                esc_html((string)$round_count)
            ],
            $template_content
        );

        if (BlitzModeService::isBlitzMode((string)($tournament['mode'] ?? ''))) {
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

        $post_result = $this->postManager->createOrUpdatePost(
            $postarr,
            $slug,
            $meta_key,
            $tournament_meta_key,
            $tournament_id,
            (string)($tournament['mode'] ?? ''),
            $iso_date
        );

        if (is_wp_error($post_result)) {
            return $post_result;
        }

        $post_id = (int)$post_result['post_id'];
        $updated = (bool)$post_result['updated'];

        update_post_meta($post_id, $meta_key, '1');
        update_post_meta($post_id, $tournament_meta_key, (string)$tournament_id);

        if ($template_post && !is_wp_error($template_post)) {
            $this->postManager->copyTemplateMetaAndTaxonomies((int)$template_post->ID, $post_id);
        }

        $year_page = null;
        if (BlitzModeService::isBlitzMode((string)($tournament['mode'] ?? ''))) {
            $year_page = (new YearStaticPageHandler())->createOrUpdate((int)$tournament['year']);
        }

        return [
            'success'       => true,
            'tournament_id' => $tournament_id,
            'post_id'       => $post_id,
            'post_updated'  => $updated,
            'year_page'     => is_wp_error($year_page) ? null : $year_page,
            'published'     => true,
        ];
    }

    private function getTournamentId($request)
    {
        $params = $request->get_json_params();
        $tournament_id = intval($params['tournament_id'] ?? 0);

        if (!$tournament_id) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        return $tournament_id;
    }

    private function loadTournament(int $tournament_id)
    {
        global $wpdb;

        $t = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, year, month, day, mode, round_count
                 FROM {$wpdb->prefix}monatsblitz_tournaments
                 WHERE id = %d",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!$t) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        return $t;
    }

    private function loadTournamentResults(int $tournament_id)
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.player_id, r.points, r.rank, p.forename, p.surname
                 FROM {$wpdb->prefix}monatsblitz_results r
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p ON r.player_id = p.id
                 WHERE r.tournament_id = %d
                 ORDER BY r.rank ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return new \WP_Error('no_results', 'Keine Ergebnisse vorhanden', ['status' => 400]);
        }

        return $results;
    }

    private function loadTournamentGames(int $tournament_id): array
    {
        global $wpdb;

        $games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT g.player1_id, g.player2_id, g.leg_type, g.result,
                        p1.forename as p1_forename, p1.surname as p1_surname,
                        p2.forename as p2_forename, p2.surname as p2_surname
                 FROM {$wpdb->prefix}monatsblitz_games g
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p1 ON g.player1_id = p1.id
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p2 ON g.player2_id = p2.id
                 WHERE g.tournament_id = %d
                 ORDER BY g.id",
                $tournament_id
            ),
            ARRAY_A
        );

        return is_array($games) ? $games : [];
    }

    private function loadTemplateContent(string $template_name): array
    {
        $template_post = get_page_by_title($template_name, OBJECT, 'post');
        $template_content = '';

        if ($template_post && !is_wp_error($template_post)) {
            $template_content = $template_post->post_content;
        } else {
            $template_path = MB_PLUGIN_PATH . 'templates/post-template.html';
            if (file_exists($template_path)) {
                $template_content = file_get_contents($template_path);
            }
        }

        return [
            'content' => $template_content,
            'post' => $template_post,
        ];
    }

    private function getMonthName(int $month): string
    {
        $month_names = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $month_names[$month] ?? '';
    }

    private function extractWinnerData(array $results): array
    {
        $winner_name = '';
        $winner_points = '';
        $winner_player_id = 0;

        if (!empty($results)) {
            $winner = $results[0];
            $winner_name = esc_html(trim($winner['forename'] . ' ' . $winner['surname']));
            $winner_points = esc_html($winner['points']);
            $winner_player_id = intval($winner['player_id']);
        }

        return [
            'name' => $winner_name,
            'points' => $winner_points,
            'player_id' => $winner_player_id,
        ];
    }


    private function buildRankingRows(array $results): string
    {
        $ranking_rows = '';
        $position = 1;

        foreach ($results as $result) {
            $name = esc_html(trim($result['forename'] . ' ' . $result['surname']));
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

    private function buildGamesList(array $games): string
    {
        $games_list = '';

        foreach ($games as $game) {
            $p1 = esc_html(trim($game['p1_forename'] . ' ' . $game['p1_surname']));
            $p2 = esc_html(trim($game['p2_forename'] . ' ' . $game['p2_surname']));
            $res = esc_html($game['result']);
            $games_list .= "<li>{$p1} vs {$p2}: {$res}</li>";
        }

        return $games_list;
    }

    private function buildPlayersFromResults(array $results): array
    {
        $players = [];

        foreach ($results as $result) {
            $players[] = [
                'id' => intval($result['player_id']),
                'name' => esc_html(trim($result['forename'] . ' ' . $result['surname'])),
                'points' => esc_html($result['points']),
                'rank' => esc_html($result['rank']),
            ];
        }

        return $players;
    }

    private function buildTableHtml(array $players, array $games, int $round_count): string
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

    private function createOrUpdatePost(
        array $postarr,
        string $slug,
        string $meta_key,
        string $tournament_meta_key,
        int $tournament_id,
        string $mode,
        string $iso_date
    ) {
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

    private function copyTemplateMetaAndTaxonomies(int $template_post_id, int $target_post_id): void
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
