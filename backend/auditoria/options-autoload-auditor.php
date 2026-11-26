<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_audit_wp_options_autoload() {
    global $wpdb;
    $in_sql     = "'yes','on','auto','auto-on'";
    $total_size = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM `{$wpdb->options}` WHERE autoload IN ({$in_sql})" );
    $total_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->options}` WHERE autoload IN ({$in_sql})" );
    $exp_trans  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `{$wpdb->options}` WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
        $wpdb->esc_like( '_transient_timeout_' ) . '%',
        time()
    ) );

    $top_rows = $wpdb->get_results(
        "SELECT option_name, LENGTH(option_value) AS bytes, autoload
         FROM `{$wpdb->options}`
         WHERE autoload IN ({$in_sql})
         ORDER BY bytes DESC
         LIMIT 20",
        ARRAY_A
    );

    return [
        'total_bytes'   => $total_size,
        'total_kb'      => round( $total_size / 1024, 1 ),
        'total_rows'    => $total_rows,
        'expired_trans' => $exp_trans,
        'top_options'   => is_array( $top_rows ) ? $top_rows : [],
        'bloated'       => ( $total_size > 819200 ),
    ];
}

function bap_purge_expired_wp_transients() {
    global $wpdb;
    $now     = time();
    $expired = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 2000",
        $wpdb->esc_like( '_transient_timeout_' ) . '%',
        $now
    ) );

    $deleted = 0;
    foreach ( (array) $expired as $t_key ) {
        $slug = substr( (string) $t_key, strlen( '_transient_timeout_' ) );
        if ( '' !== $slug && delete_transient( $slug ) ) {
            $deleted++;
        }
    }
    return $deleted;
}

function bap_render_options_autoload_auditor_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';

    if ( isset( $_POST['bap_purge_exp_transients'] ) && check_admin_referer( 'bap_opt_autoload_action', 'bap_opt_autoload_nonce' ) ) {
        $cnt    = bap_purge_expired_wp_transients();
        $notice = '<div class="notice notice-success inline"><p><strong>Limpieza completada:</strong> Se han eliminado <strong>' . (int) $cnt . '</strong> transient(s) caducado(s) de <code>wp_options</code>.</p></div>';
    }

    if ( isset( $_POST['bap_disable_opt_autoload'] ) && check_admin_referer( 'bap_opt_autoload_action', 'bap_opt_autoload_nonce' ) ) {
        $opt       = sanitize_text_field( wp_unslash( $_POST['bap_target_opt_name'] ?? '' ) );
        $core_lock = [ 'siteurl', 'home', 'blogname', 'active_plugins', 'stylesheet', 'template', 'rewrite_rules', 'cron' ];
        if ( '' !== $opt && ! in_array( $opt, $core_lock, true ) ) {
            global $wpdb;
            $wpdb->update( $wpdb->options, [ 'autoload' => 'no' ], [ 'option_name' => $opt ] );
            wp_cache_delete( 'alloptions', 'options' );
            $notice = '<div class="notice notice-warning inline"><p>Autoload desactivado para <code>' . esc_html( $opt ) . '</code>.</p></div>';
        }
    }

    $data      = bap_audit_wp_options_autoload();
    $core_lock = [ 'siteurl', 'home', 'blogname', 'active_plugins', 'stylesheet', 'template', 'rewrite_rules', 'cron' ];
    ?>
    <div id="bap-box-autoload" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Auditor y Limpiador de <code>wp_options</code> (Autoload Bloat y Persistencia)</h2>
        <p class="description">Analiza el peso en memoria de las opciones cargadas automaticamente en cada peticion (<code>autoload</code>) para detectar acumulacion de basura o datos ocultos de malware.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center;margin:12px 0;">
            <div>
                <strong>Peso total Autoload:</strong>
                <?php echo bap_render_status_badge( $data['total_kb'] . ' KB (' . $data['total_rows'] . ' opciones)', $data['bloated'] ? '#d63638' : '#00a32a' ); ?>
            </div>
            <div>
                <strong>Transients caducados:</strong> <code><?php echo (int) $data['expired_trans']; ?></code>
            </div>
            <form method="post" style="margin-left:auto;">
                <?php wp_nonce_field( 'bap_opt_autoload_action', 'bap_opt_autoload_nonce' ); ?>
                <button type="submit" name="bap_purge_exp_transients" class="button button-secondary" <?php disabled( 0 === $data['expired_trans'] ); ?>>Purgar transients caducados (<?php echo (int) $data['expired_trans']; ?>)</button>
            </form>
        </div>

        <table class="widefat striped" style="margin-top:10px;">
            <thead><tr><th>Opcion (<code>option_name</code>)</th><th style="width:100px;">Peso</th><th style="width:100px;">Autoload</th><th style="width:160px;">Accion</th></tr></thead>
            <tbody>
                <?php foreach ( $data['top_options'] as $r ) :
                    $name    = (string) $r['option_name'];
                    $kb      = round( ( (int) $r['bytes'] ) / 1024, 2 );
                    $is_lock = in_array( $name, $core_lock, true ) || 0 === strpos( $name, $GLOBALS['wpdb']->prefix . 'user_roles' );
                ?>
                    <tr>
                        <td style="word-break:break-all;"><code><?php echo esc_html( $name ); ?></code></td>
                        <td><strong><?php echo esc_html( $kb ); ?> KB</strong></td>
                        <td><code><?php echo esc_html( $r['autoload'] ); ?></code></td>
                        <td>
                            <?php if ( ! $is_lock ) : ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Desactivar autoload para esta opcion?');">
                                    <?php wp_nonce_field( 'bap_opt_autoload_action', 'bap_opt_autoload_nonce' ); ?>
                                    <input type="hidden" name="bap_target_opt_name" value="<?php echo esc_attr( $name ); ?>" />
                                    <button type="submit" name="bap_disable_opt_autoload" class="button button-small">Desactivar autoload</button>
                                </form>
                            <?php else : ?>
                                <small style="color:#646970;">Protegida (Core)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
