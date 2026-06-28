<?php

declare(strict_types=1);

use Monatsblitz\Database\CreatePlayerHandler;

it('fails when player data is missing', function () {

    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $handler = new CreatePlayerHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(\WP_Error::class);
});

it('returns existing player if already exists', function () {
    global $wpdb;

    $wpdb->get_var_result = 123;

    $request = new class {
        public function get_json_params() {
            return [
                'forename' => 'Max',
                'surname' => 'Mustermann'
            ];
        }
    };

    $handler = new CreatePlayerHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['player_id'])->toBe(123);
});

it('creates a new player if not exists', function () {
    global $wpdb;

    $wpdb->insert_id = 999;

    $request = new class {
        public function get_json_params() {
            return [
                'forename' => 'Max',
                'surname' => 'Mustermann'
            ];
        }
    };

    $handler = new CreatePlayerHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['player_id'])->toBe(999);
});

it('creates batch players from a top-level array', function () {
    global $wpdb;

    $wpdb->get_var_queue = [null, null];
    $wpdb->insert_id = 500;

    $request = new class {
        public function get_json_params() {
            return [
                ['forename' => 'Thorsten', 'surname' => 'Ehlers'],
                ['forename' => 'Justus', 'surname' => 'Kindermann'],
            ];
        }
    };

    $handler = new CreatePlayerHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(2);
    expect($result['items'])->toHaveCount(2);
    expect($result['items'][0]['player_id'])->toBe(500);
});

it('rejects invalid players batch payloads', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'players' => 'not-an-array',
            ];
        }
    };

    $handler = new CreatePlayerHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});