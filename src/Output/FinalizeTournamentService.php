<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class FinalizeTournamentService
{
    private TournamentRepository $repository;
    private TournamentPostManager $postManager;
    private TournamentTemplateLoader $templateLoader;
    private TournamentPageContentBuilder $pageBuilder;
    private TournamentViewBuilder $viewBuilder;

    public function __construct(
        ?TournamentRepository $repository = null,
        ?TournamentPostManager $postManager = null,
        ?TournamentTemplateLoader $templateLoader = null,
        ?TournamentPageContentBuilder $pageBuilder = null,
        ?TournamentViewBuilder $viewBuilder = null
    ) {
        $this->repository = $repository ?? new TournamentRepository();
        $this->postManager = $postManager ?? new TournamentPostManager();
        $this->templateLoader = $templateLoader ?? new TournamentTemplateLoader();
        $this->pageBuilder = $pageBuilder ?? new TournamentPageContentBuilder();
        $this->viewBuilder = $viewBuilder ?? new TournamentViewBuilder();
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
        $template_name = (string) sanitize_text_field(get_option('monatsblitz_template'));

        $date_str = sprintf('%02d.%02d.%04d', $tournament['day'], $tournament['month'], $tournament['year']);
        $iso_date = sprintf('%04d-%02d-%02d', $tournament['year'], $tournament['month'], $tournament['day']);
        $month_name = $this->getMonthName(intval($tournament['month'] ?? 0));

        $results = $this->repository->loadTournamentResults($tournament_id);
        if (is_wp_error($results)) {
            return $results;
        }

        $games = $this->repository->loadTournamentGames($tournament_id);
        $template_data = $this->templateLoader->load($template_name);

        $template_content = $template_data['content'];
        $template_post = $template_data['post'];

        if ($template_content === '') {
            $template_content = "<h1>{{month_name}} {{year}}</h1>
                                 <p>Die Ergebnisse unseres Blitz-Abends vom {{date}}.</p>
                                 {{ranking_rows}}{{games_list}}";
        }

        $winner_games = $this->repository->countWinnerGames(
            $tournament_id,
            intval($results[0]['player_id'] ?? 0)
        );

        $winner_data = $this->pageBuilder->buildMetadata($results, $winner_games);
        $ranking_rows = $this->viewBuilder->buildRankingRows($results);
        $games_list = $this->viewBuilder->buildGamesList($games);
        $players = $this->viewBuilder->buildPlayersFromResults($results);

        $mode = esc_html((string)($tournament['mode'] ?? ''));
        $round_count = max(1, intval($tournament['round_count'] ?? 1));
        $table_html = $this->viewBuilder->buildTableHtml($players, $games, $round_count);
        if ($table_html === '') {
            $table_html = TournamentContentBuilder::buildResultsTable($results);
        }

        $content = $this->pageBuilder->buildContent(
            $template_content,
            $this->pageBuilder->buildPlaceholders(
                $month_name,
                $date_str,
                (string)$tournament['year'],
                $mode,
                (string)$round_count,
                $ranking_rows,
                $games_list,
                $table_html,
                $winner_data
            )
        );

        $post_data = TournamentPostFactory::buildPostData((string)($tournament['mode'] ?? ''), $iso_date, $post_author_id, $content);

        $postarr = $post_data['postarr'];
        $slug = $post_data['slug'];
        $meta_key = $post_data['meta_key'];
        $tournament_meta_key = $post_data['tournament_meta_key'];

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
            $template_thumbnail_id = get_post_thumbnail_id($template_post->ID);
            if ($template_thumbnail_id) {
                set_post_thumbnail($post_id, $template_thumbnail_id);
            }

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

    private function getMonthName(int $month): string
    {
        $month_names = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return $month_names[$month] ?? '';
    }
}
