<?php

declare(strict_types=1);

namespace Monatsblitz\Update;

final class ManifestValidator
{
    public static function isValid($manifest, string $pluginSlug, string $manifestUrl): bool
    {
        if (!is_array($manifest) || ($manifest['slug'] ?? '') !== $pluginSlug) {
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

        return self::downloadUrlUsesManifestHost($manifest['download_url'], $manifestUrl);
    }

    private static function downloadUrlUsesManifestHost(string $downloadUrl, string $manifestUrl): bool
    {
        $manifestHost = wp_parse_url($manifestUrl, PHP_URL_HOST);
        $downloadHost = wp_parse_url($downloadUrl, PHP_URL_HOST);
        $downloadScheme = wp_parse_url($downloadUrl, PHP_URL_SCHEME);

        return $downloadScheme === 'https' && is_string($manifestHost) && $manifestHost === $downloadHost;
    }
}
