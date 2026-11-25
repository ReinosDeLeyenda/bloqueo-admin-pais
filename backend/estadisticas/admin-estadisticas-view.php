<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'admin-estadisticas-charts.php';
require_once plugin_dir_path( __FILE__ ) . 'admin-estadisticas-toolbar.php';
$stats_base_url = add_query_arg(
    array_filter( [
        'page'        => 'bap-admin-estadisticas',
        'filter_type' => 'all' !== $filter_type ? $filter_type : '',
    ] ),
    admin_url( 'admin.php' )
);
?>
<div class="wrap">
    <h1>Control de Acceso al Panel</h1>
    <?php bap_render_tabs( 'bap-admin-estadisticas' ); ?>
    <?php echo wp_kses_post( $notice ); ?>

    <?php bap_render_estadisticas_toolbar( $filter_uri, $filter_q, $filter_type, $stats_base_url ); ?>
    <?php bap_render_estadisticas_summary_cards( $total_blocks, $top_countries, $top_asns, $top_uris, $stats_base_url ); ?>
    <?php bap_render_estadisticas_charts( $table, $total_blocks, $top_countries, $top_uris, $stats_base_url, $type_where_sql ); ?>

    <h2 style="margin:18px 0 10px 0;">Últimos 100 intentos bloqueados</h2>
    <?php require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-table.php'; ?>
</div>