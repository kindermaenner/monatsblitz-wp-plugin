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
