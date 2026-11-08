<?php

declare(strict_types=1);

use Monatsblitz\Output\FinalizeTournamentService;

it('returns an error when tournament_id is missing', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $service = new FinalizeTournamentService();
    $result = $service->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('invalid_data');
});

it('returns an error when the tournament cannot be loaded', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 15];
        }
    };

    $service = new FinalizeTournamentService();
    $result = $service->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('not_found');
});

it('finalizes a tournament successfully using the service', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateSuccess';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 15,
        'post_title' => 'TemplateSuccess',
        'post_content' => '<article>{{table}}</article> table.monatsblitz { border-collapse: collapse; width: 100%; text-align: center; font-size: 0.95rem; margin: 1em 0; } table.monatsblitz th, table.monatsblitz td { border: 1px solid #444; padding: 6px 8px; } table.monatsblitz th { background-color: #f0f0f0; font-weight: 600; } table.monatsblitz caption { font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5em; } table.monatsblitz tbody tr:nth-child(even) { background-color: #fafafa; }',
    ];
    $GLOBALS['mb_test_next_post_id'] = 810;
    $GLOBALS['mb_test_get_posts_queue'] = [[], [], [], []];

    $wpdb->get_row_result = [
        'id' => 15,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 5.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
            ['player_id' => 2, 'points' => 4.0, 'rank' => 2, 'forename' => 'Erika', 'surname' => 'Beispiel'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 1;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 15];
        }
    };

    $service = new FinalizeTournamentService();
    $result = $service->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['post_id'])->toBe(810);
    expect($GLOBALS['mb_test_last_inserted_post'])->not->toBeNull();
    expect($GLOBALS['mb_test_last_inserted_post']['post_content'])->toContain('<article>');
    expect($GLOBALS['mb_test_last_inserted_post']['post_content'])->not->toContain('table.monatsblitz {');
    expect($wpdb->last_update_table)->toBe('wp_monatsblitz_tournaments');
    expect($wpdb->last_update_data['status'])->toBe('finalized');
    expect($wpdb->last_update_where['id'])->toBe(15);
});

it('updates round_count and cleans up exceeding games when round_count is supplied in finalize request', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateSuccess';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 15,
        'post_title' => 'TemplateSuccess',
        'post_content' => '<article>{{table}}</article>',
    ];
    $GLOBALS['mb_test_next_post_id'] = 811;
    $GLOBALS['mb_test_get_posts_queue'] = [[], [], [], []];

    $wpdb->get_row_result = [
        'id' => 15,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => 'schweizer',
        'round_count' => 2,
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 1.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 1;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 15,
                'round_count' => 1,
            ];
        }
    };

    $service = new FinalizeTournamentService();
    $result = $service->handle($request);

    expect($result['success'])->toBeTrue();
    expect($wpdb->queries)->toContain('DELETE FROM wp_monatsblitz_games WHERE tournament_id = %d AND leg_type > %d');
});

it('uses monatsblitz_template_non_ranking when counts_for_ranking is 0', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateRanking';
    $GLOBALS['mb_test_options']['monatsblitz_template_non_ranking'] = 'TemplateNonRanking';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 20,
        'post_title' => 'TemplateNonRanking',
        'post_content' => '<section>{{table}}</section>',
    ];
    $GLOBALS['mb_test_next_post_id'] = 820;
    $GLOBALS['mb_test_get_posts_queue'] = [[], [], [], []];

    $wpdb->get_row_result = [
        'id' => 16,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => '5+0',
        'round_count' => 1,
        'counts_for_ranking' => 0,
        'daily_number' => 2,
        'label' => 'Nachblitz',
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 3.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [],
    ];
    $wpdb->get_var_result = 0;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 16];
        }
    };

    $service = new FinalizeTournamentService();
    $result = $service->handle($request);

    expect($result['success'])->toBeTrue();
    expect($GLOBALS['mb_test_last_inserted_post']['post_title'])->toBe('Turnier 2026-08-12 - Nachblitz');
    expect($GLOBALS['mb_test_last_inserted_post']['post_content'])->toContain('<section>');
});

