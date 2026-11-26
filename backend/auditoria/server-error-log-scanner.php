<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_detect_default_server_error_log() {
    $saved = trim( (string) get_option( 'bap_server_error_log_path', '' ) );
    if ( '' !== $saved ) {
        return $saved;
    }
    $host       = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $clean_host = preg_replace( '/^www\./', '', $host );
    $candidates = [
        "/var/log/nginx/{$clean_host}-error.log",
        "/var/log/nginx/{$clean_host}.error.log",
        '/var/log/nginx/error.log',
        "/var/log/apache2/{$clean_host}-error.log",
        '/var/log/apache2/error.log',
        WP_CONTENT_DIR . '/debug.log',
    ];
    foreach ( $candidates as $c ) {
        if ( @is_readable( $c ) ) {
            return $c;
        }
    }
    return '/var/log/nginx/error.log';
}

function bap_tail_file_lines( $filepath, $max_lines = 4000 ) {
    if ( '' === $filepath || ! @is_file( $filepath ) || ! @is_readable( $filepath ) ) {
        return new WP_Error( 'log_unreadable', 'No se puede leer el archivo de log (verifica la ruta o los permisos de lectura para PHP).' );
    }
    $fh = @fopen( $filepath, 'rb' );
    if ( ! $fh ) {
        return new WP_Error( 'log_open_fail', 'Error al abrir el archivo de log.' );
    }
    fseek( $fh, 0, SEEK_END );
    $pos       = ftell( $fh );
    $chunk     = 65536;
    $buffer    = '';
    $line_cnt  = 0;

    while ( $pos > 0 && $line_cnt <= $max_lines ) {
        $read = min( $chunk, $pos );
        $pos -= $read;
        fseek( $fh, $pos );
        $data     = (string) fread( $fh, $read );
        $buffer   = $data . $buffer;
        $line_cnt = substr_count( $buffer, "\n" );
    }
    fclose( $fh );

    $lines = explode( "\n", $buffer );
    return array_slice( $lines, -$max_lines );
}

