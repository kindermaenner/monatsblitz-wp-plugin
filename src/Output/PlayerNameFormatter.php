<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class PlayerNameFormatter
{
    public static function format(
        string $forename,
        string $surname,
        string $rufname = '',
        ?string $displaySetting = null,
        bool $hasCollision = false
    ): string {
        $baseName = trim($forename . ' ' . $surname);
        $rufname = trim($rufname);

        if ($rufname === '') {
            return $baseName;
        }

        $setting = $displaySetting ?? (string)get_option('monatsblitz_rufname_display', 'none');

        if ($setting === 'always') {
            return $baseName !== '' ? "{$baseName} ({$rufname})" : $rufname;
        }

        if ($setting === 'collision' && $hasCollision) {
            return $baseName !== '' ? "{$baseName} ({$rufname})" : $rufname;
        }

        return $baseName;
    }

    /**
     * Determines whether there are collisions for (forename, surname) across a set of players or result items.
     * Returns an associative map: [playerId => hasCollision]
     */
    public static function buildCollisionMap(
        array $items,
        string $idKey = 'player_id',
        string $forenameKey = 'forename',
        string $surnameKey = 'surname'
    ): array {
        $nameCounts = [];
        foreach ($items as $item) {
            $name = mb_strtolower(trim(($item[$forenameKey] ?? '') . ' ' . ($item[$surnameKey] ?? '')));
            if ($name === '') {
                continue;
            }
            $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
        }

        $collisionMap = [];
        foreach ($items as $item) {
            $id = $item[$idKey] ?? null;
            if ($id === null) {
                continue;
            }
            $name = mb_strtolower(trim(($item[$forenameKey] ?? '') . ' ' . ($item[$surnameKey] ?? '')));
            $collisionMap[$id] = ($nameCounts[$name] ?? 0) > 1;
        }

        return $collisionMap;
    }
}
