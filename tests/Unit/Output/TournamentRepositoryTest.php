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

it('deletes only games exceeding round count 1 and never wipes all games', function () {
    global $wpdb;

    $repository = new TournamentRepository();
    $repository->deleteGamesExceedingRoundCount(42, 1);

    expect($wpdb->last_prepared_query)->toBe('DELETE FROM wp_monatsblitz_games WHERE tournament_id = %d AND leg_type > %d');
    expect($wpdb->last_prepared_args)->toBe([42, 1]);
    expect($wpdb->last_delete_table)->toBeNull();
});

it('deletes only games exceeding round count 2', function () {
    global $wpdb;

    $repository = new TournamentRepository();
    $repository->deleteGamesExceedingRoundCount(99, 2);

    expect($wpdb->last_prepared_query)->toBe('DELETE FROM wp_monatsblitz_games WHERE tournament_id = %d AND leg_type > %d');
    expect($wpdb->last_prepared_args)->toBe([99, 2]);
    expect($wpdb->last_delete_table)->toBeNull();
});

it('updates tournament round count in database', function () {
    global $wpdb;

    $repository = new TournamentRepository();
    $repository->updateRoundCount(55, 3);

    expect($wpdb->last_update_table)->toBe('wp_monatsblitz_tournaments');
    expect($wpdb->last_update_data)->toBe(['round_count' => 3]);
    expect($wpdb->last_update_where)->toBe(['id' => 55]);
});

it('marks tournament as finalized in database', function () {
    global $wpdb;

    $repository = new TournamentRepository();
    $repository->markFinalized(55);

    expect($wpdb->last_update_table)->toBe('wp_monatsblitz_tournaments');
    expect($wpdb->last_update_data)->toBe(['status' => 'finalized']);
    expect($wpdb->last_update_where)->toBe(['id' => 55]);
});
