<?php

declare(strict_types=1);

use Monatsblitz\Output\YearStaticPageHandler;

it('rejects invalid year for yearly static page creation', function () {
    $handler = new YearStaticPageHandler();
    $result = $handler->createOrUpdate(1999);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('invalid_data');
});

it('fails when no static page template is configured', function () {
    $handler = new YearStaticPageHandler();
    $result = $handler->createOrUpdate(2026);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('no_template');
});

it('fails when configured static page template cannot be found', function () {
    $GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'TemplateMissing';
    $GLOBALS['mb_test_template_post'] = null;

    $handler = new YearStaticPageHandler();
    $result = $handler->createOrUpdate(2026);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('template_missing');
});

it('creates yearly page and skips non-blitz modes and invalid months', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'TemplateYear';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 200,
        'post_title' => 'TemplateYear',
        'post_content' => '<h1>{{year}}</h1>{{blitz_monthly_overview}}{{blitz_ranking_year}}',
    ];

    $wpdb->get_results_queue = [[
        [
            'tournament_id' => 1,
            'month' => 1,
            'mode' => '10+0',
            'player_id' => 10,
            'rank' => 1,
            'forename' => 'Nicht',
            'surname' => 'Blitz',
        ],
        [
            'tournament_id' => 2,
            'month' => 13,
            'mode' => '5+0',
            'player_id' => 11,
            'rank' => 1,
            'forename' => 'Ungueltiger',
            'surname' => 'Monat',
        ],
        [
            'tournament_id' => 3,
            'month' => 2,
            'mode' => '5+0',
            'player_id' => 12,
            'rank' => 2,
            'forename' => 'Gueltig',
            'surname' => 'Spieler',
        ],
    ]];

    $result = (new YearStaticPageHandler())->createOrUpdate(2026);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'] ?? '';

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('<h1>2026</h1>');
    expect($content)->toContain('Gueltig Spieler');
    expect($content)->not->toContain('Nicht Blitz');
    expect($content)->not->toContain('Ungueltiger Monat');
    expect($GLOBALS['mb_test_post_meta_updates'])->toContainEqual([
        'post_id' => 123,
        'meta_key' => '_blitzmeisterschaft_2026',
        'meta_value' => '1',
    ]);
});

it('updates existing yearly page and copies only non-blacklisted template meta', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'TemplateYearUpdate';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 201,
        'post_title' => 'TemplateYearUpdate',
        'post_content' => '{{blitz_monthly_overview}}{{blitz_ranking_year}}',
    ];
    $GLOBALS['mb_test_get_posts_result'] = [(object)['ID' => 777]];
    $GLOBALS['mb_test_post_meta_result'] = [
        '_edit_lock' => ['abc'],
        'copy_me' => ['v1'],
    ];

    $wpdb->get_results_queue = [[
        [
            'tournament_id' => 4,
            'month' => 1,
            'mode' => '5+0',
            'player_id' => 13,
            'rank' => 1,
            'forename' => 'Anna',
            'surname' => 'Alpha',
        ],
    ]];

    $result = (new YearStaticPageHandler())->createOrUpdate(2026);

    expect($result['success'])->toBeTrue();
    expect($result['updated'])->toBeTrue();
    expect($result['page_id'])->toBe(777);
    expect($GLOBALS['mb_test_last_updated_post']['ID'])->toBe(777);
    expect($GLOBALS['mb_test_add_post_meta_calls'])->toHaveCount(1);
    expect($GLOBALS['mb_test_add_post_meta_calls'][0]['meta_key'])->toBe('copy_me');
});

it('returns page_error when yearly page insertion does not produce a valid id', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'TemplateYearInsertFail';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 202,
        'post_title' => 'TemplateYearInsertFail',
        'post_content' => '{{blitz_monthly_overview}}{{blitz_ranking_year}}',
    ];
    $GLOBALS['mb_test_next_post_id'] = 0;

    $wpdb->get_results_queue = [[
        [
            'tournament_id' => 5,
            'month' => 1,
            'mode' => '5+0',
            'player_id' => 14,
            'rank' => 1,
            'forename' => 'Insert',
            'surname' => 'Fail',
        ],
    ]];

    $result = (new YearStaticPageHandler())->createOrUpdate(2026);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('page_error');
});

