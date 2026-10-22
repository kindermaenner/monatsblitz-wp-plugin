<?php

declare(strict_types=1);

namespace Monatsblitz\Update;

final class PluginInformationRenderer
{
    public static function buildPluginInformation(array $manifest, string $pluginSlug, string $manifestUrl): object
    {
        $homepage = self::getGitHubUrl($manifest['homepage'] ?? null) ?? $manifestUrl;
        $releaseUrl = self::getGitHubUrl($manifest['release_url'] ?? null);
        $description = sprintf(
            'Plugin updates are provided by <a href="%s">the project on GitHub</a>.',
            esc_url($homepage)
        );
        $compatibility = self::buildCompatibilitySection($manifest);
        if ($compatibility !== '') {
            $description .= $compatibility;
        }

        $sections = [
            'description' => $description,
            'changelog' => self::buildChangelog($manifest, $releaseUrl),
        ];

        return (object) [
            'name' => is_string($manifest['name'] ?? null) && $manifest['name'] !== ''
                ? $manifest['name']
                : $pluginSlug,
            'slug' => $pluginSlug,
            'version' => $manifest['version'],
            'author' => 'Kindermaenner',
            'homepage' => $homepage,
            'download_link' => $manifest['download_url'],
            'requires_php' => self::getPhpVersion($manifest['requires_php'] ?? null),
            'last_updated' => self::formatReleaseDate($manifest['released_at'] ?? null),
            'sections' => $sections,
        ];
    }

    public static function formatUpgradeNotice(array $manifest): ?string
    {
        $releaseNotes = $manifest['release_notes'] ?? '';
        if (!is_string($releaseNotes) || $releaseNotes === '') {
            return null;
        }

        $notice = self::formatReleaseNotes($releaseNotes);
        $noticeLabels = [];
        if (($manifest['security_fixes'] ?? false) === true) {
            $noticeLabels[] = 'Security update';
        }
        if (($manifest['prerelease'] ?? false) === true) {
            $noticeLabels[] = 'Pre-release';
        }
        if ($noticeLabels !== []) {
            $notice = '<strong>' . esc_html(implode(' · ', $noticeLabels)) . ':</strong> ' . $notice;
        }

        return $notice;
    }

    public static function getGitHubUrl($url): ?string
    {
        if (!is_string($url) || !wp_http_validate_url($url)) {
            return null;
        }

        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        $host = wp_parse_url($url, PHP_URL_HOST);

        return $scheme === 'https' && $host === 'github.com' ? $url : null;
    }

    public static function getPhpVersion($version): ?string
    {
        return is_string($version) && preg_match('/^\d+(?:\.\d+){1,2}$/D', $version) === 1
            ? $version
            : null;
    }

    private static function buildChangelog(array $manifest, ?string $releaseUrl): string
    {
        $releases = $manifest['releases'] ?? null;
        if (!is_array($releases)) {
            $notes = $manifest['release_notes'] ?? '';
            if (!is_string($notes) || $notes === '') {
                return 'Release notes are not available.';
            }

            return self::appendReleaseLink(self::formatReleaseNotes($notes), $releaseUrl);
        }

        $html = '';
        foreach ($releases as $release) {
            if (!is_array($release) || !is_string($release['version'] ?? null)) {
                continue;
            }

            $html .= self::renderReleaseEntry($release);
        }

        if ($html !== '') {
            return $html;
        }

        $notes = $manifest['release_notes'] ?? '';
        if (!is_string($notes) || $notes === '') {
            return 'Release notes are not available.';
        }

        return self::appendReleaseLink(self::formatReleaseNotes($notes), $releaseUrl);
    }

    private static function renderReleaseEntry(array $release): string
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

        $releasedAt = self::getReleaseDate($release['released_at'] ?? null);
        if ($releasedAt !== '') {
            $html .= '<p>Released: ' . esc_html($releasedAt) . '</p>';
        }

        $notes = $release['notes'] ?? '';
        if (is_string($notes) && $notes !== '') {
            $html .= self::formatReleaseNotes($notes);
        }

        $releaseLink = self::getGitHubUrl($release['url'] ?? null);
        if ($releaseLink !== null) {
            $html .= '<p><a href="' . esc_url($releaseLink) . '">View release on GitHub</a>';
            $compareLink = self::getGitHubUrl($release['compare_url'] ?? null);
            if ($compareLink !== null) {
                $html .= ' | <a href="' . esc_url($compareLink) . '">Compare changes</a>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    private static function buildCompatibilitySection(array $manifest): string
    {
        $requirements = [];
        $requiresPhp = self::getPhpVersion($manifest['requires_php'] ?? null);
        if ($requiresPhp !== null) {
            $requirements[] = 'Requires PHP ' . esc_html($requiresPhp) . '.';
        }

        $testedPhp = self::getPhpVersion($manifest['tested_php'] ?? null);
        if ($testedPhp !== null) {
            $requirements[] = 'Automated CI tested with PHP ' . esc_html($testedPhp) . '.';
        }

        return $requirements === [] ? '' : '<p>' . implode('<br>', $requirements) . '</p>';
    }

    private static function appendReleaseLink(string $notes, ?string $releaseUrl): string
    {
        return $releaseUrl === null
            ? $notes
            : $notes . '<p><a href="' . esc_url($releaseUrl) . '">View release on GitHub</a></p>';
    }

    private static function formatReleaseNotes(string $notes): string
    {
        return nl2br(esc_html($notes), false);
    }

    private static function getReleaseDate($date): string
    {
        if (!is_string($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? '' : gmdate('Y-m-d', $timestamp);
    }

    private static function formatReleaseDate($date): string
    {
        if (!is_string($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? '' : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
