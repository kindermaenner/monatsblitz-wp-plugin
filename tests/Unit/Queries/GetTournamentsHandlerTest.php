<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetTournamentsHandler;

it('returns tournament list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'year' => 2024, 'month' => 10, 'day' => 5, 'status' => 'open']
    ];

    $handler = new GetTournamentsHandler();
    $result  = $handler->handle();

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
    expect($result[0]['status'])->toBe('open');
    expect($result[0]['date'])->toBe('2024-10-05');
    expect($result[0]['player_ids'])->toBeArray();
    expect($result[0]['withdrawn_player_ids'])->toBeArray();
});

it('includes registered and game-only players in tournament list', function () {
    global $wpdb;

    $wpdb->get_results_queue = [
        [['id' => 1, 'year' => 2024, 'month' => 10, 'day' => 5]],
        [
            ['tournament_id' => 1, 'player_id' => 12, 'has_left' => 0],
            ['tournament_id' => 1, 'player_id' => 13, 'has_left' => 1],
        ],
        [
            ['tournament_id' => 1, 'pid' => 12],
            ['tournament_id' => 1, 'pid' => 14],
        ],
    ];

    $result = (new GetTournamentsHandler())->handle();

    expect($result[0]['player_ids'])->toBe([12, 13, 14]);
    expect($result[0]['withdrawn_player_ids'])->toBe([13]);
});

it('filters tournaments by whether they count for ranking', function () {
    global $wpdb;
    $wpdb->get_results_queue = [
        [
            ['id' => 2, 'year' => 2026, 'month' => 11, 'day' => 6, 'counts_for_ranking' => 0],
        ],
        [],
        [],
    ];

    $result = (new GetTournamentsHandler())->handle(
        new WP_REST_Request(['counts_for_ranking' => 'false'])
    );

    expect($result)->toHaveCount(1)
        ->and($result[0]['counts_for_ranking'])->toBeFalse()
        ->and($wpdb->get_results_calls[0][0])->toContain('WHERE counts_for_ranking = %d')
        ->and($wpdb->last_prepared_args)->toBe([0]);
});

it('ignores malformed and duplicate player ids while combining registrations with game participants', function () {
    global $wpdb;
    $wpdb->get_results_queue = [
        [
            ['id' => 3, 'year' => 2026, 'month' => 11, 'day' => 6],
        ],
        [
            ['tournament_id' => 3, 'player_id' => 15, 'has_left' => 0],
            ['tournament_id' => 0, 'player_id' => 16, 'has_left' => 1],
            ['tournament_id' => 3, 'player_id' => 0, 'has_left' => 1],
        ],
        [
            ['tournament_id' => 3, 'pid' => 15],
            ['tournament_id' => 3, 'pid' => 17],
            ['tournament_id' => 0, 'pid' => 18],
            ['tournament_id' => 3, 'pid' => 0],
        ],
    ];

    $result = (new GetTournamentsHandler())->handle();

    expect($result[0]['player_ids'])->toBe([15, 17])
        ->and($result[0]['withdrawn_player_ids'])->toBe([]);
});
