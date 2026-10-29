<?php

declare(strict_types=1);

use Monatsblitz\Queries\GetTournamentHandler;

it('returns tournament by id with assigned player_ids', function () {
    global $wpdb;

    $wpdb->get_row_result = ['id' => 7, 'year' => 2026, 'month' => 6, 'day' => 24, 'mode' => 'schweizer', 'round_count' => 2, 'status' => 'open'];
    $wpdb->results = [
        ['player_id' => '12', 'has_left' => '1'],
        ['player_id' => '34'],
    ];

    $handler = new GetTournamentHandler();
    $result  = $handler->handle(['id' => 7]);

    expect($result)->toBeArray();
    expect($result['id'])->toBe(7);
    expect($result['status'])->toBe('open');
    expect($result['date'])->toBe('2026-06-24');
    expect($result['player_ids'])->toBe([12, 34]);
    expect($result['withdrawn_player_ids'])->toBe([12]);
});

it('falls back to game players when no tournament players are assigned', function () {
    global $wpdb;

    $wpdb->get_row_result = ['id' => 7, 'year' => 2026, 'month' => 6, 'day' => 24, 'mode' => 'schweizer', 'round_count' => 2, 'status' => 'open'];
    $wpdb->get_results_queue = [
        [],
        [['pid' => '12'], ['pid' => '34']],
    ];

    $result = (new GetTournamentHandler())->handle(['id' => 7]);

    expect($result['player_ids'])->toBe([12, 34])
        ->and($result['withdrawn_player_ids'])->toBe([]);
});

it('fails when tournament id is unknown', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $handler = new GetTournamentHandler();
    $result  = $handler->handle(['id' => 999]);

    expect($result)->toBeInstanceOf(WP_Error::class);
});
