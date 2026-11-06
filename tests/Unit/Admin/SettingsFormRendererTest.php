<?php

declare(strict_types=1);

use Monatsblitz\Admin\SettingsFormRenderer;

it('preserves selected default and rotation modes in the settings form', function () {
    ob_start();
    SettingsFormRenderer::render(
        [
            'defaultModeType' => 'rotation',
            'defaultFixedMode' => '5+0',
            'defaultRotationModes' => ['3+2'],
            'rufnameDisplay' => 'always',
        ],
        [],
        [
            ['id' => 1, 'name' => '5+0'],
            ['id' => 2, 'name' => '3+2'],
        ]
    );
    $html = ob_get_clean();

    expect($html)->toContain('<option value="rotation" selected="selected">')
        ->and($html)->toContain("<option value='5+0' selected=\"selected\">5+0</option>")
        ->and($html)->toContain('value="3+2" checked="checked"')
        ->and($html)->toContain('<option value="always" selected="selected">')
        ->and($html)->not->toContain('value="5+0" checked="checked"');
});

it('explains that rotation cannot be configured without tournament modes', function () {
    ob_start();
    SettingsFormRenderer::render([], [], []);
    $html = ob_get_clean();

    expect($html)->toContain('Keine Modi im Katalog vorhanden. Bitte lege weiter unten Modi an.')
        ->and($html)->not->toContain('Die App wählt dann basierend auf den vergangenen Turnieren');
});
