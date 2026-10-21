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
    expect($meta['text'])->toBe('Max Mustermann siegt mit 5 aus 7 Punkten.');

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
    expect($placeholders['{{winner_verb}}'])->toBe('siegt');
    expect($placeholders['{{winner_text}}'])->toBe('Max Mustermann siegt mit 5 aus 7 Punkten.');
    expect($placeholders['{{year}}'])->toBe('2026');
});

it('handles multiple tied winners and formats names correctly', function () {
    $builder = new TournamentPageContentBuilder();

    // 2 tied winners
    $resultsTwo = [
        ['player_id' => 1, 'forename' => 'Alexander', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
        ['player_id' => 2, 'forename' => 'Lukas', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
        ['player_id' => 3, 'forename' => 'Vitaliy', 'surname' => 'Sviridov', 'rank' => 2, 'points' => '6.5'],
    ];

    $metaTwo = $builder->buildMetadata($resultsTwo, 9);
    expect($metaTwo['name'])->toBe('Alexander Rangosch und Lukas Rangosch');
    expect($metaTwo['count'])->toBe(2);
    expect($metaTwo['is_plural'])->toBeTrue();
    expect($metaTwo['text'])->toBe('Alexander Rangosch und Lukas Rangosch siegen mit jeweils 8.0 aus 9 Punkten.');

    $placeholdersTwo = $builder->buildPlaceholders('Oktober', '21.10.2026', '2026', '3+2', '1', '', '', '', $metaTwo);
    expect($placeholdersTwo['{{winner_name}}'])->toBe('Alexander Rangosch und Lukas Rangosch');
    expect($placeholdersTwo['{{winner_verb}}'])->toBe('siegen');

    // 3 tied winners
    $resultsThree = [
        ['player_id' => 1, 'forename' => 'A', 'surname' => 'Alpha', 'rank' => 1, 'points' => 7],
        ['player_id' => 2, 'forename' => 'B', 'surname' => 'Beta', 'rank' => 1, 'points' => 7],
        ['player_id' => 3, 'forename' => 'C', 'surname' => 'Gamma', 'rank' => 1, 'points' => 7],
        ['player_id' => 4, 'forename' => 'D', 'surname' => 'Delta', 'rank' => 2, 'points' => 4],
    ];

    $metaThree = $builder->buildMetadata($resultsThree, 9);
    expect($metaThree['name'])->toBe('A Alpha, B Beta und C Gamma');
    expect($metaThree['count'])->toBe(3);
    expect($metaThree['is_plural'])->toBeTrue();
    expect($metaThree['text'])->toBe('A Alpha, B Beta und C Gamma siegen mit jeweils 7 aus 9 Punkten.');
});

it('evaluates inline plural helpers and verb placeholder for single and multiple winners', function () {
    $builder = new TournamentPageContentBuilder();

    $template = '{{winner_name}} {{plural:siegt|siegen}} mit {{plural:|jeweils }}{{winner_points}} {{winner_games}} Punkten.';

    // Single winner
    $resultsSingle = [
        ['player_id' => 1, 'forename' => 'Alexander', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
    ];
    $metaSingle = $builder->buildMetadata($resultsSingle, 9);
    $placeholdersSingle = $builder->buildPlaceholders('Oktober', '21.10.2026', '2026', '3+2', '1', '', '', '', $metaSingle);
    $renderedSingle = $builder->buildContent($template, $placeholdersSingle, $metaSingle);

    expect($renderedSingle)->toBe('Alexander Rangosch siegt mit 8.0 aus 9 Punkten.');

    // Multiple winners
    $resultsMultiple = [
        ['player_id' => 1, 'forename' => 'Alexander', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
        ['player_id' => 2, 'forename' => 'Lukas', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
    ];
    $metaMultiple = $builder->buildMetadata($resultsMultiple, 9);
    $placeholdersMultiple = $builder->buildPlaceholders('Oktober', '21.10.2026', '2026', '3+2', '1', '', '', '', $metaMultiple);
    $renderedMultiple = $builder->buildContent($template, $placeholdersMultiple, $metaMultiple);

    expect($renderedMultiple)->toBe('Alexander Rangosch und Lukas Rangosch siegen mit jeweils 8.0 aus 9 Punkten.');
});

it('evaluates block conditionals for singular and plural', function () {
    $builder = new TournamentPageContentBuilder();

    $template = '{{#singular}}Einzelsieger: {{winner_name}}!{{/singular}}{{#plural}}Geteilter Sieg: {{winner_name}}!{{/plural}}';

    // Single winner
    $resultsSingle = [
        ['player_id' => 1, 'forename' => 'Alexander', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
    ];
    $metaSingle = $builder->buildMetadata($resultsSingle, 9);
    $placeholdersSingle = $builder->buildPlaceholders('Oktober', '21.10.2026', '2026', '3+2', '1', '', '', '', $metaSingle);
    $renderedSingle = $builder->buildContent($template, $placeholdersSingle, $metaSingle);

    expect($renderedSingle)->toBe('Einzelsieger: Alexander Rangosch!');

    // Multiple winners
    $resultsMultiple = [
        ['player_id' => 1, 'forename' => 'Alexander', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
        ['player_id' => 2, 'forename' => 'Lukas', 'surname' => 'Rangosch', 'rank' => 1, 'points' => '8.0'],
    ];
    $metaMultiple = $builder->buildMetadata($resultsMultiple, 9);
    $placeholdersMultiple = $builder->buildPlaceholders('Oktober', '21.10.2026', '2026', '3+2', '1', '', '', '', $metaMultiple);
    $renderedMultiple = $builder->buildContent($template, $placeholdersMultiple, $metaMultiple);

    expect($renderedMultiple)->toBe('Geteilter Sieg: Alexander Rangosch und Lukas Rangosch!');
});
