<?php

declare(strict_types=1);

use Monatsblitz\Output\TournamentContentBuilder;

it('builds a results table from result records', function () {
    $html = TournamentContentBuilder::buildResultsTable([
        ['forename' => 'Max', 'surname' => 'Muster', 'points' => 5, 'rank' => 1],
        ['forename' => 'Erika', 'surname' => 'Beispiel', 'points' => 3, 'rank' => 2],
    ]);

    expect($html)->toContain('<table class="monatsblitz">');
    expect($html)->toContain('<td>Max Muster</td>');
    expect($html)->toContain('<td>5</td>');
    expect($html)->toContain('<td>2</td>');
});

it('builds a cross table with totals and pending cell markers', function () {
    $html = TournamentContentBuilder::buildCrossTable(
        [
            ['id' => 1, 'name' => 'Max', 'points' => 5, 'rank' => 1],
            ['id' => 2, 'name' => 'Erika', 'points' => 3, 'rank' => 2],
            ['id' => 3, 'name' => 'Paul', 'points' => 1, 'rank' => 3],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 1, 'result' => '1:0'],
        ],
        true
    );

    expect($html)->toContain('class="mb-cell-empty mb-cell-diagonal"');
    expect($html)->toContain('class="mb-cell-empty mb-cell-pending"');
    expect($html)->toContain('<th>Punkte</th><th>Platz</th>');
    expect($html)->toContain('>1<');
});

it('builds a cross table for a specific round only', function () {
    $html = TournamentContentBuilder::buildCrossTable(
        [
            ['id' => 1, 'name' => 'Max', 'points' => 5, 'rank' => 1],
            ['id' => 2, 'name' => 'Erika', 'points' => 3, 'rank' => 2],
        ],
        [
            ['player1_id' => 1, 'player2_id' => 2, 'leg_type' => 2, 'result' => '0:1'],
        ],
        false,
        1
    );

    expect($html)->not->toContain('>0<');
});

it('builds a summary table from player standings', function () {
    $html = TournamentContentBuilder::buildSummaryTable([
        ['name' => 'Max', 'points' => 5, 'rank' => 1],
    ]);

    expect($html)->toContain('<h3>Gesamtergebnis</h3>');
    expect($html)->toContain('<td>Max</td>');
    expect($html)->toContain('<td>5</td>');
});

it('normalizes common result cells with and without inversion', function () {
    expect(TournamentContentBuilder::normalizeResultCell('1:0', false))->toBe('1');
    expect(TournamentContentBuilder::normalizeResultCell('1:0', true))->toBe('0');
    expect(TournamentContentBuilder::normalizeResultCell('+:-', false))->toBe('+');
    expect(TournamentContentBuilder::normalizeResultCell('+:-', true))->toBe('-');
    expect(TournamentContentBuilder::normalizeResultCell('0.5-0.5', false))->toBe('½');
    expect(TournamentContentBuilder::normalizeResultCell('RAW', false))->toBe('RAW');
});