<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_map_shortcodes_to_plugins() {
    global $shortcode_tags;
    $plugins_dir = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
    $map         = [];
    foreach ( (array) $shortcode_tags as $tag => $cb ) {
        try {
            $ref = null;
            if ( is_string( $cb ) && false !== strpos( $cb, '::' ) ) {
                list( $cls, $mth ) = explode( '::', $cb, 2 );
                if ( method_exists( $cls, $mth ) ) { $ref = new ReflectionMethod( $cls, $mth ); }
            } elseif ( is_string( $cb ) && function_exists( $cb ) ) {
                $ref = new ReflectionFunction( $cb );
            } elseif ( is_array( $cb ) && isset( $cb[0], $cb[1] ) && method_exists( $cb[0], $cb[1] ) ) {
                $ref = new ReflectionMethod( $cb[0], $cb[1] );
            } elseif ( is_object( $cb ) && $cb instanceof Closure ) {
                $ref = new ReflectionFunction( $cb );
            }
            if ( $ref ) {
                $file = wp_normalize_path( (string) $ref->getFileName() );
                if ( 0 === strpos( $file, $plugins_dir ) ) {
                    $rel    = substr( $file, strlen( $plugins_dir ) );
                    $folder = strtok( $rel, '/' );
                    if ( '' !== $folder && 'bloqueo-admin-pais' !== $folder ) {
                        $map[ $folder ][] = (string) $tag;
                    }
                }
            }
        } catch ( Throwable $e ) {}
    }
    return $map;
}

function bap_scan_plugins_content_usage( $force = false ) {
    $cache_key = 'bap_plugin_usage_scan_v1';
    if ( ! $force ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) { return $cached; }
    }
    global $wpdb;
    if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $all_plugins = get_plugins();
    $sc_map      = bap_map_shortcodes_to_plugins();
    $posts       = $wpdb->get_results( "SELECT ID, post_title, post_type, post_status, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type NOT IN ('revision','nav_menu_item','attachment') AND (post_content LIKE '%[%' OR post_content LIKE '%<!-- wp:%')" );
    $usage       = [];

    foreach ( $all_plugins as $pfile => $pdata ) {
        $folder = dirname( $pfile );
        if ( '.' === $folder || 'bloqueo-admin-pais' === $folder ) { continue; }
        $tags    = array_unique( $sc_map[ $folder ] ?? [] );
        $matches = [];
        foreach ( (array) $posts as $p ) {
            $found_tokens = [];
            foreach ( $tags as $t ) {
                if ( false !== stripos( $p->post_content, '[' . $t ) ) {
                    $found_tokens[] = '[' . $t . ']';
                }
            }
            if ( preg_match_all( '/<!--\s+wp:(' . preg_quote( $folder, '/' ) . '\/[a-z0-9_-]+)/i', $p->post_content, $mb ) ) {
                foreach ( array_unique( $mb[1] ) as $blk ) { $found_tokens[] = 'Bloque: ' . $blk; }
            }
            if ( ! empty( $found_tokens ) ) {
                $matches[] = [
                    'id'     => (int) $p->ID,
                    'title'  => $p->post_title ?: '(Sin titulo #' . $p->ID . ')',
                    'type'   => $p->post_type,
                    'status' => $p->post_status,
                    'tokens' => array_unique( $found_tokens ),
                ];
            }
        }
        $usage[ $pfile ] = [
            'name'    => (string) ( $pdata['Name'] ?? $pfile ),
            'folder'  => $folder,
            'active'  => is_plugin_active( $pfile ),
            'tags'    => $tags,
            'matches' => $matches,
        ];
    }
    set_transient( $cache_key, $usage, 30 * MINUTE_IN_SECONDS );
    return $usage;
}

add_action( 'save_post', function() { delete_transient( 'bap_plugin_usage_scan_v1' ); } );

