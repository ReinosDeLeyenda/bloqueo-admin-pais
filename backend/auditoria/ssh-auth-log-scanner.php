<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_ssh_log_candidates() {
    $candidates = [];
    $defaults   = [
        '/var/log/auth.log' => 'Debian / Ubuntu (/var/log/auth.log)',
        '/var/log/secure'   => 'RHEL / AlmaLinux / Rocky (/var/log/secure)',
        '/var/log/syslog'   => 'Syslog general (/var/log/syslog)',
    ];
    foreach ( $defaults as $path => $label ) {
        if ( @file_exists( $path ) || is_link( $path ) ) {
            $candidates[ $path ] = $label;
        }
    }
    if ( @is_dir( '/var/log' ) ) {
        $entries = @scandir( '/var/log' );
        if ( is_array( $entries ) ) {
            foreach ( $entries as $entry ) {
                if ( '.' === $entry || '..' === $entry ) { continue; }
                $full = '/var/log/' . $entry;
                if ( is_link( $full ) && ! isset( $candidates[ $full ] ) ) {
                    $target              = @readlink( $full );
                    $candidates[ $full ] = 'Symlink personalizado (' . $full . ( $target ? ' -> ' . basename( $target ) : '' ) . ')';
                }
            }
        }
    }
    $saved = trim( (string) get_option( 'bap_ssh_auth_log_path', '' ) );
    if ( '' !== $saved && ! isset( $candidates[ $saved ] ) ) {
        $candidates[ $saved ] = 'Ruta guardada (' . $saved . ')';
    }
    if ( empty( $candidates ) ) {
        $candidates['/var/log/auth.log'] = 'Debian / Ubuntu (/var/log/auth.log)';
    }
    return $candidates;
}

function bap_analyze_ssh_auth_log( $filepath ) {
    if ( ! function_exists( 'bap_tail_file_lines' ) ) {
        return new WP_Error( 'missing_helper', 'Falta el lector de logs del servidor.' );
    }
    $lines = bap_tail_file_lines( $filepath, 5000 );
    if ( is_wp_error( $lines ) ) {
        return $lines;
    }
    $suspects = [];
    $my_ip    = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    $wl       = bap_parse_list( get_option( 'bap_whitelist_ips', '' ) );
    foreach ( $lines as $line ) {
        if ( false === strpos( $line, 'sshd' ) ) { continue; }
        $ip = ''; $user = '';
        if ( preg_match( '/Failed (?:password|publickey) for (?:invalid user )?([^\s]+) from ([0-9a-fA-F:.]+)/', $line, $m ) ) {
            $user = $m[1]; $ip = $m[2];
        } elseif ( preg_match( '/Invalid user ([^\s]+) from ([0-9a-fA-F:.]+)/', $line, $m ) ) {
            $user = $m[1]; $ip = $m[2];
        } elseif ( preg_match( '/(?:Disconnected from authenticating user|Connection closed by authenticating user) ([^\s]+) ([0-9a-fA-F:.]+)/', $line, $m ) ) {
            $user = $m[1]; $ip = $m[2];
        } elseif ( preg_match( '/maximum authentication attempts exceeded for (?:invalid user )?([^\s]+) from ([0-9a-fA-F:.]+)/', $line, $m ) ) {
            $user = $m[1]; $ip = $m[2];
        } elseif ( preg_match( '/DENY sshd connection from ([0-9a-fA-F:.]+)(?:\s*\(([A-Z]{2})\))?/i', $line, $m ) ) {
            $user = '[DENY hosts.deny]'; $ip = $m[1];
        } elseif ( preg_match( '/refused connect from (?:[^\s(]+\s*\()?([0-9a-fA-F:.]+)/i', $line, $m ) ) {
            $user = '[TCP Refused]'; $ip = $m[1];
        }
        if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) || $ip === $my_ip || bap_is_server_ip( $ip ) || bap_ip_in_list( $ip, $wl ) ) { continue; }
        if ( ! isset( $suspects[ $ip ] ) ) {
            $suspects[ $ip ] = [
                'ip'      => $ip,
                'count'   => 0,
                'country' => function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'N/D',
                'asn'     => function_exists( 'bap_get_asn_by_ip' ) ? bap_get_asn_by_ip( $ip ) : 'N/D',
                'users'   => [],
            ];
        }
        $suspects[ $ip ]['count']++;
        if ( '' !== $user && count( $suspects[ $ip ]['users'] ) < 6 && ! in_array( $user, $suspects[ $ip ]['users'], true ) ) {
            $suspects[ $ip ]['users'][] = $user;
        }
    }
    $filtered = array_filter( $suspects, fn( $s ) => $s['count'] >= 2 );
    usort( $filtered, fn( $a, $b ) => $b['count'] <=> $a['count'] );
    return [ 'lines_read' => count( $lines ), 'suspects' => array_slice( $filtered, 0, 50 ) ];
}

