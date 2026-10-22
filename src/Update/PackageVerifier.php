<?php

declare(strict_types=1);

namespace Monatsblitz\Update;

final class PackageVerifier
{
    public static function verify($reply, $package, ?array $manifest)
    {
        if (!is_string($package) || !wp_http_validate_url($package)) {
            return $reply;
        }

        if ($manifest === null || ($manifest['download_url'] ?? '') !== $package) {
            return $reply;
        }

        $expectedHash = $manifest['checksum_sha256'] ?? null;
        if (!is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/iD', $expectedHash) !== 1) {
            return new \WP_Error('monatsblitz_update_invalid_checksum', 'Invalid checksum in update manifest');
        }

        $file = download_url($package);
        if (is_wp_error($file)) {
            return $file;
        }

        $actualHash = hash_file('sha256', $file);
        if ($actualHash === false || !hash_equals($expectedHash, strtolower($actualHash))) {
            self::deleteFile($file);

            return new \WP_Error(
                'monatsblitz_update_checksum_mismatch',
                'Downloaded package checksum does not match manifest.'
            );
        }

        return $file;
    }

    private static function deleteFile(string $file): void
    {
        if (function_exists('wp_delete_file')) {
            wp_delete_file($file);
        } elseif (is_file($file)) {
            unlink($file);
        }
    }
}
