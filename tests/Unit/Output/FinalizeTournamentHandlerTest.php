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
    expect($GLOBALS['mb_test_post_meta_updates'])->toContainEqual([
        'post_id' => 555,
        'meta_key' => '_monatsblitz_tournament_id',
        'meta_value' => '9',
    ]);
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

it('finalizes successfully without games and renders fallback results table', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateNoGames';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 92,
        'post_title' => 'TemplateNoGames',
        'post_content' => '<div>{{ranking_rows}}</div><section>{{table}}</section>',
    ];

    $wpdb->get_row_result = [
        'id' => 41,
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
        [],
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 41];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'];

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('<div><tr>');
    expect($content)->toContain('<section><table class="monatsblitz">');
    expect($content)->toContain('<th>Nr.</th><th>Spieler</th><th>Punkte</th><th>Platz</th>');
    expect($content)->toContain('Max Muster');
    expect($content)->not->toContain('mb-cell-empty');
});

it('updates an existing monthly post identified by blitz-YYYY-MM-DD meta key', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateUpdate';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 93,
        'post_title' => 'TemplateUpdate',
        'post_content' => '{{date}} {{table}}',
    ];
    $GLOBALS['mb_test_get_posts_result'] = [
        (object)['ID' => 900],
    ];

    $wpdb->get_row_result = [
        'id' => 42,
        'year' => 2026,
        'month' => 6,
        'day' => 30,
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

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 42];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['post_id'])->toBe(900);
    expect($result['post_updated'])->toBeTrue();
    expect($GLOBALS['mb_test_last_updated_post'])->not->toBeNull();
    expect($GLOBALS['mb_test_last_updated_post']['post_name'])->toBe('turnier-2026-06-30');
});

it('updates an existing monthly post identified by tournament id meta key', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateUpdateTournamentId';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 94,
        'post_title' => 'TemplateUpdateTournamentId',
        'post_content' => '{{date}} {{table}}',
    ];
    $GLOBALS['mb_test_get_posts_result'] = [
        (object)['ID' => 901],
    ];

    $wpdb->get_row_result = [
        'id' => 77,
        'year' => 2026,
        'month' => 7,
        'day' => 1,
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

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 77];
        }
    };

    $handler = new FinalizeTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['post_id'])->toBe(901);
    expect($result['post_updated'])->toBeTrue();
    expect($GLOBALS['mb_test_last_updated_post'])->not->toBeNull();
    expect($GLOBALS['mb_test_post_meta_updates'])->toContainEqual([
        'post_id' => 901,
        'meta_key' => '_monatsblitz_tournament_id',
        'meta_value' => '77',
    ]);
});

it('falls back to post_name lookup when tournament id marker is missing', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateFallbackName';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 95,
        'post_title' => 'TemplateFallbackName',
        'post_content' => '{{date}} {{table}}',
    ];

    // 1) no tournament marker found, 2) post_name fallback finds existing post
    $GLOBALS['mb_test_get_posts_queue'] = [
        [],
        [(object)['ID' => 902]],
    ];

    $wpdb->get_row_result = [
        'id' => 78,
        'year' => 2026,
        'month' => 4,
        'day' => 7,
        'mode' => '5+0',
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

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 78];
        }
    };

    $result = (new FinalizeTournamentHandler())->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['post_id'])->toBe(902);
    expect($result['post_updated'])->toBeTrue();
    expect($GLOBALS['mb_test_last_updated_post'])->not->toBeNull();
});

it('uses inline default template when no template post and no template file exist', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_template_post'] = null;

    $templatePath = MB_PLUGIN_PATH . 'templates/post-template.html';
    if (file_exists($templatePath)) {
        unlink($templatePath);
    }

    $wpdb->get_row_result = [
        'id' => 51,
        'year' => 2026,
        'month' => 7,
        'day' => 1,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        'not-an-array',
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 51];
        }
    };

    $result = (new FinalizeTournamentHandler())->handle($request);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'] ?? '';

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('Die Ergebnisse unseres Blitz-Abends');
    expect($content)->toContain('Juli 2026');
});

