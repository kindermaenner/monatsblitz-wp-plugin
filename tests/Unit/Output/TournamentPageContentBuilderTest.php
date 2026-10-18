<?php

declare(strict_types=1);

use Monatsblitz\Output\TemplateProcessingException;
use Monatsblitz\Output\TournamentPageContentBuilder;

it('replaces style tag and placeholders in tournament template', function () {
    $builder = new TournamentPageContentBuilder();
    $template = '<style>table.monatsblitz { color: red; }</style><div>{{month_name}} {{year}}</div>';
    $replacements = ['{{month_name}}' => 'Januar', '{{year}}' => '2026'];

    $result = $builder->buildContent($template, $replacements);

    expect($result)->not->toContain('<style>');
    expect($result)->toContain('<div>Januar 2026</div>');
});

it('builds metadata and placeholders correctly', function () {
    $builder = new TournamentPageContentBuilder();
    $results = [
        ['player_id' => 1, 'forename' => 'Max', 'surname' => 'Mustermann', 'points' => 5],
    ];

    $meta = $builder->buildMetadata($results, 7);
    expect($meta['name'])->toBe('Max Mustermann');
    expect($meta['points'])->toBe('5');
    expect($meta['games_placeholder'])->toBe('aus 7');

    $placeholders = $builder->buildPlaceholders(
        'Januar',
        '10.01.2026',
        '2026',
        '5+0',
        '1',
        '<tr></tr>',
        '<ul></ul>',
        '<table></table>',
        $meta
    );

    expect($placeholders['{{winner_name}}'])->toBe('Max Mustermann');
    expect($placeholders['{{winner_games}}'])->toBe('aus 7');
    expect($placeholders['{{year}}'])->toBe('2026');
});