function bap_analyze_server_error_log( $filepath ) {
    $lines = bap_tail_file_lines( $filepath, 4000 );
    if ( is_wp_error( $lines ) ) {
        return $lines;
    }

    $attack_rx = '/(?:\/wp-content\/plugins\/[^"\s]+\/(?:readme|changelog|license)\.(?:txt|md)|\/\.env\b|\/\.git\b|wp-config\.php\.|\/phpmyadmin|\/adminer|\/xmlrpc\.php|\/wp-content\/uploads\/[^"\s]+\.php|\.\.\/|union\+select|eval\(|base64_decode)/i';
    $suspects  = [];
    $my_ip     = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    $wl        = bap_parse_list( get_option( 'bap_whitelist_ips', '' ) );

    foreach ( $lines as $line ) {
        if ( '' === trim( $line ) ) { continue; }
        if ( ! preg_match( '/client:\s*([0-9a-fA-F:.]+)|\[client\s+([0-9a-fA-F:.]+)(?::\d+)?\]/', $line, $mIp ) ) {
            continue;
        }
        $ip = trim( (string) ( ! empty( $mIp[1] ) ? $mIp[1] : ( $mIp[2] ?? '' ) ) );
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || $ip === $my_ip || bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, $wl ) ) {
            continue;
        }

        $is_probe = (bool) preg_match( $attack_rx, $line );
        $is_404   = ( false !== strpos( $line, 'No such file or directory' ) || false !== strpos( $line, 'Primary script unknown' ) );
        if ( ! $is_probe && ! $is_404 ) {
            continue;
        }

        $req = '';
        if ( preg_match( '/request:\s*"([^"]+)"/', $line, $mReq ) ) {
            $req = $mReq[1];
        } elseif ( preg_match( '/open\(\)\s*"([^"]+)"/', $line, $mOpen ) ) {
            $req = basename( dirname( $mOpen[1] ) ) . '/' . basename( $mOpen[1] );
        }

        if ( ! isset( $suspects[ $ip ] ) ) {
            $suspects[ $ip ] = [
                'ip'      => $ip,
                'count'   => 0,
                'probes'  => 0,
                'country' => function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'N/D',
                'asn'     => function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : 'N/D',
                'samples' => [],
            ];
        }
        $suspects[ $ip ]['count']++;
        if ( $is_probe ) {
            $suspects[ $ip ]['probes']++;
        }
        if ( '' !== $req && count( $suspects[ $ip ]['samples'] ) < 4 && ! in_array( $req, $suspects[ $ip ]['samples'], true ) ) {
            $suspects[ $ip ]['samples'][] = $req;
        }
    }

    $filtered = array_filter( $suspects, fn( $s ) => ( $s['probes'] >= 1 || $s['count'] >= 5 ) );
    usort( $filtered, fn( $a, $b ) => $b['count'] <=> $a['count'] );

    return [
        'lines_read' => count( $lines ),
        'suspects'   => array_slice( $filtered, 0, 50 ),
    ];
}
function bap_render_server_error_log_scanner_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice   = '';
    $scan_res = null;
    $log_path = bap_detect_default_server_error_log();

    if ( isset( $_POST['bap_ban_log_ip'] ) && check_admin_referer( 'bap_srv_log_action', 'bap_srv_log_nonce' ) ) {
        $bip = trim( sanitize_text_field( wp_unslash( $_POST['bap_target_log_ip'] ?? '' ) ) );
        if ( filter_var( $bip, FILTER_VALIDATE_IP ) && function_exists( 'bap_add_ip_to_blacklist' ) ) {
            bap_add_ip_to_blacklist( $bip, false );
            $notice = '<div class="notice notice-success inline"><p><strong>IP bloqueada permanentemente:</strong> <code>' . esc_html( $bip ) . '</code> añadida a la Lista Negra.</p></div>';
        }
    }

    if ( isset( $_POST['bap_scan_srv_error_log'] ) && check_admin_referer( 'bap_srv_log_action', 'bap_srv_log_nonce' ) ) {
        $raw_p = trim( sanitize_text_field( wp_unslash( $_POST['bap_srv_log_path'] ?? '' ) ) );
        if ( '' !== $raw_p ) {
            $log_path = $raw_p;
            update_option( 'bap_server_error_log_path', $log_path, false );
        }
        $scan_res = bap_analyze_server_error_log( $log_path );
    }

    $cur_bl = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Analizador de Logs del Servidor (Nginx / Apache <code>error.log</code>)</h2>
        <p class="description">Lee las últimas 4.000 líneas del registro de errores del servidor web para detectar IPs que escanean archivos sensibles (<code>readme.txt</code>, <code>.env</code>, <code>wp-config</code>, 404 masivos) y banearlas en 1 clic.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="margin:14px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_srv_log_action', 'bap_srv_log_nonce' ); ?>
            <label for="bap_srv_log_path"><strong>Ruta del archivo .log:</strong></label>
            <input type="text" id="bap_srv_log_path" name="bap_srv_log_path" value="<?php echo esc_attr( $log_path ); ?>" class="regular-text code" style="width:380px;" placeholder="/var/log/nginx/tudominio-error.log" required />
            <button type="submit" name="bap_scan_srv_error_log" class="button button-primary">Analizar error.log ahora</button>
        </form>

        <?php if ( is_wp_error( $scan_res ) ) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html( $scan_res->get_error_message() ); ?></p></div>
        <?php elseif ( is_array( $scan_res ) ) : ?>
            <?php if ( empty( $scan_res['suspects'] ) ) : ?>
                <div class="notice notice-success inline"><p><strong>Sin escaneos sospechosos</strong> en las últimas <?php echo (int) $scan_res['lines_read']; ?> líneas leídas.</p></div>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:10px;">
                    <thead><tr><th>IP Atacante</th><th>País / ASN</th><th style="width:90px;">Eventos</th><th>Rutas buscadas (Muestra)</th><th style="width:135px;">Acción</th></tr></thead>
                    <tbody>
                        <?php foreach ( $scan_res['suspects'] as $s ) :
                            $already = bap_ip_in_list( $s['ip'], $cur_bl );
                        ?>
                            <tr>
                                <td><code><?php echo esc_html( $s['ip'] ); ?></code></td>
                                <td><strong><?php echo esc_html( $s['country'] ); ?></strong><br><small><?php echo esc_html( $s['asn'] ); ?></small></td>
                                <td><?php echo bap_render_status_badge( $s['count'] . ' hits', '#d63638' ); ?></td>
                                <td><small><code><?php echo esc_html( implode( ' | ', $s['samples'] ) ?: 'Errores 404 repetidos' ); ?></code></small></td>
                                <td>
                                    <?php if ( $already ) : ?>
                                        <span style="color:#d63638;font-weight:700;font-size:11px;">En Lista Negra</span>
                                    <?php else : ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field( 'bap_srv_log_action', 'bap_srv_log_nonce' ); ?>
                                            <input type="hidden" name="bap_srv_log_path" value="<?php echo esc_attr( $log_path ); ?>" />
                                            <input type="hidden" name="bap_target_log_ip" value="<?php echo esc_attr( $s['ip'] ); ?>" />
                                            <button type="submit" name="bap_ban_log_ip" class="button button-small" style="color:#d63638;border-color:#d63638;">+ Lista Negra</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}