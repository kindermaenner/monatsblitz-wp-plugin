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
    expect($wpdb->last_insert_data['status'])->toBe('open');
    expect($wpdb->last_insert_format)->toBe(['%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s']);
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

it('reuses and resets existing open tournament on same date when duplicate entry occurs', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => '5+0',
                'round_count' => 2,
                'confirm_parallel' => true,
                'player_ids' => [101, 102],
            ];
        }
    };

    $openDuplicateWpdb = new class($wpdb) {
        public string $prefix;
        public int $insert_id = 0;
        public string $last_error = "Duplicate entry '2026-6-24' for key 'unique_tournament_date'";
        public array $updated = [];
        public array $deletedTables = [];
        public array $queries = [];

        public function query($query) {
            $this->queries[] = $query;
            return 1;
        }

        public function __construct($base) {
            $this->prefix = $base->prefix;
        }

        public function insert($table, $data, $format = null) {
            return false;
        }

        public function prepare($query, ...$args) {
            return sprintf(str_replace(['%d', '%s'], ['%s', '%s'], $query), ...$args);
        }

        public function get_row($query) {
            return (object)['id' => 42, 'status' => 'open'];
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            $this->updated = ['table' => $table, 'data' => $data, 'where' => $where];
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            $this->deletedTables[] = $table;
            return 1;
        }
    };

    $wpdb = $openDuplicateWpdb;

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue()
        ->and($result['tournament_id'])->toBe(42)
        ->and($openDuplicateWpdb->updated['data'])->toBe(['mode' => '5+0', 'round_count' => 2, 'counts_for_ranking' => 1, 'label' => ''])
        ->and($openDuplicateWpdb->deletedTables)->toContain('wp_monatsblitz_tournament_players')
        ->and($openDuplicateWpdb->deletedTables)->not->toContain('wp_monatsblitz_games')
        ->and($openDuplicateWpdb->queries)->toContain('DELETE FROM wp_monatsblitz_games WHERE tournament_id = 42 AND leg_type > 2');
});

it('reduces round_count to 1 on open tournament and deletes ONLY leg_type > 1 games, never all games', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => '5+0',
                'round_count' => 1,
                'confirm_parallel' => true,
            ];
        }
    };

    $openDuplicateWpdb = new class($wpdb) {
        public string $prefix;
        public int $insert_id = 0;
        public string $last_error = "Duplicate entry '2026-6-24' for key 'unique_tournament_date'";
        public array $updated = [];
        public array $deletedTables = [];
        public array $queries = [];

        public function query($query) {
            $this->queries[] = $query;
            return 1;
        }

        public function __construct($base) {
            $this->prefix = $base->prefix;
        }

        public function insert($table, $data, $format = null) {
            return false;
        }

        public function prepare($query, ...$args) {
            return sprintf(str_replace(['%d', '%s'], ['%s', '%s'], $query), ...$args);
        }

        public function get_row($query) {
            return (object)['id' => 42, 'status' => 'open'];
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            $this->updated = ['table' => $table, 'data' => $data, 'where' => $where];
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            $this->deletedTables[] = $table;
            return 1;
        }
    };

    $wpdb = $openDuplicateWpdb;

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue()
        ->and($result['tournament_id'])->toBe(42)
        ->and($openDuplicateWpdb->updated['data']['round_count'])->toBe(1)
        ->and($openDuplicateWpdb->deletedTables)->not->toContain('wp_monatsblitz_games')
        ->and($openDuplicateWpdb->deletedTables)->not->toContain('wp_monatsblitz_tournament_players')
        ->and($openDuplicateWpdb->queries)->toContain('DELETE FROM wp_monatsblitz_games WHERE tournament_id = 42 AND leg_type > 1');

    foreach ($openDuplicateWpdb->queries as $q) {
        if (str_contains($q, 'DELETE FROM wp_monatsblitz_games')) {
            expect($q)->toContain('leg_type > 1');
            expect($q)->not->toBe('DELETE FROM wp_monatsblitz_games WHERE tournament_id = 42');
        }
    }
});

