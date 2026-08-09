<?php

declare(strict_types=1);

use Monatsblitz\Output\TournamentPostManager;

it('creates a new tournament post when no existing post is found', function () {
    global $wpdb;

    $GLOBALS['mb_test_next_post_id'] = 444;
    $GLOBALS['mb_test_get_posts_queue'] = [[], [], [], []];

    $manager = new TournamentPostManager();
    $result = $manager->createOrUpdatePost(
        [
            'post_title' => 'Test Post',
            'post_name' => 'turnier-2026-06-30',
            'post_content' => 'content',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2026-06-30 23:30:00',
            'post_date_gmt' => '2026-06-30 23:30:00',
            'post_author' => 1,
        ],
        'turnier-2026-06-30',
        'turnier-2026-06-30',
        '_monatsblitz_tournament_id',
        10,
        'schweizer',
        '2026-06-30'
    );

    expect($result['post_id'])->toBe(444);
    expect($result['updated'])->toBeFalse();
    expect($GLOBALS['mb_test_last_inserted_post'])->not->toBeNull();
});

it('updates an existing tournament post when one is found by meta key', function () {
    global $wpdb;

    $GLOBALS['mb_test_get_posts_queue'] = [
        [(object)['ID' => 201]],
    ];

    $manager = new TournamentPostManager();
    $result = $manager->createOrUpdatePost(
        [
            'post_title' => 'Updated Post',
            'post_name' => 'turnier-2026-07-01',
            'post_content' => 'content',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2026-07-01 23:30:00',
            'post_date_gmt' => '2026-07-01 23:30:00',
            'post_author' => 1,
        ],
        'turnier-2026-07-01',
        'turnier-2026-07-01',
        '_monatsblitz_tournament_id',
        11,
        'schweizer',
        '2026-07-01'
    );

    expect($result['post_id'])->toBe(201);
    expect($result['updated'])->toBeTrue();
    expect($GLOBALS['mb_test_last_updated_post'])->not->toBeNull();
});

it('copies template meta and taxonomy terms but skips blacklisted meta and empty taxonomies', function () {
    global $wpdb;

    $GLOBALS['mb_test_post_meta_result'] = [
        '_edit_lock' => ['123'],
        'custom_meta' => ['alpha', 'beta'],
    ];
    $GLOBALS['mb_test_object_taxonomies'] = ['category', 'post_tag'];
    $GLOBALS['mb_test_object_terms'] = [
        'category' => ['news'],
        'post_tag' => [],
    ];

    $manager = new TournamentPostManager();
    $manager->copyTemplateMetaAndTaxonomies(10, 20);

    expect($GLOBALS['mb_test_add_post_meta_calls'])->toContainEqual([
        'post_id' => 20,
        'meta_key' => 'custom_meta',
        'meta_value' => 'alpha',
        'unique' => false,
    ]);

    expect($GLOBALS['mb_test_set_object_terms_calls'])->toContainEqual([
        'object_id' => 20,
        'terms' => ['news'],
        'taxonomy' => 'category',
        'append' => false,
    ]);
});