<?php

declare(strict_types=1);

namespace Monatsblitz\Update;

final class PluginUpdater
{
    private string $pluginBasename;

    private string $pluginSlug;

    private string $currentVersion;

    private string $manifestUrl;

    private string $cacheKey = 'monatsblitz_plugin_update';

    public function __construct(string $pluginFile, string $currentVersion, string $manifestUrl)
    {
        $this->pluginBasename = plugin_basename($pluginFile);
        $this->pluginSlug = dirname($this->pluginBasename);
        $this->currentVersion = $currentVersion;
        $this->manifestUrl = $manifestUrl;

        add_filter('site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_filter('plugins_api', [$this, 'pluginInformation'], 20, 3);
        add_filter('upgrader_pre_download', [$this, 'verifyDownload'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
    }

    public function checkForUpdate($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $manifest = $this->getManifest();
        if ($manifest === null || version_compare($manifest['version'], $this->currentVersion, '<=')) {
            return $transient;
        }

        $transient->response[$this->pluginBasename] = (object) [
            'id' => $this->manifestUrl,
            'slug' => $this->pluginSlug,
            'plugin' => $this->pluginBasename,
            'new_version' => $manifest['version'],
            'url' => $this->manifestUrl,
            'package' => $manifest['download_url'],
        ];

        return $transient;
    }

    public function pluginInformation($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== $this->pluginSlug) {
            return $result;
        }

        $manifest = $this->getManifest();
        if ($manifest === null) {
            return $result;
        }

        return (object) [
            'name' => $this->pluginSlug,
            'slug' => $this->pluginSlug,
            'version' => $manifest['version'],
            'author' => 'Kindermaenner',
            'homepage' => $this->manifestUrl,
            'download_link' => $manifest['download_url'],
            'sections' => [
                'description' => 'Plugin updates are provided by kindermaenner.de.',
                'changelog' => 'See the project release notes for changes in this version.',
            ],
        ];
    }

    public function clearCache($upgrader, array $options): void
    {
        if (($options['action'] ?? '') === 'update' && ($options['type'] ?? '') === 'plugin') {
            delete_site_transient($this->cacheKey);
        }
    }

    public function verifyDownload($reply, string $package)
    {
        if ($reply !== false) {
            return $reply;
        }

        $manifest = $this->getManifest();
        if ($manifest === null || $manifest['download_url'] !== $package) {
            return $reply;
        }

        $download = download_url($package, 300);
        if (is_wp_error($download)) {
            return $download;
        }

        $checksum = hash_file('sha256', $download);
        if (!is_string($checksum) || !hash_equals($manifest['checksum_sha256'], $checksum)) {
            wp_delete_file($download);
            $this->log('The downloaded package checksum did not match the manifest.');

            return new \WP_Error(
                'monatsblitz_update_checksum_mismatch',
                'The Monatsblitz update package failed its checksum verification.'
            );
        }

        return $download;
    }

    private function getManifest(): ?array
    {
        $cached = get_site_transient($this->cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_safe_remote_get($this->manifestUrl, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log('Unable to retrieve the update manifest.');
            return null;
        }

        $manifest = json_decode(wp_remote_retrieve_body($response), true);
        if (!$this->isValidManifest($manifest)) {
            $this->log('The update manifest is invalid.');
            return null;
        }

        set_site_transient($this->cacheKey, $manifest, 6 * HOUR_IN_SECONDS);

        return $manifest;
    }

    private function isValidManifest($manifest): bool
    {
        if (!is_array($manifest) || ($manifest['slug'] ?? '') !== $this->pluginSlug) {
            return false;
        }

        if (!is_string($manifest['version'] ?? null) || preg_match('/^\d+\.\d+\.\d+$/D', $manifest['version']) !== 1) {
            return false;
        }

        if (!is_string($manifest['download_url'] ?? null) || !wp_http_validate_url($manifest['download_url'])) {
            return false;
        }

        if (!is_string($manifest['checksum_sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/iD', $manifest['checksum_sha256']) !== 1) {
            return false;
        }

        return $this->downloadUrlUsesManifestHost($manifest['download_url']);
    }

    private function downloadUrlUsesManifestHost(string $downloadUrl): bool
    {
        $manifestHost = wp_parse_url($this->manifestUrl, PHP_URL_HOST);
        $downloadHost = wp_parse_url($downloadUrl, PHP_URL_HOST);
        $downloadScheme = wp_parse_url($downloadUrl, PHP_URL_SCHEME);

        return $downloadScheme === 'https' && is_string($manifestHost) && $manifestHost === $downloadHost;
    }

    private function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Monatsblitz updater: ' . $message);
        }
    }
}
