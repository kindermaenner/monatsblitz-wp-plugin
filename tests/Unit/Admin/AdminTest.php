<?php

declare(strict_types=1);

use Monatsblitz\Admin\Admin;

it('registers admin hooks on init', function () {
    Admin::init();

    expect($GLOBALS['mb_test_actions'])->toHaveCount(3);
    expect($GLOBALS['mb_test_actions'][0]['hook'])->toBe('admin_menu');
    expect($GLOBALS['mb_test_actions'][1]['hook'])->toBe('admin_init');
    expect($GLOBALS['mb_test_actions'][0]['callback'][0])->toBe(Admin::class);
    expect($GLOBALS['mb_test_actions'][0]['callback'][1])->toBe('register_menu');
    expect($GLOBALS['mb_test_actions'][2]['hook'])->toBe('admin_enqueue_scripts');
    expect($GLOBALS['mb_test_actions'][2]['callback'][0])->toBe(Admin::class);
    expect($GLOBALS['mb_test_actions'][2]['callback'][1])->toBe('enqueue_admin_assets');
});

it('registers settings inside admin_init callback', function () {
    Admin::init();

    $adminInit = $GLOBALS['mb_test_actions'][1]['callback'];
    $adminInit();

    expect($GLOBALS['mb_test_settings'])->toHaveCount(6);
    expect($GLOBALS['mb_test_settings'][0])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_author']);
    expect($GLOBALS['mb_test_settings'][1])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_template']);
    expect($GLOBALS['mb_test_settings'][2])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_template_static_page']);
    expect($GLOBALS['mb_test_settings'][3])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_blitz_modes']);
    expect($GLOBALS['mb_test_settings'][4])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_api_key']);
    expect($GLOBALS['mb_test_settings'][5])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_hide_january_overview']);
});

it('registers main menu and settings submenu', function () {
    Admin::register_menu();

    expect($GLOBALS['mb_test_menu_pages'])->toHaveCount(1);
    expect($GLOBALS['mb_test_submenu_pages'])->toHaveCount(1);

    expect($GLOBALS['mb_test_menu_pages'][0]['menu_slug'])->toBe('monatsblitz');
    expect($GLOBALS['mb_test_submenu_pages'][0]['menu_slug'])->toBe('monatsblitz-settings');
    expect($GLOBALS['mb_test_submenu_pages'][0]['parent_slug'])->toBe('monatsblitz');
});

it('renders settings page with stored values', function () {
    $GLOBALS['mb_test_options']['monatsblitz_author'] = 2;
    $GLOBALS['mb_test_options']['monatsblitz_template'] = 'Template A';
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = 'key-123';

    ob_start();
    Admin::render_settings_page();
    $html = ob_get_clean();

    expect($html)->toContain('Monatsblitz');
    expect($html)->toContain('Template A');
    expect($html)->toContain('key-123');
    expect($html)->toContain('name="monatsblitz_author"');
    expect($html)->toContain('name="monatsblitz_template"');
    expect($html)->toContain('name="monatsblitz_template_static_page"');
    expect($html)->toContain('name="monatsblitz_blitz_modes"');
    expect($html)->toContain('name="monatsblitz_hide_january_overview"');
    expect($html)->toContain('App-Einrichtung per QR-Code');
    expect($html)->toContain('id="monatsblitz-qrcode"');
    expect($html)->toContain('https://example.test');
});

it('renders settings page prompt to generate key when api key is missing', function () {
    $GLOBALS['mb_test_options']['monatsblitz_api_key'] = '';

    ob_start();
    Admin::render_settings_page();
    $html = ob_get_clean();

    expect($html)->toContain('App-Einrichtung per QR-Code');
    expect($html)->toContain('Bitte generiere zuerst');
    expect($html)->not->toContain('id="monatsblitz-qrcode"');
});

it('enqueues qrcode script only on settings page', function () {
    Admin::enqueue_admin_assets('toplevel_page_monatsblitz');
    expect($GLOBALS['mb_test_enqueued_scripts'])->toBeEmpty();

    Admin::enqueue_admin_assets('monatsblitz_page_monatsblitz-settings');
    expect($GLOBALS['mb_test_enqueued_scripts'])->toHaveCount(1);
    expect($GLOBALS['mb_test_enqueued_scripts'][0]['handle'])->toBe('monatsblitz-qrcode');
    expect($GLOBALS['mb_test_enqueued_scripts'][0]['src'])->toContain('assets/js/qrcode.min.js');
});

it('generates and stores a new api key from settings page', function () {
    $_POST['monatsblitz_generate_key'] = '1';

    ob_start();
    Admin::render_settings_page();
    ob_end_clean();

    expect($GLOBALS['mb_test_updated_options'])->toHaveKey('monatsblitz_api_key');
    expect(strlen($GLOBALS['mb_test_updated_options']['monatsblitz_api_key']))->toBe(32);
});

it('renders dashboard overview table', function () {
    global $wpdb;

    $wpdb->get_var_queue = ['yes', 4, 'yes', 2, 'yes', 12, null, 'yes', 7];

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Monatsblitz Ãœbersicht');
    expect($html)->toContain('vorhanden');
    expect($html)->toContain('fehlt');
    expect($html)->toContain('wp_monatsblitz_players');
    expect($html)->toContain('wp_monatsblitz_tournament_players');
});

it('renders table detail when table is requested', function () {
    global $wpdb;

    $_GET['table'] = 'players';
    $wpdb->results = [
        ['id' => 1, 'forename' => 'Max', 'surname' => 'Muster']
    ];

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Detailansicht');
    expect($html)->toContain('ZurÃ¼ck');
    expect($html)->toContain('Max');
});

it('shows empty message when detail table has no rows', function () {
    global $wpdb;

    $_GET['table'] = 'players';
    $wpdb->results = [];

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Keine EintrÃ¤ge vorhanden.');
});

it('does not reset tables when user lacks capability', function () {
    global $wpdb;

    $_POST['monatsblitz_action'] = 'reset_tables';
    $_POST['_wpnonce'] = 'nonce';
    $GLOBALS['mb_test_current_user_can'] = false;

    ob_start();
    Admin::render_page();
    ob_end_clean();

    expect($wpdb->queries)->toHaveCount(0);
});

it('shows security error when nonce is invalid', function () {
    global $wpdb;

    $_POST['monatsblitz_action'] = 'reset_tables';
    $_POST['_wpnonce'] = 'nonce';
    $GLOBALS['mb_test_nonce_valid'] = false;

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Security check failed');
    expect($wpdb->queries)->toHaveCount(0);
});

it('resets tables when action is valid', function () {
    global $wpdb;

    $_POST['monatsblitz_action'] = 'reset_tables';
    $_POST['_wpnonce'] = 'nonce';
    $GLOBALS['mb_test_current_user_can'] = true;
    $GLOBALS['mb_test_nonce_valid'] = true;

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Tabellen wurden neu erstellt');
    expect(in_array('DROP TABLE IF EXISTS wp_monatsblitz_tournament_players', $wpdb->queries, true))->toBeTrue();
    expect(in_array('DROP TABLE IF EXISTS wp_monatsblitz_games', $wpdb->queries, true))->toBeTrue();
    expect($wpdb->queries)->toContain('SET FOREIGN_KEY_CHECKS=0');
    expect($wpdb->queries)->toContain('SET FOREIGN_KEY_CHECKS=1');
});
