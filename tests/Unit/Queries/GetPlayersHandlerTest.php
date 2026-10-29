<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetPlayersHandler;

it('returns player list with last_participated date', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'forename' => 'Max', 'surname' => 'Mustermann', 'last_participated' => '2026-09-04'],
        ['id' => 2, 'forename' => 'Erika', 'surname' => 'Musterfrau', 'last_participated' => null],
    ];

    $handler = new GetPlayersHandler();
    $result  = $handler->handle();

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
    expect($result[0]['last_participated'])->toBe('2026-09-04');
    expect($result[1]['id'])->toBe(2);
    expect($result[1]['last_participated'])->toBeNull();
});
