<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_scan_privileged_admins() {
    global $wpdb;
    $cap_key = $wpdb->get_blog_prefix() . 'capabilities';

    $meta_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, meta_value FROM `{$wpdb->usermeta}`
             WHERE meta_key = %s AND (meta_value LIKE %s OR meta_value LIKE %s)",
            $cap_key,
            '%administrator%',
            '%manage_options%'
        ),
        ARRAY_A
    );

    $admin_ids = [];
    foreach ( (array) $meta_rows as $r ) {
        $uid  = (int) ( $r['user_id'] ?? 0 );
        $caps = maybe_unserialize( $r['meta_value'] ?? '' );
        if ( $uid > 0 && is_array( $caps ) && ( ! empty( $caps['administrator'] ) || ! empty( $caps['manage_options'] ) ) ) {
            $admin_ids[ $uid ] = $caps;
        }
    }

    $admins     = [];
    $suspicious = 0;
    $bad_names  = [ 'wp_support', 'wpsupport', 'sysadmin', 'admin_backup', 'wp_admin', 'support_user', 'testadmin' ];

    foreach ( $admin_ids as $uid => $caps ) {
        $u = get_user_by( 'id', $uid );
        if ( ! ( $u instanceof WP_User ) ) {
            $suspicious++;
            $admins[] = [
                'id'         => $uid,
                'login'      => '(Usuario huérfano sin fila en wp_users)',
                'email'      => 'N/D',
                'registered' => 'N/D',
                'has_2fa'    => false,
                'suspicious' => true,
                'flag'       => 'CRÍTICO: Privilegios de admin en usermeta sin usuario válido',
            ];
            continue;
        }

        $m_2fa   = (string) get_user_meta( $uid, '_bap_2fa_method', true );
        $has_2fa = in_array( $m_2fa, [ 'totp', 'email' ], true );
        $flags   = [];

        if ( in_array( strtolower( $u->user_login ), $bad_names, true ) ) {
            $flags[] = 'Nombre genérico sospechoso';
        }
        if ( empty( $caps['administrator'] ) && ! empty( $caps['manage_options'] ) ) {
            $flags[] = 'Capacidad manage_options directa sin rol administrator';
        }
        if ( ! is_email( $u->user_email ) ) {
            $flags[] = 'Email inválido o vacío';
        }

        $is_susp = ! empty( $flags );
        if ( $is_susp ) {
            $suspicious++;
        }

        $admins[] = [
            'id'         => $uid,
            'login'      => $u->user_login,
            'email'      => $u->user_email ?: 'Sin email',
            'registered' => $u->user_registered,
            'has_2fa'    => $has_2fa,
            'suspicious' => $is_susp,
            'flag'       => $is_susp ? implode( ' · ', $flags ) : 'Cuenta verificada',
        ];
    }

    return [
        'total'      => count( $admins ),
        'suspicious' => $suspicious,
        'admins'     => $admins,
    ];
}