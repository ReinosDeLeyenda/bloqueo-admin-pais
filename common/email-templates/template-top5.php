<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/html-parts.php';
$top5 = array_slice( $data['grouped'], 0, 5, true );
bap_email_html_header( $site_name, $data, $is_test, 'TOP 5' );
bap_email_html_table_and_footer( $top5, 'Top 5 intentos bloqueados con mayor frecuencia', $stats_url );
