<?php

declare(strict_types=1);

use Monatsblitz\Database\DatabaseSchema;

it('creates tables and seeds default modes on init', function () {
    global $wpdb;

    DatabaseSchema::init();

    expect($GLOBALS['mb_test_dbdelta_sql'] ?? '')
        ->toContain('CREATE TABLE wp_monatsblitz_tournament_players')
        ->toContain('UNIQUE KEY unique_tournament_player (tournament_id, player_id)')
        ->toContain('CREATE TABLE wp_monatsblitz_modes')
        ->toContain('UNIQUE KEY unique_mode_name (name)');

    expect($wpdb->queries)->toHaveCount(1);
    expect($wpdb->queries[0])
        ->toContain('INSERT IGNORE INTO wp_monatsblitz_modes (name) VALUES (\'3+2\'), (\'5+0\')');

    expect($GLOBALS['mb_test_updated_options']['monatsblitz_db_version'] ?? null)
        ->toBe(DatabaseSchema::DB_VERSION);
});

it('runs maybe_upgrade only when db_version option differs', function () {
    global $wpdb;

    $GLOBALS['mb_test_options']['monatsblitz_db_version'] = DatabaseSchema::DB_VERSION;
    DatabaseSchema::maybe_upgrade();
    expect($wpdb->queries)->toBeEmpty();

    $GLOBALS['mb_test_options']['monatsblitz_db_version'] = '1.0';
    DatabaseSchema::maybe_upgrade();
    expect($wpdb->queries)->toHaveCount(1);
    expect($GLOBALS['mb_test_options']['monatsblitz_db_version'])->toBe(DatabaseSchema::DB_VERSION);
});
