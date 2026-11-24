<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once plugin_dir_path( __FILE__ ) . 'admin-estadisticas-charts.php';
$stats_base_url = admin_url( 'admin.php?page=bap-admin-estadisticas' );
?>
<div class="wrap">
    <h1>Bloqueo Admin por País e IP</h1>
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-db' ) ); ?>" class="nav-tab">Administración Base de Datos</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-admin-bloqueos' ) ); ?>" class="nav-tab">Bloqueos Admin</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bap-web-bloqueos' ) ); ?>" class="nav-tab">Bloqueos Web</a>
        <a href="<?php echo esc_url( $stats_base_url ); ?>" class="nav-tab nav-tab-active">Estadísticas</a>
    </h2>

    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <?php bap_render_estadisticas_summary_cards( $total_blocks, $top_countries, $top_asns, $top_uris, $stats_base_url ); ?>
    <?php bap_render_estadisticas_charts( $table, $total_blocks, $top_countries ); ?>
    <?php bap_render_estadisticas_toolbar( $filter_uri, $filter_q, $stats_base_url ); ?>
    <?php require plugin_dir_path( __FILE__ ) . 'admin-estadisticas-table.php'; ?>
</div>
