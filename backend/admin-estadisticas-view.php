<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'admin-estadisticas-charts.php';
require_once plugin_dir_path( __FILE__ ) . 'admin-estadisticas-toolbar.php';
$stats_base_url = admin_url( 'admin.php?page=bap-admin-estadisticas' );
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-estadisticas' ); ?>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php bap_render_estadisticas_summary_cards( $total_blocks, $top_countries, $top_asns, $top_uris, $stats_base_url ); ?>
    <?php bap_render_estadisticas_charts( $table, $total_blocks, $top_countries, $top_uris, $stats_base_url ); ?>
    <?php bap_render_estadisticas_toolbar( $filter_uri, $filter_q, $stats_base_url ); ?>
    <?php require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-table.php'; ?>
</div>