// Advertencia preventiva en Plugins -> Plugins instalados si el plugin tiene shortcodes/bloques en uso
add_filter( 'plugin_action_links', function( $actions, $plugin_file ) {
    if ( ! is_admin() || empty( $actions['deactivate'] ) ) { return $actions; }
    $usage = bap_scan_plugins_content_usage( false );
    $info  = $usage[ $plugin_file ] ?? null;
    if ( is_array( $info ) && ! empty( $info['matches'] ) ) {
        $cnt  = count( $info['matches'] );
        $msg  = esc_js( sprintf( "ATENCION: Este plugin esta incrustado mediante shortcodes o bloques en %d pagina(s)/entrada(s). Si lo desactivas, se mostrara codigo sin procesar en tu web.\n\n¿Seguro que quieres desactivarlo?", $cnt ) );
        $actions['deactivate'] = preg_replace( '/<a\s+/i', '<a onclick="return confirm(\'' . $msg . '\');" ', $actions['deactivate'], 1 );
        $scan_url = admin_url( 'admin.php?page=bap-auditoria&subtab=escaneres#bap-box-plugin-usage' );
        $actions['bap_in_use'] = '<a href="' . esc_url( $scan_url ) . '" style="color:#b45309;font-weight:600;" title="Ver en que paginas esta incrustado">En uso (' . $cnt . ')</a>';
    }
    return $actions;
}, 20, 2 );

