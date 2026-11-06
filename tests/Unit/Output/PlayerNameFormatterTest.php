<?php

declare(strict_types=1);

use Monatsblitz\Output\PlayerNameFormatter;

it('formats name without rufname when display setting is none', function () {
    $result = PlayerNameFormatter::format('Pham Dai Thien', 'Nguyen', 'Thien', 'none');
    expect($result)->toBe('Pham Dai Thien Nguyen');
});

it('formats name with rufname in parentheses when display setting is always', function () {
    $result = PlayerNameFormatter::format('Pham Dai Thien', 'Nguyen', 'Thien', 'always');
    expect($result)->toBe('Pham Dai Thien Nguyen (Thien)');
});

it('formats name without rufname when rufname is empty even if setting is always', function () {
    $result = PlayerNameFormatter::format('Max', 'Mustermann', '', 'always');
    expect($result)->toBe('Max Mustermann');
});

it('formats name with rufname on collision only when collision exists', function () {
    $noCollision = PlayerNameFormatter::format('Michael', 'Schmidt', 'Michi', 'collision', false);
    expect($noCollision)->toBe('Michael Schmidt');

    $withCollision = PlayerNameFormatter::format('Michael', 'Schmidt', 'Michi', 'collision', true);
    expect($withCollision)->toBe('Michael Schmidt (Michi)');
});

it('builds collision map accurately for players with same forename and surname', function () {
    $items = [
        ['player_id' => 1, 'forename' => 'Michael', 'surname' => 'Schmidt'],
        ['player_id' => 2, 'forename' => 'Michael', 'surname' => 'Schmidt'],
        ['player_id' => 3, 'forename' => 'Anna', 'surname' => 'Meier'],
    ];

    $map = PlayerNameFormatter::buildCollisionMap($items);

    expect($map[1])->toBeTrue();
    expect($map[2])->toBeTrue();
    expect($map[3])->toBeFalse();
});
