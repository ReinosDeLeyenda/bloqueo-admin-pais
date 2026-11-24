<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/html-parts.php';
$top10 = array_slice( $data['grouped'], 0, 10, true );
bap_email_html_header( $site_name, $data, $is_test, 'TOP 10' );
bap_email_html_table_and_footer( $top10, 'Top 10 intentos bloqueados con mayor frecuencia', $stats_url );
