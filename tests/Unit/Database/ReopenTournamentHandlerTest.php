<?php

declare(strict_types=1);

use Monatsblitz\Database\ReopenTournamentHandler;

it('fails when tournament id is missing or invalid', function () {
    $handler = new ReopenTournamentHandler();

    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $result = $handler->handle($request);
    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_data');
});

it('fails when tournament does not exist in database', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $handler = new ReopenTournamentHandler();
    $result = $handler->handle(['tournament_id' => 999]);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('not_found');
});

it('returns already_open when tournament is already open', function () {
    global $wpdb;

    $wpdb->get_row_result = (object) [
        'id' => 12,
        'status' => 'open',
    ];

    $handler = new ReopenTournamentHandler();
    $result = $handler->handle(['tournament_id' => 12]);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['tournament_id'])->toBe(12)
        ->and($result['status'])->toBe('open')
        ->and($result['already_open'])->toBeTrue()
        ->and($wpdb->last_update_table)->toBeNull();
});

it('reopens finalized tournament and updates status to open in database', function () {
    global $wpdb;

    $wpdb->get_row_result = (object) [
        'id' => 42,
        'status' => 'finalized',
    ];

    $handler = new ReopenTournamentHandler();
    $request = new class {
        public function get_param($key) {
            return $key === 'id' ? '42' : null;
        }
    };

    $result = $handler->handle($request);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['tournament_id'])->toBe(42)
        ->and($result['status'])->toBe('open')
        ->and($result['already_open'])->toBeFalse()
        ->and($wpdb->last_update_table)->toBe('wp_monatsblitz_tournaments')
        ->and($wpdb->last_update_data)->toBe(['status' => 'open'])
        ->and($wpdb->last_update_where)->toBe(['id' => 42]);
});

it('returns db_error when database update fails', function () {
    global $wpdb;

    $wpdb->get_row_result = (object) [
        'id' => 42,
        'status' => 'finalized',
    ];
    $wpdb->update_result = false;
    $wpdb->last_error = 'Disk full';

    $handler = new ReopenTournamentHandler();
    $result = $handler->reopen(42);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('db_error')
        ->and($result->message)->toBe('Disk full');
});
