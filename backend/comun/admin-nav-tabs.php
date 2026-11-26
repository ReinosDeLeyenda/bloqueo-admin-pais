<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_render_admin_tabs( $active_slug ) {
    $tabs = [
        'bap-admin-db'           => '1. Bases de Datos',
        'bap-bloqueos'           => '2. Bloqueos',
        'bap-auditoria'          => '3. Auditoria y Hardening',
        'bap-plantillas'         => '4. Plantillas',
        'bap-notificaciones'     => '5. Notificaciones',
        'bap-admin-estadisticas' => '6. Estadisticas',
    ];
    echo '<h2 class="nav-tab-wrapper" style="margin-bottom:20px;">';
    foreach ( $tabs as $slug => $label ) {
        $class = ( $active_slug === $slug ) ? 'nav-tab nav-tab-active' : 'nav-tab';
        $url   = esc_url( admin_url( 'admin.php?page=' . $slug ) );
        echo '<a href="' . $url . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';
}