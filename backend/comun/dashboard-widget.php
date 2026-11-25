<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_dashboard_setup', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    wp_add_dashboard_widget(
        'bap_dashboard_activity_widget',
        'Actividad de Bloqueo Admin en los últimos 30 días',
        'bap_render_dashboard_widget'
    );
} );

function bap_dashboard_country_label( $iso ) {
    $iso = strtoupper( trim( (string) $iso ) );
    if ( ! preg_match( '/^[A-Z]{2}$/', $iso ) ) {
        return '🌐 ' . ( '' !== $iso ? $iso : 'Desconocido' );
    }
    $flag = mb_chr( 127397 + ord( $iso[0] ), 'UTF-8' ) . mb_chr( 127397 + ord( $iso[1] ), 'UTF-8' );
    $name = $iso;
    if ( class_exists( 'Locale' ) ) {
        $localized = Locale::getDisplayRegion( '-' . $iso, 'es' );
        if ( is_string( $localized ) && '' !== $localized ) {
            $name = mb_convert_case( $localized, MB_CASE_TITLE, 'UTF-8' );
        }
    }
    return $flag . ' ' . $name;
}

function bap_render_dashboard_widget() {
    global $wpdb;
    $table = bap_get_logs_table_name();
    $since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

    $top_ips = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ip_address, MAX(country_code) AS country, COUNT(*) AS hits
             FROM `{$table}`
             WHERE created_at >= %s AND ip_address != ''
             GROUP BY ip_address
             ORDER BY hits DESC
             LIMIT 5",
            $since
        )
    );

    $top_countries = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT country_code, COUNT(DISTINCT ip_address) AS unique_ips, COUNT(*) AS hits
             FROM `{$table}`
             WHERE created_at >= %s AND country_code != ''
             GROUP BY country_code
             ORDER BY hits DESC
             LIMIT 5",
            $since
        )
    );

    $failed_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT reason, COUNT(*) AS hits
             FROM `{$table}`
             WHERE created_at >= %s AND (reason LIKE %s OR reason LIKE %s)
             GROUP BY reason
             ORDER BY hits DESC
             LIMIT 25",
            $since,
            'Usuario inexistente:%',
            'Login fallido (%'
        )
    );

    $failed_users = [];
    foreach ( (array) $failed_rows as $fr ) {
        $uname = '';
        if ( 0 === strpos( $fr->reason, 'Usuario inexistente:' ) ) {
            $uname = trim( substr( $fr->reason, strlen( 'Usuario inexistente:' ) ) );
        } elseif ( preg_match( '/^Login fallido \((.+)\)$/', $fr->reason, $m ) ) {
            $uname = trim( $m[1] );
        }
        if ( '' !== $uname ) {
            $failed_users[ $uname ] = ( $failed_users[ $uname ] ?? 0 ) + (int) $fr->hits;
        }
    }
    arsort( $failed_users );
    $failed_users = array_slice( $failed_users, 0, 5, true );

    $updates_needed = [];
    $db_checks      = [
        'Base de datos Países (.mmdb)' => bap_get_mmdb_path(),
        'Base de datos ASN (.mmdb)'    => bap_get_asn_mmdb_path(),
        'Lista AbuseIPDB'              => bap_get_abuseipdb_path(),
        'Nodos de salida Tor'          => bap_get_tor_list_path(),
        'Redes FireHOL Level 1'        => bap_get_firehol_list_path(),
    ];
    foreach ( $db_checks as $label => $path ) {
        if ( ! file_exists( $path ) ) {
            $updates_needed[] = $label . ' sin descargar';
        }
    }
    if ( function_exists( 'bap_get_github_latest_release' ) ) {
        $rel     = bap_get_github_latest_release();
        $cur_ver = get_file_data( dirname( __DIR__ ) . '/bloqueo-admin-pais.php', [ 'Version' => 'Version' ] )['Version'] ?? '1.2.37';
        if ( $rel && ! empty( $rel['version'] ) && version_compare( $rel['version'], $cur_ver, '>' ) ) {
            $updates_needed[] = 'Nueva versión disponible: v' . $rel['version'];
        }
    }
    ?>
    <div style="margin:-6px -12px -12px -12px;padding:14px;background:#0b111a;color:#e5e7eb;font-size:13px;">
        <div style="display:flex;align-items:center;gap:10px;padding-bottom:12px;margin-bottom:14px;border-bottom:1px solid #1f2937;">
            <span class="dashicons dashicons-shield" style="font-size:28px;width:28px;height:28px;color:#38bdf8;"></span>
            <div>
                <strong style="font-size:16px;color:#ffffff;display:block;line-height:1.1;">Bloqueo Admin y Web</strong>
                <span style="font-size:11px;color:#38bdf8;">Resumen de seguridad (30 días)</span>
            </div>
        </div>
        <h3 style="color:#ffffff;font-size:15px;margin:0 0 8px 0;">Principales 5 IPs bloqueadas</h3>
        <table class="widefat" style="background:#0f172a;border:1px solid #1e293b;color:#e5e7eb;margin-bottom:10px;">
            <thead>
                <tr style="background:#111827;">
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">IP</th>
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">País</th>
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">Recuento de bloqueos</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $top_ips ) ) : ?>
                    <tr><td colspan="3" style="color:#9ca3af;">Sin bloqueos registrados.</td></tr>
                <?php else : ?>
                    <?php foreach ( $top_ips as $row ) : ?>
                        <tr>
                            <td style="border-bottom:1px solid #1e293b;"><code style="background:transparent;color:#ffffff;font-weight:600;"><?php echo esc_html( $row->ip_address ); ?></code></td>
                            <td style="border-bottom:1px solid #1e293b;color:#e5e7eb;"><?php echo esc_html( bap_dashboard_country_label( $row->country ) ); ?></td>
                            <td style="border-bottom:1px solid #1e293b;color:#ffffff;font-weight:700;"><?php echo number_format_i18n( (int) $row->hits ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin:0 0 18px 0;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-bloqueos#bap_blacklist_textarea' ) ); ?>" class="button button-primary">Actualizar IPs bloqueadas</a>
        </p>

        <h3 style="color:#ffffff;font-size:15px;margin:0 0 8px 0;">Principales 5 países bloqueados</h3>
        <table class="widefat" style="background:#0f172a;border:1px solid #1e293b;color:#e5e7eb;margin-bottom:10px;">
            <thead>
                <tr style="background:#111827;">
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">País</th>
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">Total de IPs bloqueadas</th>
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">Recuento de bloqueos</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $top_countries ) ) : ?>
                    <tr><td colspan="3" style="color:#9ca3af;">Sin datos por país.</td></tr>
                <?php else : ?>
                    <?php foreach ( $top_countries as $row ) : ?>
                        <tr>
                            <td style="border-bottom:1px solid #1e293b;color:#ffffff;"><?php echo esc_html( bap_dashboard_country_label( $row->country_code ) ); ?></td>
                            <td style="border-bottom:1px solid #1e293b;color:#e5e7eb;"><?php echo number_format_i18n( (int) $row->unique_ips ); ?></td>
                            <td style="border-bottom:1px solid #1e293b;color:#ffffff;font-weight:700;"><?php echo number_format_i18n( (int) $row->hits ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin:0 0 18px 0;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-bloqueos' ) ); ?>" class="button button-primary">Actualizar países bloqueados</a>
        </p>

        <h3 style="color:#ffffff;font-size:15px;margin:0 0 8px 0;">Principales 5 accesos fallidos</h3>
        <table class="widefat" style="background:#0f172a;border:1px solid #1e293b;color:#e5e7eb;margin-bottom:10px;">
            <thead>
                <tr style="background:#111827;">
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">Nombre de usuario</th>
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">Intentos de acceso</th>
                    <th style="color:#cbd5e1;border-bottom:1px solid #1e293b;">Usuario existente</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $failed_users ) ) : ?>
                    <tr><td colspan="3" style="color:#9ca3af;">Sin intentos fallidos de login.</td></tr>
                <?php else : ?>
                    <?php foreach ( $failed_users as $uname => $cnt ) :
                        $exists = (bool) ( username_exists( $uname ) || ( is_email( $uname ) && email_exists( $uname ) ) );
                    ?>
                        <tr>
                            <td style="border-bottom:1px solid #1e293b;color:#ffffff;"><?php echo esc_html( $uname ); ?></td>
                            <td style="border-bottom:1px solid #1e293b;color:#e5e7eb;"><?php echo number_format_i18n( (int) $cnt ); ?></td>
                            <td style="border-bottom:1px solid #1e293b;font-weight:700;color:<?php echo $exists ? '#4ade80' : '#f87171'; ?>;">
                                <?php echo $exists ? 'Sí' : 'No'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin:0 0 18px 0;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-bloqueos' ) ); ?>" class="button button-primary">Actualizar las opciones de seguridad en el acceso</a>
        </p>

        <h3 style="color:#ffffff;font-size:15px;margin:0 0 6px 0;">Actualizaciones necesarias</h3>
        <?php if ( empty( $updates_needed ) ) : ?>
            <p style="margin:0;color:#9ca3af;">No hay actualizaciones disponibles en este momento.</p>
        <?php else : ?>
            <ul style="margin:0 0 10px 18px;list-style:disc;color:#fbbf24;">
                <?php foreach ( $updates_needed as $up_msg ) : ?>
                    <li><?php echo esc_html( $up_msg ); ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-db' ) ); ?>" class="button button-secondary">Ir a Base de Datos</a>
        <?php endif; ?>
    </div>
    <?php
}
