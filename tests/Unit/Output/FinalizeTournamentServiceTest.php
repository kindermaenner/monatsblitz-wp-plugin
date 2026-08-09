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
        'post_content' => '<article>{{table}}</article>',
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
});