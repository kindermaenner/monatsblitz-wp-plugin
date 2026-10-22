<?php

declare(strict_types=1);

use Monatsblitz\Admin\SettingsPage;

it('renders settings page directly from SettingsPage class', function () {
    $GLOBALS['mb_test_options']['monatsblitz_author'] = 1;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'Direct Template';
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'direct-key-456';

    ob_start();
    SettingsPage::render();
    $html = ob_get_clean();

    expect($html)->toContain('Monatsblitz');
    expect($html)->toContain('Direct Template');
    expect($html)->toContain('direct-key-456');
    expect($html)->toContain('App-Einrichtung per QR-Code');
});
