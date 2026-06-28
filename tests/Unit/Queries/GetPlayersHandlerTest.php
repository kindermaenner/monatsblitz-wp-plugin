<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetPlayersHandler;

it('returns player list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'forename' => 'Max', 'surname' => 'Mustermann']
    ];

    $handler = new GetPlayersHandler();
    $result  = $handler->handle();

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
});