it('loads content from template file when template post is missing', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_template_post'] = null;

    $templateDir = MB_PLUGIN_PATH . 'templates';
    if (!is_dir($templateDir)) {
        mkdir($templateDir, 0777, true);
    }
    $templatePath = $templateDir . '/post-template.html';
    file_put_contents($templatePath, '<article>FILE_TEMPLATE {{date}} {{games_list}}</article>');

    $wpdb->get_row_result = [
        'id' => 52,
        'year' => 2026,
        'month' => 7,
        'day' => 2,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0', 'p1_forename' => 'Max', 'p1_surname' => 'Muster', 'p2_forename' => 'Erika', 'p2_surname' => 'Beispiel'],
        ],
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 52];
        }
    };

    $result = (new FinalizeTournamentHandler())->handle($request);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'] ?? '';

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('FILE_TEMPLATE');
    expect($content)->toContain('02.07.2026');

    unlink($templatePath);
});

it('returns post_error when post insertion fails', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateInsertFail';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 120,
        'post_title' => 'TemplateInsertFail',
        'post_content' => '{{date}}',
    ];
    $GLOBALS['mb_test_next_post_id'] = new WP_Error('insert_failed', 'failed', ['status' => 500]);

    $wpdb->get_row_result = [
        'id' => 53,
        'year' => 2026,
        'month' => 7,
        'day' => 3,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [],
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 53];
        }
    };

    $result = (new FinalizeTournamentHandler())->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('post_error');
});

it('copies featured image, non-blacklisted meta, and non-empty taxonomy terms from template post', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateMetaTax';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 130,
        'post_title' => 'TemplateMetaTax',
        'post_content' => '{{date}}',
    ];
    $GLOBALS['mb_test_thumbnail_id'] = 333;
    $GLOBALS['mb_test_post_meta_result'] = [
        '_edit_lock' => ['123'],
        'custom_meta' => ['alpha', 'beta'],
    ];
    $GLOBALS['mb_test_object_taxonomies'] = ['category', 'post_tag'];
    $GLOBALS['mb_test_object_terms'] = [
        'category' => ['news'],
        'post_tag' => [],
    ];

    $wpdb->get_row_result = [
        'id' => 54,
        'year' => 2026,
        'month' => 7,
        'day' => 4,
        'mode' => 'schweizer',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [],
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 54];
        }
    };

    $result = (new FinalizeTournamentHandler())->handle($request);

    expect($result['success'])->toBeTrue();
    expect($GLOBALS['mb_test_set_thumbnail_calls'])->toHaveCount(1);
    expect($GLOBALS['mb_test_set_thumbnail_calls'][0]['thumbnail_id'])->toBe(333);

    expect($GLOBALS['mb_test_add_post_meta_calls'])->toHaveCount(2);
    expect($GLOBALS['mb_test_add_post_meta_calls'][0]['meta_key'])->toBe('custom_meta');
    expect($GLOBALS['mb_test_set_object_terms_calls'])->toHaveCount(1);
    expect($GLOBALS['mb_test_set_object_terms_calls'][0]['taxonomy'])->toBe('category');
});

it('normalizes remaining result cell branches including unknown values', function () {
    expect(FinalizeTournamentHandler::normalize_result_cell('-:+', false))->toBe('-');
    expect(FinalizeTournamentHandler::normalize_result_cell('-:+', true))->toBe('+');
    expect(FinalizeTournamentHandler::normalize_result_cell('0.5-0.5', false))->toBe('½');
    expect(FinalizeTournamentHandler::normalize_result_cell('RAW', false))->toBe('RAW');
});

it('rejects non-array and non-string values for normalize_string_list', function () {
    $result = FinalizeTournamentHandler::normalize_string_list(1234);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('invalid_data');
});

it('returns null year_page when yearly page generation returns WP_Error', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'TemplateYearError';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 140,
        'post_title' => 'TemplateYearError',
        'post_content' => '{{date}}',
    ];

    $wpdb->get_row_result = [
        'id' => 55,
        'year' => 2026,
        'month' => 7,
        'day' => 5,
        'mode' => '5+0',
        'round_count' => 1,
    ];
    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 2.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [],
    ];

    $request = new class {
        public function get_json_params() {
            return ['tournament_id' => 55];
        }
    };

    $result = (new FinalizeTournamentHandler())->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['year_page'])->toBeNull();
});

