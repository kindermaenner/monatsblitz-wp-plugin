<?php

namespace Monatsblitz\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Api {

    public static function init() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes() {

        register_rest_route('monatsblitz/v1', '/player', [
            'methods'  => 'POST',
            'callback' => [self::class, 'create_player'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/tournament', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'create_tournament'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/game', [
            'methods'  => 'POST',
            'callback' => [self::class, 'create_game'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/players', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_players'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/tournaments', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_tournaments'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/tournament/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_tournament'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/games/(?P<tournament_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_games'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/result', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'create_result'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/results/(?P<tournament_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_results'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/finalize', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'finalize_tournament'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

        register_rest_route('monatsblitz/v1', '/normalize-items', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'normalize_items'],
            'permission_callback' => [self::class, 'verify_api_key']
        ]);

    }

    public static function verify_api_key() {
        $api_key = get_option('monatsblitz_api_key');
        $header_key = $_SERVER['HTTP_X_MB_KEY'] ?? '';

        if (!$api_key || $header_key !== $api_key) {
            return new \WP_Error(
                'rest_forbidden',
                'Unauthorized',
                ['status' => 401]
            );
        }

        return true;
    }

    public static function finalize_tournament($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $tournament_id = intval($params['tournament_id'] ?? 0);

        if (!$tournament_id) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        // Turnier laden
        $t = $wpdb->get_row(
            $wpdb->prepare("SELECT id, year, month, day, mode, round_count FROM {$wpdb->prefix}monatsblitz_tournaments WHERE id = %d", $tournament_id),
            ARRAY_A
        );

        if (!$t) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        // Einstellungen laden
        $post_author_id = intval( get_option('monatsblitz_author') );
        $template_name  = sanitize_text_field( get_option('monatsblitz_template') );

        // Datum formate
        $date_str = sprintf('%02d.%02d.%04d', $t['day'], $t['month'], $t['year']);
        $iso_date = sprintf('%04d-%02d-%02d', $t['year'], $t['month'], $t['day']);
        $monthNames = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ];
        $monthName = $monthNames[intval($t['month'] ?? 0)] ?? '';

        // Ergebnisse und Spieler laden
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT r.player_id, r.points, r.rank, p.forename, p.surname
                FROM {$wpdb->prefix}monatsblitz_results r
                LEFT JOIN {$wpdb->prefix}monatsblitz_players p ON r.player_id = p.id
                WHERE r.tournament_id = %d
                ORDER BY r.rank ASC", $tournament_id),
            ARRAY_A
        );

        if (empty($results)) {
            return new \WP_Error('no_results', 'Keine Ergebnisse vorhanden', ['status' => 400]);
        }

        $games = $wpdb->get_results(
            $wpdb->prepare("SELECT g.player1_id, g.player2_id, g.leg_type, g.result,
                p1.forename as p1_forename, p1.surname as p1_surname,
                p2.forename as p2_forename, p2.surname as p2_surname
                FROM {$wpdb->prefix}monatsblitz_games g
                LEFT JOIN {$wpdb->prefix}monatsblitz_players p1 ON g.player1_id = p1.id
                LEFT JOIN {$wpdb->prefix}monatsblitz_players p2 ON g.player2_id = p2.id
                WHERE g.tournament_id = %d
                ORDER BY g.id", $tournament_id),
            ARRAY_A
        );

        // Template-Post laden (Template_Monatsblitz)
        $template_post = get_page_by_title($template_name, OBJECT, 'post');
        $template_content = '';
        if ($template_post && !is_wp_error($template_post)) {
            $template_content = $template_post->post_content;
            $template_title = $template_post->post_title;
        } else {
            // Fallback: Datei
            $template_path = MB_PLUGIN_PATH . 'templates/post-template.html';
            if (file_exists($template_path)) {
                $template_content = file_get_contents($template_path);
            }
        }

        if (empty($template_content)) {
            $template_content = "<h1>{{month_name}} {{year}}</h1><p>Die Ergebnisse unseres Blitz-Abends vom {{date}}.</p>{{ranking_rows}}{{games_list}}";
        }

        // Gewinner bestimmen (erster Rang)
        $winner_name = '';
        $winner_points = '';
        $winner_games = 0;
        $winner_player_id = 0;
        if (!empty($results)) {
            $winner = $results[0];
            $winner_name = esc_html(trim($winner['forename'] . ' ' . $winner['surname']));
            $winner_points = esc_html($winner['points']);
            $winner_player_id = intval($winner['player_id']);
        }

        if ($winner_player_id) {
            $winner_games = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}monatsblitz_games WHERE tournament_id = %d AND (player1_id = %d OR player2_id = %d)", $tournament_id, $winner_player_id, $winner_player_id)
            );
        }

        // Ranking rows
        $ranking_rows = '';
        $i = 1;
        foreach ($results as $r) {
            $name = esc_html(trim($r['forename'] . ' ' . $r['surname']));
            $points = esc_html($r['points']);
            $rank = esc_html($r['rank']);
            $ranking_rows .= "<tr>\n<td style=\"border:1px solid #ccc; padding:6px;\">{$i}</td>\n<td style=\"border:1px solid #ccc; padding:6px;\">{$name}</td>\n<td style=\"border:1px solid #ccc; padding:6px;\">{$points}</td>\n<td style=\"border:1px solid #ccc; padding:6px;\">{$rank}</td>\n</tr>\n";
            $i++;
        }

        // Games list
        $games_list = '';
        foreach ($games as $g) {
            $p1 = esc_html(trim($g['p1_forename'] . ' ' . $g['p1_surname']));
            $p2 = esc_html(trim($g['p2_forename'] . ' ' . $g['p2_surname']));
            $res = esc_html($g['result']);
            $games_list .= "<li>{$p1} vs {$p2}: {$res}</li>\n";
        }

        // Kreuztabelle (matrix) erzeugen für {{table}}
        $players = [];
        foreach ($results as $r) {
            $players[] = [
                'id' => intval($r['player_id']),
                'name' => esc_html(trim($r['forename'] . ' ' . $r['surname'])),
                'points' => esc_html($r['points']),
                'rank' => esc_html($r['rank'])
            ];
        }

        $mode = esc_html((string)($t['mode'] ?? ''));
        $round_count = max(1, intval($t['round_count'] ?? 1));

        if ($round_count === 1) {
            $table_html = self::build_cross_table($players, $games, true);
        } else {
            $table_html = '';
            for ($round = 1; $round <= $round_count; $round++) {
                $table_html .= '<h3>Runde ' . $round . '</h3>';
                $table_html .= self::build_cross_table($players, $games, false, $round);
            }
            $table_html .= self::build_summary_table($players);
        }

        // Platzhalter ersetzen (inkl. {{table}})
        $content = str_replace(
            ['{{month_name}}','{{year}}','{{date}}','{{winner_name}}','{{winner_games}}','{{winner_points}}','{{ranking_rows}}','{{games_list}}','{{table}}','{{mode}}','{{round_count}}'],
            [esc_html($monthName), esc_html($t['year']), esc_html($date_str), $winner_name, esc_html($winner_games), $winner_points, $ranking_rows, $games_list, $table_html, $mode, esc_html((string)$round_count)],
            $template_content
        );

        // Post-Titel im gewünschten Format: Monatsblitz YYYY-MM-DD
        $post_title = 'Monatsblitz ' . $iso_date;
        $post_time = '23:30:00';
        $post_date_local = $iso_date . ' ' . $post_time;
        $post_date_gmt   = get_gmt_from_date( $post_date_local );

        // Neuen Beitrag anlegen und direkt veröffentlichen
        $postarr = [
            'post_title'     => $post_title,
            'post_content'   => $content,
            'post_status'    => 'publish',
            'post_type'      => 'post',
            'post_date'      => $post_date_local,
            'post_date_gmt'  => $post_date_gmt,
            'post_author'    => $post_author_id
        ];

        $post_id = wp_insert_post($postarr);

        if (is_wp_error($post_id)) {
            return new \WP_Error('post_error', 'Fehler beim Anlegen des Beitrags', ['status' => 500]);
        }

        if ($template_post && !is_wp_error($template_post)) {
            // Featured Image kopieren
            $template_thumbnail_id = get_post_thumbnail_id($template_post->ID);
            if ($template_thumbnail_id) {
                set_post_thumbnail($post_id, $template_thumbnail_id);
            }

            // Template-Meta kopieren. Blacklist für WP-internen Meta-Einträge.
            $blacklist_meta = [
                '_thumbnail_id',
                '_edit_last',
                '_edit_lock',
                '_wp_old_slug',
                '_wp_trash_meta_status',
                '_wp_trash_meta_time'
            ];

            $template_meta = get_post_meta($template_post->ID);
            foreach ($template_meta as $meta_key => $meta_values) {
                if (in_array($meta_key, $blacklist_meta, true)) {
                    continue;
                }
                foreach ($meta_values as $meta_value) {
                    add_post_meta($post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }

            // Taxonomie-Zuordnungen übernehmen (z. B. Kategorien, Tags, Template-spezifische Taxonomien)
            $taxonomies = get_object_taxonomies('post', 'names');
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_object_terms($template_post->ID, $taxonomy, ['fields' => 'slugs']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    wp_set_object_terms($post_id, $terms, $taxonomy, false);
                }
            }
        }

        return rest_ensure_response([
            'success' => true,
            'tournament_id' => $tournament_id,
            'post_id' => $post_id,
            'published' => true
        ]);
    }

    public static function normalize_items($request) {
        $params = $request->get_json_params();
        $input = $params;

        if (is_array($params) && array_key_exists('items', $params) && count($params) === 1) {
            $input = $params['items'];
        }

        $items = self::normalize_string_list($input);

        if (is_wp_error($items)) {
            return $items;
        }

        return rest_ensure_response([
            'count' => count($items),
            'items' => $items,
        ]);
    }

    public static function create_player($request) {
        global $wpdb;

        $params = $request->get_json_params();

        if (self::is_player_batch_payload($params)) {
            return self::create_players_batch($wpdb, $params);
        }

        return self::create_single_player($wpdb, $params);
    }

    private static function is_player_batch_payload($params) {
        if (!is_array($params)) {
            return false;
        }

        if (isset($params['players'])) {
            return true;
        }

        return array_is_list($params);
    }

    private static function create_players_batch($wpdb, array $params) {
        $entries = isset($params['players']) ? $params['players'] : $params;

        if (!is_array($entries)) {
            return new \WP_Error('invalid_data', 'players must be an array', ['status' => 400]);
        }

        if (count($entries) === 0) {
            return new \WP_Error('invalid_data', 'At least one player is required', ['status' => 400]);
        }

        $responses = [];
        foreach ($entries as $playerEntry) {
            if (!is_array($playerEntry)) {
                return new \WP_Error('invalid_data', 'Each player must be an object', ['status' => 400]);
            }

            $playerResponse = self::create_single_player($wpdb, $playerEntry);

            if (is_wp_error($playerResponse)) {
                return $playerResponse;
            }

            $responses[] = $playerResponse;
        }

        return rest_ensure_response([
            'success' => true,
            'count'   => count($responses),
            'items'   => $responses,
        ]);
    }

    private static function create_single_player($wpdb, array $params) {
        $forename = sanitize_text_field($params['forename'] ?? '');
        $surname  = sanitize_text_field($params['surname'] ?? '');

        // 🔒 Validierung
        if (empty($forename) || empty($surname)) {
            return new \WP_Error('invalid_data', 'Vorname und Nachname sind erforderlich', ['status' => 400]);
        }

        // 👉 Optional: prüfen ob Spieler schon existiert
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_players 
                 WHERE forename = %s AND surname = %s",
                $forename,
                $surname
            )
        );

