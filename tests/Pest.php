<?php

use Tests\TestCase;

require __DIR__ . '/../bootstrap/WordPress.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');


pest()->beforeEach(function () {
    global $wpdb;

    unset($_SERVER['HTTP_X_MB_KEY']);
    $_POST = [];
    $_GET = [];
    $GLOBALS['mb_test_options'] = [];
    $GLOBALS['mb_test_actions'] = [];
    $GLOBALS['mb_test_settings'] = [];
    $GLOBALS['mb_test_menu_pages'] = [];
    $GLOBALS['mb_test_submenu_pages'] = [];
    $GLOBALS['mb_test_updated_options'] = [];
    $GLOBALS['mb_test_nonce_valid'] = true;
    $GLOBALS['mb_test_current_user_can'] = true;
    $GLOBALS['mb_test_template_post'] = null;
    $GLOBALS['mb_test_last_inserted_post'] = null;
    $GLOBALS['mb_test_next_post_id'] = 123;

    $wpdb = new class {
        public string $prefix = 'wp_';
        public $get_var_result = null;
        public array $get_var_queue = [];
        public $get_row_result = null;
        public $insert_id = 0;
        public array $results = [];
        public array $get_results_queue = [];
        public ?string $last_insert_table = null;
        public array $last_insert_data = [];
        public array $last_insert_format = [];
        public ?string $last_update_table = null;
        public array $last_update_data = [];
        public array $last_update_where = [];
        public array $queries = [];

        public function get_var($query = null) {
            if (!empty($this->get_var_queue)) {
                return array_shift($this->get_var_queue);
            }
            return $this->get_var_result;
        }

        public function get_row($query = null, $output = null) {
            return $this->get_row_result;
        }

        public function prepare($query, ...$args) {
            return $query;
        }

        public function get_results($query = null) {
            if (!empty($this->get_results_queue)) {
                return array_shift($this->get_results_queue);
            }
            return $this->results;
        }

        public function insert($table, $data, $format = null) {
            $this->last_insert_table = $table;
            $this->last_insert_data = $data;
            $this->last_insert_format = $format ?? [];
            return true;
        }

        public function update($table, $data, $where) {
            $this->last_update_table = $table;
            $this->last_update_data = $data;
            $this->last_update_where = $where;
            return true;
        }

        public function query($sql) {
            $this->queries[] = $sql;
            return true;
        }

        public function get_charset_collate() {
            return '';
        }
    };
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
