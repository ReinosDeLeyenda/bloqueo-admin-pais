<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_all_active_user_sessions() {
    $users  = get_users( [
        'meta_key'     => 'session_tokens',
        'meta_compare' => 'EXISTS',
        'fields'       => [ 'ID', 'user_login', 'user_email', 'display_name' ],
    ] );
    $list   = [];
    $now    = time();
    $cur_id = get_current_user_id();
    $cur_tk = wp_get_session_token();

    foreach ( $users as $u ) {
        $tokens = get_user_meta( $u->ID, 'session_tokens', true );
        if ( ! is_array( $tokens ) || empty( $tokens ) ) {
            continue;
        }
        $wp_u  = get_userdata( $u->ID );
        $roles = $wp_u ? implode( ', ', (array) $wp_u->roles ) : '';

        foreach ( $tokens as $verifier => $sess ) {
            $exp = (int) ( $sess['expiration'] ?? 0 );
            if ( $exp > 0 && $exp < $now ) {
                continue;
            }
            $ip      = (string) ( $sess['ip'] ?? 'N/D' );
            $country = ( '' !== $ip && 'N/D' !== $ip && function_exists( 'bap_get_country_by_ip' ) ) ? bap_get_country_by_ip( $ip ) : 'N/D';
            $asn     = ( '' !== $ip && 'N/D' !== $ip && function_exists( 'bap_get_asn_by_ip' ) ) ? bap_get_asn_by_ip( $ip ) : 'N/D';

            $list[] = [
                'user_id'    => (int) $u->ID,
                'user_login' => (string) $u->user_login,
                'user_email' => (string) $u->user_email,
                'roles'      => $roles,
                'verifier'   => (string) $verifier,
                'ip'         => $ip,
                'country'    => $country,
                'asn'        => $asn,
                'ua'         => (string) ( $sess['ua'] ?? '' ),
                'login'      => (int) ( $sess['login'] ?? 0 ),
                'expiration' => $exp,
                'is_current' => ( (int) $u->ID === $cur_id && '' !== $cur_tk && hash_equals( $verifier, hash( 'sha256', $cur_tk ) ) ),
            ];
        }
    }

    usort( $list, fn( $a, $b ) => $b['login'] <=> $a['login'] );
    return $list;
}

function bap_destroy_single_session_verifier( $user_id, $verifier ) {
    $tokens = get_user_meta( $user_id, 'session_tokens', true );
    if ( is_array( $tokens ) && isset( $tokens[ $verifier ] ) ) {
        unset( $tokens[ $verifier ] );
        if ( empty( $tokens ) ) {
            delete_user_meta( $user_id, 'session_tokens' );
        } else {
            update_user_meta( $user_id, 'session_tokens', $tokens );
        }
        return true;
    }
    return false;
}

function bap_handle_sessions_post_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }

    if ( isset( $_POST['bap_kill_session'] ) && check_admin_referer( 'bap_sessions_action', 'bap_sessions_nonce' ) ) {
        $uid = (int) ( $_POST['bap_sess_uid'] ?? 0 );
        $ver = sanitize_text_field( wp_unslash( $_POST['bap_sess_verifier'] ?? '' ) );
        if ( $uid > 0 && '' !== $ver && bap_destroy_single_session_verifier( $uid, $ver ) ) {
            return '<div class="notice notice-success is-dismissible"><p><strong>Sesión cerrada remotamente.</strong></p></div>';
        }
    }

    if ( isset( $_POST['bap_kill_all_other_sessions'] ) && check_admin_referer( 'bap_sessions_action', 'bap_sessions_nonce' ) ) {
        $cur_id = get_current_user_id();
        $users  = get_users( [ 'meta_key' => 'session_tokens', 'meta_compare' => 'EXISTS', 'fields' => 'ID' ] );
        $count  = 0;

        foreach ( $users as $uid ) {
            $uid = (int) $uid;
            if ( $uid === $cur_id ) {
                if ( class_exists( 'WP_Session_Tokens' ) && wp_get_session_token() ) {
                    WP_Session_Tokens::get_instance( $uid )->destroy_others( wp_get_session_token() );
                }
            } else {
                delete_user_meta( $uid, 'session_tokens' );
                $count++;
            }
        }
        return '<div class="notice notice-warning is-dismissible"><p><strong>Todas las demás sesiones activas han sido expulsadas</strong> (' . (int) $count . ' cuenta(s) desconectadas).</p></div>';
    }

    return '';
}

