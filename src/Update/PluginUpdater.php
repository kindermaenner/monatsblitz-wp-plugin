<?php

declare(strict_types=1);

namespace Monatsblitz\Update;

final class PluginUpdater
{
    public const CACHE_KEY = 'monatsblitz_plugin_update';

    private static ?self $instance = null;

    private string $pluginBasename;

    private string $pluginSlug;

    private string $currentVersion;

    private string $manifestUrl;

    private string $cacheKey = self::CACHE_KEY;

    public function __construct(string $pluginFile, string $currentVersion, string $manifestUrl)
    {
        self::$instance = $this;

        $this->pluginBasename = plugin_basename($pluginFile);
        $this->pluginSlug = dirname($this->pluginBasename);
        $this->currentVersion = $currentVersion;
        $this->manifestUrl = $manifestUrl;

        add_filter('site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_filter('plugins_api', [$this, 'pluginInformation'], 20, 3);
        add_filter('upgrader_pre_download', [$this, 'verifyDownload'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);
        add_action('load-update-core.php', [$this, 'clearManifestCache']);
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

        $update = (object) [
            'id' => $this->manifestUrl,
            'slug' => $this->pluginSlug,
            'plugin' => $this->pluginBasename,
            'new_version' => $manifest['version'],
            'url' => PluginInformationRenderer::getGitHubUrl($manifest['release_url'] ?? null) ?? $this->manifestUrl,
            'package' => $manifest['download_url'],
        ];

        $upgradeNotice = PluginInformationRenderer::formatUpgradeNotice($manifest);
        if ($upgradeNotice !== null) {
            $update->upgrade_notice = $upgradeNotice;
        }

        $requiresPhp = PluginInformationRenderer::getPhpVersion($manifest['requires_php'] ?? null);
        if ($requiresPhp !== null) {
            $update->requires_php = $requiresPhp;
        }

        $transient->response[$this->pluginBasename] = $update;

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

        return PluginInformationRenderer::buildPluginInformation($manifest, $this->pluginSlug, $this->manifestUrl);
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function clearManifestCache(): void
    {
        delete_site_transient($this->cacheKey);
    }

    public function clearCache($upgrader = null, array $options = []): void
    {
        if (
            $upgrader === null ||
            (($options['action'] ?? '') === 'update' && ($options['type'] ?? '') === 'plugin')
        ) {
            $this->clearManifestCache();
        }
    }

    public function verifyDownload($reply, $package)
    {
        return PackageVerifier::verify($reply, $package, $this->getManifest());
    }

    public function checkUpdateNow(): array
    {
        self::purge();

        $manifest = $this->fetchRemoteManifest();
        if ($manifest === null) {
            return [
                'has_update' => false,
                'current_version' => $this->currentVersion,
                'latest_version' => null,
            ];
        }

        set_site_transient($this->cacheKey, $manifest, 6 * HOUR_IN_SECONDS);

        $hasUpdate = version_compare($manifest['version'], $this->currentVersion, '>');

        return [
            'has_update' => $hasUpdate,
            'current_version' => $this->currentVersion,
            'latest_version' => $manifest['version'],
        ];
    }

    public static function purge(): void
    {
        delete_site_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
    }

    public function getManifest(): ?array
    {
        $cached = get_site_transient($this->cacheKey);
        if (is_array($cached) && ManifestValidator::isValid($cached, $this->pluginSlug, $this->manifestUrl)) {
            return $cached;
        }

        $manifest = $this->fetchRemoteManifest();
        if ($manifest === null) {
            return null;
        }

        set_site_transient($this->cacheKey, $manifest, 6 * HOUR_IN_SECONDS);

        return $manifest;
    }

    private function fetchRemoteManifest(): ?array
    {
        $response = wp_safe_remote_get($this->manifestUrl, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . ($GLOBALS['wp_version'] ?? 'unknown') . '; Monatsblitz/' . $this->currentVersion,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log('Failed to fetch manifest from ' . $this->manifestUrl);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $manifest = json_decode($body, true);

        if (!ManifestValidator::isValid($manifest, $this->pluginSlug, $this->manifestUrl)) {
            $this->log('Manifest validation failed for ' . $this->manifestUrl);
            return null;
        }

        return $manifest;
    }

    private function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Monatsblitz updater: ' . $message);
        }
    }
}
