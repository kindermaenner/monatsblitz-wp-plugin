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
            'url' => $this->getGitHubUrl($manifest['release_url'] ?? null) ?? $this->manifestUrl,
            'package' => $manifest['download_url'],
        ];
        $releaseNotes = $manifest['release_notes'] ?? '';
        if (is_string($releaseNotes) && $releaseNotes !== '') {
            $update->upgrade_notice = $this->formatReleaseNotes($releaseNotes);
            $noticeLabels = [];
            if (($manifest['security_fixes'] ?? false) === true) {
                $noticeLabels[] = 'Security update';
            }
            if (($manifest['prerelease'] ?? false) === true) {
                $noticeLabels[] = 'Pre-release';
            }
            if ($noticeLabels !== []) {
                $update->upgrade_notice = '<strong>' . esc_html(implode(' · ', $noticeLabels)) . ':</strong> '
                    . $update->upgrade_notice;
            }
        }
        $requiresPhp = $this->getPhpVersion($manifest['requires_php'] ?? null);
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

        $homepage = $this->getGitHubUrl($manifest['homepage'] ?? null) ?? $this->manifestUrl;
        $releaseUrl = $this->getGitHubUrl($manifest['release_url'] ?? null);
        $description = sprintf(
            'Plugin updates are provided by <a href="%s">the project on GitHub</a>.',
            esc_url($homepage)
        );
        $compatibility = $this->buildCompatibilitySection($manifest);
        if ($compatibility !== '') {
            $description .= $compatibility;
        }

        $sections = [
            'description' => $description,
            'changelog' => $this->buildChangelog($manifest, $releaseUrl),
        ];

        return (object) [
            'name' => is_string($manifest['name'] ?? null) && $manifest['name'] !== ''
                ? $manifest['name']
                : $this->pluginSlug,
            'slug' => $this->pluginSlug,
            'version' => $manifest['version'],
            'author' => 'Kindermaenner',
            'homepage' => $homepage,
            'download_link' => $manifest['download_url'],
            'requires_php' => $this->getPhpVersion($manifest['requires_php'] ?? null),
            'last_updated' => $this->formatReleaseDate($manifest['released_at'] ?? null),
            'sections' => $sections,
        ];
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
            delete_site_transient($this->cacheKey);
        }
    }

    public function purgeCache(): void
    {
        delete_site_transient($this->cacheKey);
        delete_site_transient('update_plugins');
        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
    }

    public static function purge(): void
    {
        if (self::$instance !== null) {
            self::$instance->purgeCache();
        } else {
            delete_site_transient(self::CACHE_KEY);
            delete_site_transient('update_plugins');
            if (function_exists('wp_update_plugins')) {
                wp_update_plugins();
            }
        }
    }

    public function checkUpdateNow(): array
    {
        $this->purgeCache();
        $manifest = $this->getManifest();

        $latestVersion = is_array($manifest) && is_string($manifest['version'] ?? null)
            ? $manifest['version']
            : null;

        $hasUpdate = $latestVersion !== null && version_compare($latestVersion, $this->currentVersion, '>');

        return [
            'current_version' => $this->currentVersion,
            'latest_version'  => $latestVersion,
            'has_update'      => $hasUpdate,
            'release_url'     => is_array($manifest) ? ($manifest['release_url'] ?? null) : null,
            'download_url'    => is_array($manifest) ? ($manifest['download_url'] ?? null) : null,
        ];
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

    private function buildChangelog(array $manifest, ?string $releaseUrl): string
    {
        $releases = $manifest['releases'] ?? null;
        if (!is_array($releases)) {
            $notes = $manifest['release_notes'] ?? '';
            if (!is_string($notes) || $notes === '') {
                return 'Release notes are not available.';
            }

            return $this->appendReleaseLink($this->formatReleaseNotes($notes), $releaseUrl);
        }

        $html = '';
        foreach ($releases as $release) {
            if (!is_array($release) || !is_string($release['version'] ?? null)) {
                continue;
            }

            $html .= $this->renderReleaseEntry($release);
        }

        if ($html !== '') {
            return $html;
        }

        $notes = $manifest['release_notes'] ?? '';
        if (!is_string($notes) || $notes === '') {
            return 'Release notes are not available.';
        }

        return $this->appendReleaseLink($this->formatReleaseNotes($notes), $releaseUrl);
    }

    private function renderReleaseEntry(array $release): string
    {
        $html = '<h4>' . esc_html($release['version']);
        $labels = [];
        if (($release['security_fixes'] ?? false) === true) {
            $labels[] = 'Security update';
        }
        if (($release['prerelease'] ?? false) === true) {
            $labels[] = 'Pre-release';
        }
        if ($labels !== []) {
            $html .= ' <em>(' . esc_html(implode(', ', $labels)) . ')</em>';
        }
        $html .= '</h4>';

        $releasedAt = $this->getReleaseDate($release['released_at'] ?? null);
        if ($releasedAt !== '') {
            $html .= '<p>Released: ' . esc_html($releasedAt) . '</p>';
        }

        $notes = $release['notes'] ?? '';
        if (is_string($notes) && $notes !== '') {
            $html .= $this->formatReleaseNotes($notes);
        }

        $releaseLink = $this->getGitHubUrl($release['url'] ?? null);
        if ($releaseLink !== null) {
            $html .= '<p><a href="' . esc_url($releaseLink) . '">View release on GitHub</a>';
            $compareLink = $this->getGitHubUrl($release['compare_url'] ?? null);
            if ($compareLink !== null) {
                $html .= ' | <a href="' . esc_url($compareLink) . '">Compare changes</a>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    private function buildCompatibilitySection(array $manifest): string
    {
        $requirements = [];
        $requiresPhp = $this->getPhpVersion($manifest['requires_php'] ?? null);
        if ($requiresPhp !== null) {
            $requirements[] = 'Requires PHP ' . esc_html($requiresPhp) . '.';
        }

        $testedPhp = $this->getPhpVersion($manifest['tested_php'] ?? null);
        if ($testedPhp !== null) {
            $requirements[] = 'Automated CI tested with PHP ' . esc_html($testedPhp) . '.';
        }

        return $requirements === [] ? '' : '<p>' . implode('<br>', $requirements) . '</p>';
    }

    private function appendReleaseLink(string $notes, ?string $releaseUrl): string
    {
        return $releaseUrl === null
            ? $notes
            : $notes . '<p><a href="' . esc_url($releaseUrl) . '">View release on GitHub</a></p>';
    }

    private function formatReleaseNotes(string $notes): string
    {
        return nl2br(esc_html($notes), false);
    }

    private function getGitHubUrl($url): ?string
    {
        if (!is_string($url) || !wp_http_validate_url($url)) {
            return null;
        }

        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        $host = wp_parse_url($url, PHP_URL_HOST);

        return $scheme === 'https' && $host === 'github.com' ? $url : null;
    }

    private function getPhpVersion($version): ?string
    {
        return is_string($version) && preg_match('/^\d+(?:\.\d+){1,2}$/D', $version) === 1
            ? $version
            : null;
    }

    private function getReleaseDate($date): string
    {
        if (!is_string($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? '' : gmdate('Y-m-d', $timestamp);
    }

    private function formatReleaseDate($date): string
    {
        if (!is_string($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? '' : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Monatsblitz updater: ' . $message);
        }
    }
}