// Alerta de seguridad si se crea un nuevo Administrador o se asciende un usuario a Administrador
function bap_notify_new_admin_created( $user_id, $context_label ) {
    $user = get_user_by( 'id', $user_id );
    if ( ! ( $user instanceof WP_User ) || ! in_array( 'administrator', (array) $user->roles, true ) ) {
        return;
    }

    $actor     = wp_get_current_user();
    $actor_str = ( $actor && $actor->exists() ) ? $actor->user_login : 'Sistema / Código / MySQL';
    $ip        = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : 'N/D';
    $site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $time      = current_time( 'mysql' );

    $subject = "[{$site}] ALERTA: {$context_label} ({$user->user_login})";
    $body    = "Se ha detectado un nuevo usuario con privilegios de ADMINISTRADOR en {$site}:\n\n"
             . "• Evento: {$context_label}\n"
             . "• Usuario afectado: {$user->user_login} ({$user->user_email})\n"
             . "• Ejecutado por: {$actor_str}\n"
             . "• IP de origen: {$ip}\n"
             . "• Fecha / Hora: {$time}\n";

    $recipients = function_exists( 'bap_get_notify_recipients' ) ? bap_get_notify_recipients() : [ get_option( 'admin_email' ) ];
    if ( function_exists( 'bap_send_mail' ) ) {
        bap_send_mail( $recipients, $subject, $body );
    } else {
        wp_mail( $recipients, $subject, $body );
    }

    if ( function_exists( 'bap_send_telegram_rich_article' ) ) {
        $e    = fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        $rich = "<h2>🚨 Nuevo Administrador Detectado — {$e( $site )}</h2>"
              . "<p><b>{$e( $context_label )}</b></p>"
              . "<table><tr><th>Detalle</th><th>Valor</th></tr>"
              . "<tr><td><b>Usuario</b></td><td><b>{$e( $user->user_login )}</b> ({$e( $user->user_email )})</td></tr>"
              . "<tr><td><b>Realizado por</b></td><td>{$e( $actor_str )}</td></tr>"
              . "<tr><td><b>IP</b></td><td><code>{$e( $ip )}</code></td></tr>"
              . "<tr><td><b>Fecha / Hora</b></td><td><code>{$e( $time )}</code></td></tr></table>";
        bap_send_telegram_rich_article( $rich, strip_tags( $rich ) );
    }
}

add_action( 'user_register', function( $user_id ) {
    bap_notify_new_admin_created( $user_id, 'Nuevo usuario Administrador registrado' );
}, 20 );

add_action( 'set_user_role', function( $user_id, $role, $old_roles ) {
    if ( 'administrator' === $role && ! in_array( 'administrator', (array) $old_roles, true ) ) {
        bap_notify_new_admin_created( $user_id, 'Usuario ascendido al rol Administrador' );
    }
}, 20, 3 );
function bap_render_active_sessions_box() {
    $notice   = bap_handle_sessions_post_actions();
    $sessions = bap_get_all_active_user_sessions();
    ?>
    <div class="card" style="max-width:960px;margin-top:20px;padding:18px 24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
            <div>
                <h2 style="margin:0;">Sesiones Activas en Vivo (<?php echo count( $sessions ); ?>)</h2>
                <p class="description" style="margin:4px 0 0 0;">Supervisa todos los usuarios conectados actualmente y expulsa cualquier sesión sospechosa al instante.</p>
            </div>
            <?php if ( count( $sessions ) > 1 ) : ?>
                <form method="post" onsubmit="return confirm('¿Expulsar todas las demás sesiones activas excepto la tuya actual?');">
                    <?php wp_nonce_field( 'bap_sessions_action', 'bap_sessions_nonce' ); ?>
                    <button type="submit" name="bap_kill_all_other_sessions" class="button button-secondary" style="color:#d63638;border-color:#d63638;">Cerrar todas las demás sesiones</button>
                </form>
            <?php endif; ?>
        </div>

        <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div style="overflow-x:auto;">
            <table class="widefat striped" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th>Usuario / Rol</th>
                        <th>IP / País</th>
                        <th>ASN / Proveedor</th>
                        <th>Inicio / Caduca</th>
                        <th>Navegador</th>
                        <th style="width:110px;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sessions ) ) : ?>
                        <tr><td colspan="6">No hay sesiones activas registradas.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $sessions as $s ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $s['user_login'] ); ?></strong>
                                    <?php if ( $s['is_current'] ) : ?>
                                        <span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;">TÚ</span>
                                    <?php endif; ?>
                                    <br><small style="color:#646970;"><?php echo esc_html( $s['roles'] ); ?></small>
                                </td>
                                <td style="white-space:nowrap;">
                                    <code><?php echo esc_html( $s['ip'] ); ?></code><br>
                                    <strong><?php echo esc_html( $s['country'] ); ?></strong>
                                </td>
                                <td><small><?php echo esc_html( $s['asn'] ); ?></small></td>
                                <td style="white-space:nowrap;font-size:11px;">
                                    Inicio: <code><?php echo $s['login'] > 0 ? esc_html( wp_date( 'd/m H:i', $s['login'] ) ) : 'N/D'; ?></code><br>
                                    Expira: <code><?php echo $s['expiration'] > 0 ? esc_html( wp_date( 'd/m H:i', $s['expiration'] ) ) : 'N/D'; ?></code>
                                </td>
                                <td><small style="color:#50575e;word-break:break-word;"><?php echo esc_html( mb_substr( $s['ua'] ?: 'No registrado', 0, 85 ) ); ?></small></td>
                                <td>
                                    <?php if ( $s['is_current'] ) : ?>
                                        <span style="color:#646970;font-size:12px;">Sesión actual</span>
                                    <?php else : ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field( 'bap_sessions_action', 'bap_sessions_nonce' ); ?>
                                            <input type="hidden" name="bap_sess_uid" value="<?php echo (int) $s['user_id']; ?>" />
                                            <input type="hidden" name="bap_sess_verifier" value="<?php echo esc_attr( $s['verifier'] ); ?>" />
                                            <button type="submit" name="bap_kill_session" class="button button-small" style="color:#d63638;">Expulsar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
