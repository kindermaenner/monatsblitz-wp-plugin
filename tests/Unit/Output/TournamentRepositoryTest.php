<?php

declare(strict_types=1);

use Monatsblitz\Output\TournamentRepository;

it('returns an error when the tournament cannot be found', function () {
    global $wpdb;

    $wpdb->get_row_result = null;

    $repository = new TournamentRepository();
    $result = $repository->loadTournament(10);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('not_found');
});

it('loads tournament results and games successfully', function () {
    global $wpdb;

    $wpdb->get_results_queue = [
        [
            ['player_id' => 1, 'points' => 5, 'rank' => 1, 'forename' => 'Max', 'surname' => 'Muster'],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0'],
        ],
    ];

    $repository = new TournamentRepository();

    $results = $repository->loadTournamentResults(10);
    expect($results)->toBeArray();
    expect($results[0]['forename'])->toBe('Max');

    $games = $repository->loadTournamentGames(10);
    expect($games)->toBeArray();
    expect($games[0]['player1_id'])->toBe(1);
});

it('returns an error when tournament results are empty', function () {
    global $wpdb;

    $wpdb->get_results_queue = [[]];

    $repository = new TournamentRepository();
    $result = $repository->loadTournamentResults(10);

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect($result->code)->toBe('no_results');
});

it('counts winner games from the database', function () {
    global $wpdb;

    $wpdb->get_var_result = '3';

    $repository = new TournamentRepository();
    $count = $repository->countWinnerGames(10, 1);

    expect($count)->toBe(3);
});