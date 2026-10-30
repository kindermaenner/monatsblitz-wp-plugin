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
