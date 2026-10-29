<?php

declare(strict_types=1);

use Monatsblitz\Api\Api;
use Monatsblitz\Service\MainService;

beforeEach(function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = '';
    unset($_SERVER['HTTP_X_MB_KEY']);
    unset($_SERVER['HTTP_X_MB_API_VERSION']);
    $GLOBALS['mb_test_actions'] = [];
    $GLOBALS['mb_test_routes'] = [];
});

it('registers rest_api_init action on init', function () {
    Api::init();

    expect($GLOBALS['mb_test_actions'])->toHaveCount(1)
        ->and($GLOBALS['mb_test_actions'][0]['hook'])->toBe('rest_api_init')
        ->and($GLOBALS['mb_test_actions'][0]['callback'])->toBe([Api::class, 'register_routes']);
});

it('registers all REST routes with API key permission callback', function () {
    Api::register_routes();

    expect($GLOBALS['mb_test_routes'])->toHaveCount(15);

    $expectedRoutes = [
        '/player' => [MainService::class, 'createPlayer'],
        '/mode' => [MainService::class, 'createMode'],
        '/modes' => [MainService::class, 'getModes'],
        '/tournament' => [MainService::class, 'createTournament'],
        '/game' => [MainService::class, 'createGame'],
        '/players' => [MainService::class, 'getPlayers'],
        '/tournaments' => [MainService::class, 'getTournaments'],
        '/tournament/(?P<id>\d+)' => [MainService::class, 'getTournament'],
        '/games/(?P<tournament_id>\d+)' => [MainService::class, 'getGames'],
        '/result' => [MainService::class, 'createResult'],
        '/results/(?P<tournament_id>\d+)' => [MainService::class, 'getResults'],
        '/finalize' => [MainService::class, 'finalizeTournament'],
        '/buildYearPage' => [MainService::class, 'buildYearStaticPage'],
        '/recreatePosts' => [MainService::class, 'recreatePosts'],
        '/tournament/withdraw' => [MainService::class, 'withdrawPlayer'],
    ];

    foreach ($GLOBALS['mb_test_routes'] as $route) {
        expect($route['namespace'])->toBe('monatsblitz/v1')
            ->and($route['args']['permission_callback'])->toBe([Api::class, 'verify_api_key'])
            ->and($route['args']['callback'])->toBe($expectedRoutes[$route['route']]);
    }
});

it('verifies api key successfully', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'secret';
    $_SERVER['HTTP_X_MB_API_VERSION'] = (string) Api::API_VERSION;

    $result = Api::verify_api_key();

    expect($result)->toBeTrue();
});

it('fails api key verification when configured key does not match request header', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'wrong';
    $_SERVER['HTTP_X_MB_API_VERSION'] = (string) Api::API_VERSION;

    $result = Api::verify_api_key();

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('rest_forbidden')
        ->and($result->data['status'])->toBe(401);
});

it('fails api key verification when no key is configured', function () {
    $_SERVER['HTTP_X_MB_KEY'] = 'secret';
    $_SERVER['HTTP_X_MB_API_VERSION'] = (string) Api::API_VERSION;

    $result = Api::verify_api_key();

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('rest_forbidden')
        ->and($result->data['status'])->toBe(401);
});

it('fails verification when API version header is missing', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'secret';
    // No HTTP_X_MB_API_VERSION set

    $result = Api::verify_api_key();

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('rest_api_version_mismatch')
        ->and($result->data['status'])->toBe(426)
        ->and($result->data['reason'])->toBe('app_upgrade_required')
        ->and($result->message)->toContain('Monatsblitz-App');
});

it('fails verification when client API version is older than plugin API version', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'secret';
    $_SERVER['HTTP_X_MB_API_VERSION'] = (string) (Api::API_VERSION - 1);

    $result = Api::verify_api_key();

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('rest_api_version_mismatch')
        ->and($result->data['status'])->toBe(426)
        ->and($result->data['reason'])->toBe('app_upgrade_required')
        ->and($result->message)->toContain('Monatsblitz-App');
});

it('fails verification when client API version is newer than plugin API version', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'secret';
    $_SERVER['HTTP_X_MB_KEY'] = 'secret';
    $_SERVER['HTTP_X_MB_API_VERSION'] = (string) (Api::API_VERSION + 1);

    $result = Api::verify_api_key();

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('rest_api_version_mismatch')
        ->and($result->data['status'])->toBe(426)
        ->and($result->data['reason'])->toBe('plugin_upgrade_required')
        ->and($result->message)->toContain('WordPress-Plugin');
});









