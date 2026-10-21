<?php

declare(strict_types=1);

use Monatsblitz\Database\CreateModeHandler;

it('fails when mode name is missing or empty', function () {
    $request = new class {
        public function get_json_params() {
            return [];
        }
    };

    $handler = new CreateModeHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(\WP_Error::class);
    expect($result->get_error_message())->toBe('Name ist erforderlich');
});

it('fails when mode name is longer than 50 chars', function () {
    $request = new class {
        public function get_json_params() {
            return [
                'name' => str_repeat('a', 51)
            ];
        }
    };

    $handler = new CreateModeHandler();
    $result  = $handler->handle($request);

    expect($result)->toBeInstanceOf(\WP_Error::class);
    expect($result->get_error_message())->toBe('Name darf maximal 50 Zeichen lang sein');
});

it('returns existing mode if already exists (case-insensitive)', function () {
    global $wpdb;

    $wpdb->get_row_result = ['id' => 5, 'name' => '3+2'];

    $request = new class {
        public function get_json_params() {
            return [
                'name' => '3+2'
            ];
        }
    };

    $handler = new CreateModeHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['id'])->toBe(5);
    expect($result['name'])->toBe('3+2');
    expect($result['message'])->toBe('Modus existiert bereits');
});

it('creates a new mode when not exists', function () {
    global $wpdb;

    $wpdb->get_row_result = null;
    $wpdb->insert_id = 42;

    $request = new class {
        public function get_json_params() {
            return [
                'name' => '10+0'
            ];
        }
    };

    $handler = new CreateModeHandler();
    $result  = $handler->handle($request);

    expect($result['success'])->toBeTrue();
    expect($result['id'])->toBe(42);
    expect($result['name'])->toBe('10+0');
});
