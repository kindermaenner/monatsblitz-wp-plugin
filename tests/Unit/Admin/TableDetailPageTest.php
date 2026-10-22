<?php

declare(strict_types=1);

use Monatsblitz\Admin\TableDetailPage;

it('renders table detail directly from TableDetailPage class', function () {
    ob_start();
    TableDetailPage::render('players', 'wp_monatsblitz_players');
    $html = ob_get_clean();

    expect($html)->toContain('Detailansicht: wp_monatsblitz_players');
    expect($html)->toContain('Keine Einträge vorhanden.');
});
