<?php

declare(strict_types=1);

use Monatsblitz\Database\CreateTournamentHandler;

it('fails when date is missing', function () {
    $request = new class {
        public function get_json_params() {
            return ['mode' => 'schweizer'];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_data');
});

it('fails on invalid date format', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => 'invalid', 'mode' => 'schweizer'];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_date');
});

it('fails on non-numeric date parts', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => '2026-06-aa', 'mode' => 'schweizer'];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_date')
        ->and($result->data['input_date'])->toBe('2026-06-aa');
});

it('fails on invalid calendar date', function () {
    $request = new class {
        public function get_json_params() {
            return ['date' => '2026-02-30', 'mode' => 'schweizer'];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_date')
        ->and($result->data['parsed'])->toBe([
            'year' => 2026,
            'month' => 2,
            'day' => 30,
        ]);
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

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_data');
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

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('invalid_data');
});

it('creates tournament with explicit round_count', function () {
    global $wpdb;

    $wpdb->insert_id = 88;

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

    expect($result['success'])->toBeTrue();
    expect($result['tournament_id'])->toBe(88);
    expect($wpdb->last_insert_data['round_count'])->toBe(3);
});

it('fails with duplicate_tournament when db reports duplicate entry', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer',
                'round_count' => 2,
            ];
        }
    };

    $failingWpdb = new class($wpdb) {
        public string $prefix;
        public int $insert_id = 0;
        public string $last_error;
        private $base;

        public function __construct($base) {
            $this->base = $base;
            $this->prefix = $base->prefix;
            $this->last_error = "Duplicate entry '2026-6-24' for key 'unique_tournament_date'";
        }

        public function insert($table, $data, $format = null) {
            return false;
        }
    };

    $wpdb = $failingWpdb;

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('duplicate_tournament')
        ->and($result->data['status'])->toBe(409);
});

it('fails with db_insert_failed when insert returns false without duplicate error', function () {
    global $wpdb;

    $failingWpdb = new class($wpdb) {
        public string $prefix;
        public int $insert_id = 0;
        public string $last_error = 'Some SQL failure';

        public function __construct($base) {
            $this->prefix = $base->prefix;
        }

        public function insert($table, $data, $format = null) {
            return false;
        }
    };

    $wpdb = $failingWpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer',
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('db_insert_failed')
        ->and($result->data['status'])->toBe(500);
});

it('fails when insert succeeds but insert_id is zero', function () {
    global $wpdb;

    $wpdb->insert_id = 0;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => 'schweizer',
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)
        ->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('db_insert_invalid_id')
        ->and($result->data['insert_id'])->toBe(0);
});