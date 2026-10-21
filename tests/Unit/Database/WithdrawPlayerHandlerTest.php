<?php

declare(strict_types=1);

use Monatsblitz\Database\WithdrawPlayerHandler;

it('fails when tournament or player id is missing', function () {
    $handler = new WithdrawPlayerHandler();
    $res = $handler->handle(['tournament_id' => 0, 'player_id' => 1]);
    expect($res)->toBeInstanceOf(WP_Error::class);

    $res2 = $handler->handle(['tournament_id' => 1, 'player_id' => 0]);
    expect($res2)->toBeInstanceOf(WP_Error::class);
});

it('marks tournament player as withdrawn', function () {
    global $wpdb;

    $handler = new WithdrawPlayerHandler();
    $res = $handler->handle([
        'tournament_id' => 10,
        'player_id'     => 5,
        'has_left'      => 1,
    ]);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue()
        ->and($res['tournament_id'])->toBe(10)
        ->and($res['player_id'])->toBe(5)
        ->and($res['has_left'])->toBe(1);

    expect($wpdb->last_update_table)->toContain('monatsblitz_tournament_players')
        ->and($wpdb->last_update_data)->toBe(['has_left' => 1])
        ->and($wpdb->last_update_where)->toBe(['tournament_id' => 10, 'player_id' => 5]);
});
