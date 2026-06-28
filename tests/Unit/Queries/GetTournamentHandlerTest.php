<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetTournamentHandler;

it('returns tournament by id', function () {
    global $wpdb;

    $wpdb->get_row_result = ['id' => 7, 'year' => 2026, 'month' => 6, 'day' => 24, 'mode' => 'schweizer', 'round_count' => 2];

    $handler = new GetTournamentHandler();
    $result  = $handler->handle(['id' => 7]);

    expect($result)->toBeArray();
    expect($result['id'])->toBe(7);
});

it('fails when tournament id is unknown', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $handler = new GetTournamentHandler();
    $result  = $handler->handle(['id' => 999]);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

