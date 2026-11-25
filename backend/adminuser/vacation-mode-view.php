<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_vacation_mode_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    $tz     = wp_timezone();

    if ( isset( $_POST['bap_set_vacation'] ) && check_admin_referer( 'bap_vacation_action', 'bap_vacation_nonce' ) ) {
        $uid   = (int) ( $_POST['bap_vac_user_id'] ?? 0 );
        $s_raw = trim( sanitize_text_field( wp_unslash( $_POST['bap_vac_start'] ?? '' ) ) );
        $e_raw = trim( sanitize_text_field( wp_unslash( $_POST['bap_vac_end'] ?? '' ) ) );
        $s_dt  = date_create_immutable( $s_raw, $tz );
        $e_dt  = date_create_immutable( $e_raw, $tz );

        if ( $uid <= 0 || ! get_user_by( 'id', $uid ) ) {
            $notice = '<div class="notice notice-error inline"><p>Selecciona una cuenta de usuario válida.</p></div>';
        } elseif ( ! $s_dt || ! $e_dt || $e_dt->getTimestamp() <= $s_dt->getTimestamp() ) {
            $notice = '<div class="notice notice-error inline"><p>La fecha de fin debe ser posterior a la fecha de inicio.</p></div>';
        } else {
            update_user_meta( $uid, '_bap_vacation_start', $s_dt->getTimestamp() );
            update_user_meta( $uid, '_bap_vacation_end', $e_dt->getTimestamp() );
            bap_destroy_user_sessions_except_current( $uid );
            $u_obj  = get_user_by( 'id', $uid );
            $notice = '<div class="notice notice-success inline"><p><strong>Modo Vacaciones programado</strong> para <code>' . esc_html( $u_obj->user_login ) . '</code>.</p></div>';
        }
    }

    if ( isset( $_POST['bap_cancel_vacation'] ) && check_admin_referer( 'bap_vacation_action', 'bap_vacation_nonce' ) ) {
        $uid = (int) ( $_POST['bap_vac_cancel_uid'] ?? 0 );
        if ( $uid > 0 ) {
            delete_user_meta( $uid, '_bap_vacation_start' );
            delete_user_meta( $uid, '_bap_vacation_end' );
            $notice = '<div class="notice notice-warning inline"><p>Modo Vacaciones cancelado para el usuario.</p></div>';
        }
    }

    $users = get_users( [ 'fields' => [ 'ID', 'user_login', 'display_name' ], 'orderby' => 'login' ] );
    $now   = time();
    $def_s = wp_date( 'Y-m-d\TH:i', $now );
    $def_e = wp_date( 'Y-m-d\TH:i', $now + ( 7 * DAY_IN_SECONDS ) );
    ?>
    <div class="card" style="max-width:960px;margin-top:20px;padding:18px 24px;">
        <h2 style="margin-top:0;">Modo Vacaciones por Cuenta (Bloqueo Temporal)</h2>
        <p class="description">Suspende el acceso de una cuenta específica durante un periodo elegido. Si necesitas hacer un mantenimiento de emergencia, puedes entrar usando la <strong>Llave secreta URL</strong> (con fondo amarillo y duración máxima de <strong>30 minutos</strong>).</p>
        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:14px 0;">
            <?php wp_nonce_field( 'bap_vacation_action', 'bap_vacation_nonce' ); ?>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Cuenta</label>
                <select name="bap_vac_user_id" required>
                    <?php foreach ( $users as $u ) : ?>
                        <option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->user_login . ' (' . $u->display_name . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Desde</label>
                <input type="datetime-local" name="bap_vac_start" value="<?php echo esc_attr( $def_s ); ?>" required />
            </div>
            <div>
                <label style="display:block;font-weight:600;margin-bottom:4px;">Hasta</label>
                <input type="datetime-local" name="bap_vac_end" value="<?php echo esc_attr( $def_e ); ?>" required />
            </div>
            <div><button type="submit" name="bap_set_vacation" class="button button-primary">Activar vacaciones</button></div>
        </form>

        <table class="widefat striped" style="margin-top:10px;">
            <thead><tr><th>Usuario</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Acción</th></tr></thead>
            <tbody>
                <?php
                $has_rows = false;
                foreach ( $users as $u ) :
                    $win = bap_get_user_vacation_window( $u->ID );
                    if ( ! $win ) { continue; }
                    $has_rows = true;
                    $active   = ( $now >= $win['start'] && $now < $win['end'] );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $u->user_login ); ?></strong></td>
                        <td><code><?php echo esc_html( wp_date( 'Y-m-d H:i', $win['start'] ) ); ?></code></td>
                        <td><code><?php echo esc_html( wp_date( 'Y-m-d H:i', $win['end'] ) ); ?></code></td>
                        <td><span style="background:<?php echo $active ? '#d63638' : '#2271b1'; ?>;color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:700;"><?php echo $active ? 'EN VACACIONES' : 'PROGRAMADO'; ?></span></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'bap_vacation_action', 'bap_vacation_nonce' ); ?>
                                <input type="hidden" name="bap_vac_cancel_uid" value="<?php echo (int) $u->ID; ?>" />
                                <button type="submit" name="bap_cancel_vacation" class="button button-small">Cancelar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( ! $has_rows ) : ?><tr><td colspan="5">No hay cuentas en modo vacaciones actualmente.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}