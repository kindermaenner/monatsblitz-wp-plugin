<?php

declare(strict_types=1);

use Monatsblitz\Database\AddTournamentPlayerHandler;

it('fails when tournament or player id is missing', function () {
    $handler = new AddTournamentPlayerHandler();
    $res = $handler->handle(['tournament_id' => 0, 'player_id' => 1]);
    expect($res)->toBeInstanceOf(WP_Error::class);

    $res2 = $handler->handle(['tournament_id' => 1, 'player_id' => 0]);
    expect($res2)->toBeInstanceOf(WP_Error::class);
});

it('fails when tournament does not exist', function () {
    global $wpdb;
    $wpdb->get_var_queue = [null]; // tournament status null

    $handler = new AddTournamentPlayerHandler();
    $res = $handler->handle(['tournament_id' => 1, 'player_id' => 2]);

    expect($res)->toBeInstanceOf(WP_Error::class)
        ->and($res->get_error_code())->toBe('invalid_tournament');
});

it('fails when tournament is finalized', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['finalized'];

    $handler = new AddTournamentPlayerHandler();
    $res = $handler->handle(['tournament_id' => 1, 'player_id' => 2]);

    expect($res)->toBeInstanceOf(WP_Error::class)
        ->and($res->get_error_code())->toBe('tournament_finalized');
});

it('fails when player does not exist', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['open', null]; // tournament status open, player id null

    $handler = new AddTournamentPlayerHandler();
    $res = $handler->handle(['tournament_id' => 1, 'player_id' => 2]);

    expect($res)->toBeInstanceOf(WP_Error::class)
        ->and($res->get_error_code())->toBe('invalid_player');
});

it('inserts player into tournament_players when not existing', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['open', 2]; // tournament status open, player exists

    $handler = new AddTournamentPlayerHandler();
    $res = $handler->handle(['tournament_id' => 10, 'player_id' => 2]);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue()
        ->and($res['tournament_id'])->toBe(10)
        ->and($res['player_id'])->toBe(2);

    expect($wpdb->last_insert_table)->toContain('monatsblitz_tournament_players')
        ->and($wpdb->last_insert_data)->toBe([
            'tournament_id' => 10,
            'player_id' => 2,
            'has_left' => 0,
        ]);
});

it('resets has_left to 0 when player was previously withdrawn', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['open', 2]; // tournament status open, player exists
    $wpdb->get_row_result = (object) ['id' => 123, 'has_left' => 1];

    $handler = new AddTournamentPlayerHandler();
    $res = $handler->handle(['tournament_id' => 10, 'player_id' => 2]);

    expect($res)->toBeArray()
        ->and($res['success'])->toBeTrue();

    expect($wpdb->last_update_table)->toContain('monatsblitz_tournament_players')
        ->and($wpdb->last_update_data)->toBe(['has_left' => 0])
        ->and($wpdb->last_update_where)->toBe(['tournament_id' => 10, 'player_id' => 2]);
});

it('does not update an existing player who has not withdrawn', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['open', 2];
    $wpdb->get_row_result = (object) ['id' => 123, 'has_left' => 0];

    $result = (new AddTournamentPlayerHandler())->handle(['tournament_id' => 10, 'player_id' => 2]);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($wpdb->last_update_table)->toBeNull();
});

it('returns a database error when reactivating a withdrawn player fails', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['open', 2];
    $wpdb->get_row_result = (object) ['id' => 123, 'has_left' => 1];
    $wpdb->update_result = false;
    $wpdb->last_error = 'update failed';

    $result = (new AddTournamentPlayerHandler())->handle(['tournament_id' => 10, 'player_id' => 2]);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('db_error')
        ->and($result->get_error_message())->toBe('update failed');
});

it('returns a database error when inserting a tournament player fails', function () {
    global $wpdb;
    $wpdb->get_var_queue = ['open', 2];
    $wpdb->insert_result = false;
    $wpdb->last_error = 'insert failed';

    $result = (new AddTournamentPlayerHandler())->handle(['tournament_id' => 10, 'player_id' => 2]);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('db_error')
        ->and($result->get_error_message())->toBe('insert failed');
});
