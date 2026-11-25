<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_rogue_admin_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    $cur_id = get_current_user_id();

    if ( isset( $_POST['bap_demote_admin_uid'] ) && check_admin_referer( 'bap_rogue_admin_action', 'bap_rogue_admin_nonce' ) ) {
        $uid = (int) $_POST['bap_demote_admin_uid'];
        if ( $uid > 0 && $uid !== $cur_id && ( $u = get_user_by( 'id', $uid ) ) ) {
            $u->set_role( 'subscriber' );
            bap_destroy_user_sessions_except_current( $uid );
            $notice = '<div class="notice notice-warning inline"><p>El usuario <code>' . esc_html( $u->user_login ) . '</code> ha sido degradado a Suscriptor y sus sesiones cerradas.</p></div>';
        }
    }

    $scan = bap_scan_privileged_admins();
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Auditoría de Usuarios Administradores (Rogue Admin Detector)</h2>
        <p class="description">Inspecciona directamente <code>wp_usermeta</code> para detectar todas las cuentas con rol <strong>Administrador</strong> o capacidad <code>manage_options</code> (incluidos administradores ocultos inyectados por SQL).</p>
        <?php echo wp_kses_post( $notice ); ?>

        <table class="widefat striped" style="margin-top:12px;">
            <thead>
                <tr>
                    <th>ID / Usuario</th>
                    <th>Email</th>
                    <th>Alta</th>
                    <th>2FA</th>
                    <th>Diagnóstico</th>
                    <th style="width:140px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $scan['admins'] as $a ) : ?>
                    <tr>
                        <td>
                            <code>#<?php echo (int) $a['id']; ?></code> <strong><?php echo esc_html( $a['login'] ); ?></strong>
                            <?php if ( (int) $a['id'] === $cur_id ) : ?>
                                <span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;">TÚ</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $a['email'] ); ?></td>
                        <td><code><?php echo esc_html( $a['registered'] ); ?></code></td>
                        <td>
                            <?php if ( $a['has_2fa'] ) : ?>
                                <span style="color:#008a20;font-weight:700;">Activo</span>
                            <?php else : ?>
                                <span style="color:#d97706;font-weight:600;">Sin 2FA</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $a['suspicious'] ) : ?>
                                <?php echo bap_render_status_badge( $a['flag'], '#d63638' ); ?>
                            <?php else : ?>
                                <span style="color:#008a20;"><?php echo esc_html( $a['flag'] ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( (int) $a['id'] !== $cur_id ) : ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('¿Quitar privilegios de administrador a <?php echo esc_js( $a['login'] ); ?>?');">
                                    <?php wp_nonce_field( 'bap_rogue_admin_action', 'bap_rogue_admin_nonce' ); ?>
                                    <input type="hidden" name="bap_demote_admin_uid" value="<?php echo (int) $a['id']; ?>" />
                                    <button type="submit" class="button button-small" style="color:#d63638;">Degradar</button>
                                </form>
                            <?php else : ?>
                                <span style="color:#646970;font-size:11px;">Cuenta actual</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}