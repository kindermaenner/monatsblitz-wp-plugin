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
it('finds existing player case-insensitively', function () {
    global $wpdb;

    // Simulate existing player in DB (e.g. "Max Mustermann" with ID 42)
    $dbPlayers = [
        ['id' => 42, 'forename' => 'Max', 'surname' => 'Mustermann']
    ];

    // Simulate MySQL _ci (case-insensitive) collation behavior
    $wpdb->get_var_callback = function ($query, $args) use ($dbPlayers) {
        $searchForename = $args[0] ?? '';
        $searchSurname  = $args[1] ?? '';

        foreach ($dbPlayers as $player) {
            if (strcasecmp($player['forename'], $searchForename) === 0 &&
                strcasecmp($player['surname'], $searchSurname) === 0) {
                return $player['id'];
            }
        }
        return null;
    };

    $handler = new CreatePlayerHandler();

    // 1. Lowercase search: "max" "mustermann"
    $requestLower = new class {
        public function get_json_params() {
            return ['forename' => 'max', 'surname' => 'mustermann'];
        }
    };
    $resultLower = $handler->handle($requestLower);
    expect($resultLower['success'])->toBeTrue();
    expect($resultLower['player_id'])->toBe(42);
    expect($resultLower['message'])->toBe('Spieler existiert bereits');

    // 2. Uppercase search: "MAX" "MUSTERMANN"
    $requestUpper = new class {
        public function get_json_params() {
            return ['forename' => 'MAX', 'surname' => 'MUSTERMANN'];
        }
    };
    $resultUpper = $handler->handle($requestUpper);
    expect($resultUpper['success'])->toBeTrue();
    expect($resultUpper['player_id'])->toBe(42);

    // 3. Different player: "Moritz" "Mustermann" -> creates new
    $wpdb->insert_id = 99;
    $requestNew = new class {
        public function get_json_params() {
            return ['forename' => 'Moritz', 'surname' => 'Mustermann'];
        }
    };
    $resultNew = $handler->handle($requestNew);
    expect($resultNew['success'])->toBeTrue();
    expect($resultNew['player_id'])->toBe(99);
});
