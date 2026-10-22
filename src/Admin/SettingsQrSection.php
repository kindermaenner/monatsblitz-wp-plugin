<?php

declare(strict_types=1);

namespace Monatsblitz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsQrSection
{
    private const TAG_DIV_CLOSE = '</div>';

    public static function render(
        string $apiKey,
        string $defaultModeType,
        string $defaultFixedMode,
        array $defaultRotationModes
    ): void {
        echo '<div class="card" style="max-width:600px; margin:20px 0; padding:16px 20px;">';
        echo '<h2 style="margin-top:0;">📱 App-Einrichtung per QR-Code</h2>';
        if (empty($apiKey)) {
            echo '<p style="color:#d63638;"><em>Bitte generiere zuerst unten unter Einstellungen einen API-Key, damit der QR-Code für die App erzeugt werden kann.</em></p>';
            echo self::TAG_DIV_CLOSE;
            return;
        }

        $payload = self::build_qr_payload($apiKey, $defaultModeType, $defaultFixedMode, $defaultRotationModes);
        $qrPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        self::render_qr_markup($apiKey, $defaultModeType, $defaultFixedMode, $defaultRotationModes);
        self::render_qr_script($qrPayload);
        echo self::TAG_DIV_CLOSE;
    }

    private static function build_qr_payload(
        string $apiKey,
        string $defaultModeType,
        string $defaultFixedMode,
        array $defaultRotationModes
    ): array {
        $payload = [
            'url' => home_url(),
            'key' => $apiKey,
        ];
        if (in_array($defaultModeType, ['fixed', 'rotation', 'none'], true)) {
            $payload['mode_type'] = $defaultModeType;
            if ($defaultModeType === 'fixed' && !empty($defaultFixedMode)) {
                $payload['fixed_mode'] = $defaultFixedMode;
            } elseif ($defaultModeType === 'rotation' && !empty($defaultRotationModes)) {
                $payload['rotation_modes'] = array_values($defaultRotationModes);
            }
        }
        return $payload;
    }

    private static function render_qr_markup(
        string $apiKey,
        string $defaultModeType,
        string $defaultFixedMode,
        array $defaultRotationModes
    ): void {
        echo '<p>Scanne diesen QR-Code mit der <strong>Monatsblitz-App</strong> (unter <em>Einstellungen &rarr; QR-Code scannen</em>), um Server-Adresse und API-Key automatisch einzurichten:</p>';
        echo '<div style="margin:16px 0; display:flex; align-items:center; gap:20px; flex-wrap:wrap;">';
        echo '<div id="monatsblitz-qrcode" style="display:inline-block; padding:12px; background:#fff; border:1px solid #ccd0d4; border-radius:4px; min-width:180px; min-height:180px;">' . self::TAG_DIV_CLOSE;
        echo '<div style="font-size:13px; color:#555;">';
        echo '<p style="margin-top:0;"><strong>Enthaltene Konfiguration:</strong></p>';
        echo '<ul style="list-style:disc; margin-left:20px;">';
        echo '<li><strong>URL:</strong> <code>' . esc_html(home_url()) . '</code></li>';
        echo '<li><strong>API-Key:</strong> <code>' . esc_html(substr($apiKey, 0, 6) . '...' . substr($apiKey, -4)) . '</code></li>';
        if ($defaultModeType === 'fixed' && !empty($defaultFixedMode)) {
            echo '<li><strong>Turniermodus:</strong> Fest (<code>' . esc_html($defaultFixedMode) . '</code>)</li>';
        } elseif ($defaultModeType === 'rotation' && !empty($defaultRotationModes)) {
            echo '<li><strong>Turniermodus:</strong> Rotation (<code>' . esc_html(implode(', ', $defaultRotationModes)) . '</code>)</li>';
        } else {
            echo '<li><strong>Turniermodus:</strong> Keine automatische Vorauswahl</li>';
        }
        echo '</ul>';
        echo self::TAG_DIV_CLOSE;
        echo self::TAG_DIV_CLOSE;
    }

    private static function render_qr_script(string|false $qrPayload): void
    {
        echo '<script type="text/javascript">' . Admin::get_qrcode_js() . '</script>';
        echo '<script type="text/javascript">
        (function() {
            function renderQr() {
                var container = document.getElementById("monatsblitz-qrcode");
                if (!container || typeof QRCode === "undefined") {
                    return;
                }
                if (container.querySelector("canvas, img")) {
                    return;
                }
                container.innerHTML = "";
                new QRCode(container, {
                    text: ' . wp_json_encode($qrPayload) . ',
                    width: 180,
                    height: 180,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            renderQr();
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", renderQr);
            }
        })();
        </script>';
    }
}
