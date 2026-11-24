<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_2fa_generate_secret( $length = 16 ) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $secret .= $alphabet[ random_int( 0, 31 ) ];
    }
    return $secret;
}

function bap_2fa_base32_decode( $secret ) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = strtoupper( preg_replace( '/[^A-Z2-7]/', '', (string) $secret ) );
    $bits     = '';
    for ( $i = 0, $len = strlen( $secret ); $i < $len; $i++ ) {
        $val = strpos( $alphabet, $secret[ $i ] );
        if ( false !== $val ) {
            $bits .= str_pad( decbin( $val ), 5, '0', STR_PAD_LEFT );
        }
    }
    $binary = '';
    for ( $i = 0, $len = strlen( $bits ); $i + 8 <= $len; $i += 8 ) {
        $binary .= chr( bindec( substr( $bits, $i, 8 ) ) );
    }
    return $binary;
}

function bap_2fa_verify_totp( $secret, $code, $window = 1 ) {
    $code = preg_replace( '/\s+/', '', (string) $code );
    if ( ! preg_match( '/^\d{6}$/', $code ) ) {
        return false;
    }
    $key       = bap_2fa_base32_decode( $secret );
    $timestamp = floor( time() / 30 );

    for ( $i = -$window; $i <= $window; $i++ ) {
        $counter = pack( 'N*', 0 ) . pack( 'N*', $timestamp + $i );
        $hash    = hash_hmac( 'sha1', $counter, $key, true );
        $offset  = ord( substr( $hash, -1 ) ) & 0x0F;
        $value   = (
            ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 ) |
            ( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
            ( ord( $hash[ $offset + 3 ] ) & 0xFF )
        ) % 1000000;

        if ( hash_equals( str_pad( (string) $value, 6, '0', STR_PAD_LEFT ), $code ) ) {
            return true;
        }
    }
    return false;
}

function bap_2fa_generate_backup_codes( $user_id ) {
    $plain  = [];
    $hashed = [];
    for ( $i = 0; $i < 8; $i++ ) {
        $code     = strtoupper( bin2hex( random_bytes( 4 ) ) );
        $plain[]  = substr( $code, 0, 4 ) . '-' . substr( $code, 4, 4 );
        $hashed[] = wp_hash_password( $code );
    }
    update_user_meta( $user_id, '_bap_2fa_backup_codes', $hashed );
    return $plain;
}

function bap_2fa_verify_backup_code( $user_id, $input ) {
    $clean  = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $input ) );
    $stored = get_user_meta( $user_id, '_bap_2fa_backup_codes', true );
    if ( '' === $clean || ! is_array( $stored ) ) {
        return false;
    }
    foreach ( $stored as $idx => $hash ) {
        if ( wp_check_password( $clean, $hash ) ) {
            unset( $stored[ $idx ] );
            update_user_meta( $user_id, '_bap_2fa_backup_codes', array_values( $stored ) );
            return true;
        }
    }
    return false;
}

function bap_2fa_send_email_code( $user ) {
    $code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    set_transient( 'bap_2fa_mail_' . $user->ID, [
        'hash'    => wp_hash_password( $code ),
        'expires' => time() + 600,
    ], 600 );

    $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $subject = sprintf( '[%s] Tu código de verificación: %s', $site, $code );
    $body    = "Hola {$user->display_name},\n\n"
             . "Tu código de verificación en dos pasos es:\n\n"
             . "    {$code}\n\n"
             . "Caduca en 10 minutos.\n";

    wp_mail( $user->user_email, $subject, $body );
}

function bap_2fa_user_requires_2fa( $user ) {
    if ( '1' !== (string) get_option( 'bap_2fa_enabled', '0' ) ) {
        return false;
    }
    $method = get_user_meta( $user->ID, '_bap_2fa_method', true );
    if ( in_array( $method, [ 'totp', 'email' ], true ) ) {
        return true;
    }
    if ( '1' === (string) get_option( 'bap_2fa_enforce_admins', '0' ) && user_can( $user, 'manage_options' ) ) {
        return true;
    }
    return false;
}

function bap_2fa_is_trusted_device( $user_id ) {
    if ( '1' !== (string) get_option( 'bap_2fa_remember_device', '1' ) ) {
        return false;
    }
    $cookie_name = 'bap_2fa_trust_' . $user_id;
    if ( empty( $_COOKIE[ $cookie_name ] ) ) {
        return false;
    }
    $raw_cookie = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
    $tokens     = get_user_meta( $user_id, '_bap_2fa_trusted_devices', true );
    if ( ! is_array( $tokens ) ) {
        return false;
    }
    $now = time();
    foreach ( $tokens as $item ) {
        if ( ! empty( $item['hash'] ) && ! empty( $item['expires'] ) && $now < (int) $item['expires'] ) {
            if ( hash_equals( $item['hash'], hash( 'sha256', $raw_cookie . AUTH_SALT ) ) ) {
                return true;
            }
        }
    }
    return false;
}

function bap_2fa_trust_current_device( $user_id ) {
    $raw_token = bin2hex( random_bytes( 24 ) );
    $expires   = time() + ( 30 * DAY_IN_SECONDS );
    $tokens    = get_user_meta( $user_id, '_bap_2fa_trusted_devices', true );
    if ( ! is_array( $tokens ) ) {
        $tokens = [];
    }
    $now      = time();
    $tokens   = array_values( array_filter( $tokens, fn( $t ) => ! empty( $t['expires'] ) && $now < (int) $t['expires'] ) );
    $tokens[] = [
        'hash'    => hash( 'sha256', $raw_token . AUTH_SALT ),
        'expires' => $expires,
    ];
    if ( count( $tokens ) > 10 ) {
        $tokens = array_slice( $tokens, -10 );
    }
    update_user_meta( $user_id, '_bap_2fa_trusted_devices', $tokens );

    if ( ! headers_sent() ) {
        setcookie( 'bap_2fa_trust_' . $user_id, $raw_token, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }
}
