<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_tg_template_types() {
    return [
        'single' => 'Alerta Individual de Bloqueo',
        'digest' => 'Resumen Agrupado (Programado)',
        'login'  => 'Aviso de Conexión Exitosa',
    ];
}

function bap_get_tg_default_templates() {
    return [
        'single' => "<h2>🛡️ {badge}Bloqueo de Seguridad — {site_name}</h2>\n"
                  . "<p>Se ha interceptado una petición sospechosa en tu sitio.</p>\n"
                  . "<table><tr><th>Parámetro</th><th>Detalle</th></tr>\n"
                  . "<tr><td><b>IP Atacante</b></td><td><code>{ip}</code></td></tr>\n"
                  . "<tr><td><b>País</b></td><td><b>{country}</b></td></tr>\n"
                  . "<tr><td><b>ASN / Red</b></td><td>{asn}</td></tr>\n"
                  . "<tr><td><b>Riesgo AbuseIPDB</b></td><td><b>{risk}</b></td></tr>\n"
                  . "<tr><td><b>Motivo</b></td><td>{reason}</td></tr>\n"
                  . "<tr><td><b>Fecha / Hora</b></td><td><code>{time}</code></td></tr></table>\n"
                  . "<blockquote expandable><b>Ruta:</b> <code>{uri}</code></blockquote>",

        'digest' => "<h1>📊 {badge}Informe de Seguridad ({period})</h1>\n"
                  . "<p><b>Sitio:</b> {site_name} · <b>Total bloqueos:</b> <b>{total_hits}</b> · <b>IPs únicas:</b> <b>{unique_ips}</b></p>\n"
                  . "<h3>🌍 Top Países Atacantes</h3>\n{top_countries_list}\n"
                  . "<h3>🚨 Eventos Bloqueados Agrupados</h3>\n{events_table}\n"
                  . "<blockquote expandable><b>🏢 Top ASNs:</b>\n{top_asns_text}</blockquote>",

        'login'  => "<h2>{status_icon} {badge}Conexión Exitosa — {site_name}</h2>\n"
                  . "<p><b>{asn_alert}</b></p>\n"
                  . "<table><tr><th>Parámetro</th><th>Detalle</th></tr>\n"
                  . "<tr><td><b>Usuario</b></td><td><b>{user_login}</b> ({user_email})</td></tr>\n"
                  . "<tr><td><b>IP / País</b></td><td><code>{ip}</code> (<b>{country}</b>)</td></tr>\n"
                  . "<tr><td><b>ASN Actual</b></td><td><b>{asn}</b></td></tr>\n"
                  . "<tr><td><b>ASN Anterior</b></td><td>{last_asn}</td></tr>\n"
                  . "<tr><td><b>Fecha / Hora</b></td><td><code>{time}</code></td></tr></table>\n"
                  . "<blockquote expandable><b>Sesiones activas:</b> {active_asns}\n<b>UA:</b> <code>{user_agent}</code></blockquote>",
    ];
}

function bap_get_tg_template_html( $type ) {
    $saved    = get_option( 'bap_tg_custom_templates', [] );
    $defaults = bap_get_tg_default_templates();
    if ( is_array( $saved ) && ! empty( $saved[ $type ] ) ) {
        return (string) $saved[ $type ];
    }
    return $defaults[ $type ] ?? '';
}

function bap_sanitize_tg_rich_html( $html ) {
    $allowed = [
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'p'          => [],
        'br'         => [],
        'hr'         => [],
        'b'          => [],
        'strong'     => [],
        'i'          => [],
        'em'         => [],
        'u'          => [],
        's'          => [],
        'del'        => [],
        'code'       => [],
        'pre'        => [],
        'a'          => [ 'href' => true ],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'blockquote' => [ 'expandable' => true ],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => [],
        'td'         => [],
        'tg-spoiler' => [],
    ];
    return trim( wp_kses( (string) $html, $allowed ) );
}

function bap_tg_rich_to_fallback_html( $rich_html ) {
    $txt = str_replace( [ '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>' ], [ '<b>', "</b>\n", '<b>', "</b>\n", '<b>', "</b>\n", '<b>', "</b>\n" ], $rich_html );
    $txt = str_replace( [ '<p>', '</p>', '<br>', '<br/>', '<br />', '</tr>', '</li>', '<hr>', '<hr/>' ], [ '', "\n", "\n", "\n", "\n", "\n", "\n", "\n───\n", "\n───\n" ], $txt );
    $txt = str_replace( [ '</td>', '</th>', '<li>' ], [ ' | ', ' | ', '• ' ], $txt );
    $txt = strip_tags( $txt, '<b><strong><i><em><u><s><del><code><pre><a><blockquote><tg-spoiler>' );
    $txt = preg_replace( "/\n{3,}/", "\n\n", $txt );
    return trim( (string) $txt );
}

function bap_render_custom_tg_template( $type, array $vars, $override_html = null ) {
    $tpl  = ( null !== $override_html ) ? bap_sanitize_tg_rich_html( $override_html ) : bap_get_tg_template_html( $type );
    $rich = strtr( $tpl, $vars );
    return [
        'rich'     => $rich,
        'fallback' => bap_tg_rich_to_fallback_html( $rich ),
    ];
}
