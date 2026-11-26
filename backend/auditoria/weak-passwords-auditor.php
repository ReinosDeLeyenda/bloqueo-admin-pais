<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_get_common_weak_passwords( WP_User $u, $site_slug ) {
    $login = strtolower( (string) $u->user_login );
    $mail  = strtolower( (string) strtok( (string) $u->user_email, '@' ) );
    $base  = [
        '123456', '12345678', '123456789', '1234567890', '12345', '111111', '000000',
        'password', 'password1', 'password123', 'pass1234', 'qwerty', 'qwerty123',
        'admin', 'admin123', 'admin1234', 'administrator', 'root', 'toor', 'test', 'demo',
        'wordpress', 'wp123456', 'changeme', 'welcome', 'welcome1', 'abc123', 'letmein',
        'master', 'dragon', 'shadow', 'sunshine', 'princess', 'football', 'iloveyou',
        'contraseña', 'contrasena', 'clave123', 'usuario', 'acceso', 'invitado',
        $login, $login . '1', $login . '123', $login . '2024', $login . '2025', $login . '2026',
        $mail, $mail . '123', $site_slug, $site_slug . '123', $site_slug . '2025', $site_slug . '2026',
    ];
    return array_values( array_unique( array_filter( $base, fn( $p ) => is_string( $p ) && '' !== $p ) ) );
}

function bap_audit_privileged_users_weak_passwords() {
    $users = get_users( [
        'role__in' => [ 'administrator', 'editor', 'author', 'shop_manager' ],
        'number'   => 150,
    ] );
    $site_slug = sanitize_title( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
    $weak      = [];

    foreach ( $users as $u ) {
        if ( ! ( $u instanceof WP_User ) ) { continue; }
        foreach ( bap_get_common_weak_passwords( $u, $site_slug ) as $cand ) {
            if ( wp_check_password( $cand, $u->user_pass, $u->ID ) ) {
                $weak[] = [
                    'id'     => (int) $u->ID,
                    'login'  => (string) $u->user_login,
                    'email'  => (string) $u->user_email,
                    'roles'  => implode( ', ', (array) $u->roles ),
                    'reason' => ( $cand === strtolower( $u->user_login ) ) ? 'Coincide con su nombre de usuario' : 'Coincide con clave comun / predecible',
                ];
                break;
            }
        }
    }

    return [ 'checked' => count( $users ), 'weak' => $weak ];
}
function bap_render_weak_passwords_auditor_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    $res    = null;

    if ( isset( $_POST['bap_force_reset_weak_uid'] ) && check_admin_referer( 'bap_weak_pwd_action', 'bap_weak_pwd_nonce' ) ) {
        $uid = (int) $_POST['bap_force_reset_weak_uid'];
        if ( $uid > 0 && ( $u = get_user_by( 'id', $uid ) ) ) {
            update_user_meta( $uid, '_bap_pwd_force_expire', '1' );
            bap_destroy_user_sessions_except_current( $uid );
            $notice = '<div class="notice notice-warning inline"><p>Cambio de clave obligatorio activado para <code>' . esc_html( $u->user_login ) . '</code>.</p></div>';
        }
    }

    if ( isset( $_POST['bap_run_weak_pwd_audit'] ) && check_admin_referer( 'bap_weak_pwd_action', 'bap_weak_pwd_nonce' ) ) {
        $res = bap_audit_privileged_users_weak_passwords();
    }
    ?>
    <div id="bap-box-weak-pwd" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Auditoria de Contraseñas Debiles en Usuarios</h2>
        <p class="description">Analiza en local los hashes de Administradores, Editores y Autores para detectar claves triviales, predecibles o identicas al nombre de usuario.</p>
        <?php echo wp_kses_post( $notice ); ?>
        <form method="post" style="margin:14px 0;">
            <?php wp_nonce_field( 'bap_weak_pwd_action', 'bap_weak_pwd_nonce' ); ?>
            <button type="submit" name="bap_run_weak_pwd_audit" class="button button-primary">Auditar contraseñas de usuarios ahora</button>
        </form>
        <?php if ( is_array( $res ) ) : ?>
            <?php if ( empty( $res['weak'] ) ) : ?>
                <div class="notice notice-success inline"><p><strong>Sin contraseñas debiles detectadas.</strong> Se han verificado <strong><?php echo (int) $res['checked']; ?></strong> cuenta(s) con privilegios.</p></div>
            <?php else : ?>
                <div class="notice notice-error inline"><p><strong>Atencion: Se han encontrado <?php echo count( $res['weak'] ); ?> cuenta(s) con contraseña debil:</strong></p></div>
                <table class="widefat striped" style="margin-top:10px;">
                    <thead><tr><th>Usuario</th><th>Rol</th><th>Diagnostico</th><th style="width:180px;">Accion</th></tr></thead>
                    <tbody>
                        <?php foreach ( $res['weak'] as $w ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $w['login'] ); ?></strong><br><small><?php echo esc_html( $w['email'] ); ?></small></td>
                                <td><code><?php echo esc_html( $w['roles'] ); ?></code></td>
                                <td><?php echo bap_render_status_badge( $w['reason'], '#d63638' ); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'bap_weak_pwd_action', 'bap_weak_pwd_nonce' ); ?>
                                        <input type="hidden" name="bap_force_reset_weak_uid" value="<?php echo (int) $w['id']; ?>" />
                                        <button type="submit" class="button button-small button-primary">Forzar cambio de clave</button>
                                    </form>
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