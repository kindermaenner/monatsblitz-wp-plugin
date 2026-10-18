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
});
