<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

use function dbDelta;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseSchema
{
    public const DB_VERSION = '1.4';
    public const OPTION_DB_VERSION = 'monatsblitz_db_version';

    public static function init()
    {
        self::create_tables();
        self::migrate_columns_and_indices();
        if (function_exists('update_option')) {
            update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
        }
    }

    public static function maybe_upgrade(): void
    {
        $installed_version = function_exists('get_option')
            ? (string) get_option(self::OPTION_DB_VERSION, '')
            : '';

        if ($installed_version !== self::DB_VERSION) {
            static::init();
        }
    }

    public static function reset_tables()
    {
        self::create_tables();
        self::migrate_columns_and_indices();
    }

    private static function migrate_columns_and_indices(): void
    {
        global $wpdb;

        if (!$wpdb || !method_exists($wpdb, 'query')) {
            return;
        }

        $tournaments_table = $wpdb->prefix . 'monatsblitz_tournaments';

        if (method_exists($wpdb, 'get_var')) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tournaments_table));
            if ($table_exists !== $tournaments_table) {
                return;
            }
        }

        if (method_exists($wpdb, 'get_results')) {
            $indices = $wpdb->get_results("SHOW INDEX FROM {$tournaments_table} WHERE Key_name = 'unique_tournament_date'");
            if (!empty($indices)) {
                $wpdb->query("ALTER TABLE {$tournaments_table} DROP INDEX unique_tournament_date");
            }
        }

        $wpdb->query("UPDATE {$tournaments_table} SET daily_number = 1 WHERE daily_number IS NULL OR daily_number = 0");
        $wpdb->query("UPDATE {$tournaments_table} SET counts_for_ranking = 1 WHERE counts_for_ranking IS NULL");
    }

    private static function create_tables()
    {
        global $wpdb;

        if (!function_exists('dbDelta') && defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Tabellen-Namen
        $players_table            = $wpdb->prefix . 'monatsblitz_players';
        $tournaments_table        = $wpdb->prefix . 'monatsblitz_tournaments';
        $tournament_players_table = $wpdb->prefix . 'monatsblitz_tournament_players';
        $games_table              = $wpdb->prefix . 'monatsblitz_games';
        $results_table            = $wpdb->prefix . 'monatsblitz_results';
        $modes_table              = $wpdb->prefix . 'monatsblitz_modes';

        $sql = "
            CREATE TABLE {$modes_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(50) NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY unique_mode_name (name)
            ) $charset_collate;

            CREATE TABLE {$players_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                forename VARCHAR(100) NOT NULL,
                surname VARCHAR(100) NOT NULL,
                rufname VARCHAR(100) NOT NULL DEFAULT '',

                PRIMARY KEY (id)
            ) $charset_collate;

            CREATE TABLE {$tournaments_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                year SMALLINT NOT NULL,
                month TINYINT NOT NULL,
                day TINYINT NOT NULL,
                daily_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
                label VARCHAR(100) NOT NULL DEFAULT '',
                counts_for_ranking TINYINT(1) NOT NULL DEFAULT 1,
                start_time TIME NULL,
                mode VARCHAR(30) NOT NULL,
                round_count TINYINT(1) NOT NULL DEFAULT 1,
                status ENUM('open','finalized') NOT NULL DEFAULT 'open',

                PRIMARY KEY (id),
                UNIQUE KEY unique_tournament_day_counter (year, month, day, daily_number)
            ) $charset_collate;

            CREATE TABLE {$tournament_players_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id BIGINT UNSIGNED NOT NULL,
                player_id BIGINT UNSIGNED NOT NULL,
                has_left TINYINT(1) NOT NULL DEFAULT 0,

                PRIMARY KEY (id),

                UNIQUE KEY unique_tournament_player (tournament_id, player_id),

                KEY idx_tournament (tournament_id),
                KEY idx_player (player_id)
            ) $charset_collate;

            CREATE TABLE {$games_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id BIGINT UNSIGNED NOT NULL,
                player1_id BIGINT UNSIGNED NOT NULL,
                player2_id BIGINT UNSIGNED NOT NULL,
                leg_type TINYINT NOT NULL DEFAULT 1,
                result ENUM('1:0','0:1','0.5:0.5','+:-','-:+','=:=','offen') NOT NULL,

                PRIMARY KEY (id),

                KEY idx_tournament (tournament_id),
                KEY idx_player1 (player1_id),
                KEY idx_player2 (player2_id),

                UNIQUE KEY unique_game (tournament_id, player1_id, player2_id, leg_type)
            ) $charset_collate;

            CREATE TABLE {$results_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id BIGINT UNSIGNED NOT NULL,
                player_id BIGINT UNSIGNED NOT NULL,
                points DECIMAL(4,1) NOT NULL,
                rank INT NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY unique_player_tournament (tournament_id, player_id),

                KEY idx_tournament (tournament_id),
                KEY idx_player (player_id)
            ) $charset_collate;
        ";

        dbDelta($sql);
    }
}
