<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_record_admin_activity( $category, $action, $target ) {
    $logs = get_option( 'bap_admin_activity_logs', [] );
    if ( ! is_array( $logs ) ) {
        $logs = [];
    }

    $user    = wp_get_current_user();
    $actor   = ( $user && $user->exists() ) ? $user->user_login : 'Sistema / WP-CLI';
    $ip      = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    $country = ( '' !== $ip && function_exists( 'bap_get_country_by_ip' ) ) ? bap_get_country_by_ip( $ip ) : 'N/D';

    array_unshift( $logs, [
        'time'     => current_time( 'mysql' ),
        'actor'    => $actor,
        'ip'       => $ip ?: '127.0.0.1',
        'country'  => $country,
        'category' => (string) $category,
        'action'   => (string) $action,
        'target'   => mb_substr( (string) $target, 0, 220 ),
    ] );

    if ( count( $logs ) > 500 ) {
        $logs = array_slice( $logs, 0, 500 );
    }

    update_option( 'bap_admin_activity_logs', $logs, false );
}

// 1. Plugins (activación, desactivación y borrado)
add_action( 'activated_plugin', function( $plugin ) {
    bap_record_admin_activity( 'Plugin', 'Activado', $plugin );
}, 10, 1 );

add_action( 'deactivated_plugin', function( $plugin ) {
    bap_record_admin_activity( 'Plugin', 'Desactivado', $plugin );
}, 10, 1 );

add_action( 'deleted_plugin', function( $plugin, $deleted ) {
    if ( $deleted ) {
        bap_record_admin_activity( 'Plugin', 'Eliminado', $plugin );
    }
}, 10, 2 );

// 2. Cambio de tema activo
add_action( 'switch_theme', function( $new_name ) {
    bap_record_admin_activity( 'Tema', 'Tema activado', $new_name );
}, 10, 1 );

// 3. Usuarios (alta, borrado y cambio de rol)
add_action( 'user_register', function( $user_id ) {
    $u = get_user_by( 'id', $user_id );
    if ( $u ) {
        bap_record_admin_activity( 'Usuario', 'Usuario creado', $u->user_login . ' (' . $u->user_email . ')' );
    }
}, 10, 1 );

add_action( 'delete_user', function( $user_id ) {
    $u = get_user_by( 'id', $user_id );
    $label = $u ? $u->user_login . ' (' . $u->user_email . ')' : 'ID #' . (int) $user_id;
    bap_record_admin_activity( 'Usuario', 'Usuario eliminado', $label );
}, 10, 1 );

add_action( 'set_user_role', function( $user_id, $role, $old_roles ) {
    if ( empty( $old_roles ) ) {
        return;
    }
    $u = get_user_by( 'id', $user_id );
    if ( $u ) {
        bap_record_admin_activity( 'Usuario', 'Cambio de rol', $u->user_login . ': ' . implode( ',', (array) $old_roles ) . ' -> ' . $role );
    }
}, 10, 3 );

// 4. Ajustes críticos de WordPress
add_action( 'updated_option', function( $option, $old_val, $new_val ) {
    $watched = [
        'siteurl'            => 'URL de WordPress (siteurl)',
        'home'               => 'URL del Sitio (home)',
        'admin_email'        => 'Email de Administración',
        'users_can_register' => 'Registro público de usuarios',
        'default_role'       => 'Rol por defecto de nuevos usuarios',
    ];
    if ( isset( $watched[ $option ] ) && $old_val !== $new_val ) {
        $old_s = is_scalar( $old_val ) ? (string) $old_val : wp_json_encode( $old_val );
        $new_s = is_scalar( $new_val ) ? (string) $new_val : wp_json_encode( $new_val );
        bap_record_admin_activity( 'Ajustes WP', $watched[ $option ], $old_s . ' -> ' . $new_s );
    }
}, 10, 3 );

function bap_render_activity_logs_subtab() {
    $notice = '';
    if ( isset( $_POST['bap_clear_activity_logs'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_activity_logs_action' ) ) {
        delete_option( 'bap_admin_activity_logs' );
        $notice = '<div class="notice notice-success is-dismissible"><p>Registro de actividad administrativa vaciado.</p></div>';
    }

    $logs = get_option( 'bap_admin_activity_logs', [] );
    if ( ! is_array( $logs ) ) {
        $logs = [];
    }
    ?>
    <?php echo wp_kses_post( $notice ); ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin:10px 0 14px 0;flex-wrap:wrap;gap:10px;">
        <p class="description" style="margin:0;">Auditoría de cambios administrativos críticos (últimos <strong><?php echo count( $logs ); ?></strong> eventos registrados; máximo 500).</p>
        <?php if ( ! empty( $logs ) ) : ?>
            <form method="post" onsubmit="return confirm('¿Vaciar todo el historial de actividad administrativa?');">
                <?php wp_nonce_field( 'bap_activity_logs_action' ); ?>
                <button type="submit" name="bap_clear_activity_logs" class="button button-link-delete">Vaciar actividad</button>
            </form>
        <?php endif; ?>
    </div>

    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:145px;">Fecha y Hora</th>
                <th style="width:130px;">Usuario</th>
                <th style="width:150px;">IP / País</th>
                <th style="width:105px;">Categoría</th>
                <th style="width:170px;">Acción</th>
                <th>Elemento afectado</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr><td colspan="6">Aún no se ha registrado ninguna acción administrativa.</td></tr>
            <?php else : ?>
                <?php foreach ( $logs as $row ) :
                    $cat = (string) ( $row['category'] ?? '' );
                    $bg  = ( 'Usuario' === $cat ) ? '#d63638' : ( ( 'Ajustes WP' === $cat ) ? '#d97706' : ( ( 'Plugin' === $cat ) ? '#2271b1' : '#00a32a' ) );
                ?>
                    <tr>
                        <td><code><?php echo esc_html( $row['time'] ?? '' ); ?></code></td>
                        <td><strong><?php echo esc_html( $row['actor'] ?? '' ); ?></strong></td>
                        <td><code><?php echo esc_html( $row['ip'] ?? '' ); ?></code> (<?php echo esc_html( $row['country'] ?? '' ); ?>)</td>
                        <td><span style="background:<?php echo esc_attr( $bg ); ?>;color:#fff;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:700;"><?php echo esc_html( $cat ); ?></span></td>
                        <td><strong><?php echo esc_html( $row['action'] ?? '' ); ?></strong></td>
                        <td><code><?php echo esc_html( $row['target'] ?? '' ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}