        if ($existing) {
            return [
                'success' => true,
                'player_id' => $existing,
                'message' => 'Spieler existiert bereits'
            ];
        }

        // 👉 Einfügen
        $wpdb->insert(
            $wpdb->prefix . 'monatsblitz_players',
            [
                'forename' => $forename,
                'surname'  => $surname
            ]
        );

        return [
            'success'   => true,
            'player_id' => $wpdb->insert_id
        ];
    }

    public static function create_tournament($request) {
        global $wpdb;

        $params = $request->get_json_params();

        if (empty($params['date'])) {
            return new \WP_Error('invalid_data', 'Date is required', ['status' => 400]);
        }

        // 📅 Datum zerlegen
        $date = $params['date'];
        $parts = explode('-', $date);

        if (count($parts) !== 3) {
            return new \WP_Error('invalid_date', 'Invalid date format', ['status' => 400]);
        }

        list($year, $month, $day) = $parts;
        $mode = sanitize_text_field($params['mode'] ?? '');
        $round_count = intval($params['round_count'] ?? 1);

        if ($mode === '') {
            return new \WP_Error('invalid_data', 'Mode is required', ['status' => 400]);
        }

        if ($round_count < 1) {
            return new \WP_Error('invalid_data', 'round_count must be >= 1', ['status' => 400]);
        }

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $wpdb->insert(
            $table,
            [
                'year'  => (int)$year,
                'month' => (int)$month,
                'day'   => (int)$day,
                'mode'  => $mode,
                'round_count' => $round_count
            ],
            ['%d', '%d', '%d', '%s', '%d']
        );

        return rest_ensure_response([
            'success' => true,
            'tournament_id' => $wpdb->insert_id
        ]);
    }   

    public static function create_game($request) {
        global $wpdb;

        $params = $request->get_json_params();

        if (isset($params['games'])) {
            return self::create_games_batch($wpdb, $params);
        }

        return self::create_single_game($wpdb, $params);
    }

    private static function create_games_batch($wpdb, array $params) {
        if (intval($params['tournament_id'] ?? 0) <= 0) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        if (!is_array($params['games'])) {
            return new \WP_Error('invalid_data', 'games must be an array', ['status' => 400]);
        }

        $responses = [];
        foreach ($params['games'] as $gameEntry) {
            if (!is_array($gameEntry)) {
                return new \WP_Error('invalid_data', 'Each game must be an object', ['status' => 400]);
            }

            $gameEntry['tournament_id'] = $params['tournament_id'];
            $gameResponse = self::create_single_game($wpdb, $gameEntry);

            if (is_wp_error($gameResponse)) {
                return $gameResponse;
            }

            $responses[] = $gameResponse;
        }

        return rest_ensure_response([
            'success' => true,
            'count'   => count($responses),
            'items'   => $responses,
        ]);
    }

    private static function create_single_game($wpdb, array $params) {
        $tournament_id = intval($params['tournament_id'] ?? 0);
        $player1_id    = intval($params['player1_id'] ?? 0);
        $player2_id    = intval($params['player2_id'] ?? 0);
        $leg_type      = intval($params['leg_type'] ?? 1);
        $result        = sanitize_text_field($params['result'] ?? '');

        // 🔒 einfache Validierung
        if ($tournament_id <= 0 || $player1_id <= 0 || $player2_id <= 0) {
            return new \WP_Error('invalid_data', 'Fehlende Daten', ['status' => 400]);
        }

        if ($leg_type <= 0) {
            return new \WP_Error('invalid_data', 'leg_type muss >= 1 sein', ['status' => 400]);
        }

        // 👉 prüfen ob Turnier existiert
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_tournaments WHERE id = %d",
                $tournament_id
            )
        );

        if (!$exists) {
            return new \WP_Error('invalid_tournament', 'Turnier existiert nicht', ['status' => 404]);
        }

        // 👉 speichern
        $wpdb->insert(
            $wpdb->prefix . 'monatsblitz_games',
            [
                'tournament_id' => $tournament_id,
                'player1_id'    => $player1_id,
                'player2_id'    => $player2_id,
                'leg_type'      => $leg_type,
                'result'        => $result
            ],
            ['%d', '%d', '%d', '%d', '%s']
        );

        return [
            'success' => true,
            'game_id' => $wpdb->insert_id
        ];
    }

    public static function get_players() {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_players';

        $results = $wpdb->get_results(
            "SELECT id, forename, surname FROM $table ORDER BY surname, forename",
            ARRAY_A
        );

        return rest_ensure_response($results);
    }

    public static function get_tournaments() {
        global $wpdb;

        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $results = $wpdb->get_results(
            "SELECT id, year, month, day, mode, round_count, CONCAT(day, '.', month, '.', year) as date_formatted 
             FROM $table ORDER BY year DESC, month DESC, day DESC",
            ARRAY_A
        );

        return rest_ensure_response($results);
    }

    public static function get_tournament($request) {
        global $wpdb;

        $tournament_id = intval($request['id']);
        $table = $wpdb->prefix . 'monatsblitz_tournaments';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, year, month, day, mode, round_count, CONCAT(day, '.', month, '.', year) as date_formatted 
                 FROM $table WHERE id = %d",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!$result) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        return rest_ensure_response($result);
    }

    public static function get_games($request) {
        global $wpdb;

        $tournament_id = intval($request['tournament_id']);
        $games_table = $wpdb->prefix . 'monatsblitz_games';
        $players_table = $wpdb->prefix . 'monatsblitz_players';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    g.id,
                    g.tournament_id,
                    g.player1_id,
                    g.player2_id,
                    g.leg_type,
                    g.result,
                    p1.forename as player1_forename,
                    p1.surname as player1_surname,
                    p2.forename as player2_forename,
                    p2.surname as player2_surname
                FROM $games_table g
                LEFT JOIN $players_table p1 ON g.player1_id = p1.id
                LEFT JOIN $players_table p2 ON g.player2_id = p2.id
                WHERE g.tournament_id = %d
                ORDER BY g.id",
                $tournament_id
            ),
            ARRAY_A
        );

        return rest_ensure_response($results);
    }

    public static function create_result($request) {
        global $wpdb;

        $params = $request->get_json_params();

        if (isset($params['results'])) {
            return self::create_results_batch($params);
        }

        return self::create_single_result($wpdb, $params);
    }

    private static function create_results_batch(array $params) {
        global $wpdb;

        if (!isset($params['tournament_id'])) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        if (!is_array($params['results'])) {
            return new \WP_Error('invalid_data', 'results must be an array', ['status' => 400]);
        }

        $responses = [];
        foreach ($params['results'] as $resultEntry) {
            if (!is_array($resultEntry)) {
                return new \WP_Error('invalid_data', 'Each result must be an object', ['status' => 400]);
            }

            $resultEntry['tournament_id'] = $params['tournament_id'];
            $resultResponse = self::create_single_result($wpdb, $resultEntry);

            if (is_wp_error($resultResponse)) {
                return $resultResponse;
            }

            $responses[] = $resultResponse;
        }

        return rest_ensure_response([
            'success' => true,
            'count'   => count($responses),
            'items'   => $responses,
        ]);
    }

    private static function create_single_result($wpdb, array $params) {
        $tournament_id = intval($params['tournament_id'] ?? 0);
        $player_id     = intval($params['player_id'] ?? 0);
        $points        = floatval($params['points'] ?? 0);
        $rank          = intval($params['rank'] ?? 0);

        // 🔒 Validierung
        if (!$tournament_id || !$player_id) {
            return new \WP_Error('invalid_data', 'Turnier-ID und Spieler-ID erforderlich', ['status' => 400]);
        }

        // 👉 Prüfe ob Turnier existiert
        $tournament_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_tournaments WHERE id = %d",
                $tournament_id
            )
        );

        if (!$tournament_exists) {
            return new \WP_Error('invalid_tournament', 'Turnier existiert nicht', ['status' => 404]);
        }

        // 👉 Prüfe ob Spieler existiert
        $player_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_players WHERE id = %d",
                $player_id
            )
        );

        if (!$player_exists) {
            return new \WP_Error('invalid_player', 'Spieler existiert nicht', ['status' => 404]);
        }

        // 👉 Prüfe ob Ergebnis bereits existiert
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}monatsblitz_results 
                 WHERE tournament_id = %d AND player_id = %d",
                $tournament_id,
                $player_id
            )
        );

        if ($existing) {
            // Update wenn bereits vorhanden
            $wpdb->update(
                $wpdb->prefix . 'monatsblitz_results',
                [
                    'points' => $points,
                    'rank'   => $rank
                ],
                [
                    'tournament_id' => $tournament_id,
                    'player_id'     => $player_id
                ]
            );

            return [
                'success'   => true,
                'result_id' => $existing,
                'message'   => 'Ergebnis aktualisiert'
            ];
        }

        // 👉 Neu einfügen
        $wpdb->insert(
            $wpdb->prefix . 'monatsblitz_results',
            [
                'tournament_id' => $tournament_id,
                'player_id'     => $player_id,
                'points'        => $points,
                'rank'          => $rank
            ]
        );

        return [
            'success'   => true,
            'result_id' => $wpdb->insert_id
        ];
    }

    public static function get_results($request) {
        global $wpdb;

        $tournament_id = intval($request['tournament_id']);
        $results_table = $wpdb->prefix . 'monatsblitz_results';
        $players_table = $wpdb->prefix . 'monatsblitz_players';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    r.id,
                    r.tournament_id,
                    r.player_id,
                    r.points,
                    r.rank,
                    p.forename,
                    p.surname
                FROM $results_table r
                LEFT JOIN $players_table p ON r.player_id = p.id
                WHERE r.tournament_id = %d
                ORDER BY r.rank ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        return rest_ensure_response($results);
    }

    private static function build_cross_table(array $players, array $games, bool $include_totals, ?int $round = null): string {
        $game_map = [];
        foreach ($games as $g) {
            $leg_type = intval($g['leg_type'] ?? 1);
            if ($round !== null && $leg_type !== $round) {
                continue;
            }

            $p1 = intval($g['player1_id']);
            $p2 = intval($g['player2_id']);
            $game_map[$p1][$p2] = $g['result'];
        }

        $n = count($players);
        $table_html = '<table class="monatsblitz">';
        $table_html .= '<thead><tr><th>Nr.</th><th>Spieler</th>';
        for ($c = 1; $c <= $n; $c++) {
            $table_html .= '<th>' . $c . '</th>';
        }
        if ($include_totals) {
            $table_html .= '<th>Punkte</th><th>Platz</th>';
        }
        $table_html .= '</tr></thead>';

        $table_html .= '<tbody>';
        for ($i = 0; $i < $n; $i++) {
            $rowPlayer = $players[$i];
            $table_html .= '<tr>';
            $table_html .= '<td>' . ($i + 1) . '</td>';
            $table_html .= '<td>' . $rowPlayer['name'] . '</td>';

            for ($j = 0; $j < $n; $j++) {
                $cell_attr = '';
                if ($i === $j) {
                    $cell = '&nbsp;';
                    $cell_attr = ' class="mb-cell-empty mb-cell-diagonal" style="background-color:#eeeeee !important; color:#666666 !important;"';
                } else {
                    $p_i = $rowPlayer['id'];
                    $p_j = $players[$j]['id'];
                    $cell = '';
                    if (isset($game_map[$p_i][$p_j])) {
                        $cell = self::normalize_result_cell($game_map[$p_i][$p_j], false);
                    } elseif (isset($game_map[$p_j][$p_i])) {
                        $cell = self::normalize_result_cell($game_map[$p_j][$p_i], true);
                    } else {
                        $cell = '&nbsp;';
                        $cell_attr = ' class="mb-cell-empty mb-cell-pending" style="background-color:#eeeeee !important; color:#666666 !important;"';
                    }
                }
                $table_html .= '<td' . $cell_attr . '>' . $cell . '</td>';
            }

            if ($include_totals) {
                $table_html .= '<td>' . $rowPlayer['points'] . '</td>';
                $table_html .= '<td>' . $rowPlayer['rank'] . '</td>';
            }
            $table_html .= '</tr>';
        }

        $table_html .= '</tbody></table>';

        return $table_html;
    }

    private static function build_summary_table(array $players): string {
        $summary = '<h3>Gesamtergebnis</h3>';
        $summary .= '<table class="monatsblitz">';
        $summary .= '<thead><tr><th>Spieler</th><th>Gesamtpunkte</th><th>Platz</th></tr></thead><tbody>';

        foreach ($players as $player) {
            $summary .= '<tr>';
            $summary .= '<td>' . $player['name'] . '</td>';
            $summary .= '<td>' . $player['points'] . '</td>';
            $summary .= '<td>' . $player['rank'] . '</td>';
            $summary .= '</tr>';
        }

        $summary .= '</tbody></table>';

        return $summary;
    }

    private static function normalize_result_cell(string $result, bool $invert): string {
        if (!$invert) {
            if ($result === '1:0' || $result === '1-0') return '1';
            if ($result === '0:1' || $result === '0-1') return '0';
            if ($result === '+:-') return '+';
            if ($result === '-:+') return '-';
        } else {
            if ($result === '1:0' || $result === '1-0') return '0';
            if ($result === '0:1' || $result === '0-1') return '1';
            if ($result === '+:-') return '-';
            if ($result === '-:+') return '+';
        }

        if ($result === '0.5:0.5' || $result === '0.5-0.5' || $result === '½') {
            return '½';
        }

        return esc_html($result);
    }

    private static function normalize_string_list($input) {
        if ($input === null) {
            return new \WP_Error('invalid_data', 'Input must be a string or an array of strings', ['status' => 400]);
        }

        if (is_string($input)) {
            $input = [$input];
        }

        if (!is_array($input)) {
            return new \WP_Error('invalid_data', 'Input must be a string or an array of strings', ['status' => 400]);
        }

        $normalized = [];
        foreach ($input as $item) {
            if (!is_string($item)) {
                return new \WP_Error('invalid_data', 'Input must be a string or an array of strings', ['status' => 400]);
            }

            $item = trim($item);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

}