it('preserves existing tournament players when re-saving open tournament without player_ids', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => '5+0',
                'round_count' => 2,
                'confirm_parallel' => true,
                // player_ids intentionally omitted (e.g. mobile app sync)
            ];
        }
    };

    $openDuplicateWpdb = new class($wpdb) {
        public string $prefix;
        public int $insert_id = 0;
        public string $last_error = "Duplicate entry '2026-6-24' for key 'unique_tournament_date'";
        public array $deletedTables = [];
        public array $queries = [];

        public function query($query) {
            $this->queries[] = $query;
            return 1;
        }

        public function __construct($base) {
            $this->prefix = $base->prefix;
        }

        public function insert($table, $data, $format = null) {
            return false;
        }

        public function prepare($query, ...$args) {
            return sprintf(str_replace(['%d', '%s'], ['%s', '%s'], $query), ...$args);
        }

        public function get_row($query) {
            return (object)['id' => 42, 'status' => 'open'];
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            $this->deletedTables[] = $table;
            return 1;
        }
    };

    $wpdb = $openDuplicateWpdb;

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue()
        ->and($openDuplicateWpdb->deletedTables)->toBeEmpty();
});

it('refuses to reset or delete games of a finalized tournament on duplicate date', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => '5+0',
                'round_count' => 1,
                'confirm_parallel' => true,
                'player_ids' => [1, 2],
            ];
        }
    };

    $finalizedDuplicateWpdb = new class($wpdb) {
        public string $prefix;
        public int $insert_id = 0;
        public string $last_error = "Duplicate entry '2026-6-24' for key 'unique_tournament_date'";
        public array $deletedTables = [];
        public array $queries = [];
        public array $updated = [];

        public function query($query) {
            $this->queries[] = $query;
            return 1;
        }

        public function __construct($base) {
            $this->prefix = $base->prefix;
        }

        public function insert($table, $data, $format = null) {
            return false;
        }

        public function prepare($query, ...$args) {
            return sprintf(str_replace(['%d', '%s'], ['%s', '%s'], $query), ...$args);
        }

        public function get_row($query) {
            // Tournament is already finalized!
            return (object)['id' => 42, 'status' => 'finalized'];
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            $this->updated[] = compact('table', 'data', 'where');
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            $this->deletedTables[] = $table;
            return 1;
        }
    };

    $wpdb = $finalizedDuplicateWpdb;

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('duplicate_tournament')
        ->and($finalizedDuplicateWpdb->deletedTables)->toBeEmpty()
        ->and($finalizedDuplicateWpdb->queries)->toBeEmpty()
        ->and($finalizedDuplicateWpdb->updated)->toBeEmpty();
});

it('inserts assigned player_ids into monatsblitz_tournament_players when tournament is created', function () {
    global $wpdb;

    $recordingWpdb = new class($wpdb) {
        public string $prefix = 'wp_';
        public int $insert_id = 99;
        public array $inserts = [];

        public function __construct($base) {
            $this->prefix = $base->prefix;
        }

        public function insert($table, $data, $format = null) {
            $this->inserts[] = ['table' => $table, 'data' => $data, 'format' => $format];
            return 1;
        }
    };

    $wpdb = $recordingWpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => '3+2',
                'round_count' => 1,
                'player_ids' => [10, 20, 10, 30], // duplicate 10 should be deduplicated
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue()
        ->and($result['tournament_id'])->toBe(99)
        ->and($recordingWpdb->inserts)->toHaveCount(4)
        ->and($recordingWpdb->inserts[0]['table'])->toBe('wp_monatsblitz_tournaments')
        ->and($recordingWpdb->inserts[1])->toBe([
            'table' => 'wp_monatsblitz_tournament_players',
            'data' => ['tournament_id' => 99, 'player_id' => 10],
            'format' => ['%d', '%d'],
        ])
        ->and($recordingWpdb->inserts[2]['data'])->toBe(['tournament_id' => 99, 'player_id' => 20])
        ->and($recordingWpdb->inserts[3]['data'])->toBe(['tournament_id' => 99, 'player_id' => 30]);
});

it('does not insert tournament or player entries when validation fails before start', function () {
    global $wpdb;

    $request = new class {
        public function get_json_params() {
            return [
                'date' => '2026-06-24',
                'mode' => '',
                'player_ids' => [10, 20],
            ];
        }
    };

    $handler = new CreateTournamentHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($wpdb->last_insert_table)->toBeNull();
});
