<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_content_guard_collect_hashes() {
    global $wpdb;
    $front_id = (int) get_option( 'page_on_front', 0 );
    $rows     = $wpdb->get_results(
        "SELECT ID, post_title, post_type, post_status, post_content
         FROM `{$wpdb->posts}`
         WHERE post_status = 'publish' AND post_type IN ('page', 'post')
         ORDER BY (post_type = 'page') DESC, ID ASC
         LIMIT 80",
        ARRAY_A
    );

    $hashes = [];
    foreach ( (array) $rows as $r ) {
        $id   = (int) $r['ID'];
        $sig  = hash( 'sha256', $r['post_title'] . '|' . $r['post_status'] . '|' . $r['post_content'] );
        $name = ( $id === $front_id ) ? '[Portada] ' . $r['post_title'] : $r['post_title'];
        $hashes[ $id ] = [
            'id'     => $id,
            'title'  => (string) ( '' !== trim( $name ) ? $name : '(Sin titulo #' . $id . ')' ),
            'type'   => (string) $r['post_type'],
            'sha256' => $sig,
        ];
    }
    return $hashes;
}

function bap_content_guard_refresh_baseline() {
    $items = bap_content_guard_collect_hashes();
    $snap  = [
        'items'    => $items,
        'count'    => count( $items ),
        'saved_at' => current_time( 'mysql' ),
    ];
    update_option( 'bap_content_guard_baseline', $snap, false );
    return $snap;
}

function bap_content_guard_verify() {
    $stored = get_option( 'bap_content_guard_baseline', null );
    if ( ! is_array( $stored ) || ! is_array( $stored['items'] ?? null ) ) {
        $stored = bap_content_guard_refresh_baseline();
    }

    $base_items = (array) $stored['items'];
    $cur_items  = bap_content_guard_collect_hashes();
    $modified   = [];
    $deleted    = [];

    foreach ( $base_items as $id => $old ) {
        if ( ! isset( $cur_items[ $id ] ) ) {
            $deleted[] = $old['title'] ?? ( 'ID #' . $id );
        } elseif ( ! hash_equals( (string) ( $old['sha256'] ?? '' ), (string) ( $cur_items[ $id ]['sha256'] ?? '' ) ) ) {
            $modified[] = [
                'id'    => (int) $id,
                'title' => (string) ( $cur_items[ $id ]['title'] ?? '' ),
                'type'  => (string) ( $cur_items[ $id ]['type'] ?? 'page' ),
            ];
        }
    }

    return [
        'changed'  => ( ! empty( $modified ) || ! empty( $deleted ) ),
        'modified' => $modified,
        'deleted'  => $deleted,
        'checked'  => count( $base_items ),
        'saved_at' => (string) ( $stored['saved_at'] ?? 'N/D' ),
    ];
}
function bap_render_content_defacement_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';

    if ( isset( $_POST['bap_approve_content_baseline'] ) && check_admin_referer( 'bap_content_guard_action', 'bap_content_guard_nonce' ) ) {
        $saved  = bap_content_guard_refresh_baseline();
        $notice = '<div class="notice notice-success inline"><p><strong>Huella de contenidos actualizada:</strong> Se han indexado <strong>' . (int) $saved['count'] . '</strong> páginas/entradas como referencia limpia.</p></div>';
    }

    $res = bap_content_guard_verify();
    ?>
    <div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Monitor Anti-Defacement de Páginas y Contenidos (SHA-256)</h2>
        <p class="description">Vigila mediante huella SHA-256 la portada y tus páginas/entradas principales para detectar alteraciones silenciosas, iframes ocultos o desfiguraciones (<em>defacement</em>).</p>
        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:10px 0;">
            <strong>Estado actual:</strong>
            <?php echo bap_render_status_badge( $res['changed'] ? 'CONTENIDO ALTERADO' : 'INTACTO', $res['changed'] ? '#d63638' : '#00a32a' ); ?>
            &nbsp;|&nbsp; Vigilados: <code><?php echo (int) $res['checked']; ?></code>
            &nbsp;|&nbsp; Referencia: <code><?php echo esc_html( $res['saved_at'] ); ?></code>
        </p>

        <?php if ( $res['changed'] ) : ?>
            <table class="widefat striped" style="margin:10px 0;">
                <thead><tr><th style="width:140px;">Estado</th><th>Página / Entrada afectada</th><th style="width:120px;">Acción</th></tr></thead>
                <tbody>
                    <?php foreach ( $res['modified'] as $m ) : ?>
                        <tr>
                            <td><?php echo bap_render_status_badge( 'MODIFICADA', '#d97706' ); ?></td>
                            <td><strong><?php echo esc_html( $m['title'] ); ?></strong> <code>(#<?php echo (int) $m['id']; ?>)</code></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( (int) $m['id'] ) ); ?>" class="button button-small">Revisar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ( $res['deleted'] as $del_title ) : ?>
                        <tr>
                            <td><?php echo bap_render_status_badge( 'ELIMINADA', '#d63638' ); ?></td>
                            <td><strong><?php echo esc_html( $del_title ); ?></strong></td>
                            <td><small style="color:#646970;">No publicada</small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field( 'bap_content_guard_action', 'bap_content_guard_nonce' ); ?>
            <button type="submit" name="bap_approve_content_baseline" class="button button-secondary">Aprobar / Actualizar huella de contenidos</button>
        </form>
    </div>
    <?php
}