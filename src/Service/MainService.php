<?php

declare(strict_types=1);

namespace Monatsblitz\Service;

if (!defined('ABSPATH')) {
    exit;
}

use Monatsblitz\Database\CreatePlayerHandler;
use Monatsblitz\Database\CreateTournamentHandler;
use Monatsblitz\Database\CreateGameHandler;
use Monatsblitz\Database\CreateResultHandler;
use Monatsblitz\Database\CreateModeHandler;
use Monatsblitz\Queries\GetModesHandler;
use Monatsblitz\Queries\GetPlayersHandler;
use Monatsblitz\Queries\GetTournamentsHandler;
use Monatsblitz\Queries\GetTournamentHandler;
use Monatsblitz\Queries\GetGamesHandler;
use Monatsblitz\Queries\GetResultsHandler;
use Monatsblitz\Output\FinalizeTournamentHandler;
use Monatsblitz\Output\RecreatePostsHandler;
use Monatsblitz\Output\YearStaticPageHandler;

class MainService
{
    public static function createPlayer($request)
    {
        return (new CreatePlayerHandler())->handle($request);
    }

    public static function createMode($request)
    {
        $result = (new CreateModeHandler())->handle($request);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function getModes()
    {
        return (new GetModesHandler())->handle();
    }

    public static function createTournament($request)
    {
        $result = (new CreateTournamentHandler())->handle($request);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function createGame($request)
    {
        $result = (new CreateGameHandler())->handle($request);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function createResult($request)
    {
        $result = (new CreateResultHandler())->handle($request);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function getPlayers($request)
    {
        return (new GetPlayersHandler())->handle();
    }

    public static function getTournaments($request)
    {
        return (new GetTournamentsHandler())->handle();
    }

    public static function getTournament($request)
    {
        return (new GetTournamentHandler())->handle($request);
    }

    public static function getGames($request)
    {
        return (new GetGamesHandler())->handle($request);
    }

    public static function getResults($request)
    {
        return (new GetResultsHandler())->handle($request);
    }

    public static function finalizeTournament($request)
    {
        $result = (new FinalizeTournamentHandler())->handle($request);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function buildYearStaticPage($request)
    {
        $params = $request->get_json_params();
        $year = intval($params['year'] ?? 0);

        $result = (new YearStaticPageHandler())->createOrUpdate($year);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function recreatePosts($request)
    {
        $result = (new RecreatePostsHandler())->handle();

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function withdrawPlayer($request)
    {
        $params = self::getRequestParams($request);
        $result = (new \Monatsblitz\Database\WithdrawPlayerHandler())->handle($params);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function addTournamentPlayer($request)
    {
        $params = self::getRequestParams($request);
        $result = (new \Monatsblitz\Database\AddTournamentPlayerHandler())->handle($params);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function reopenTournament($request)
    {
        $result = (new \Monatsblitz\Database\ReopenTournamentHandler())->handle($request);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function verifyMaintenanceAuth($request)
    {
        return rest_ensure_response([
            'valid'        => true,
            'role'         => 'admin',
            'api_version'  => \Monatsblitz\Api\Api::API_VERSION,
            'capabilities' => ['reopen_tournament', 'recreate_posts'],
        ]);
    }


    private static function getRequestParams($request): array
    {
        if (is_object($request) && method_exists($request, 'get_json_params')) {
            return $request->get_json_params() ?? [];
        }

        if (is_array($request)) {
            return $request;
        }

        return [];
    }
}
