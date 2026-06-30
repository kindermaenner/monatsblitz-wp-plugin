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
use Monatsblitz\Queries\GetPlayersHandler;
use Monatsblitz\Queries\GetTournamentsHandler;
use Monatsblitz\Queries\GetTournamentHandler;
use Monatsblitz\Queries\GetGamesHandler;
use Monatsblitz\Queries\GetResultsHandler;
use Monatsblitz\Output\FinalizeTournamentHandler;
use Monatsblitz\Output\YearStaticPageHandler;

class MainService
{
    public static function createPlayer($request)
    {
        return (new CreatePlayerHandler())->handle($request);
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

        $result = (new YearStaticPageHandler())->createOrUpdate($year, true);

        if ($result instanceof \WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

}
