<?php

declare(strict_types=1);

namespace Monatsblitz\Api;

use Monatsblitz\Service\MainService;

if (!defined('ABSPATH')) {
    exit;
}

class Api
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {

        $rest_api_namespace = 'monatsblitz/v1';

        register_rest_route($rest_api_namespace, '/player', [
            'methods'  => 'POST',
            'callback' => [MainService::class, 'createPlayer'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/tournament', [
            'methods'  => 'POST',
            'callback' => [MainService::class, 'createTournament'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/game', [
            'methods'  => 'POST, PUT',
            'callback' => [MainService::class, 'createGame'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/players', [
            'methods'  => 'GET',
            'callback' => [MainService::class, 'getPlayers'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/tournaments', [
            'methods'  => 'GET',
            'callback' => [MainService::class, 'getTournaments'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/tournament/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [MainService::class, 'getTournament'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/games/(?P<tournament_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [MainService::class, 'getGames'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/result', [
            'methods'  => 'POST',
            'callback' => [MainService::class, 'createResult'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/results/(?P<tournament_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [MainService::class, 'getResults'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/finalize', [
            'methods'  => 'POST',
            'callback' => [MainService::class, 'finalizeTournament'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/buildYearPage', [
            'methods'  => 'POST',
            'callback' => [MainService::class, 'buildYearStaticPage'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);

        register_rest_route($rest_api_namespace, '/recreatePosts', [
            'methods'  => 'POST',
            'callback' => [MainService::class, 'recreatePosts'],
            'permission_callback' => [self::class, 'verify_api_key'],
        ]);
    }

    public static function verify_api_key()
    {
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
}
