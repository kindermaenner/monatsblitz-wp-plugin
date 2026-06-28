<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetGamesHandler;

it('returns games list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'tournament_id' => 1, 'player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0']
    ];

    $handler = new GetGamesHandler();
    $result  = $handler->handle(['tournament_id' => 1]);

    expect($result)->toBeArray();
    expect($result[0]['leg_type'])->toBe(1);
});

