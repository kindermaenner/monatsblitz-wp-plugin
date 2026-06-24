<?php

use monatsblitz\MB_API;

it('can instantiate the API class', function () {
    $api = new MB_API();
    expect($api)->toBeInstanceOf(MB_API::class);
});

it('fails when player data is missing', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $result = MB_API::create_player($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('returns existing player if already exists', function () {
    global $wpdb;

    $wpdb->get_var_result = 123;

    $request = new class {
        public function get_json_params() {
            return [
                'forename' => 'Max',
                'surname' => 'Mustermann'
            ];
        }
    };

    $result = MB_API::create_player($request);

    expect($result['success'])->toBeTrue();
    expect($result['player_id'])->toBe(123);
});

it('creates a new player if not exists', function () {
    global $wpdb;

    $wpdb->insert_id = 999;

    $request = new class {
        public function get_json_params() {
            return [
                'forename' => 'Max',
                'surname' => 'Mustermann'
            ];
        }
    };

    $result = MB_API::create_player($request);

    expect($result['success'])->toBeTrue();
    expect($result['player_id'])->toBe(999);
});

it('fails on invalid date', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => 'invalid'];
        }
    };

    $result = MB_API::create_tournament($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('creates tournament with mode and default round_count', function () {
    global $wpdb;

    $wpdb->insert_id = 77;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer'
            ];
        }
    };

    $result = MB_API::create_tournament($request);

    expect($result['success'])->toBeTrue();
    expect($result['tournament_id'])->toBe(77);
    expect($wpdb->last_insert_data['mode'])->toBe('schweizer');
    expect($wpdb->last_insert_data['round_count'])->toBe(1);
});

it('fails when tournament mode is missing', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => '2026-06-24'];
        }
    };

    $result = MB_API::create_tournament($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails when game data is missing', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $result = MB_API::create_game($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('creates game with default leg_type', function () {
    global $wpdb;

    $wpdb->get_var_result = 1;
    $wpdb->insert_id = 12;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'result' => '1-0'
            ];
        }
    };

    $result = MB_API::create_game($request);

    expect($result['success'])->toBeTrue();
    expect($wpdb->last_insert_data['leg_type'])->toBe(1);
});

it('creates game with explicit leg_type', function () {
    global $wpdb;

    $wpdb->get_var_result = 1;
    $wpdb->insert_id = 13;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'leg_type' => 2,
                'result' => '0-1'
            ];
        }
    };

    $result = MB_API::create_game($request);

    expect($result['success'])->toBeTrue();
    expect($wpdb->last_insert_data['leg_type'])->toBe(2);
});

it('returns player list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'forename' => 'Max', 'surname' => 'Mustermann']
    ];

    $result = MB_API::get_players();

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
});

it('returns tournament list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'year' => 2024, 'month' => 10, 'day' => 5]
    ];

    $result = MB_API::get_tournaments();

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
});

it('verifies api key successfully', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'secret';

    $result = MB_API::verify_api_key();

    expect($result)->toBeTrue();
});

it('fails api key verification on mismatch', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'wrong';

    $result = MB_API::verify_api_key();

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails when round_count is below one', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer',
                'round_count' => 0
            ];
        }
    };

    $result = MB_API::create_tournament($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('creates tournament with explicit round_count', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'rundenturnier',
                'round_count' => 3
            ];
        }
    };

    MB_API::create_tournament($request);

    expect($wpdb->last_insert_data['round_count'])->toBe(3);
});

it('fails when leg_type is invalid', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'leg_type' => 0,
                'result' => '1-0'
            ];
        }
    };

    $result = MB_API::create_game($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails when tournament does not exist for game', function () {
    global $wpdb;

    $wpdb->get_var_result = null;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'result' => '1-0'
            ];
        }
    };

    $result = MB_API::create_game($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('returns tournament by id', function () {
    global $wpdb;

    $wpdb->get_row_result = ['id' => 7, 'year' => 2026, 'month' => 6, 'day' => 24, 'mode' => 'schweizer', 'round_count' => 2];

    $result = MB_API::get_tournament(['id' => 7]);

    expect($result)->toBeArray();
    expect($result['id'])->toBe(7);
});

it('fails when tournament id is unknown', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $result = MB_API::get_tournament(['id' => 999]);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('returns games list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'tournament_id' => 1, 'player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1-0']
    ];

    $result = MB_API::get_games(['tournament_id' => 1]);

    expect($result)->toBeArray();
    expect($result[0]['leg_type'])->toBe(1);
});

it('fails create_result when ids are missing', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 0,
                'player_id' => 0,
                'points' => 0,
                'rank' => 0
            ];
        }
    };

    $result = MB_API::create_result($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails create_result when tournament does not exist', function () {
    global $wpdb;

    $wpdb->get_var_queue = [null];

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 3.5,
                'rank' => 1
            ];
        }
    };

    $result = MB_API::create_result($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails create_result when player does not exist', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, null];

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 3.5,
                'rank' => 1
            ];
        }
    };

    $result = MB_API::create_result($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('updates existing result', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, 2, 55];

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 4.0,
                'rank' => 1
            ];
        }
    };

    $result = MB_API::create_result($request);

    expect($result['success'])->toBeTrue();
    expect($result['result_id'])->toBe(55);
    expect($wpdb->last_update_data['points'])->toBe(4.0);
});

it('creates new result when none exists', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, 2, null];
    $wpdb->insert_id = 88;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 2.5,
                'rank' => 4
            ];
        }
    };

    $result = MB_API::create_result($request);

    expect($result['success'])->toBeTrue();
    expect($result['result_id'])->toBe(88);
    expect($wpdb->last_insert_data['points'])->toBe(2.5);
});

it('returns results list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'tournament_id' => 1, 'player_id' => 1, 'points' => 5.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster']
    ];

    $result = MB_API::get_results(['tournament_id' => 1]);

    expect($result)->toBeArray();
    expect($result[0]['rank'])->toBe(1);
});

it('fails finalize when tournament_id is missing', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $result = MB_API::finalize_tournament($request);

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

    $result = MB_API::finalize_tournament($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('finalizes single-round tournament and writes classic table', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateSingle';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 11,
        'post_title' => 'TemplateSingle',
        'post_content' => '<article>{{table}}</article>',
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
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1-0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 1;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 9];
        }
    };

    $result = MB_API::finalize_tournament($request);

    expect($result['success'])->toBeTrue();
    expect($result['post_id'])->toBe(555);
    expect($GLOBALS['mb_test_last_inserted_post'])->not->toBeNull();
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
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1-0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 2, 'result' => '0-1', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];
    $wpdb->get_var_result = 2;

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 12];
        }
    };

    $result = MB_API::finalize_tournament($request);
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