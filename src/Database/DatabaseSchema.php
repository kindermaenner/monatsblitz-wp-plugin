<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

use function dbDelta;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseSchema
{
    public const DB_VERSION = '1.2';

    public static function init()
    {
        self::create_tables();
        if (function_exists('update_option')) {
            update_option('monatsblitz_db_version', self::DB_VERSION);
        }
    }

    public static function maybe_upgrade(): void
    {
        $installed_version = function_exists('get_option')
            ? (string) get_option('monatsblitz_db_version', '')
            : '';

        if ($installed_version !== self::DB_VERSION) {
            static::init();
        }
    }

    public static function reset_tables()
    {
        self::create_tables();
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

                PRIMARY KEY (id),
                UNIQUE KEY unique_player_name (forename, surname)
            ) $charset_collate;

            CREATE TABLE {$tournaments_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                year SMALLINT NOT NULL,
                month TINYINT NOT NULL,
                day TINYINT NOT NULL,
                mode VARCHAR(30) NOT NULL,
                round_count TINYINT(1) NOT NULL DEFAULT 1,
                status ENUM('open','finalized') NOT NULL DEFAULT 'open',

                PRIMARY KEY (id),
                UNIQUE KEY unique_tournament_date (year, month, day)
            ) $charset_collate;

            CREATE TABLE {$tournament_players_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id BIGINT UNSIGNED NOT NULL,
                player_id BIGINT UNSIGNED NOT NULL,

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
                result ENUM('1:0','0:1','0.5:0.5','+:-','-:+','offen') NOT NULL,

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

        if (isset($wpdb) && method_exists($wpdb, 'query')) {
            $wpdb->query(
                "INSERT IGNORE INTO {$modes_table} (name) VALUES ('3+2'), ('5+0')"
            );
        }
    }
}
