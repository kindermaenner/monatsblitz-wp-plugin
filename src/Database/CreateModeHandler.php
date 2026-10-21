<?php

declare(strict_types=1);

namespace Monatsblitz\Database;

if (!defined('ABSPATH')) {
    exit;
}

class CreateModeHandler
{
    public function handle($request)
    {
        global $wpdb;

        $params = is_array($request) ? $request : (method_exists($request, 'get_json_params') ? ($request->get_json_params() ?? []) : []);

        $name = sanitize_text_field(trim((string)($params['name'] ?? '')));

        if ($name === '') {
            return new \WP_Error('invalid_data', 'Name ist erforderlich', ['status' => 400]);
        }

        if (mb_strlen($name) > 50) {
            return new \WP_Error('invalid_data', 'Name darf maximal 50 Zeichen lang sein', ['status' => 400]);
        }

        $table = $wpdb->prefix . 'monatsblitz_modes';

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name FROM {$table} WHERE LOWER(name) = LOWER(%s)",
                $name
            ),
            ARRAY_A
        );

        if ($existing) {
            return [
                'success' => true,
                'id'      => (int) $existing['id'],
                'name'    => (string) $existing['name'],
                'message' => 'Modus existiert bereits'
            ];
        }

        $inserted = $wpdb->insert(
            $table,
            ['name' => $name]
        );

        if ($inserted === false) {
            return new \WP_Error('db_error', 'Fehler beim Speichern des Modus', ['status' => 500]);
        }

        return [
            'success' => true,
            'id'      => (int) $wpdb->insert_id,
            'name'    => $name
        ];
    }
}
