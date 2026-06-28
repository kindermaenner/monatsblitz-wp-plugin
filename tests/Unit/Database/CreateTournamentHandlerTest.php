<?php

declare(strict_types=1);

use Monatsblitz\Database\CreateTournamentHandler;

it('fails on invalid date', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => 'invalid'];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('creates tournament with mode and default round_count', function () {
    global $wpdb;

    $wpdb->insert_id = 77;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer'
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['tournament_id'])->toBe(77);
    expect($wpdb->last_insert_data['mode'])->toBe('schweizer');
    expect($wpdb->last_insert_data['round_count'])->toBe(1);
});

it('fails when tournament mode is missing', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => '2026-06-24'];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails when round_count is below one', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer',
                'round_count' => 0
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('creates tournament with explicit round_count', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'rundenturnier',
                'round_count' => 3
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($wpdb->last_insert_data['round_count'])->toBe(3);
});