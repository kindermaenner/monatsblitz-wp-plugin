<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetTournamentsHandler;

it('returns tournament list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'year' => 2024, 'month' => 10, 'day' => 5]
    ];

    $handler = new GetTournamentsHandler();
    $result  = $handler->handle();

    expect($result)->toBeArray();
    expect($result[0]['id'])->toBe(1);
});
