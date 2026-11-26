<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_db_schema_get_current_tables() {
    global $wpdb;
    $tables = $wpdb->get_col( 'SHOW TABLES' );
    $list   = array_values( array_unique( array_map( 'strval', (array) $tables ) ) );
    sort( $list );
    return $list;
}

function bap_db_schema_refresh_baseline() {
    $tables = bap_db_schema_get_current_tables();
    $snap   = [
        'tables'   => $tables,
        'count'    => count( $tables ),
        'saved_at' => current_time( 'mysql' ),
    ];
    update_option( 'bap_db_schema_baseline', $snap, false );
    return $snap;
}

function bap_db_schema_verify() {
    $stored = get_option( 'bap_db_schema_baseline', null );
    if ( ! is_array( $stored ) || ! is_array( $stored['tables'] ?? null ) ) {
        $stored = bap_db_schema_refresh_baseline();
    }

    $base_tables = (array) $stored['tables'];
    $cur_tables  = bap_db_schema_get_current_tables();

    $added   = array_values( array_diff( $cur_tables, $base_tables ) );
    $removed = array_values( array_diff( $base_tables, $cur_tables ) );

    return [
        'changed'  => ( ! empty( $added ) || ! empty( $removed ) ),
        'added'    => $added,
        'removed'  => $removed,
        'total'    => count( $cur_tables ),
        'saved_at' => (string) ( $stored['saved_at'] ?? 'N/D' ),
    ];
}
function bap_render_db_schema_monitor_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';

    if ( isset( $_POST['bap_approve_db_schema'] ) && check_admin_referer( 'bap_db_schema_action', 'bap_db_schema_nonce' ) ) {
        $saved  = bap_db_schema_refresh_baseline();
        $notice = '<div class="notice notice-success inline"><p><strong>Esquema de base de datos aprobado:</strong> Se han registrado <strong>' . (int) $saved['count'] . '</strong> tablas como referencia limpia.</p></div>';
    }

    $res = bap_db_schema_verify();
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor de Tablas en Base de Datos (Schema &amp; Rogue Tables Watcher)</h2>
        <p class="description">Vigila la lista de tablas de MySQL (<code>SHOW TABLES</code>) para detectar la creacion de tablas ocultas maliciosas o la eliminacion inesperada de tablas del sitio.</p>
        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:10px 0;">
            <strong>Estado actual:</strong>
            <?php echo bap_render_status_badge( $res['changed'] ? 'CAMBIOS DETECTADOS' : 'INTACTO', $res['changed'] ? '#d63638' : '#00a32a' ); ?>
            &nbsp;|&nbsp; Tablas actuales: <code><?php echo (int) $res['total']; ?></code>
            &nbsp;|&nbsp; Referencia: <code><?php echo esc_html( $res['saved_at'] ); ?></code>
        </p>

        <?php if ( $res['changed'] ) : ?>
            <table class="widefat striped" style="margin:10px 0;">
                <thead><tr><th style="width:140px;">Cambio</th><th>Tabla MySQL</th></tr></thead>
                <tbody>
                    <?php foreach ( $res['added'] as $tbl ) : ?>
                        <tr><td><?php echo bap_render_status_badge( 'TABLA NUEVA', '#d97706' ); ?></td><td><code><?php echo esc_html( $tbl ); ?></code></td></tr>
                    <?php endforeach; ?>
                    <?php foreach ( $res['removed'] as $tbl ) : ?>
                        <tr><td><?php echo bap_render_status_badge( 'ELIMINADA', '#d63638' ); ?></td><td><code><?php echo esc_html( $tbl ); ?></code></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field( 'bap_db_schema_action', 'bap_db_schema_nonce' ); ?>
            <button type="submit" name="bap_approve_db_schema" class="button button-secondary">Aprobar / Actualizar lista de tablas actual</button>
        </form>
    </div>
    <?php
}