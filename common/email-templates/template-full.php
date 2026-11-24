<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/html-parts.php';
bap_email_html_header( $site_name, $data, $is_test, 'COMPLETA' );
bap_email_html_table_and_footer( $data['grouped'], 'Detalle de intentos bloqueados (Todos los eventos agrupados)', $stats_url );
