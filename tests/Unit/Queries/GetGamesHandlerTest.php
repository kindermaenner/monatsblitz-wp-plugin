<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetGamesHandler;

it('returns games list', function () {
    global $wpdb;

    $wpdb->results = [
        [
            'id' => 1,
            'tournament_id' => 1,
            'player1_id' => 1,
            'player2_id' => 2,
            'leg_type' => 1,
            'result' => '1:0',
            'player1_forename' => 'Michael',
            'player1_surname' => 'Schmidt',
            'player1_rufname' => 'Michi',
            'player2_forename' => 'Max',
            'player2_surname' => 'Mustermann',
            'player2_rufname' => '',
        ]
    ];

    $handler = new GetGamesHandler();
    $result  = $handler->handle(['tournament_id' => 1]);

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
    expect($result[0]['game_id'])->toBe(1);
    expect($result[0]['leg_type'])->toBe(1);
    expect($result[0]['player1_rufname'])->toBe('Michi');
    expect($result[0]['player2_rufname'])->toBe('');
});
