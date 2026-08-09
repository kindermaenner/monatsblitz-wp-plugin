<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class FinalizeTournamentHandler
{
    private TournamentPostManager $postManager;

    public function __construct()
    {
        $this->postManager = new TournamentPostManager();
    }

    public function handle($request)
    {
        global $wpdb;

        $tournament_id = $this->getTournamentId($request);
        if (!$tournament_id) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        $tournament = $this->loadTournament($wpdb, $tournament_id);
        if (!$tournament) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        $post_author_id = intval(get_option('monatsblitz_author'));
        $template_name = (string) sanitize_text_field(get_option('monatsblitz_template'));
        $date_str = sprintf('%02d.%02d.%04d', $tournament['day'], $tournament['month'], $tournament['year']);
        $iso_date = sprintf('%04d-%02d-%02d', $tournament['year'], $tournament['month'], $tournament['day']);
        $month_name = $this->resolveMonthName(intval($tournament['month'] ?? 0));

        [$template_post, $template_content] = $this->loadTemplate($template_name);
        $results = $this->loadResults($wpdb, $tournament_id);
        if (empty($results)) {
            return new \WP_Error('no_results', 'Keine Ergebnisse vorhanden', ['status' => 400]);
        }

        $games = $this->loadGames($wpdb, $tournament_id);
        $winner_data = $this->resolveWinner($wpdb, $results, $tournament_id);
        $ranking_rows = $this->buildRankingRows($results);
        $games_list = $this->buildGamesList($games);
        $players = $this->buildPlayers($results);
        $mode = esc_html((string)($tournament['mode'] ?? ''));
        $round_count = max(1, intval($tournament['round_count'] ?? 1));
        $table_html = $this->buildTableHtml($players, $games, $round_count, $results);

        $content = str_replace(
            [
                '{{month_name}}','{{year}}','{{date}}','{{winner_name}}',
                '{{winner_games}}','{{winner_points}}','{{ranking_rows}}',
                '{{games_list}}','{{table}}','{{mode}}','{{round_count}}'
            ],
            [
                esc_html($month_name), esc_html($tournament['year']), esc_html($date_str),
                esc_html($winner_data['name']), $winner_data['games'], esc_html($winner_data['points']),
                $ranking_rows, $games_list, $table_html, $mode,
                esc_html((string)$round_count)
            ],
            $template_content
        );

        [$post_title, $slug] = $this->resolvePostTitleAndSlug($mode, $iso_date);
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
        $this->copyTemplateAssets($template_post, $post_id);

        $year_page = null;
        if (BlitzModeService::isBlitzMode($mode)) {
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

    private function getTournamentId($request): int
    {
        $params = $request->get_json_params();
        return intval($params['tournament_id'] ?? 0);
    }

    private function loadTournament($wpdb, int $tournament_id): ?array
    {
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, year, month, day, mode, round_count
                 FROM {$wpdb->prefix}monatsblitz_tournaments
                 WHERE id = %d",
                $tournament_id
            ),
            ARRAY_A
        );
    }

    private function loadResults($wpdb, int $tournament_id): array
    {
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.player_id, r.points, r.rank, p.forename, p.surname
                 FROM {$wpdb->prefix}monatsblitz_results r
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p ON r.player_id = p.id
                 WHERE r.tournament_id = %d
                 ORDER BY r.rank ASC",
                $tournament_id
            ),
            ARRAY_A
        ) ?: [];
    }

    private function loadGames($wpdb, int $tournament_id): array
    {
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

    private function loadTemplate(string $template_name): array
    {
        $template_post = get_page_by_title($template_name, OBJECT, 'post');
        $template_content = '';

        if ($template_post && !is_wp_error($template_post)) {
            $template_content = $template_post->post_content;
        }

        if ($template_content === '') {
            $template_path = MB_PLUGIN_PATH . 'templates/post-template.html';
            if (file_exists($template_path)) {
                $template_content = file_get_contents($template_path);
            }
        }

        if ($template_content === '') {
            $template_content = "<h1>{{month_name}} {{year}}</h1>
                                 <p>Die Ergebnisse unseres Blitz-Abends vom {{date}}.</p>
                                 {{ranking_rows}}{{games_list}}";
        }

        return [$template_post, $template_content];
    }

    private function resolveMonthName(int $month): string
    {
        $monthNames = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $monthNames[$month] ?? '';
    }

    private function resolveWinner($wpdb, array $results, int $tournament_id): array
    {
        $name = '';
        $points = '';
        $games = 0;

        if (!empty($results)) {
            $winner = $results[0];
            $name = esc_html(trim($winner['forename'] . ' ' . $winner['surname']));
            $points = esc_html($winner['points']);
            $winner_player_id = intval($winner['player_id']);

            if ($winner_player_id) {
                $games = intval($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}monatsblitz_games
                         WHERE tournament_id = %d
                           AND (player1_id = %d OR player2_id = %d)",
                        $tournament_id,
                        $winner_player_id,
                        $winner_player_id
                    )
                ));
            }
        }

        return [
            'name' => $name,
            'points' => $points,
            'games' => $games ? 'aus ' . esc_html((string)$games) : '',
        ];
    }

    private function buildRankingRows(array $results): string
    {
        $rows = '';
        $index = 1;

        foreach ($results as $result) {
            $name = esc_html(trim($result['forename'] . ' ' . $result['surname']));
            $points = esc_html($result['points']);
            $rank = esc_html($result['rank']);

            $rows .= "<tr>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$index}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$name}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$points}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$rank}</td>
            </tr>";

            $index++;
        }

        return $rows;
    }

    private function buildGamesList(array $games): string
    {
        $list = '';

        foreach ($games as $game) {
            $player1 = esc_html(trim($game['p1_forename'] . ' ' . $game['p1_surname']));
            $player2 = esc_html(trim($game['p2_forename'] . ' ' . $game['p2_surname']));
            $result = esc_html($game['result']);
            $list .= "<li>{$player1} vs {$player2}: {$result}</li>";
        }

        return $list;
    }

    private function buildPlayers(array $results): array
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

    private function buildTableHtml(array $players, array $games, int $round_count, array $results): string
    {
        if (empty($games)) {
            return TournamentContentBuilder::buildResultsTable($results);
        }

        if ($round_count === 1) {
            return TournamentContentBuilder::buildCrossTable($players, $games, true);
        }

        $html = '';
        for ($round = 1; $round <= $round_count; $round++) {
            $html .= '<h3>Runde ' . $round . '</h3>';
            $html .= TournamentContentBuilder::buildCrossTable($players, $games, false, $round);
        }

        $html .= TournamentContentBuilder::buildSummaryTable($players);
        return $html;
    }

    private function resolvePostTitleAndSlug(string $mode, string $iso_date): array
    {
        if (BlitzModeService::isBlitzMode($mode)) {
            return ['Monatsblitz ' . $iso_date, 'blitz-' . $iso_date];
        }

        return ['Turnier ' . $iso_date, 'turnier-' . $iso_date];
    }

    private function copyTemplateAssets($template_post, int $post_id): void
    {
        if (!$template_post || is_wp_error($template_post)) {
            return;
        }

        $template_thumbnail_id = get_post_thumbnail_id($template_post->ID);
        if ($template_thumbnail_id) {
            set_post_thumbnail($post_id, $template_thumbnail_id);
        }

        $this->postManager->copyTemplateMetaAndTaxonomies((int)$template_post->ID, $post_id);
    }

    public function normalize_items($request)
    {
        return RequestNormalizer::normalizeItems($request);
    }

    public static function normalize_result_cell(string $result, bool $invert): string
    {
        return TournamentContentBuilder::normalizeResultCell($result, $invert);
    }

    public static function normalize_string_list($input)
    {
        return RequestNormalizer::normalizeStringList($input);
    }
}
