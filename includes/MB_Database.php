<?php

namespace monatsblitz;

if (!defined('ABSPATH')) {
    exit;
}

class MB_Database {

    public static function init() {
        self::create_tables();
    }

    public static function reset_tables() {
        self::create_tables();
    }

    private static function create_tables() {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Tabellen-Namen
        $players_table     = $wpdb->prefix . 'monatsblitz_players';
        $tournaments_table = $wpdb->prefix . 'monatsblitz_tournaments';
        $games_table       = $wpdb->prefix . 'monatsblitz_games';
        $results_table     = $wpdb->prefix . 'monatsblitz_results';

        $sql = "
            CREATE TABLE {$wpdb->prefix}monatsblitz_players (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                forename VARCHAR(100) NOT NULL,
                surname VARCHAR(100) NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY unique_player_name (forename, surname)
            ) $charset_collate;
            
            CREATE TABLE {$wpdb->prefix}monatsblitz_tournaments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                year SMALLINT NOT NULL,
                month TINYINT NOT NULL,
                day TINYINT NOT NULL,
                mode VARCHAR(10) NOT NULL,
                round_count TINYINT(1) NOT NULL DEFAULT 1,

                PRIMARY KEY (id),
                UNIQUE KEY unique_tournament_date (year, month, day)
            ) $charset_collate;
            
            CREATE TABLE {$wpdb->prefix}monatsblitz_games (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id BIGINT UNSIGNED NOT NULL,
                player1_id BIGINT UNSIGNED NOT NULL,
                player2_id BIGINT UNSIGNED NOT NULL,
                leg_type TINYINT NOT NULL DEFAULT 1,
                result ENUM('1:0','0:1','0.5:0.5','+:-','-:+') NOT NULL,

                PRIMARY KEY (id),

                KEY idx_tournament (tournament_id),
                KEY idx_player1 (player1_id),
                KEY idx_player2 (player2_id),

                UNIQUE KEY unique_game (tournament_id, player1_id, player2_id, leg_type)
            ) $charset_collate;

            CREATE TABLE {$wpdb->prefix}monatsblitz_results (
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

\class_alias(__NAMESPACE__ . '\\MB_Database', 'MB_Database');