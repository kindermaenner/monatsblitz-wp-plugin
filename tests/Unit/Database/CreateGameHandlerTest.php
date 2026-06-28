<?php

declare(strict_types=1);

use Monatsblitz\Database\CreateGameHandler;

it('fails when game data is missing', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('creates game with default leg_type', function () {
    global $wpdb;

    $wpdb->get_var_result = 1;
    $wpdb->insert_id = 12;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'result' => '1:0'
            ];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($wpdb->last_insert_data['leg_type'])->toBe(1);
});

it('creates game with explicit leg_type', function () {
    global $wpdb;

    $wpdb->get_var_result = 1;
    $wpdb->insert_id = 13;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'leg_type' => 2,
                'result' => '0:1'
            ];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($wpdb->last_insert_data['leg_type'])->toBe(2);
});

it('creates batch games from a games array', function () {
    global $wpdb;

    $wpdb->get_var_queue = [1, 1, 1, 1, 1, 1];
    $wpdb->insert_id = 200;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 3,
                'games' => [
                    ['player1_id' => 1, 'player2_id' => 8, 'result' => '1:0', 'leg_type' => 1],
                    ['player1_id' => 1, 'player2_id' => 8, 'result' => '0:1', 'leg_type' => 2],
                ],
            ];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(2);
    expect($result['items'])->toHaveCount(2);
    expect($result['items'][0]['game_id'])->toBe(200);
});

it('rejects invalid games batch payloads', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 3,
                'games' => 'not-an-array',
            ];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails when leg_type is invalid', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'leg_type' => 0,
                'result' => '1:0'
            ];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('fails when tournament does not exist for game', function () {
    global $wpdb;

    $wpdb->get_var_result = null;

    $request = new class {
        public function get_json_params() {
            return [
                'tournament_id' => 9,
                'player1_id' => 1,
                'player2_id' => 2,
                'result' => '1:0'
            ];
        }
    };

    $handler = new CreateGameHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});