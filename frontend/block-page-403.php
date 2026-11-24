<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_custom_403_page( $title, $message, $ip = '', $country = '', $reason = '' ) {
    status_header( 403 );
    nocache_headers();
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex, nofollow" />
        <title>403 - <?php echo esc_html( $title ); ?></title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
                color: #f8fafc;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
                padding: 20px;
            }
            .bap-card {
                width: 100%;
                max-width: 820px;
                background: rgba(15, 23, 42, 0.85);
                border: 1px solid #334155;
                border-radius: 16px;
                padding: 40px 32px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 28px;
            }
            .bap-svg-box {
                flex: 0 0 110px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .bap-content {
                flex: 1;
                text-align: center;
            }
            .bap-badge {
                display: inline-block;
                background: rgba(239, 68, 68, 0.15);
                color: #f87171;
                border: 1px solid rgba(239, 68, 68, 0.4);
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 1.5px;
                padding: 4px 12px;
                border-radius: 999px;
                margin-bottom: 14px;
            }
            h1 {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 12px;
                color: #ffffff;
            }
            p.bap-msg {
                font-size: 16px;
                line-height: 1.6;
                color: #cbd5e1;
                margin-bottom: 22px;
            }
            .bap-meta {
                display: inline-block;
                background: #020617;
                border: 1px solid #1e293b;
                border-radius: 8px;
                padding: 10px 16px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 12px;
                color: #94a3b8;
            }
            .bap-meta strong { color: #38bdf8; }
            @media (max-width: 680px) {
                .bap-card { flex-direction: column; padding: 28px 20px; }
                .bap-svg-box { flex: none; }
            }
        </style>
    </head>
    <body>
        <div class="bap-card">
            <!-- SVG Izquierda: Candado de seguridad -->
            <div class="bap-svg-box" aria-hidden="true">
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://w3.org">
                    <circle cx="50" cy="50" r="46" fill="#1E293B" stroke="#38BDF8" stroke-width="2" stroke-opacity="0.35"/>
                    <path d="M35 44V34C35 25.7157 41.7157 19 50 19C58.2843 19 65 25.7157 65 34V44" stroke="#38BDF8" stroke-width="5" stroke-linecap="round"/>
                    <rect x="27" y="44" width="46" height="36" rx="8" fill="#0F172A" stroke="#38BDF8" stroke-width="3.5"/>
                    <circle cx="50" cy="59" r="4.5" fill="#F87171"/>
                    <path d="M50 63V70" stroke="#F87171" stroke-width="3.5" stroke-linecap="round"/>
                </svg>
            </div>

            <!-- Contenido Central -->
            <div class="bap-content">
                <span class="bap-badge">ERROR 403 · ACCESO BLOQUEADO</span>
                <h1><?php echo esc_html( $title ); ?></h1>
                <p class="bap-msg"><?php echo esc_html( $message ); ?></p>
                <?php if ( '' !== $ip || '' !== $reason ) : ?>
                    <div class="bap-meta">
                        <?php if ( '' !== $ip ) : ?>
                            IP: <strong><?php echo esc_html( $ip ); ?></strong>
                        <?php endif; ?>
                        <?php if ( '' !== $country ) : ?>
                            &nbsp;|&nbsp; País: <strong><?php echo esc_html( $country ); ?></strong>
                        <?php endif; ?>
                        <?php if ( '' !== $reason ) : ?>
                            <br />Motivo: <strong><?php echo esc_html( $reason ); ?></strong>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SVG Derecha: Objetivo / Mira de rastreo -->
            <div class="bap-svg-box" aria-hidden="true">
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://w3.org">
                    <circle cx="50" cy="50" r="46" fill="#1E293B" stroke="#EF4444" stroke-width="2" stroke-opacity="0.35"/>
                    <circle cx="50" cy="50" r="30" stroke="#F87171" stroke-width="3"/>
                    <circle cx="50" cy="50" r="17" stroke="#F87171" stroke-width="2" stroke-dasharray="4 4"/>
                    <circle cx="50" cy="50" r="5" fill="#EF4444"/>
                    <line x1="50" y1="12" x2="50" y2="26" stroke="#F87171" stroke-width="3" stroke-linecap="round"/>
                    <line x1="50" y1="74" x2="50" y2="88" stroke="#F87171" stroke-width="3" stroke-linecap="round"/>
                    <line x1="12" y1="50" x2="26" y2="50" stroke="#F87171" stroke-width="3" stroke-linecap="round"/>
                    <line x1="74" y1="50" x2="88" y2="50" stroke="#F87171" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