it('does not publish or finalize a tournament without results', function () {
    global $wpdb;

    $wpdb->get_row_result = [
        'id' => 21,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => 'schweizer',
        'round_count' => 3,
    ];
    $wpdb->get_results_queue = [[]];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 21];
        }
    };

    $result = (new FinalizeTournamentService())->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('no_results')
        ->and($GLOBALS['mb_test_last_inserted_post'])->toBeNull()
        ->and($wpdb->update_calls)->toBeEmpty();
});

it('records withdrawn players and clamps a non-positive round count before publishing', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateSuccess';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 22,
        'post_title' => 'TemplateSuccess',
        'post_content' => '<article>{{round_count}}</article>',
    ];
    $wpdb->get_row_result = [
        'id' => 22,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => 'schweizer',
        'round_count' => 4,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [],
    ];

    $request = new WP_REST_Request([
        'tournament_id' => 22,
        'withdrawn_player_ids' => [7, 8],
        'round_count' => 0,
    ]);

    $result = (new FinalizeTournamentService())->handle($request);

    expect($result['success'])->toBeTrue()
        ->and($GLOBALS['mb_test_last_inserted_post']['post_content'])->toContain('<article>1</article>');

    expect($wpdb->update_calls)->toContain(
        [
            'table' => 'wp_monatsblitz_tournament_players',
            'data' => ['has_left' => 1],
            'where' => ['tournament_id' => 22, 'player_id' => 7],
        ],
        [
            'table' => 'wp_monatsblitz_tournament_players',
            'data' => ['has_left' => 1],
            'where' => ['tournament_id' => 22, 'player_id' => 8],
        ],
        [
            'table' => 'wp_monatsblitz_tournaments',
            'data' => ['round_count' => 1],
            'where' => ['id' => 22],
        ]
    );
});

it('does not mark a tournament finalized when publishing fails', function () {
    global $wpdb;

    $wpdb->get_row_result = [
        'id' => 23,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [],
    ];

    $postManager = new class extends \Monatsblitz\Output\TournamentPostManager {
        public function createOrUpdatePost(
            array $postarr,
            string $slug,
            string $meta_key,
            string $tournament_meta_key,
            int $tournament_id,
            string $mode,
            string $iso_date
        ): array|\WP_Error {
            return new WP_Error('post_error', 'Publishing failed');
        }
    };
    $service = new FinalizeTournamentService(postManager: $postManager);
    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 23];
        }
    };

    $result = $service->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('post_error')
        ->and($wpdb->update_calls)->toBeEmpty()
        ->and($GLOBALS['mb_test_post_meta_updates'])->toBeEmpty();
});

it('executes deleteGamesExceedingRoundCount with default round_count during finalize and never wipes all games', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateSuccess';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 24,
        'post_title' => 'TemplateSuccess',
        'post_content' => '<article>{{table}}</article>',
    ];
    $GLOBALS['mb_test_next_post_id'] = 824;
    $GLOBALS['mb_test_get_posts_queue'] = [[], [], [], []];

    $wpdb->get_row_result = [
        'id' => 24,
        'year' => 2026,
        'month' => 8,
        'day' => 12,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 1.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 1;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 24,
                // no round_count override: should use tournament's round_count (1)
            ];
        }
    };

    $service = new FinalizeTournamentService();
    $result = $service->handle($request);

    expect($result['success'])->toBeTrue();
    expect($wpdb->queries)->toContain('DELETE FROM wp_monatsblitz_games WHERE tournament_id = %d AND leg_type > %d');
    expect($wpdb->last_delete_table)->toBeNull();

    // Verify no unqualified delete exists for games or results
    foreach ($wpdb->queries as $q) {
        if (str_contains($q, 'DELETE FROM wp_monatsblitz_games')) {
            expect($q)->toContain('leg_type > %d');
        }
        expect($q)->not->toContain('DELETE FROM wp_monatsblitz_results');
        expect($q)->not->toContain('DELETE FROM wp_monatsblitz_tournament_players');
    }
});