function bap_render_plugin_usage_scanner_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $replace_notice = '';
    if ( isset( $_POST['bap_bulk_replace_shortcode'] ) && check_admin_referer( 'bap_plugin_usage_action', 'bap_plugin_usage_nonce' ) ) {
        $search_sc  = trim( (string) wp_unslash( $_POST['bap_sc_search'] ?? '' ) );
        $replace_sc = trim( (string) wp_unslash( $_POST['bap_sc_replace'] ?? '' ) );
        $post_ids   = array_filter( array_map( 'absint', (array) ( $_POST['bap_sc_post_ids'] ?? [] ) ) );
        if ( '' !== $search_sc && ! empty( $post_ids ) ) {
            $updated_cnt = 0;
            foreach ( $post_ids as $pid ) {
                $post_obj = get_post( $pid );
                if ( ! $post_obj || false === strpos( $post_obj->post_content, $search_sc ) ) { continue; }
                $new_body = str_replace( $search_sc, $replace_sc, $post_obj->post_content );
                $res_upd  = wp_update_post( [ 'ID' => $pid, 'post_content' => wp_slash( $new_body ) ], true );
                if ( ! is_wp_error( $res_upd ) ) { $updated_cnt++; }
            }
            delete_transient( 'bap_plugin_usage_scan_v1' );
            $replace_notice = '<div class="notice notice-success inline"><p><strong>Reemplazo completado:</strong> Se actualizo <code>' . esc_html( $search_sc ) . '</code> en ' . (int) $updated_cnt . ' pagina(s) (revision de WordPress guardada automaticamente).</p></div>';
        }
    }
    $force = ! empty( $replace_notice ) || ( isset( $_POST['bap_run_plugin_usage_scan'] ) && check_admin_referer( 'bap_plugin_usage_action', 'bap_plugin_usage_nonce' ) );
    $usage = bap_scan_plugins_content_usage( $force );
    $filter_plugin = isset( $_POST['bap_filter_plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['bap_filter_plugin_slug'] ) ) : '';

    echo '<div id="bap-box-plugin-usage" class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">';
    echo '<h2 style="margin-top:0;">Buscador de Plugins Incrustados en Paginas (Shortcodes y Bloques)</h2>';
    echo '<p class="description">Localiza exactamente en que paginas, entradas o tipos de contenido esta incrustado cada plugin (formularios, galerias, tablas, reproductores) para poder editarlas antes de desactivar o sustituir el plugin.</p>';
    echo wp_kses_post( $replace_notice );
    echo '<form method="post" style="margin:14px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
    wp_nonce_field( 'bap_plugin_usage_action', 'bap_plugin_usage_nonce' );
    echo '<select name="bap_filter_plugin_slug"><option value="">-- Todos los plugins con incrustaciones --</option>';
    foreach ( $usage as $pf => $inf ) {
        $cnt = count( $inf['matches'] );
        if ( $cnt > 0 || $pf === $filter_plugin ) {
            echo '<option value="' . esc_attr( $pf ) . '"' . selected( $filter_plugin, $pf, false ) . '>' . esc_html( $inf['name'] . ' (' . $cnt . ' paginas)' ) . '</option>';
        }
    }
    echo '</select>';
    echo '<button type="submit" name="bap_run_plugin_usage_scan" class="button button-primary">Escanear paginas y shortcodes</button></form>';

    $shown = 0;
    foreach ( $usage as $pf => $inf ) {
        if ( '' !== $filter_plugin && $pf !== $filter_plugin ) { continue; }
        if ( empty( $inf['matches'] ) ) { continue; }
        $shown++;
        echo '<div style="margin-top:12px;border:1px solid #dcdcde;border-radius:4px;padding:12px 14px;background:#fff;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
        echo '<strong>' . esc_html( $inf['name'] ) . '</strong> <code>' . esc_html( implode( ', ', $inf['tags'] ) ?: 'Bloques Gutenberg' ) . '</code>';
        echo bap_render_status_badge( count( $inf['matches'] ) . ' pagina(s)', '#2271b1' );
        echo '</div><table class="widefat striped"><thead><tr><th>Titulo</th><th style="width:90px;">Tipo</th><th style="width:180px;">Etiqueta detectada</th><th style="width:140px;">Acciones</th></tr></thead><tbody>';
        foreach ( $inf['matches'] as $m ) {
            $edit_u = get_edit_post_link( $m['id'], '' );
            $view_u = get_permalink( $m['id'] );
            echo '<tr><td><strong>' . esc_html( $m['title'] ) . '</strong> <small>(#' . (int) $m['id'] . ')</small></td>';
            echo '<td><code>' . esc_html( $m['type'] ) . '</code></td>';
            echo '<td><code>' . esc_html( implode( ', ', $m['tokens'] ) ) . '</code></td>';
            echo '<td>';
            if ( $edit_u ) { echo '<a href="' . esc_url( $edit_u ) . '" target="_blank" class="button button-small">Editar</a> '; }
            if ( $view_u ) { echo '<a href="' . esc_url( $view_u ) . '" target="_blank" class="button button-small">Ver</a>'; }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        $ids_csv   = array_map( fn( $x ) => (int) $x['id'], $inf['matches'] );
        $first_tok = (string) ( $inf['matches'][0]['tokens'][0] ?? '' );
        echo '<form method="post" style="margin-top:10px;padding-top:10px;border-top:1px dashed #dcdcde;display:flex;gap:8px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm(\'¿Reemplazar este shortcode/etiqueta en todas las paginas listadas arriba? Se guardara una revision automatica de WordPress.\');">';
        wp_nonce_field( 'bap_plugin_usage_action', 'bap_plugin_usage_nonce' );
        foreach ( $ids_csv as $hid ) { echo '<input type="hidden" name="bap_sc_post_ids[]" value="' . (int) $hid . '" />'; }
        echo '<span style="font-size:12px;font-weight:600;">Reemplazo masivo:</span>';
        echo '<input type="text" name="bap_sc_search" value="' . esc_attr( $first_tok ) . '" placeholder="[shortcode_viejo]" class="regular-text" style="max-width:210px;" required />';
        echo '<span>&rarr;</span>';
        echo '<input type="text" name="bap_sc_replace" value="" placeholder="[shortcode_nuevo] (o vacio para borrar)" class="regular-text" style="max-width:230px;" />';
        echo '<button type="submit" name="bap_bulk_replace_shortcode" class="button button-secondary">Reemplazar en ' . count( $ids_csv ) . ' pagina(s)</button>';
        echo '</form></div>';
    }
    if ( 0 === $shown ) {
        echo '<p style="color:#008a20;font-weight:600;">No se han encontrado shortcodes ni bloques incrustados para el filtro seleccionado.</p>';
    }
    echo '</div>';
}