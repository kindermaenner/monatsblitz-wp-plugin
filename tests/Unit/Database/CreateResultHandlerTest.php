<?php

declare(strict_types=1);

use Monatsblitz\Database\CreateResultHandler;

it('fails create_result when ids are missing', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 0,
                'player_id' => 0,
                'points' => 0,
                'rank' => 0
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails create_result when tournament does not exist', function () {
    global $wpdb;

    $wpdb->get_var_queue = [null];

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 3.5,
                'rank' => 1
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails create_result when player does not exist', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, null];

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 3.5,
                'rank' => 1
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('updates existing result', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, 2, 55];

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 4.0,
                'rank' => 1
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['result_id'])->toBe(55);
    expect($wpdb->last_update_data['points'])->toBe(4.0);
});

it('creates new result when none exists', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, 2, null];
    $wpdb->insert_id = 88;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 1,
                'player_id' => 2,
                'points' => 2.5,
                'rank' => 4
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['result_id'])->toBe(88);
    expect($wpdb->last_insert_data['points'])->toBe(2.5);
});

it('creates batch results from a results array', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, 5, null, 1, 3, null, 1, 1, null];
    $wpdb->insert_id = 100;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 3,
                'results' => [
                    ['player_id' => 5, 'points' => 8, 'rank' => 1],
                    ['player_id' => 3, 'points' => 6, 'rank' => 2],
                    ['player_id' => 1, 'points' => 3, 'rank' => 3],
                ],
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(3);
    expect($result['items'])->toHaveCount(3);
    expect($result['items'][0]['result_id'])->toBe(100);
});

it('rejects invalid batch result payloads', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 3,
                'results' => 'not-an-array',
            ];
        }
    };

    $handler = new CreateResultHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

