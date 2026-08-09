<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class RequestNormalizer
{
    public const INVALID_STRING_LIST_MESSAGE = 'Input must be a string or an array of strings';

    public static function normalizeStringList($input)
    {
        if ($input === null) {
            return new \WP_Error('invalid_data', static::INVALID_STRING_LIST_MESSAGE, ['status' => 400]);
        }

        if (is_string($input)) {
            $input = [$input];
        }

        if (!is_array($input)) {
            return new \WP_Error('invalid_data', static::INVALID_STRING_LIST_MESSAGE, ['status' => 400]);
        }

        $normalized = [];
        foreach ($input as $item) {
            if (!is_string($item)) {
                return new \WP_Error('invalid_data', static::INVALID_STRING_LIST_MESSAGE, ['status' => 400]);
            }

            $item = trim($item);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    public static function normalizeItems($request)
    {
        $params = $request->get_json_params();
        $input = $params;

        if (is_array($params) && array_key_exists('items', $params) && count($params) === 1) {
            $input = $params['items'];
        }

        $items = static::normalizeStringList($input);
        if (is_wp_error($items)) {
            return $items;
        }

        return rest_ensure_response([
            'count' => count($items),
            'items' => $items,
        ]);
    }
}
