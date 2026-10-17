<?php

declare(strict_types=1);

use Monatsblitz\Update\PluginUpdater;

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        $GLOBALS['mb_test_filters'][] = compact('hook', 'callback', 'priority', 'acceptedArgs');
        return true;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file)
    {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient($key)
    {
        return $GLOBALS['mb_test_site_transients'][$key] ?? false;
    }
}

if (!function_exists('set_site_transient')) {
    function set_site_transient($key, $value, $expiration = 0)
    {
        $GLOBALS['mb_test_site_transients'][$key] = $value;
        $GLOBALS['mb_test_site_transient_expiration'][$key] = $expiration;
        return true;
    }
}

if (!function_exists('delete_site_transient')) {
    function delete_site_transient($key)
    {
        unset($GLOBALS['mb_test_site_transients'][$key]);
        $GLOBALS['mb_test_deleted_site_transients'][] = $key;
        return true;
    }
}

if (!function_exists('wp_safe_remote_get')) {
    function wp_safe_remote_get($url, $args = [])
    {
        $GLOBALS['mb_test_remote_get_calls'][] = [$url, $args];
        return $GLOBALS['mb_test_remote_response'];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return $response['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_http_validate_url')) {
    function wp_http_validate_url($url)
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (!function_exists('download_url')) {
    function download_url($url, $timeout = 300)
    {
        $GLOBALS['mb_test_download_url_calls'][] = [$url, $timeout];
        return $GLOBALS['mb_test_download_result'];
    }
}

if (!function_exists('wp_delete_file')) {
    function wp_delete_file($file)
    {
        $GLOBALS['mb_test_deleted_files'][] = $file;
        if (is_file($file)) {
            unlink($file);
        }
    }
}

beforeEach(function () {
    $GLOBALS['mb_test_filters'] = [];
    $GLOBALS['mb_test_site_transients'] = [];
    $GLOBALS['mb_test_site_transient_expiration'] = [];
    $GLOBALS['mb_test_deleted_site_transients'] = [];
    $GLOBALS['mb_test_remote_get_calls'] = [];
    $GLOBALS['mb_test_remote_response'] = [
        'code' => 200,
        'body' => '',
    ];
    $GLOBALS['mb_test_download_result'] = '';
    $GLOBALS['mb_test_download_url_calls'] = [];
    $GLOBALS['mb_test_deleted_files'] = [];

    $this->updater = new PluginUpdater(
        '/var/www/wp-content/plugins/monatsblitz/monatsblitz.php',
        '0.9.0',
        'https://kindermaenner.de/plugin-updates/monatsblitz/manifest.json'
    );
});

function monatsblitzManifest(array $overrides = []): array
{
    return array_merge([
        'slug' => 'monatsblitz',
        'version' => '1.0.0',
        'download_url' => 'https://kindermaenner.de/plugin-updates/monatsblitz/versions/v1.0.0/monatsblitz.zip',
        'checksum_sha256' => str_repeat('a', 64),
    ], $overrides);
}

function monatsblitzCacheManifest(array $manifest): void
{
    $GLOBALS['mb_test_site_transients']['monatsblitz_plugin_update'] = $manifest;
}

it('registers WordPress updater hooks with the required argument counts', function () {
    expect($GLOBALS['mb_test_filters'])->toHaveCount(3)
        ->and(array_column($GLOBALS['mb_test_filters'], 'hook'))->toBe([
            'site_transient_update_plugins',
            'plugins_api',
            'upgrader_pre_download',
        ])
        ->and($GLOBALS['mb_test_filters'][2]['acceptedArgs'])->toBe(2);
});

it('adds an update when the manifest version is newer', function () {
    $GLOBALS['mb_test_remote_response']['body'] = json_encode(monatsblitzManifest());
    $transient = (object) [
        'checked' => ['monatsblitz/monatsblitz.php' => '0.9.0'],
        'response' => [],
    ];

    $result = $this->updater->checkForUpdate($transient);

    expect($result->response['monatsblitz/monatsblitz.php']->new_version)->toBe('1.0.0')
        ->and($result->response['monatsblitz/monatsblitz.php']->package)->toBe(monatsblitzManifest()['download_url'])
        ->and($GLOBALS['mb_test_site_transient_expiration']['monatsblitz_plugin_update'])->toBe(6 * HOUR_IN_SECONDS);
});

it('does not add an update when the manifest version is not newer', function () {
    $GLOBALS['mb_test_remote_response']['body'] = json_encode(monatsblitzManifest(['version' => '0.9.0']));
    $transient = (object) [
        'checked' => ['monatsblitz/monatsblitz.php' => '0.9.0'],
        'response' => [],
    ];

    expect($this->updater->checkForUpdate($transient)->response)->toBeEmpty();
});

it('ignores invalid manifests', function () {
    $GLOBALS['mb_test_remote_response']['body'] = json_encode(monatsblitzManifest(['slug' => 'another-plugin']));
    $transient = (object) [
        'checked' => ['monatsblitz/monatsblitz.php' => '0.9.0'],
        'response' => [],
    ];

    expect($this->updater->checkForUpdate($transient)->response)->toBeEmpty()
        ->and($GLOBALS['mb_test_site_transients'])->toBeEmpty();
});

it('returns plugin information only for this plugin', function () {
    monatsblitzCacheManifest(monatsblitzManifest());
    $otherResult = (object) ['unchanged' => true];

    expect($this->updater->pluginInformation($otherResult, 'plugin_information', (object) ['slug' => 'another-plugin']))
        ->toBe($otherResult)
        ->and($this->updater->pluginInformation(null, 'plugin_information', (object) ['slug' => 'monatsblitz'])->version)
        ->toBe('1.0.0');
});

it('clears its update manifest cache after plugin updates', function () {
    monatsblitzCacheManifest(monatsblitzManifest());

    $this->updater->clearCache(new stdClass(), ['action' => 'update', 'type' => 'plugin']);

    expect($GLOBALS['mb_test_site_transients'])->toBeEmpty()
        ->and($GLOBALS['mb_test_deleted_site_transients'])->toBe(['monatsblitz_plugin_update']);
});

it('verifies a downloaded package checksum', function () {
    $file = tempnam(sys_get_temp_dir(), 'monatsblitz-update-');
    file_put_contents($file, 'release package');
    $GLOBALS['mb_test_download_result'] = $file;
    $manifest = monatsblitzManifest(['checksum_sha256' => hash_file('sha256', $file)]);
    monatsblitzCacheManifest($manifest);

    try {
        expect($this->updater->verifyDownload(false, $manifest['download_url']))->toBe($file);
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
    }
});

it('rejects a downloaded package with a checksum mismatch', function () {
    $manifest = monatsblitzManifest();
    monatsblitzCacheManifest($manifest);
    $file = tempnam(sys_get_temp_dir(), 'monatsblitz-update-');
    file_put_contents($file, 'tampered package');
    $GLOBALS['mb_test_download_result'] = $file;

    $result = $this->updater->verifyDownload(false, $manifest['download_url']);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('monatsblitz_update_checksum_mismatch')
        ->and(is_file($file))->toBeFalse();
});
