<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_waf_flatten_values( $data, $depth = 0 ) {
    if ( $depth > 4 ) {
        return [];
    }
    $out = [];
    if ( is_array( $data ) ) {
        foreach ( $data as $k => $v ) {
            if ( is_string( $k ) && '' !== $k ) {
                $out[] = $k;
            }
            $out = array_merge( $out, bap_waf_flatten_values( $v, $depth + 1 ) );
        }
    } elseif ( is_scalar( $data ) ) {
        $str = (string) $data;
        if ( '' !== $str ) {
            $out[] = mb_substr( $str, 0, 8192 );
        }
    }
    return $out;
}

function bap_waf_detect_attack_signature( $raw_value ) {
    $raw = (string) $raw_value;
    if ( '' === trim( $raw ) ) {
        return '';
    }

    if ( false !== strpos( $raw, "\0" ) || false !== stripos( $raw, '%00' ) ) {
        return 'Null-Byte Injection (%00)';
    }

    if ( preg_match( '/%25(?:2e%252e|3c|3e|27|22|00)/i', $raw ) ) {
        return 'Evasión por doble codificación URL';
    }

    $val = rawurldecode( $raw );

    $rules = [
        'SQL Injection (SQLi)'          => '/(?:\bunion\s+(?:all\s+)?select\b|\bselect\b.{1,60}\bfrom\s+(?:information_schema|mysql\.|wp_users)|\b(?:sleep|benchmark)\s*\(\s*\d+|\b(?:drop|truncate)\s+table\b|\'\s*(?:or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+)/i',
        'Cross-Site Scripting (XSS)'    => '/(?:<\s*script\b|javascript\s*:|vbscript\s*:|\bon(?:error|load|mouseover|focus|click)\s*=|<\s*iframe\b[^>]*src\s*=)/i',
        'Path Traversal / LFI'          => '/(?:\.\.\/|\.\.\\\\|\/etc\/passwd\b|\/etc\/shadow\b|\/proc\/self\/environ|php:\/\/(?:input|filter)|expect:\/\/)/i',
        'Inyección PHP / Object Inject' => '/(?:<\?php\b|\b(?:base64_decode|gzinflate|shell_exec|passthru|system|eval)\s*\(|O:\d+:"[a-zA-Z0-9_\\\\]+":\d+:\{)/i',
    ];

    foreach ( $rules as $label => $regex ) {
        if ( preg_match( $regex, $val ) ) {
            return $label;
        }
    }

    return '';
}

function bap_waf_detect_dangerous_upload( array $files ) {
    $bad_exts = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'sh', 'bash', 'exe', 'bat', 'cmd', 'jsp', 'asp', 'aspx', 'cgi', 'pl' ];

    foreach ( $files as $file_entry ) {
        $names = $file_entry['name'] ?? '';
        foreach ( bap_waf_flatten_values( $names ) as $fn ) {
            $fn_clean = strtolower( trim( (string) $fn ) );
            if ( '' === $fn_clean ) {
                continue;
            }
            if ( false !== strpos( $fn_clean, "\0" ) ) {
                return 'Subida maliciosa (Null-byte en nombre de archivo)';
            }
            $parts = explode( '.', $fn_clean );
            if ( count( $parts ) > 1 ) {
                array_shift( $parts );
                foreach ( $parts as $ext ) {
                    if ( in_array( $ext, $bad_exts, true ) ) {
                        return 'Subida bloqueada (extensión ejecutable .' . $ext . ')';
                    }
                }
            }
        }
    }

    return '';
}

add_action( 'init', function() {
    if ( '1' !== (string) get_option( 'bap_waf_enabled', '0' ) ) {
        return;
    }
    if ( is_user_logged_in() || wp_doing_cron() ) {
        return;
    }

    $ip = bap_get_user_ip();
    if ( bap_is_frontend_request_exempt( $ip, false ) ) {
        return;
    }

    $threat  = '';
    $req_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

    // 1. Verificar longitud excesiva de URI (> 2048 caracteres)
    if ( strlen( $req_uri ) > 2048 ) {
        $threat = 'URI excesivamente larga (> 2048 caracteres)';
    }

    // 2. Revisar subida de archivos ejecutables
    if ( '' === $threat && ! empty( $_FILES ) ) {
        $threat = bap_waf_detect_dangerous_upload( $_FILES );
    }

    // 3. Revisar URI, HTTP_REFERER, parámetros GET y cuerpo POST
    if ( '' === $threat ) {
        $samples   = [];
        $samples[] = $req_uri;
        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $samples[] = (string) wp_unslash( $_SERVER['HTTP_REFERER'] );
        }
        if ( ! empty( $_GET ) ) {
            $samples = array_merge( $samples, bap_waf_flatten_values( wp_unslash( $_GET ) ) );
        }
        if ( ! empty( $_POST ) ) {
            $samples = array_merge( $samples, bap_waf_flatten_values( wp_unslash( $_POST ) ) );
        }

        foreach ( $samples as $item ) {
            $hit = bap_waf_detect_attack_signature( $item );
            if ( '' !== $hit ) {
                $threat = $hit;
                break;
            }
        }
    }

    if ( '' === $threat ) {
        return;
    }

    $reason  = 'Web: WAF (' . $threat . ')';
    $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
    bap_log_blocked_attempt( $ip, $country, $reason );

    if ( '1' === (string) get_option( 'bap_waf_autoban', '0' ) ) {
        bap_add_ip_to_blacklist( $ip, true );
    }

    $msg = (string) get_option( 'bap_web_block_message', 'Petición bloqueada por el cortafuegos de seguridad (WAF).' );
    if ( function_exists( 'bap_render_custom_403_page' ) ) {
        bap_render_custom_403_page( 'Petición bloqueada (WAF)', $msg, $ip, $country, $reason );
    }
    wp_die( '<h1>Acceso denegado</h1><p>' . esc_html( $msg ) . '</p>', 'WAF Bloqueo', [ 'response' => 403 ] );
}, 1 );