function bap_render_ssh_auth_log_scanner_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice     = '';
    $scan_res   = null;
    $candidates = bap_get_ssh_log_candidates();
    $saved_path = trim( (string) get_option( 'bap_ssh_auth_log_path', '' ) );
    $selected   = ( '' !== $saved_path ) ? $saved_path : (string) array_key_first( $candidates );
    if ( isset( $_POST['bap_ban_ssh_ip'] ) && check_admin_referer( 'bap_ssh_log_action', 'bap_ssh_log_nonce' ) ) {
        $bip = trim( sanitize_text_field( wp_unslash( $_POST['bap_target_ssh_ip'] ?? '' ) ) );
        if ( filter_var( $bip, FILTER_VALIDATE_IP ) && function_exists( 'bap_add_ip_to_blacklist' ) ) {
            bap_add_ip_to_blacklist( $bip, false );
            $notice = '<div class="notice notice-success inline"><p><strong>IP bloqueada en WordPress:</strong> <code>' . esc_html( $bip ) . '</code> añadida a la Lista Negra.</p></div>';
        }
    }
    if ( isset( $_POST['bap_ban_all_ssh_ips'] ) && check_admin_referer( 'bap_ssh_log_action', 'bap_ssh_log_nonce' ) ) {
        $preset_b = trim( sanitize_text_field( wp_unslash( $_POST['bap_ssh_log_select'] ?? '' ) ) );
        $selected = ( '' !== $preset_b ) ? $preset_b : $selected;
        $res_b    = bap_analyze_ssh_auth_log( $selected );
        $added_c  = 0;
        if ( is_array( $res_b ) && ! empty( $res_b['suspects'] ) && function_exists( 'bap_add_ip_to_blacklist' ) ) {
            $cur_bl_check = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
            foreach ( $res_b['suspects'] as $sus ) {
                $sip = $sus['ip'] ?? '';
                if ( filter_var( $sip, FILTER_VALIDATE_IP ) && ! bap_ip_in_list( $sip, $cur_bl_check ) && bap_add_ip_to_blacklist( $sip, false ) ) {
                    $added_c++;
                }
            }
        }
        $notice   = '<div class="notice notice-success inline"><p><strong>Bloqueo masivo SSH completado:</strong> Se han anadido <strong>' . (int) $added_c . '</strong> IP(s) nuevas a la Lista Negra.</p></div>';
        $scan_res = is_array( $res_b ) ? $res_b : null;
    }
    if ( isset( $_POST['bap_scan_ssh_auth_log'] ) && check_admin_referer( 'bap_ssh_log_action', 'bap_ssh_log_nonce' ) ) {
        $preset = trim( sanitize_text_field( wp_unslash( $_POST['bap_ssh_log_select'] ?? '' ) ) );
        $custom = trim( sanitize_text_field( wp_unslash( $_POST['bap_ssh_log_custom'] ?? '' ) ) );
        $chosen = ( '__custom__' === $preset && '' !== $custom ) ? $custom : $preset;
        if ( '' !== $chosen ) {
            $selected = $chosen;
            update_option( 'bap_ssh_auth_log_path', $selected, false );
            if ( ! isset( $candidates[ $selected ] ) ) {
                $candidates[ $selected ] = 'Ruta personalizada (' . $selected . ')';
            }
        }
        $scan_res = bap_analyze_ssh_auth_log( $selected );
    }
    $cur_bl = bap_parse_list( get_option( 'bap_blacklist_ips', '' ), false );
    echo '<div id="bap-box-ssh-log" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">';
    echo '<h2 style="margin-top:0;">Analizador de Accesos Fallidos por SSH (<code>auth.log</code> / Symlink)</h2>';
    echo '<p class="description">Analiza las últimas 5.000 líneas del registro de autenticación SSH para detectar ataques de fuerza bruta por puerto 22 y bloquear esas IPs también en tu web.</p>';
    echo wp_kses_post( $notice );
    echo '<form method="post" style="margin:14px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
    wp_nonce_field( 'bap_ssh_log_action', 'bap_ssh_log_nonce' );
    echo '<label for="bap_ssh_log_select"><strong>Fichero / Symlink SSH:</strong></label>';
    echo '<select id="bap_ssh_log_select" name="bap_ssh_log_select" onchange="document.getElementById(\'bap_ssh_log_custom\').style.display=(this.value===\'__custom__\')?\'inline-block\':\'none\';">';
    foreach ( $candidates as $path => $lbl ) {
        echo '<option value="' . esc_attr( $path ) . '"' . selected( $selected, $path, false ) . '>' . esc_html( $lbl ) . '</option>';
    }
    echo '<option value="__custom__">Otra ruta o symlink personalizado...</option></select>';
    echo '<input type="text" id="bap_ssh_log_custom" name="bap_ssh_log_custom" value="" placeholder="/var/log/mi-ssh-auth.log" class="regular-text code" style="width:260px;display:none;" />';
    echo '<button type="submit" name="bap_scan_ssh_auth_log" class="button button-primary">Analizar accesos SSH ahora</button></form>';
    if ( is_wp_error( $scan_res ) ) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html( $scan_res->get_error_message() ) . '</p>';
        echo '<p style="font-size:12px;">Si tienes acceso root por SSH, crea un enlace simbólico con el nombre que prefieras y concede lectura al usuario web (ej. <code>www-data</code>):<br><code>sudo ln -s /var/log/auth.log /var/log/bap-ssh.log && sudo setfacl -m u:www-data:r /var/log/auth.log</code></p></div>';
    } elseif ( is_array( $scan_res ) ) {
        if ( empty( $scan_res['suspects'] ) ) {
            echo '<div class="notice notice-success inline"><p><strong>Sin ataques de fuerza bruta SSH detectados</strong> en las últimas ' . (int) $scan_res['lines_read'] . ' líneas leídas.</p></div>';
        } else {
            $unbanned_cnt = count( array_filter( $scan_res['suspects'], fn( $x ) => ! bap_ip_in_list( $x['ip'], $cur_bl ) ) );
            echo '<div class="notice notice-warning inline" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:8px 12px;">';
            echo '<span>Se han detectado <strong>' . count( $scan_res['suspects'] ) . '</strong> IP(s) atacantes en SSH:</span>';
            if ( $unbanned_cnt > 0 ) {
                echo '<form method="post" style="margin:0;" onsubmit="return confirm(\'Anadir las ' . (int) $unbanned_cnt . ' IPs nuevas a la Lista Negra permanente?\' );">';
                wp_nonce_field( 'bap_ssh_log_action', 'bap_ssh_log_nonce' );
                echo '<input type="hidden" name="bap_ssh_log_select" value="' . esc_attr( $selected ) . '" />';
                echo '<button type="submit" name="bap_ban_all_ssh_ips" class="button button-secondary" style="color:#d63638;border-color:#d63638;">+ Bloquear todas en Lista Negra (' . (int) $unbanned_cnt . ')</button></form>';
            }
            echo '</div>';
            echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th>IP Atacante</th><th>País / ASN</th><th style="width:95px;">Fallos SSH</th><th>Usuarios probados</th><th style="width:135px;">Acción</th></tr></thead><tbody>';
            foreach ( $scan_res['suspects'] as $s ) {
                $already = bap_ip_in_list( $s['ip'], $cur_bl );
                echo '<tr><td><code>' . esc_html( $s['ip'] ) . '</code></td>';
                echo '<td><strong>' . esc_html( $s['country'] ) . '</strong><br><small>' . esc_html( $s['asn'] ) . '</small></td>';
                echo '<td>' . bap_render_status_badge( $s['count'] . ' fallos', '#d63638' ) . '</td>';
                echo '<td><small><code>' . esc_html( implode( ', ', $s['users'] ) ?: 'N/D' ) . '</code></small></td><td>';
                if ( $already ) {
                    echo '<span style="color:#d63638;font-weight:700;font-size:11px;">En Lista Negra</span>';
                } else {
                    echo '<form method="post" style="display:inline;">';
                    wp_nonce_field( 'bap_ssh_log_action', 'bap_ssh_log_nonce' );
                    echo '<input type="hidden" name="bap_ssh_log_select" value="' . esc_attr( $selected ) . '" />';
                    echo '<input type="hidden" name="bap_target_ssh_ip" value="' . esc_attr( $s['ip'] ) . '" />';
                    echo '<button type="submit" name="bap_ban_ssh_ip" class="button button-small" style="color:#d63638;border-color:#d63638;">+ Lista Negra</button></form>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';

            $ips_for_fw = array_map( fn( $x ) => $x['ip'], array_slice( $scan_res['suspects'], 0, 50 ) );
            $ipset_cmds = "ipset create bap_ssh_ban hash:ip -exist\niptables -C INPUT -m set --match-set bap_ssh_ban src -j DROP 2>/dev/null || iptables -I INPUT -m set --match-set bap_ssh_ban src -j DROP\n";
            foreach ( $ips_for_fw as $fip ) {
                if ( filter_var( $fip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                    $ipset_cmds .= "ipset add bap_ssh_ban " . $fip . " -exist\n";
                }
            }
            echo '<details style="margin-top:16px;background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:4px;padding:12px 16px;">';
            echo '<summary style="cursor:pointer;font-weight:700;">Recomendacion de bloqueo a nivel de Servidor / Kernel (ipset, Fail2ban, UFW / nftables)</summary>';
            echo '<p class="description" style="margin:8px 0;">Bloquear aqui solo impide el acceso a WordPress. Para cortar por completo el acceso a toda la maquina (SSH, web, correo) a nivel de kernel:</p>';
            echo '<p style="margin:8px 0 4px;"><strong>1. Bloqueo masivo instantaneo en kernel con <code>ipset + iptables</code> (con las IPs detectadas arriba):</strong></p>';
            echo '<pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;max-height:180px;">' . esc_html( trim( $ipset_cmds ) ) . '</pre>';
            echo '<p style="margin:10px 0 4px;"><strong>2. Bloqueo automatico permanente con <code>Fail2ban</code> (Debian / Ubuntu):</strong></p>';
            echo '<pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;">apt update &amp;&amp; apt install -y fail2ban
cat &lt;&lt; "EOF" &gt; /etc/fail2ban/jail.d/sshd-hardening.local
[sshd]
enabled  = true
port     = ssh
backend  = systemd
maxretry = 3
findtime = 10m
bantime  = 7d
EOF
systemctl enable --now fail2ban &amp;&amp; fail2ban-client status sshd</pre>';
            echo '<p style="margin:10px 0 4px;"><strong>3. Alternativa con <code>UFW</code> o bloqueo geografico TCP Wrappers (<code>/etc/hosts.deny</code>):</strong></p>';
            echo '<pre style="background:#1d2327;color:#e5e7eb;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;"># Con UFW (limita a 6 conexiones por 30s al puerto 22):
ufw limit 22/tcp comment "Rate-limit SSH"

# Con nftables nativo (Debian 12/13):
nft add table inet filter
nft add set inet filter ssh_blacklist { type ipv4_addr\; flags interval\; }</pre>';
            echo '</details>';
        }
    }
    echo '</div>';
}