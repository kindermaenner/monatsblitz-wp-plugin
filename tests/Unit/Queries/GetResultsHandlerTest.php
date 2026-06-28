<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetResultsHandler;

it('returns results list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'tournament_id' => 1, 'player_id' => 1, 'points' => 5.0, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster']
    ];

    $handler = new GetResultsHandler();
    $result  = $handler->handle(['tournament_id' => 1]);

    expect($result)->toBeArray();
    expect($result[0]['rank'])->toBe(1);
});
