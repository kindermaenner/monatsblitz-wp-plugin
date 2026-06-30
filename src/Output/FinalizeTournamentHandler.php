<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class FinalizeTournamentHandler
{
    public function handle($request)
    {
        global $wpdb;

        $params = $request->get_json_params();
        $tournament_id = intval($params['tournament_id'] ?? 0);

        if (!$tournament_id) {
            return new \WP_Error('invalid_data', 'Turnier-ID erforderlich', ['status' => 400]);
        }

        // Turnier laden
        $t = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, year, month, day, mode, round_count 
                 FROM {$wpdb->prefix}monatsblitz_tournaments 
                 WHERE id = %d",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!$t) {
            return new \WP_Error('not_found', 'Turnier nicht gefunden', ['status' => 404]);
        }

        // Einstellungen laden
        $post_author_id = intval(get_option('monatsblitz_author'));
        $template_name  = sanitize_text_field(get_option('monatsblitz_template'));

        // Datum formate
        $date_str = sprintf('%02d.%02d.%04d', $t['day'], $t['month'], $t['year']);
        $iso_date = sprintf('%04d-%02d-%02d', $t['year'], $t['month'], $t['day']);

        $monthNames = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        $monthName = $monthNames[intval($t['month'] ?? 0)] ?? '';

        // Ergebnisse laden
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.player_id, r.points, r.rank, p.forename, p.surname
                 FROM {$wpdb->prefix}monatsblitz_results r
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p ON r.player_id = p.id
                 WHERE r.tournament_id = %d
                 ORDER BY r.rank ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return new \WP_Error('no_results', 'Keine Ergebnisse vorhanden', ['status' => 400]);
        }

        // Spiele laden
        $games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT g.player1_id, g.player2_id, g.leg_type, g.result,
                        p1.forename as p1_forename, p1.surname as p1_surname,
                        p2.forename as p2_forename, p2.surname as p2_surname
                 FROM {$wpdb->prefix}monatsblitz_games g
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p1 ON g.player1_id = p1.id
                 LEFT JOIN {$wpdb->prefix}monatsblitz_players p2 ON g.player2_id = p2.id
                 WHERE g.tournament_id = %d
                 ORDER BY g.id",
                $tournament_id
            ),
            ARRAY_A
        );

        if (!is_array($games)) {
            $games = [];
        }

        // Template laden
        $template_post = get_page_by_title($template_name, OBJECT, 'post');
        $template_content = '';

        if ($template_post && !is_wp_error($template_post)) {
            $template_content = $template_post->post_content;
            $template_title   = $template_post->post_title;
        } else {
            $template_path = MB_PLUGIN_PATH . 'templates/post-template.html';
            if (file_exists($template_path)) {
                $template_content = file_get_contents($template_path);
            }
        }

        if (empty($template_content)) {
            $template_content = "<h1>{{month_name}} {{year}}</h1>
                                 <p>Die Ergebnisse unseres Blitz-Abends vom {{date}}.</p>
                                 {{ranking_rows}}{{games_list}}";
        }

        // Gewinner bestimmen
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
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$wpdb->prefix}monatsblitz_games 
                     WHERE tournament_id = %d 
                       AND (player1_id = %d OR player2_id = %d)",
                    $tournament_id,
                    $winner_player_id,
                    $winner_player_id
                )
            );
        }

        // Ranking rows
        $ranking_rows = '';
        $i = 1;
        foreach ($results as $r) {
            $name   = esc_html(trim($r['forename'] . ' ' . $r['surname']));
            $points = esc_html($r['points']);
            $rank   = esc_html($r['rank']);

            $ranking_rows .= "<tr>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$i}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$name}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$points}</td>
                <td style=\"border:1px solid #ccc; padding:6px;\">{$rank}</td>
            </tr>";

            $i++;
        }

        // Games list
        $games_list = '';
        foreach ($games as $g) {
            $p1 = esc_html(trim($g['p1_forename'] . ' ' . $g['p1_surname']));
            $p2 = esc_html(trim($g['p2_forename'] . ' ' . $g['p2_surname']));
            $res = esc_html($g['result']);
            $games_list .= "<li>{$p1} vs {$p2}: {$res}</li>";
        }

        // Spieler-Liste für Kreuztabelle
        $players = [];
        foreach ($results as $r) {
            $players[] = [
                'id'     => intval($r['player_id']),
                'name'   => esc_html(trim($r['forename'] . ' ' . $r['surname'])),
                'points' => esc_html($r['points']),
                'rank'   => esc_html($r['rank'])
            ];
        }

        $mode        = esc_html((string)($t['mode'] ?? ''));
        $round_count = max(1, intval($t['round_count'] ?? 1));

        // Kreuztabelle optional: finalize must still work when games are missing.
        $table_html = '';
        if (!empty($games)) {
            if ($round_count === 1) {
                $table_html = self::build_cross_table($players, $games, true);
            } else {
                for ($round = 1; $round <= $round_count; $round++) {
                    $table_html .= '<h3>Runde ' . $round . '</h3>';
                    $table_html .= self::build_cross_table($players, $games, false, $round);
                }
                $table_html .= self::build_summary_table($players);
            }
        }

        if ($table_html === '') {
            $table_html = self::build_results_table($results);
        }

        // Platzhalter ersetzen
        $winner_games_placeholder = ((int)$winner_games > 0)
            ? ('aus ' . esc_html((string)$winner_games))
            : '';

        $content = str_replace(
            [
                '{{month_name}}','{{year}}','{{date}}','{{winner_name}}',
                '{{winner_games}}','{{winner_points}}','{{ranking_rows}}',
                '{{games_list}}','{{table}}','{{mode}}','{{round_count}}'
            ],
            [
                esc_html($monthName), esc_html($t['year']), esc_html($date_str),
                $winner_name, $winner_games_placeholder, $winner_points,
                $ranking_rows, $games_list, $table_html, $mode,
                esc_html((string)$round_count)
            ],
            $template_content
        );

        if (BlitzModeService::isBlitzMode((string)($t['mode'] ?? ''))) {
            $post_title = 'Monatsblitz ' . $iso_date;
            $slug = 'blitz-' . $iso_date;
        } else {
            $post_title = 'Turnier ' . $iso_date;
            $slug = 'turnier-' . $iso_date;
        }

        // Post erzeugen oder aktualisieren
        $meta_key = $slug;
        $tournament_meta_key = '_monatsblitz_tournament_id';
        $post_time = '23:30:00';
        $post_date_local = $iso_date . ' ' . $post_time;
        $post_date_gmt   = get_gmt_from_date($post_date_local);

        $postarr = [
            'post_title'    => $post_title,
            'post_name'     => $slug,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_date'     => $post_date_local,
            'post_date_gmt' => $post_date_gmt,
            'post_author'   => $post_author_id
        ];

        // Prefer stable lookup by tournament id so repeated finalize always updates the same post.
        $existing_posts = get_posts([
            'post_type'   => 'post',
            'meta_key'    => $tournament_meta_key,
            'meta_value'  => (string)$tournament_id,
            'numberposts' => 1,
        ]);

        // Fallback: search by current post_name/slug.
        if (empty($existing_posts)) {
            $existing_posts = get_posts([
                'post_type'   => 'post',
                'name'        => $slug,
                'numberposts' => 1,
            ]);
        }

        // Legacy fallback: older monthly posts can use "monatsblitz-YYYY-MM-DD" as slug.
        if (empty($existing_posts) && BlitzModeService::isBlitzMode((string)($t['mode'] ?? ''))) {
            $existing_posts = get_posts([
                'post_type'   => 'post',
                'name'        => 'monatsblitz-' . $iso_date,
                'numberposts' => 1,
            ]);
        }

        // Backward compatibility for already published posts identified only by date slug marker meta.
        if (empty($existing_posts)) {
            $existing_posts = get_posts([
                'post_type'   => 'post',
                'meta_key'    => $meta_key,
                'meta_value'  => '1',
                'numberposts' => 1,
            ]);
        }

        $updated = false;
        if (!empty($existing_posts)) {
            $postarr['ID'] = (int)$existing_posts[0]->ID;
            $post_id = wp_update_post($postarr);
            $updated = true;
        } else {
            $post_id = wp_insert_post($postarr);
        }

        if (is_wp_error($post_id)) {
            return new \WP_Error('post_error', 'Fehler beim Anlegen des Beitrags', ['status' => 500]);
        }

        update_post_meta((int)$post_id, $meta_key, '1');
        update_post_meta((int)$post_id, $tournament_meta_key, (string)$tournament_id);

        // Template-Meta übernehmen
        if ($template_post && !is_wp_error($template_post)) {

            // Featured Image
            $template_thumbnail_id = get_post_thumbnail_id($template_post->ID);
            if ($template_thumbnail_id) {
                set_post_thumbnail($post_id, $template_thumbnail_id);
            }

            // Meta kopieren
            $blacklist_meta = [
                '_thumbnail_id','_edit_last','_edit_lock',
                '_wp_old_slug','_wp_trash_meta_status','_wp_trash_meta_time'
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

            // Taxonomien übernehmen
            $taxonomies = get_object_taxonomies('post', 'names');
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_object_terms($template_post->ID, $taxonomy, ['fields' => 'slugs']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    wp_set_object_terms($post_id, $terms, $taxonomy, false);
                }
            }
        }

        $year_page = null;
        if (BlitzModeService::isBlitzMode((string)($t['mode'] ?? ''))) {
            $year_page = (new YearStaticPageHandler())->createOrUpdate((int)$t['year']);
        }

        return [
            'success'       => true,
            'tournament_id' => $tournament_id,
            'post_id'       => $post_id,
            'post_updated'  => $updated,
            'year_page'     => is_wp_error($year_page) ? null : $year_page,
            'published'     => true
        ];
    }

    public static function build_cross_table(array $players, array $games, bool $include_totals, ?int $round = null): string {
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

    public static function build_summary_table(array $players): string {
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

    public static function build_results_table(array $results): string {
        $html = '<table class="monatsblitz">';
        $html .= '<thead><tr><th>Nr.</th><th>Spieler</th><th>Punkte</th><th>Platz</th></tr></thead><tbody>';

        $i = 1;
        foreach ($results as $r) {
            $name   = esc_html(trim(((string)($r['forename'] ?? '')) . ' ' . ((string)($r['surname'] ?? ''))));
            $points = esc_html((string)($r['points'] ?? ''));
            $rank   = esc_html((string)($r['rank'] ?? ''));

            $html .= '<tr>';
            $html .= '<td>' . $i . '</td>';
            $html .= '<td>' . $name . '</td>';
            $html .= '<td>' . $points . '</td>';
            $html .= '<td>' . $rank . '</td>';
            $html .= '</tr>';

            $i++;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public static function normalize_result_cell(string $result, bool $invert): string {
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

    public static function normalize_string_list($input) {
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

}