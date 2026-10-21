<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetModesHandler;

it('returns modes list', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 1, 'name' => '3+2'],
        ['id' => 2, 'name' => '5+0']
    ];

    $handler = new GetModesHandler();
    $result  = $handler->handle();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    expect($result[0]['id'])->toBe(1);
    expect($result[0]['name'])->toBe('3+2');
    expect($result[1]['id'])->toBe(2);
    expect($result[1]['name'])->toBe('5+0');
});

it('returns empty array when no modes found', function () {
    global $wpdb;

    $wpdb->results = [];

    $handler = new GetModesHandler();
    $result  = $handler->handle();

    expect($result)->toBe([]);
});
