<?php

declare(strict_types=1);

use Monatsblitz\Database\DatabaseSchema;

it('creates tables on init', function () {
    global $wpdb;

    DatabaseSchema::init();

    expect($GLOBALS['mb_test_dbdelta_sql'] ?? '')
        ->toContain('CREATE TABLE wp_monatsblitz_tournament_players')
        ->toContain('UNIQUE KEY unique_tournament_player (tournament_id, player_id)')
        ->toContain('CREATE TABLE wp_monatsblitz_modes')
        ->toContain('UNIQUE KEY unique_mode_name (name)');

    expect($wpdb->queries)->toBeEmpty();

    expect($GLOBALS['mb_test_updated_options']['monatsblitz_db_version'] ?? null)
        ->toBe(DatabaseSchema::DB_VERSION);
});

it('runs maybe_upgrade only when db_version option differs', function () {
    $GLOBALS['mb_test_options']['monatsblitz_db_version'] = DatabaseSchema::DB_VERSION;
    $GLOBALS['mb_test_dbdelta_sql'] = null;
    DatabaseSchema::maybe_upgrade();
    expect($GLOBALS['mb_test_dbdelta_sql'])->toBeNull();

    $GLOBALS['mb_test_options']['monatsblitz_db_version'] = '1.0';
    DatabaseSchema::maybe_upgrade();
    expect($GLOBALS['mb_test_dbdelta_sql'])->not->toBeNull();
    expect($GLOBALS['mb_test_options']['monatsblitz_db_version'])->toBe(DatabaseSchema::DB_VERSION);
});