it('applies rank points for 2 to 5 and sorts yearly ranking with tie-break by name', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'TemplateYearRank';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 203,
        'post_title' => 'TemplateYearRank',
        'post_content' => '{{blitz_monthly_overview}}{{blitz_ranking_year}}',
    ];

    $wpdb->get_results_queue = [[
        ['tournament_id' => 10, 'month' => 1, 'mode' => '5+0', 'player_id' => 1, 'rank' => 1, 'forename' => 'Berta', 'surname' => 'Beta'],
        ['tournament_id' => 11, 'month' => 2, 'mode' => '5+0', 'player_id' => 1, 'rank' => 3, 'forename' => 'Berta', 'surname' => 'Beta'],

        ['tournament_id' => 12, 'month' => 1, 'mode' => '5+0', 'player_id' => 2, 'rank' => 1, 'forename' => 'Anna', 'surname' => 'Alpha'],
        ['tournament_id' => 13, 'month' => 2, 'mode' => '5+0', 'player_id' => 2, 'rank' => 4, 'forename' => 'Anna', 'surname' => 'Alpha'],

        ['tournament_id' => 14, 'month' => 3, 'mode' => '5+0', 'player_id' => 3, 'rank' => 2, 'forename' => 'Clara', 'surname' => 'Gamma'],
        ['tournament_id' => 15, 'month' => 4, 'mode' => '5+0', 'player_id' => 3, 'rank' => 4, 'forename' => 'Clara', 'surname' => 'Gamma'],

        ['tournament_id' => 16, 'month' => 5, 'mode' => '5+0', 'player_id' => 4, 'rank' => 2, 'forename' => 'Dora', 'surname' => 'Delta'],
        ['tournament_id' => 17, 'month' => 6, 'mode' => '5+0', 'player_id' => 4, 'rank' => 4, 'forename' => 'Dora', 'surname' => 'Delta'],

        ['tournament_id' => 18, 'month' => 7, 'mode' => '5+0', 'player_id' => 5, 'rank' => 5, 'forename' => 'Eva', 'surname' => 'Epsilon'],
    ]];

    $result = (new YearStaticPageHandler())->createOrUpdate(2026);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'] ?? '';

    expect($result['success'])->toBeTrue();

    // Rank 5 -> 1 point should appear in overview month cell.
    expect($content)->toContain('Eva Epsilon');
    expect($content)->toContain('<td>1</td>');
    expect($content)->not->toContain('1/1');

    $rankingSection = explode('<table class="monatsblitz-year-ranking">', $content)[1] ?? '';

    // Berta (8) should be ahead of Anna (7), and Clara/Dora both 6 sorted by name.
    expect(strpos($rankingSection, 'Berta Beta'))->toBeLessThan(strpos($rankingSection, 'Anna Alpha'));
    expect(strpos($rankingSection, 'Clara Gamma'))->toBeLessThan(strpos($rankingSection, 'Dora Delta'));
});

it('hides january column in monthly overview when configured', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'TemplateYearNoJan';
    $GLOBALS['mb_test_options']['monatsblitz_hide_january_overview'] = '1';
    $GLOBALS['mb_test_template_post'] = (object) [
        'ID' => 204,
        'post_title' => 'TemplateYearNoJan',
        'post_content' => '{{blitz_monthly_overview}}',
    ];

    $wpdb->get_results_queue = [[
        ['tournament_id' => 20, 'month' => 1, 'mode' => '5+0', 'player_id' => 1, 'rank' => 1, 'forename' => 'Anna', 'surname' => 'Alpha'],
        ['tournament_id' => 21, 'month' => 2, 'mode' => '5+0', 'player_id' => 1, 'rank' => 2, 'forename' => 'Anna', 'surname' => 'Alpha'],
    ]];

    $result = (new YearStaticPageHandler())->createOrUpdate(2026);
    $content = $GLOBALS['mb_test_last_inserted_post']['post_content'] ?? '';

    expect($result['success'])->toBeTrue();
    expect($content)->toContain('<th>2</th>');
    expect($content)->not->toContain('<th>1</th>');
    expect($content)->toContain('<td>4</td>');
    expect($content)->not->toContain('<td>0/0</td>');
});
