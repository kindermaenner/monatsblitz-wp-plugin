<?php

declare(strict_types=1);

use Monatsblitz\Database\DatabaseSchema;

it('creates monatsblitz_tournament_players table and backfills from results on init', function () {
    global $wpdb;

    DatabaseSchema::init();

    expect($GLOBALS['mb_test_dbdelta_sql'] ?? '')
        ->toContain('CREATE TABLE wp_monatsblitz_tournament_players')
        ->toContain('UNIQUE KEY unique_tournament_player (tournament_id, player_id)');

    expect($wpdb->queries)->toHaveCount(1);
    expect($wpdb->queries[0])
        ->toContain('INSERT IGNORE INTO wp_monatsblitz_tournament_players (tournament_id, player_id)')
        ->toContain('SELECT tournament_id, player_id FROM wp_monatsblitz_results');

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
