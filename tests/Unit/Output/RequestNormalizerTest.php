<?php

declare(strict_types=1);

use Monatsblitz\Output\RequestNormalizer;

it('normalizes a single string to an array', function () {
    $result = RequestNormalizer::normalizeStringList('item1');

    expect($result)->toBe(['item1']);
});

it('normalizes an array of strings and removes empty values', function () {
    $result = RequestNormalizer::normalizeStringList(['item1', '', '  ', 'item2']);

    expect($result)->toBe(['item1', 'item2']);
});

it('rejects invalid input for normalizeStringList', function () {
    $result = RequestNormalizer::normalizeStringList(123);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('invalid_data');
});

it('normalizes request items using normalizeItems', function () {
    $request = new class {
        public function get_json_params() {
            return ['items' => 'item1'];
        }
    };

    $result = RequestNormalizer::normalizeItems($request);

    expect($result)->toBeArray();
    expect($result['count'])->toBe(1);
    expect($result['items'])->toBe(['item1']);
});

it('rejects invalid request input for normalizeItems', function () {
    $request = new class {
        public function get_json_params() {
            return ['items' => ['item1', 123]];
        }
    };

    $result = RequestNormalizer::normalizeItems($request);

    expect($result)->toBeInstanceOf(WP_Error::class);
});