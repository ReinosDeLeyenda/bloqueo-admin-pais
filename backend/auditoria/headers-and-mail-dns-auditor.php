<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }



function bap_detect_local_mail_server() {
    if ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) ) { return 'windows'; }
    $sm_bin = @readlink( '/usr/sbin/sendmail' );
    $sm_low = strtolower( (string) $sm_bin );
    if ( false !== strpos( $sm_low, 'postfix' ) || @file_exists( '/usr/sbin/postfix' ) || @is_dir( '/etc/postfix' ) ) { return 'postfix'; }
    if ( false !== strpos( $sm_low, 'exim' ) || @file_exists( '/usr/sbin/exim4' ) || @is_dir( '/etc/exim4' ) ) { return 'exim4'; }
    if ( @file_exists( '/etc/mail/sendmail.cf' ) || @file_exists( '/usr/lib/sm.bin/sendmail' ) ) { return 'sendmail'; }
    if ( false !== strpos( $sm_low, 'msmtp' ) || false !== strpos( $sm_low, 'ssmtp' ) || false !== strpos( $sm_low, 'nullmailer' ) ) { return 'relay'; }
    return 'unknown';
}

function bap_audit_mail_dns_records() {
    $domain = bap_get_site_root_domain();
    if ( '' === $domain || in_array( $domain, [ 'localhost', '127.0.0.1' ], true ) ) {
        return new WP_Error( 'local_dom', 'Entorno local: no se pueden consultar registros DNS públicos.' );
    }

    $checks = [];

    // 1. Registro SPF
    $txt_recs = @dns_get_record( $domain, DNS_TXT );
    $spf_val  = '';
    foreach ( (array) $txt_recs as $rec ) {
        $txt = (string) ( $rec['txt'] ?? '' );
        if ( 0 === stripos( trim( $txt ), 'v=spf1' ) ) {
            $spf_val = trim( $txt );
            break;
        }
    }
    $checks[] = [
        'type'   => 'SPF (TXT)',
        'status' => '' !== $spf_val ? 'ok' : 'error',
        'value'  => '' !== $spf_val ? $spf_val : 'No encontrado — Los correos de WordPress pueden marcarse como SPAM.',
    ];

    // 2. Registro DMARC
    $dmarc_recs = @dns_get_record( '_dmarc.' . $domain, DNS_TXT );
    $dmarc_val  = '';
    foreach ( (array) $dmarc_recs as $rec ) {
        $txt = (string) ( $rec['txt'] ?? '' );
        if ( 0 === stripos( trim( $txt ), 'v=DMARC1' ) ) {
            $dmarc_val = trim( $txt );
            break;
        }
    }
    $checks[] = [
        'type'   => 'DMARC (_dmarc)',
        'status' => '' !== $dmarc_val ? 'ok' : 'warning',
        'value'  => '' !== $dmarc_val ? $dmarc_val : 'No configurado — Recomendado añadir registro TXT en _dmarc.' . $domain,
    ];

    // 3. Registros MX
    $mx_hosts = [];
    if ( @getmxrr( $domain, $mx_hosts ) && ! empty( $mx_hosts ) ) {
        $checks[] = [
            'type'   => 'MX (Correo)',
            'status' => 'ok',
            'value'  => implode( ', ', array_slice( $mx_hosts, 0, 3 ) ),
        ];
    } else {
        $checks[] = [
            'type'   => 'MX (Correo)',
            'status' => 'warning',
            'value'  => 'Sin registros MX detectados en ' . $domain,
        ];
    }

    // 4. Registro DKIM (selectores habituales)
    $dkim_found = '';
    foreach ( [ 'default', 'mail', 'dkim', 'selector1', 'google', 's1', 'k1' ] as $sel ) {
        $dk_recs = @dns_get_record( $sel . '.' . '_domainkey.' . $domain, DNS_TXT );
        foreach ( (array) $dk_recs as $rec ) {
            $txt = (string) ( $rec['txt'] ?? '' );
            if ( false !== stripos( $txt, 'v=DKIM1' ) || false !== stripos( $txt, 'p=' ) ) {
                $dkim_found = $sel . '.' . '_domainkey (Activo)';
                break 2;
            }
        }
    }
    $dkim_hint = 'No detectado en selectores comunes — Gmail envia a SPAM si tienes DMARC p=reject sin firma DKIM (instala OpenDKIM o usa SMTP autenticado).';
    $checks[]  = [
        'type'   => 'DKIM (_domainkey)',
        'status' => '' !== $dkim_found ? 'ok' : 'error',
        'value'  => '' !== $dkim_found ? $dkim_found : $dkim_hint,
    ];

    // 5. DNS Inverso / PTR (rDNS) de la IP del servidor
    $srv_ip = @gethostbyname( $domain );
    if ( filter_var( $srv_ip, FILTER_VALIDATE_IP ) ) {
        $ptr     = rtrim( strtolower( (string) @gethostbyaddr( $srv_ip ) ), '.' );
        $ptr_ok  = ( '' !== $ptr && $ptr !== $srv_ip && false !== strpos( $ptr, $domain ) );
        $ptr_msg = $ptr_ok
            ? $srv_ip . ' -> ' . $ptr
            : $srv_ip . ' -> ' . ( $ptr ?: 'Sin PTR' ) . ' — Gmail penaliza si el rDNS (PTR) en tu proveedor VPS no apunta a ' . $domain;
        $checks[] = [
            'type'   => 'PTR / rDNS (IP)',
            'status' => $ptr_ok ? 'ok' : 'warning',
            'value'  => $ptr_msg,
        ];
    }

    return [ 'domain' => $domain, 'records' => $checks, 'mta' => bap_detect_local_mail_server(), 'dkim_ok' => ( '' !== $dkim_found ) ];
}

function bap_audit_live_http_headers() {
    $res = wp_remote_head( home_url( '/' ), [ 'timeout' => 8, 'sslverify' => false ] );
    if ( is_wp_error( $res ) ) {
        return $res;
    }

    $raw_headers = wp_remote_retrieve_headers( $res );
    $headers     = [];
    if ( is_object( $raw_headers ) && method_exists( $raw_headers, 'getAll' ) ) {
        $headers = array_change_key_case( $raw_headers->getAll(), CASE_LOWER );
    } elseif ( is_array( $raw_headers ) ) {
        $headers = array_change_key_case( $raw_headers, CASE_LOWER );
    }

    $wanted = [
        'strict-transport-security' => 'HSTS (HTTPS estricto)',
        'x-frame-options'           => 'X-Frame-Options (Anti-Clickjacking)',
        'x-content-type-options'    => 'X-Content-Type-Options (nosniff)',
        'referrer-policy'           => 'Referrer-Policy',
        'permissions-policy'        => 'Permissions-Policy',
        'content-security-policy'   => 'Content-Security-Policy (CSP)',
    ];

    $rows = [];
    foreach ( $wanted as $key => $label ) {
        $val    = $headers[ $key ] ?? '';
        $val_s  = is_array( $val ) ? implode( '; ', $val ) : (string) $val;
        $rows[] = [
            'label'   => $label,
            'present' => '' !== trim( $val_s ),
            'value'   => '' !== trim( $val_s ) ? mb_substr( $val_s, 0, 120 ) : 'Ausente',
        ];
    }

    return $rows;
}