<?php

declare(strict_types=1);

use Monatsblitz\Service\MainService;
use Tests\Unit\Helper\MainServiceFakeRequest;

it('creates a player through the service', function () {
	global $wpdb;

	$wpdb->get_var_result = 33;

	$result = MainService::createPlayer(new MainServiceFakeRequest([
		'forename' => 'Max',
		'surname' => 'Mustermann',
	]));

	expect($result['success'])->toBeTrue()
		->and($result['player_id'])->toBe(33);
});

it('wraps successful tournament creation response', function () {
	global $wpdb;

	$wpdb->insert_id = 101;

	$result = MainService::createTournament(new MainServiceFakeRequest([
		'date' => '2026-06-28',
		'mode' => '3+2',
		'round_count' => 2,
	]));

	expect($result['success'])->toBeTrue()
		->and($result['tournament_id'])->toBe(101);
});

it('returns WP_Error for invalid tournament input', function () {
	$result = MainService::createTournament(new MainServiceFakeRequest([
		'mode' => '3+2',
	]));

	expect($result)->toBeInstanceOf(WP_Error::class)
		->and($result->code)->toBe('invalid_data');
});

it('wraps successful game creation response', function () {
	global $wpdb;

	$wpdb->get_var_result = 1;
	$wpdb->insert_id = 202;

	$result = MainService::createGame(new MainServiceFakeRequest([
		'tournament_id' => 1,
		'player1_id' => 1,
		'player2_id' => 2,
		'result' => '1-0',
	]));

	expect($result['success'])->toBeTrue()
		->and($result['game_id'])->toBe(202);
});

it('returns WP_Error for invalid game input', function () {
	$result = MainService::createGame(new MainServiceFakeRequest([
		'tournament_id' => 0,
		'player1_id' => 0,
		'player2_id' => 0,
	]));

	expect($result)->toBeInstanceOf(WP_Error::class)
		->and($result->code)->toBe('invalid_data');
});

it('wraps successful result creation response', function () {
	global $wpdb;

	$wpdb->get_var_queue = [1, 1, null];
	$wpdb->insert_id = 303;

	$result = MainService::createResult(new MainServiceFakeRequest([
		'tournament_id' => 1,
		'player_id' => 1,
		'points' => 2.5,
		'rank' => 1,
	]));

	expect($result['success'])->toBeTrue()
		->and($result['result_id'])->toBe(303);
});

it('returns WP_Error for invalid result input', function () {
	$result = MainService::createResult(new MainServiceFakeRequest([
		'tournament_id' => 0,
		'player_id' => 0,
	]));

	expect($result)->toBeInstanceOf(WP_Error::class)
		->and($result->code)->toBe('invalid_data');
});

it('returns players list through service query wrapper', function () {
	global $wpdb;

	$wpdb->results = [
		['id' => 1, 'forename' => 'Max', 'surname' => 'Mustermann'],
	];

	$result = MainService::getPlayers([]);

	expect($result)->toHaveCount(1)
		->and($result[0]['id'])->toBe(1);
});

it('returns tournaments list through service query wrapper', function () {
	global $wpdb;

	$wpdb->results = [
		['id' => 1, 'year' => 2026, 'month' => 6, 'day' => 28, 'mode' => '3+2', 'round_count' => 2],
	];

	$result = MainService::getTournaments([]);

	expect($result)->toHaveCount(1)
		->and($result[0]['id'])->toBe(1);
});

it('returns a single tournament through service query wrapper', function () {
	global $wpdb;

	$wpdb->get_row_result = ['id' => 7, 'year' => 2026];

	$result = MainService::getTournament(['id' => 7]);

	expect($result['id'])->toBe(7);
});

it('returns games through service query wrapper', function () {
	global $wpdb;

	$wpdb->results = [
		['id' => 11, 'tournament_id' => 5],
	];

	$result = MainService::getGames(['tournament_id' => 5]);

	expect($result)->toHaveCount(1)
		->and($result[0]['id'])->toBe(11);
});

it('returns results through service query wrapper', function () {
	global $wpdb;

	$wpdb->results = [
		['id' => 12, 'tournament_id' => 5, 'rank' => 1],
	];

	$result = MainService::getResults(['tournament_id' => 5]);

	expect($result)->toHaveCount(1)
		->and($result[0]['id'])->toBe(12);
});

it('returns WP_Error when finalization input is invalid', function () {
	$result = MainService::finalizeTournament(new MainServiceFakeRequest([]));

	expect($result)->toBeInstanceOf(WP_Error::class)
		->and($result->code)->toBe('invalid_data');
});

it('wraps successful finalize response', function () {
	global $wpdb;

	$GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
	$GLOBALS['mb_test_options']['monatsblitz_template'] = 'Template_Monatsblitz';

	$GLOBALS['mb_test_template_post'] = (object) [
		'ID' => 77,
		'post_title' => 'Template_Monatsblitz',
		'post_content' => '{{month_name}} {{year}} {{date}} {{winner_name}} {{winner_games}} {{winner_points}} {{ranking_rows}} {{games_list}} {{table}} {{mode}} {{round_count}}',
	];

	$wpdb->get_row_result = [
		'id' => 1,
		'year' => 2026,
		'month' => 6,
		'day' => 28,
		'mode' => '3+2',
		'round_count' => 1,
	];

	$wpdb->get_results_queue = [
		[
			['player_id' => 1, 'points' => '3.0', 'rank' => 1, 'forename' => 'Max', 'surname' => 'Mustermann'],
		],
		[
			['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1-0', 'p1_forename' => 'Max', 'p1_surname' => 'Mustermann', 'p2_forename' => 'Anna', 'p2_surname' => 'Musterfrau'],
		],
	];
	$wpdb->get_var_result = 1;

	$result = MainService::finalizeTournament(new MainServiceFakeRequest([
		'tournament_id' => 1,
	]));

	expect($result['success'])->toBeTrue()
		->and($result['published'])->toBeTrue()
		->and($result['tournament_id'])->toBe(1);
});

it('rebuilds yearly static page through service wrapper', function () {
	global $wpdb;

	$GLOBALS['mb_test_options']['monatsblitz_template_static_page'] = 'Template_Jahr';
	$GLOBALS['mb_test_template_post'] = (object) [
		'ID' => 88,
		'post_title' => 'Template_Jahr',
		'post_content' => '{{blitz_monthly_overview}}{{blitz_ranking_year}}',
	];

	$wpdb->get_results_queue = [
		[
			[
				'tournament_id' => 1,
				'month' => 1,
				'mode' => '5+0',
				'player_id' => 10,
				'rank' => 1,
				'forename' => 'Max',
				'surname' => 'Mustermann',
			],
		],
	];

	$result = MainService::buildYearStaticPage(new MainServiceFakeRequest([
		'year' => 2026,
	]));

	expect($result['success'])->toBeTrue()
		->and($result['year'])->toBe(2026)
		->and($result['page_id'])->toBe(123);
});

