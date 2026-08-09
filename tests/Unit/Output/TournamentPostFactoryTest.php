<?php

declare(strict_types=1);

use Monatsblitz\Output\TournamentPostFactory;

it('builds post data for blitz mode', function () {
    $iso = '2026-06-24';
    $data = TournamentPostFactory::buildPostData('5+0', $iso, 5, '<p>content</p>');

    expect($data)->toBeArray();
    expect($data['slug'])->toBe('blitz-' . $iso);
    expect($data['postarr']['post_title'])->toBe('Monatsblitz ' . $iso);
    expect($data['postarr']['post_author'])->toBe(5);
    expect($data['postarr']['post_content'])->toBe('<p>content</p>');
    expect($data['post_date_local'])->toBe($iso . ' 23:30:00');
});

it('builds post data for regular mode', function () {
    $iso = '2026-07-01';
    $data = TournamentPostFactory::buildPostData('schweizer', $iso, 3, 'ok');

    expect($data['slug'])->toBe('turnier-' . $iso);
    expect($data['postarr']['post_title'])->toBe('Turnier ' . $iso);
    expect($data['postarr']['post_author'])->toBe(3);
    expect($data['postarr']['post_content'])->toBe('ok');
});
