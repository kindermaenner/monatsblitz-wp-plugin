<?php

use Monatsblitz\Admin\Admin;

it('registers admin hooks on init', function () {
    Admin::init();

    expect($GLOBALS['mb_test_actions'])->toHaveCount(2);
    expect($GLOBALS['mb_test_actions'][0]['hook'])->toBe('admin_menu');
    expect($GLOBALS['mb_test_actions'][1]['hook'])->toBe('admin_init');
    expect($GLOBALS['mb_test_actions'][0]['callback'][0])->toBe(Admin::class);
    expect($GLOBALS['mb_test_actions'][0]['callback'][1])->toBe('register_menu');
});

it('registers settings inside admin_init callback', function () {
    Admin::init();

    $adminInit = $GLOBALS['mb_test_actions'][1]['callback'];
    $adminInit();

    expect($GLOBALS['mb_test_settings'])->toHaveCount(3);
    expect($GLOBALS['mb_test_settings'][0])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_author']);
    expect($GLOBALS['mb_test_settings'][1])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_template']);
    expect($GLOBALS['mb_test_settings'][2])->toBe(['group' => 'monatsblitz_settings', 'name' => 'monatsblitz_api_key']);
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

    $wpdb->get_var_queue = ['yes', 4, 'yes', 2, null, 'yes', 7];

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Monatsblitz Übersicht');
    expect($html)->toContain('vorhanden');
    expect($html)->toContain('fehlt');
    expect($html)->toContain('wp_monatsblitz_players');
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
    expect($html)->toContain('Zurück');
    expect($html)->toContain('Max');
});

it('shows empty message when detail table has no rows', function () {
    global $wpdb;

    $_GET['table'] = 'players';
    $wpdb->results = [];

    ob_start();
    Admin::render_page();
    $html = ob_get_clean();

    expect($html)->toContain('Keine Einträge vorhanden.');
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
    expect(in_array('DROP TABLE IF EXISTS wp_monatsblitz_games', $wpdb->queries, true))->toBeTrue();
    expect($wpdb->queries)->toContain('SET FOREIGN_KEY_CHECKS=0');
    expect($wpdb->queries)->toContain('SET FOREIGN_KEY_CHECKS=1');
});
