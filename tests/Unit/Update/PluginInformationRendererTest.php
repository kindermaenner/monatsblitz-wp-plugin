<?php

declare(strict_types=1);

use Monatsblitz\Update\PluginInformationRenderer;

it('builds plugin information and upgrade notices', function () {
    $manifest = [
        'name' => 'Monatsblitz',
        'slug' => 'monatsblitz',
        'version' => '1.0.0',
        'download_url' => 'https://kindermaenner.de/monatsblitz.zip',
        'release_url' => 'https://github.com/kindermaenner/monatsblitz-wp-plugin/releases/tag/v1.0.0',
        'release_notes' => 'Initial release',
        'security_fixes' => true,
        'prerelease' => true,
        'requires_php' => '8.1',
        'tested_php' => '8.4',
    ];

    $notice = PluginInformationRenderer::formatUpgradeNotice($manifest);
    expect($notice)->toContain('Security update')
        ->and($notice)->toContain('Pre-release')
        ->and($notice)->toContain('Initial release');

    $info = PluginInformationRenderer::buildPluginInformation($manifest, 'monatsblitz', 'https://kindermaenner.de/manifest.json');
    expect($info->name)->toBe('Monatsblitz')
        ->and($info->version)->toBe('1.0.0')
        ->and($info->requires_php)->toBe('8.1')
        ->and($info->sections['changelog'])->toContain('Initial release');
});

it('validates github urls and php versions', function () {
    expect(PluginInformationRenderer::getGitHubUrl('https://github.com/org/repo'))->toBe('https://github.com/org/repo')
        ->and(PluginInformationRenderer::getGitHubUrl('https://malicious.com'))->toBeNull()
        ->and(PluginInformationRenderer::getPhpVersion('8.2'))->toBe('8.2')
        ->and(PluginInformationRenderer::getPhpVersion('8.2.1'))->toBe('8.2.1')
        ->and(PluginInformationRenderer::getPhpVersion('invalid'))->toBeNull();
});
