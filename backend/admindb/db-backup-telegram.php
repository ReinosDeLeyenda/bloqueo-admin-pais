<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_generate_sql_gz_backup() {
    global $wpdb;
    $tmp_sql = wp_tempnam( 'bap_db_dump' );
    $fh      = @fopen( $tmp_sql, 'wb' );
    if ( ! $fh ) {
        return new WP_Error( 'tmp_fail', 'No se pudo crear el archivo temporal de copia de seguridad.' );
    }

    fwrite( $fh, "-- Backup WordPress generado por Bloqueo Admin\n-- Fecha: " . current_time( 'mysql' ) . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" );

    $skip_suffixes = [ 'bap_blocked_logs', 'wfhits', 'wfblockediplog', 'actionscheduler_logs' ];
    $tables        = $wpdb->get_col( 'SHOW TABLES' );

    foreach ( (array) $tables as $table ) {
        $low  = strtolower( (string) $table );
        $skip = false;
        foreach ( $skip_suffixes as $suf ) {
            if ( substr( $low, -strlen( $suf ) ) === $suf ) { $skip = true; break; }
        }
        if ( $skip ) { continue; }

        $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
        if ( ! empty( $create ) ) {
            fwrite( $fh, "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n" );
        }

        $offset = 0;
        $batch  = 250;
        while ( true ) {
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $batch, $offset ), ARRAY_A );
            if ( empty( $rows ) ) { break; }

            foreach ( $rows as $row ) {
                $vals = [];
                foreach ( $row as $val ) {
                    if ( null === $val ) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = "'" . esc_sql( (string) $val ) . "'";
                    }
                }
                fwrite( $fh, "INSERT INTO `{$table}` VALUES (" . implode( ',', $vals ) . ");\n" );
            }
            $offset += $batch;
        }
        fwrite( $fh, "\n" );
    }

    fwrite( $fh, "SET FOREIGN_KEY_CHECKS=1;\n" );
    fclose( $fh );

    if ( function_exists( 'gzopen' ) ) {
        $gz_path = $tmp_sql . '.sql.gz';
        $in      = @fopen( $tmp_sql, 'rb' );
        $gz      = @gzopen( $gz_path, 'wb6' );
        if ( $in && $gz ) {
            while ( ! feof( $in ) ) { gzwrite( $gz, fread( $in, 65536 ) ); }
            fclose( $in );
            gzclose( $gz );
            @unlink( $tmp_sql );
            return $gz_path;
        }
    }

    $sql_path = $tmp_sql . '.sql';
    @rename( $tmp_sql, $sql_path );
    return $sql_path;
}

function bap_send_db_backup_to_telegram() {
    $token = trim( (string) get_option( 'bap_tg_bot_token', '' ) );
    $chats = trim( (string) get_option( 'bap_tg_chat_ids', '' ) );
    if ( '' === $token || '' === $chats ) {
        return new WP_Error( 'tg_cfg', 'Configura primero el Bot Token y Chat ID en la pestana Notificaciones.' );
    }

    $file = bap_generate_sql_gz_backup();
    if ( is_wp_error( $file ) ) { return $file; }

    $site     = sanitize_title( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ?: 'wordpress';
    $ext      = ( '.gz' === substr( $file, -3 ) ) ? '.sql.gz' : '.sql';
    $filename = 'backup-' . $site . '-' . wp_date( 'Y-m-d-Hi' ) . $ext;
    $size_str = size_format( filesize( $file ), 2 );
    $caption  = 'Copia de seguridad BD (' . get_bloginfo( 'name' ) . ') - ' . wp_date( 'Y-m-d H:i' ) . ' (' . $size_str . ')';

    $host = 'api' . '.telegram.org';
    $url  = 'https://' . $host . '/bot' . rawurlencode( $token ) . '/sendDocument';
    $raw  = (string) file_get_contents( $file );
    @unlink( $file );

    $last_err = null;
    foreach ( array_unique( array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', $chats ) ) ) ) as $cid ) {
        $boundary = wp_generate_password( 24, false );
        $body     = "--{$boundary}\r\nContent-Disposition: form-data; name=\"chat_id\"\r\n\r\n{$cid}\r\n"
                  . "--{$boundary}\r\nContent-Disposition: form-data; name=\"caption\"\r\n\r\n{$caption}\r\n"
                  . "--{$boundary}\r\nContent-Disposition: form-data; name=\"document\"; filename=\"{$filename}\"\r\nContent-Type: application/gzip\r\n\r\n{$raw}\r\n"
                  . "--{$boundary}--\r\n";

        $res = wp_remote_post( $url, [
            'timeout' => 45,
            'headers' => [ 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
            $last_err = is_wp_error( $res ) ? $res : new WP_Error( 'tg_doc', 'Telegram devolvio HTTP ' . wp_remote_retrieve_response_code( $res ) );
        }
    }

    return null === $last_err ? true : $last_err;
}
add_action( 'admin_init', function() {
    if ( isset( $_POST['bap_download_sql_backup'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_db_backup_action', 'bap_db_backup_nonce' ) ) {
        $file = bap_generate_sql_gz_backup();
        if ( ! is_wp_error( $file ) && file_exists( $file ) ) {
            $site = sanitize_title( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ?: 'wordpress';
            $ext  = ( '.gz' === substr( $file, -3 ) ) ? '.sql.gz' : '.sql';
            bap_send_download_headers( 'backup-' . $site . '-' . wp_date( 'Y-m-d-Hi' ) . $ext, 'application/gzip' );
            readfile( $file );
            @unlink( $file );
            exit;
        }
    }
} );

function bap_render_db_backup_telegram_card() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_send_backup_tg_now'] ) && check_admin_referer( 'bap_db_backup_action', 'bap_db_backup_nonce' ) ) {
        $res    = bap_send_db_backup_to_telegram();
        $notice = is_wp_error( $res )
            ? '<div class="notice notice-error inline"><p><strong>Error:</strong> ' . esc_html( $res->get_error_message() ) . '</p></div>'
            : '<div class="notice notice-success inline"><p><strong>Copia de seguridad (.sql.gz) enviada a Telegram correctamente.</strong></p></div>';
    }
    ?>
    <div class="card" style="max-width:760px;padding:16px 24px;margin-top:24px;">
        <h2>Copia de Seguridad de Base de Datos (.sql.gz)</h2>
        <p class="description">Genera un volcado comprimido de las tablas de WordPress (omitiendo logs de ataques para mantenerlo ligero) y descargalo o envialo directo a tu chat de Telegram.</p>
        <?php echo wp_kses_post( $notice ); ?>
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
            <?php wp_nonce_field( 'bap_db_backup_action', 'bap_db_backup_nonce' ); ?>
            <button type="submit" name="bap_send_backup_tg_now" class="button button-primary">Enviar copia (.sql.gz) a Telegram ahora</button>
            <button type="submit" name="bap_download_sql_backup" class="button button-secondary">Descargar copia (.sql.gz)</button>
        </form>
    </div>
    <?php
}