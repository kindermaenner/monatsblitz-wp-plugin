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
        ->toContain('UNIQUE KEY unique_mode_name (name)')
        ->toContain('CREATE TABLE wp_monatsblitz_players')
        ->toContain('rufname VARCHAR(100) NOT NULL DEFAULT \'\'');

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

it('migrates existing tournament dates and removes the obsolete unique index', function () {
    global $wpdb;
    $wpdb->get_var_result = 'wp_monatsblitz_tournaments';
    $wpdb->results = [(object) ['Key_name' => 'unique_tournament_date']];

    DatabaseSchema::init();

    expect($wpdb->queries)->toBe([
        'ALTER TABLE wp_monatsblitz_tournaments DROP INDEX unique_tournament_date',
        'UPDATE wp_monatsblitz_tournaments SET daily_number = 1 WHERE daily_number IS NULL OR daily_number = 0',
        'UPDATE wp_monatsblitz_tournaments SET counts_for_ranking = 1 WHERE counts_for_ranking IS NULL',
    ]);
});

it('skips tournament data migrations when the tournaments table does not exist', function () {
    global $wpdb;
    $wpdb->get_var_result = null;
    $wpdb->results = [(object) ['Key_name' => 'unique_tournament_date']];

    DatabaseSchema::init();

    expect($wpdb->queries)->toBeEmpty();
});
