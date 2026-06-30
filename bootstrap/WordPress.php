<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

if (!defined('MB_PLUGIN_PATH')) {
    define('MB_PLUGIN_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return $text; // minimal mock: keine Veränderung
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($data) {
        return $data;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        $GLOBALS['mb_test_dbdelta_sql'] = $sql;
        return true;
    }
}

if (!isset($GLOBALS['mb_test_options'])) {
    $GLOBALS['mb_test_options'] = [];
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['mb_test_options'][$key] ?? $default;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($value) {
        return $value instanceof WP_Error;
    }
}

if (!isset($GLOBALS['mb_test_actions'])) {
    $GLOBALS['mb_test_actions'] = [];
}

if (!isset($GLOBALS['mb_test_routes'])) {
    $GLOBALS['mb_test_routes'] = [];
}

if (!isset($GLOBALS['mb_test_settings'])) {
    $GLOBALS['mb_test_settings'] = [];
}

if (!isset($GLOBALS['mb_test_menu_pages'])) {
    $GLOBALS['mb_test_menu_pages'] = [];
}

if (!isset($GLOBALS['mb_test_submenu_pages'])) {
    $GLOBALS['mb_test_submenu_pages'] = [];
}

if (!isset($GLOBALS['mb_test_nonce_valid'])) {
    $GLOBALS['mb_test_nonce_valid'] = true;
}

if (!isset($GLOBALS['mb_test_current_user_can'])) {
    $GLOBALS['mb_test_current_user_can'] = true;
}

if (!isset($GLOBALS['mb_test_updated_options'])) {
    $GLOBALS['mb_test_updated_options'] = [];
}

if (!isset($GLOBALS['mb_test_template_post'])) {
    $GLOBALS['mb_test_template_post'] = null;
}

if (!isset($GLOBALS['mb_test_last_inserted_post'])) {
    $GLOBALS['mb_test_last_inserted_post'] = null;
}

if (!isset($GLOBALS['mb_test_next_post_id'])) {
    $GLOBALS['mb_test_next_post_id'] = 123;
}

if (!isset($GLOBALS['mb_test_get_posts_result'])) {
    $GLOBALS['mb_test_get_posts_result'] = [];
}

if (!isset($GLOBALS['mb_test_last_updated_post'])) {
    $GLOBALS['mb_test_last_updated_post'] = null;
}

if (!isset($GLOBALS['mb_test_post_meta_updates'])) {
    $GLOBALS['mb_test_post_meta_updates'] = [];
}

if (!isset($GLOBALS['mb_test_thumbnail_id'])) {
    $GLOBALS['mb_test_thumbnail_id'] = 0;
}

if (!isset($GLOBALS['mb_test_set_thumbnail_calls'])) {
    $GLOBALS['mb_test_set_thumbnail_calls'] = [];
}

if (!isset($GLOBALS['mb_test_post_meta_result'])) {
    $GLOBALS['mb_test_post_meta_result'] = [];
}

if (!isset($GLOBALS['mb_test_add_post_meta_calls'])) {
    $GLOBALS['mb_test_add_post_meta_calls'] = [];
}

if (!isset($GLOBALS['mb_test_object_taxonomies'])) {
    $GLOBALS['mb_test_object_taxonomies'] = [];
}

if (!isset($GLOBALS['mb_test_object_terms'])) {
    $GLOBALS['mb_test_object_terms'] = [];
}

if (!isset($GLOBALS['mb_test_set_object_terms_calls'])) {
    $GLOBALS['mb_test_set_object_terms_calls'] = [];
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        $GLOBALS['mb_test_actions'][] = ['hook' => $hook, 'callback' => $callback];
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {
        $GLOBALS['mb_test_routes'][] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];

        return true;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($group, $name) {
        $GLOBALS['mb_test_settings'][] = ['group' => $group, 'name' => $name];
        return true;
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url = '', $position = null) {
        $GLOBALS['mb_test_menu_pages'][] = [
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'icon_url' => $icon_url,
            'position' => $position,
        ];

        return $menu_slug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback) {
        $GLOBALS['mb_test_submenu_pages'][] = [
            'parent_slug' => $parent_slug,
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
        ];

        return $menu_slug;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        return true;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return str_repeat('x', $length);
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['mb_test_options'][$key] = $value;
        $GLOBALS['mb_test_updated_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('get_users')) {
    function get_users($args = []) {
        return [
            (object) ['ID' => 1, 'user_login' => 'admin'],
            (object) ['ID' => 2, 'user_login' => 'editor'],
        ];
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce') {
        echo '<input type="hidden" name="' . $name . '" value="nonce" />';
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($value) {
        return (string) $value;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $display = true) {
        $result = ((string)$selected === (string)$current) ? 'selected="selected"' : '';
        if ($display) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {
        echo '<input type="hidden" name="option_page" value="' . $option_group . '" />';
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = 'Save Changes') {
        echo '<button type="submit">' . $text . '</button>';
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return (bool) ($GLOBALS['mb_test_current_user_can'] ?? false);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return (bool) ($GLOBALS['mb_test_nonce_valid'] ?? false);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return (string) $text;
    }
}

if (!function_exists('get_page_by_title')) {
    function get_page_by_title($title, $output = OBJECT, $post_type = 'post') {
        return $GLOBALS['mb_test_template_post'];
    }
}

if (!function_exists('get_gmt_from_date')) {
    function get_gmt_from_date($date_string) {
        return $date_string;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr) {
        $GLOBALS['mb_test_last_inserted_post'] = $postarr;
        return $GLOBALS['mb_test_next_post_id'];
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr) {
        $GLOBALS['mb_test_last_updated_post'] = $postarr;
        return $postarr['ID'] ?? 0;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        if (!empty($GLOBALS['mb_test_get_posts_queue']) && is_array($GLOBALS['mb_test_get_posts_queue'])) {
            return array_shift($GLOBALS['mb_test_get_posts_queue']);
        }

        return $GLOBALS['mb_test_get_posts_result'] ?? [];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        $GLOBALS['mb_test_post_meta_updates'][] = [
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
        ];

        return true;
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post_id) {
        return (int)($GLOBALS['mb_test_thumbnail_id'] ?? 0);
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post_id, $thumbnail_id) {
        $GLOBALS['mb_test_set_thumbnail_calls'][] = [
            'post_id' => $post_id,
            'thumbnail_id' => $thumbnail_id,
        ];

        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        return $GLOBALS['mb_test_post_meta_result'] ?? [];
    }
}

if (!function_exists('add_post_meta')) {
    function add_post_meta($post_id, $meta_key, $meta_value, $unique = false) {
        $GLOBALS['mb_test_add_post_meta_calls'][] = [
            'post_id' => $post_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
            'unique' => $unique,
        ];

        return true;
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($value) {
        return $value;
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object_type, $output = 'names') {
        return $GLOBALS['mb_test_object_taxonomies'] ?? [];
    }
}

if (!function_exists('wp_get_object_terms')) {
    function wp_get_object_terms($object_id, $taxonomy, $args = []) {
        return $GLOBALS['mb_test_object_terms'][$taxonomy] ?? [];
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false) {
        $GLOBALS['mb_test_set_object_terms_calls'][] = [
            'object_id' => $object_id,
            'terms' => $terms,
            'taxonomy' => $taxonomy,
            'append' => $append,
        ];

        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public array $data = []
        ) {}
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;

        public function __construct($data = null) {
            $this->data = $data;
        }

        public function get_data() {
            return $this->data;
        }
    }
}
