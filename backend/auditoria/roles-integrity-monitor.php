<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_roles_get_current_snapshot() {
    global $wpdb;
    $roles_key = $wpdb->get_blog_prefix() . 'user_roles';
    $roles     = get_option( $roles_key, [] );
    $def_role  = (string) get_option( 'default_role', 'subscriber' );
    $payload   = [
        'roles'        => is_array( $roles ) ? $roles : [],
        'default_role' => $def_role,
    ];
    return [
        'sha256'   => hash( 'sha256', wp_json_encode( $payload ) ),
        'snapshot' => $payload,
        'saved_at' => current_time( 'mysql' ),
    ];
}

function bap_roles_refresh_baseline() {
    $snap = bap_roles_get_current_snapshot();
    update_option( 'bap_roles_baseline_data', $snap, false );
    return $snap;
}

function bap_roles_verify_integrity() {
    $stored = get_option( 'bap_roles_baseline_data', null );
    if ( ! is_array( $stored ) || empty( $stored['sha256'] ) ) {
        $stored = bap_roles_refresh_baseline();
    }

    $cur     = bap_roles_get_current_snapshot();
    $changed = ! hash_equals( (string) $stored['sha256'], (string) $cur['sha256'] );

    $danger_caps = [ 'manage_options', 'edit_plugins', 'install_plugins', 'activate_plugins', 'edit_users', 'create_users', 'delete_users', 'edit_files', 'unfiltered_html' ];
    $escalations = [];

    foreach ( (array) ( $cur['snapshot']['roles'] ?? [] ) as $role_slug => $rdata ) {
        if ( 'administrator' === $role_slug ) { continue; }
        $caps = (array) ( $rdata['capabilities'] ?? [] );
        foreach ( $danger_caps as $dc ) {
            if ( 'unfiltered_html' === $dc && 'editor' === $role_slug && ! is_multisite() ) { continue; }
            if ( ! empty( $caps[ $dc ] ) ) {
                $escalations[] = "{$role_slug} tiene capacidad crítica ({$dc})";
            }
        }
    }

    if ( 'administrator' === ( $cur['snapshot']['default_role'] ?? '' ) ) {
        $escalations[] = 'El rol por defecto de nuevos registros es ADMINISTRATOR';
    }

    return [
        'changed'     => $changed,
        'saved_at'    => (string) ( $stored['saved_at'] ?? 'N/D' ),
        'sha_short'   => substr( (string) $cur['sha256'], 0, 16 ) . '...',
        'escalations' => $escalations,
        'baseline'    => $stored['snapshot'] ?? null,
    ];
}
function bap_render_roles_integrity_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';

    if ( isset( $_POST['bap_approve_roles_baseline'] ) && check_admin_referer( 'bap_roles_mon_action', 'bap_roles_mon_nonce' ) ) {
        bap_roles_refresh_baseline();
        $notice = '<div class="notice notice-success inline"><p><strong>Huella SHA-256 de roles y permisos actualizada.</strong></p></div>';
    }

    if ( isset( $_POST['bap_restore_roles_baseline'] ) && check_admin_referer( 'bap_roles_mon_action', 'bap_roles_mon_nonce' ) ) {
        $st = get_option( 'bap_roles_baseline_data', null );
        if ( is_array( $st ) && is_array( $st['snapshot'] ?? null ) ) {
            global $wpdb;
            update_option( $wpdb->get_blog_prefix() . 'user_roles', (array) $st['snapshot']['roles'] );
            update_option( 'default_role', sanitize_key( (string) ( $st['snapshot']['default_role'] ?? 'subscriber' ) ) );
            $notice = '<div class="notice notice-warning inline"><p><strong>Roles y permisos restaurados</strong> a la copia segura aprobada.</p></div>';
        }
    }

    $res = bap_roles_verify_integrity();
    ?>
    <div id="bap-box-roles" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor de Roles y Capacidades (Anti-Escalada de Privilegios)</h2>
        <p class="description">Vigila mediante huella SHA-256 la tabla de roles (<code>wp_user_roles</code>) y el rol por defecto (<code>default_role</code>) para detectar escaladas de privilegios silenciosas.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:10px 0;">
            <strong>Estado actual:</strong>
            <?php if ( ! $res['changed'] && empty( $res['escalations'] ) ) : ?>
                <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:3px;font-weight:700;font-size:11px;">INTACTO</span>
            <?php else : ?>
                <span style="background:#d63638;color:#fff;padding:2px 8px;border-radius:3px;font-weight:700;font-size:11px;">ALTERADO / REVISAR</span>
            <?php endif; ?>
            &nbsp;|&nbsp; Huella: <code><?php echo esc_html( $res['sha_short'] ); ?></code>
            &nbsp;|&nbsp; Referencia: <code><?php echo esc_html( $res['saved_at'] ); ?></code>
        </p>

        <?php if ( ! empty( $res['escalations'] ) ) : ?>
            <div class="notice notice-error inline"><p><strong>Peligro detectado:</strong> <?php echo esc_html( implode( ' · ', $res['escalations'] ) ); ?></p></div>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_roles_mon_action', 'bap_roles_mon_nonce' ); ?>
            <button type="submit" name="bap_approve_roles_baseline" class="button button-secondary">Aprobar / Actualizar huella de roles</button>
            <?php if ( $res['changed'] ) : ?>
                <button type="submit" name="bap_restore_roles_baseline" class="button button-primary" onclick="return confirm('Restaurar los roles y permisos a la referencia guardada?');">Restaurar roles originales en 1 clic</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
}