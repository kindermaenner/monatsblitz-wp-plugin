<?php

declare(strict_types=1);

use Monatsblitz\Output\YearStaticContentBuilder;

it('calculates points from rank correctly', function () {
    $builder = new YearStaticContentBuilder();

    expect($builder->pointsFromRank(1))->toBe(5);
    expect($builder->pointsFromRank(2))->toBe(4);
    expect($builder->pointsFromRank(3))->toBe(3);
    expect($builder->pointsFromRank(4))->toBe(2);
    expect($builder->pointsFromRank(5))->toBe(1);
    expect($builder->pointsFromRank(6))->toBe(0);
});

it('aggregates monthly stats and builds content', function () {
    $builder = new YearStaticContentBuilder();
    $rows = [
        [
            'tournament_id' => 1,
            'month' => 2,
            'mode' => '5+0',
            'player_id' => 10,
            'rank' => 1,
            'forename' => 'Max',
            'surname' => 'Mustermann',
        ],
    ];

    [$monthly, $pointsPerTournament] = $builder->aggregateMonthlyStats($rows);

    expect($monthly)->toHaveKey(10);
    expect($monthly[10]['name'])->toBe('Max Mustermann');
    expect($monthly[10]['months'][2]['points'])->toBe(5);
    expect($pointsPerTournament[10])->toBe([5]);

    $content = $builder->buildContent(
        '<h1>{{year}}</h1>{{blitz_monthly_overview}}{{blitz_ranking_year}}',
        2026,
        $monthly,
        $pointsPerTournament,
        false
    );

    expect($content)->toContain('<h1>2026</h1>');
    expect($content)->toContain('Max Mustermann');
    expect($content)->toContain('monatsblitz-year-overview');
    expect($content)->toContain('monatsblitz-year-ranking');
});

it('includes 0-point tournaments in pointsPerTournament and counts them towards top 7 participations', function () {
    $builder = new YearStaticContentBuilder();
    $rows = [
        // Player 1: 3 tournaments (5, 0, 0) -> counted 3, points 5
        ['tournament_id' => 1, 'month' => 1, 'mode' => '5+0', 'player_id' => 1, 'rank' => 1, 'forename' => 'Alice', 'surname' => 'A'],
        ['tournament_id' => 2, 'month' => 2, 'mode' => '5+0', 'player_id' => 1, 'rank' => 6, 'forename' => 'Alice', 'surname' => 'A'],
        ['tournament_id' => 3, 'month' => 3, 'mode' => '5+0', 'player_id' => 1, 'rank' => 7, 'forename' => 'Alice', 'surname' => 'A'],

        // Player 2: 8 tournaments (5, 4, and 6x 0) -> counted 7, points 9
        ['tournament_id' => 1, 'month' => 1, 'mode' => '5+0', 'player_id' => 2, 'rank' => 1, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 2, 'month' => 2, 'mode' => '5+0', 'player_id' => 2, 'rank' => 2, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 3, 'month' => 3, 'mode' => '5+0', 'player_id' => 2, 'rank' => 6, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 4, 'month' => 4, 'mode' => '5+0', 'player_id' => 2, 'rank' => 6, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 5, 'month' => 5, 'mode' => '5+0', 'player_id' => 2, 'rank' => 6, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 6, 'month' => 6, 'mode' => '5+0', 'player_id' => 2, 'rank' => 6, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 7, 'month' => 7, 'mode' => '5+0', 'player_id' => 2, 'rank' => 6, 'forename' => 'Bob', 'surname' => 'B'],
        ['tournament_id' => 8, 'month' => 8, 'mode' => '5+0', 'player_id' => 2, 'rank' => 6, 'forename' => 'Bob', 'surname' => 'B'],

        // Player 3: 2 tournaments with 0 points -> counted 2, points 0
        ['tournament_id' => 1, 'month' => 1, 'mode' => '5+0', 'player_id' => 3, 'rank' => 8, 'forename' => 'Charlie', 'surname' => 'C'],
        ['tournament_id' => 2, 'month' => 2, 'mode' => '5+0', 'player_id' => 3, 'rank' => 9, 'forename' => 'Charlie', 'surname' => 'C'],
    ];

    [$monthly, $pointsPerTournament] = $builder->aggregateMonthlyStats($rows);

    expect($pointsPerTournament[1])->toBe([5, 0, 0]);
    expect($pointsPerTournament[2])->toBe([5, 4, 0, 0, 0, 0, 0, 0]);
    expect($pointsPerTournament[3])->toBe([0, 0]);

    $rankingHtml = $builder->buildYearRankingTable($monthly, $pointsPerTournament);

    // Bob: 9 points, 7 counted participations
    expect($rankingHtml)->toContain('<td>Bob B</td><td>9</td><td>7</td>');
    // Alice: 5 points, 3 counted participations
    expect($rankingHtml)->toContain('<td>Alice A</td><td>5</td><td>3</td>');
    // Charlie: 0 points, 2 counted participations
    expect($rankingHtml)->toContain('<td>Charlie C</td><td>0</td><td>2</td>');
});
