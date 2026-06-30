<?php

declare(strict_types=1);

use Monatsblitz\Service\BlitzModeService;

it('classifies <=5+X modes as blitz by default', function () {
    expect(BlitzModeService::isBlitzMode('5+0'))->toBeTrue();
    expect(BlitzModeService::isBlitzMode('3+2'))->toBeTrue();
    expect(BlitzModeService::isBlitzMode('6+0'))->toBeFalse();
});

it('classifies configured additional modes as blitz', function () {
    $GLOBALS['mb_test_options']['monatsblitz_blitz_modes'] = 'Armageddon, Blitz';

    expect(BlitzModeService::isBlitzMode('Armageddon'))->toBeTrue();
    expect(BlitzModeService::isBlitzMode('Blitz'))->toBeTrue();
    expect(BlitzModeService::isBlitzMode('Handicap'))->toBeFalse();
    expect(BlitzModeService::isBlitzMode('Rapid'))->toBeFalse();
});
