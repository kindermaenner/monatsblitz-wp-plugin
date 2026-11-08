<?php

declare(strict_types=1);

use Monatsblitz\Admin\TableDetailPage;

it('renders table detail directly from TableDetailPage class', function () {
    ob_start();
    TableDetailPage::render('players', 'wp_monatsblitz_players');
    $html = ob_get_clean();

    expect($html)->toContain('Detailansicht: wp_monatsblitz_players');
    expect($html)->toContain('Keine Einträge vorhanden.');
});

it('defaults tournament-linked tables to the latest open tournament', function () {
    global $wpdb;

    $wpdb->get_results_queue = [
        [
            (object) [
                'id' => 42,
                'year' => 2026,
                'month' => 11,
                'day' => 6,
                'mode' => '5+0',
                'status' => 'open',
            ],
        ],
        [
            ['id' => 7, 'tournament_id' => 42, 'player1_name' => 'Alpha, Anna'],
        ],
    ];

    ob_start();
    TableDetailPage::render('games', 'wp_monatsblitz_games');
    $html = ob_get_clean();
    $dataQuery = $wpdb->get_results_calls[1][0];

    expect($html)->toContain('2026-11-06 (5+0) [OFFEN]')
        ->and($html)->toContain('Alpha, Anna')
        ->and($dataQuery)->toContain('WHERE g.tournament_id = %d')
        ->and($wpdb->last_prepared_args)->toBe([42]);
});

it('shows all tournament games when the explicit all filter is selected', function () {
    global $wpdb;
    $_GET['tournament_id'] = 'all';
    $wpdb->get_results_queue = [
        [
            ['id' => 1, 'year' => 2026, 'month' => 1, 'day' => 1, 'mode' => '5+0', 'status' => 'finalized'],
        ],
        [
            ['id' => 8, 'tournament_id' => 1, 'result' => '1:0'],
        ],
    ];

    ob_start();
    TableDetailPage::render('games', 'wp_monatsblitz_games');
    $html = ob_get_clean();
    $dataQuery = $wpdb->get_results_calls[1][0];

    expect($html)->toContain('Alle Turniere')
        ->and($html)->toContain('1:0')
        ->and($dataQuery)->not->toContain('WHERE g.tournament_id')
        ->and($dataQuery)->toContain('ORDER BY g.id DESC LIMIT 100');
});

it('orders filtered results by rank and preserves the selected tournament', function () {
    global $wpdb;
    $_GET['tournament_id'] = '9';
    $wpdb->get_results_queue = [
        [
            [
                'id' => 9,
                'year' => 2026,
                'month' => 10,
                'day' => 3,
                'daily_number' => 2,
                'label' => 'Herbstturnier',
                'mode' => '5+0',
                'status' => 'finalized',
            ],
        ],
        [
            ['id' => 31, 'player_name' => 'Alpha, Anna', 'points' => '4.0', 'rank' => 1],
            ['id' => 32, 'player_name' => 'Beta, Ben', 'points' => '3.0', 'rank' => 2],
        ],
    ];

    ob_start();
    TableDetailPage::render('results', 'wp_monatsblitz_results');
    $html = ob_get_clean();
    $dataQuery = $wpdb->get_results_calls[1][0];

    expect($html)->toContain('Herbstturnier')
        ->and($html)->toContain('selected="selected"')
        ->and($html)->toContain('Alpha, Anna')
        ->and($html)->toContain('Beta, Ben')
        ->and($dataQuery)->toContain('WHERE r.tournament_id = %d')
        ->and($dataQuery)->toContain('ORDER BY r.rank ASC, r.id DESC')
        ->and($wpdb->last_prepared_args)->toBe([9]);
});

it('filters tournament participants by the selected tournament', function () {
    global $wpdb;
    $_GET['tournament_id'] = '12';
    $wpdb->get_results_queue = [
        [
            (object) [
                'id' => 12,
                'year' => 2026,
                'month' => 9,
                'day' => 5,
                'daily_number' => 3,
                'mode' => '3+2',
                'status' => 'finalized',
            ],
        ],
        [
            ['id' => 44, 'tournament_id' => 12, 'player_name' => 'Muster, Max'],
        ],
    ];

    ob_start();
    TableDetailPage::render('tournament_players', 'wp_monatsblitz_tournament_players');
    $html = ob_get_clean();
    $dataQuery = $wpdb->get_results_calls[1][0];

    expect($html)->toContain('2026-09-05 (3+2) (#3)')
        ->and($html)->toContain('selected="selected"')
        ->and($html)->toContain('Muster, Max')
        ->and($dataQuery)->toContain('WHERE tp.tournament_id = %d')
        ->and($dataQuery)->toContain('ORDER BY tp.id DESC')
        ->and($wpdb->last_prepared_args)->toBe([12]);
});

it('renders reopen action button for finalized tournaments in tournaments detail view', function () {
    global $wpdb;

    $wpdb->results = [
        [
            'id' => 15,
            'year' => 2026,
            'month' => 11,
            'day' => 8,
            'status' => 'finalized',
        ],
        [
            'id' => 16,
            'year' => 2026,
            'month' => 11,
            'day' => 9,
            'status' => 'open',
        ],
    ];

    ob_start();
    TableDetailPage::render('tournaments', 'wp_monatsblitz_tournaments');
    $html = ob_get_clean();

    expect($html)->toContain('Aktionen');
    expect($html)->toContain('value="🔓 Wieder öffnen"');
    expect($html)->toContain('value="15"');
    expect($html)->toContain('Aktiv');
});

