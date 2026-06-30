<?php

declare(strict_types=1);

namespace Monatsblitz\Service;

class BlitzModeService
{
    public static function isBlitzMode(string $mode): bool
    {
        $normalizedMode = trim($mode);
        if ($normalizedMode === '') {
            return false;
        }

        if (self::matchesDefaultRule($normalizedMode)) {
            return true;
        }

        $configuredModes = self::configuredAdditionalModes();
        return in_array(strtolower($normalizedMode), $configuredModes, true);
    }

    /**
     * Default: every mode with base time <= 5 minutes counts as blitz.
     * Examples: 5+0, 3+2, 5 + 5
     */
    private static function matchesDefaultRule(string $mode): bool
    {
        if (!preg_match('/^\s*(\d+)\s*\+\s*(\d+)\s*$/', $mode, $matches)) {
            return false;
        }

        $baseMinutes = (int)($matches[1] ?? 0);
        return $baseMinutes > 0 && $baseMinutes <= 5;
    }

    /**
     * Reads additional blitz modes from the admin setting as comma-separated list.
     */
    private static function configuredAdditionalModes(): array
    {
        $raw = (string)get_option('monatsblitz_blitz_modes', '');
        if (trim($raw) === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $raw));
        $items = array_filter($items, static fn(string $value): bool => $value !== '');

        return array_values(array_map('strtolower', $items));
    }
}
