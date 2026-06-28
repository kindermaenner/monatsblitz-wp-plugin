<?php

declare(strict_types=1);

use Monatsblitz\Output\FinalizeTournamentHandler;

it('normalizes a single string to an array', function () {
    $request = new class {
        public function get_json_params() {
            return ['items' => 'item1'];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->normalize_items($request);

    expect($result['count'])->toBe(1);
    expect($result['items'])->toBe(['item1']);
});

it('keeps an array of strings and removes empty values', function () {
    $request = new class {
        public function get_json_params() {
            return ['items' => ['item1', '', '  ', 'item2']];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->normalize_items($request);

    expect($result['count'])->toBe(2);
    expect($result['items'])->toBe(['item1', 'item2']);
});

it('rejects null input for normalization', function () {
    $request = new class {
        public function get_json_params() {
            return ['items' => null];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->normalize_items($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('rejects invalid normalization input types', function () {
    $request = new class {
        public function get_json_params() {
            return ['items' => ['item1', 123]];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->normalize_items($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails finalize when tournament_id is missing', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);
 
    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails finalize when tournament is not found', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 10];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails finalize when tournament has no results', function () {
    global $wpdb;

    $wpdb->get_row_result = [
        'id' => 9,
        'year' => 2026,
        'month' => 6,
        'day' => 24,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [],
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 9];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('no_results');
});

it('finalizes single-round tournament and writes classic table', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateSingle';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 11,
        'post_title' => 'TemplateSingle',
        'post_content' => '<article><h1>{{month_name}} {{year}}</h1>{{table}}</article>',
    ];
    $GLOBALS['mb_test_next_post_id'] = 555;

    $wpdb->get_row_result = [
        'id' => 9,
        'year' => 2026,
        'month' => 6,
        'day' => 24,
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
            return ['tournament_id' => 9];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['post_id'])->toBe(555);
    expect($GLOBALS['mb_test_last_inserted_post'])->not->toBeNull();
    expect($GLOBALS['mb_test_last_inserted_post']['post_content'])->toContain('Juni 2026');
    expect($GLOBALS['mb_test_last_inserted_post']['post_content'])->toContain('<th>Punkte</th><th>Platz</th>');
    expect($GLOBALS['mb_test_last_inserted_post']['post_content'])->not->toContain('Runde 1');
});

it('finalizes multi-round tournament with per-round tables and summary', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateX';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 42,
        'post_title' => 'TemplateX',
        'post_content' => '<p>{{mode}}/{{round_count}}</p>{{table}}',
    ];

    $wpdb->get_row_result = [
        'id' => 12,
        'year' => 2026,
        'month' => 6,
        'day' => 24,
        'mode' => 'rundenturnier',
        'round_count' => 2,
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 3.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
            ['player_id' => 2, 'points' => 2.0, 'rank' => 2, 'forename' => 'Erika', 'surname' => 'Beispiel'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 2, 'result' => '0:1', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 2;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 12];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'];

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('rundenturnier/2');
    expect($content)->toContain('<h3>Runde 1</h3>');
    expect($content)->toContain('<h3>Runde 2</h3>');
    expect($content)->toContain('<h3>Gesamtergebnis</h3>');
    expect($content)->toContain('Gesamtpunkte');

    $round1Section = explode('<h3>Runde 2</h3>', explode('<h3>Runde 1</h3>', $content)[1])[0];
    $round2Section = explode('<h3>Gesamtergebnis</h3>', explode('<h3>Runde 2</h3>', $content)[1])[0];

    expect($round1Section)->not->toContain('<th>Punkte</th><th>Platz</th>');
    expect($round2Section)->not->toContain('<th>Punkte</th><th>Platz</th>');
    expect($content)->toContain('<th>Gesamtpunkte</th><th>Platz</th>');
});

it('renders forfeit results as plus and minus cells using the same inversion logic as wins and losses', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateForfeit';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 77,
        'post_title' => 'TemplateForfeit',
        'post_content' => '<div>{{table}}</div>',
    ];
    $GLOBALS['mb_test_next_post_id'] = 777;

    $wpdb->get_row_result = [
        'id' => 21,
        'year' => 2026,
        'month' => 6,
        'day' => 25,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 1.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
            ['player_id' => 2, 'points' => 0.0, 'rank' => 2, 'forename' => 'Erika', 'surname' => 'Beispiel'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '+:-', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 1;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 21];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'];

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('>+<');
    expect($content)->toContain('>-<');
    expect($content)->not->toContain('+:-');
    expect($content)->not->toContain('-:+');
});

it('renders missing cross-table results as grey cells', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplatePending';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 91,
        'post_title' => 'TemplatePending',
        'post_content' => '<div>{{table}}</div>',
    ];

    $wpdb->get_row_result = [
        'id' => 31,
        'year' => 2026,
        'month' => 6,
        'day' => 25,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 1.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
            ['player_id' => 2, 'points' => 0.0, 'rank' => 2, 'forename' => 'Erika', 'surname' => 'Beispiel'],
            ['player_id' => 3, 'points' => 0.0, 'rank' => 3, 'forename' => 'Paul', 'surname' => 'Probe'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 1;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 31];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'];

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('class="mb-cell-empty mb-cell-pending"');
    expect($content)->toContain('class="mb-cell-empty mb-cell-diagonal"');
    expect($content)->toContain('background-color:#eeeeee !important; color:#666666 !important;');
    expect($content)->toContain('&nbsp;');
});

