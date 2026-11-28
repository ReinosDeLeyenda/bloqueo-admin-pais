<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_is_under_attack_active() {
    $until = (int) get_option( 'bap_under_attack_until', 0 );
    if ( $until <= 0 ) {
        return false;
    }
    if ( -1 !== $until && time() >= $until ) {
        delete_option( 'bap_under_attack_until' );
        return false;
    }
    return true;
}

add_filter( 'pre_option_bap_js_challenge_mode', function( $pre ) {
    return bap_is_under_attack_active() ? 'all' : $pre;
}, 99 );

add_filter( 'pre_option_bap_block_empty_ua', function( $pre ) {
    return bap_is_under_attack_active() ? '1' : $pre;
}, 99 );

add_filter( 'pre_option_users_can_register', function( $pre ) {
    return bap_is_under_attack_active() ? 0 : $pre;
}, 998 );

add_action( 'init', function() {
    if ( ! bap_is_under_attack_active() ) { return; }
    if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) { return; }

    $ip = function_exists( 'bap_get_user_ip' ) ? bap_get_user_ip() : '';
    if ( function_exists( 'bap_is_frontend_request_exempt' ) && bap_is_frontend_request_exempt( $ip ) ) { return; }

    $reason = '';
    if ( function_exists( 'bap_is_tor_exit_node' ) && bap_is_tor_exit_node( $ip ) ) {
        $reason = 'Web: Modo Bajo Ataque (Nodo Tor)';
    } elseif ( function_exists( 'bap_is_in_firehol' ) && false !== bap_is_in_firehol( $ip ) ) {
        $reason = 'Web: Modo Bajo Ataque (Red FireHOL)';
    } elseif ( function_exists( 'bap_get_abuseipdb_score' ) ) {
        $score = bap_get_abuseipdb_score( $ip, false );
        if ( null !== $score && $score >= 25 ) {
            $reason = "Web: Modo Bajo Ataque (AbuseIPDB {$score}%)";
        }
    }

    if ( '' !== $reason ) {
        $country = function_exists( 'bap_get_country_by_ip' ) ? bap_get_country_by_ip( $ip ) : 'DESCONOCIDO';
        if ( function_exists( 'bap_log_blocked_attempt' ) ) { bap_log_blocked_attempt( $ip, $country, $reason ); }
        if ( function_exists( 'bap_render_custom_403_page' ) ) {
            bap_render_custom_403_page( 'Proteccion DDoS Activa', 'El sitio se encuentra temporalmente en Modo Bajo Ataque.', $ip, $country, $reason );
        }
        wp_die( '<h1>Proteccion DDoS Activa</h1><p>Acceso temporalmente restringido.</p>', 'Modo Bajo Ataque', [ 'response' => 403 ] );
    }
}, 2 );

add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) || ! bap_is_under_attack_active() ) { return; }
    $wp_admin_bar->add_node( [
        'id'    => 'bap-under-attack-pill',
        'title' => '<span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:4px;font-weight:700;">&#9888; MODO BAJO ATAQUE ACTIVO</span>',
        'href'  => admin_url( 'admin.php?page=bap-bloqueos&subtab=general' ),
    ] );
}, 100 );

function bap_render_under_attack_panic_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_toggle_under_attack'] ) && check_admin_referer( 'bap_panic_mode_action', 'bap_panic_mode_nonce' ) ) {
        if ( bap_is_under_attack_active() ) {
            delete_option( 'bap_under_attack_until' );
            $notice = '<div class="notice notice-success inline"><p><strong>Modo Bajo Ataque desactivado.</strong> La web ha vuelto a sus reglas normales.</p></div>';
        } else {
            $hours = (int) ( $_POST['bap_panic_hours'] ?? 3 );
            $until = ( -1 === $hours ) ? -1 : ( time() + ( max( 1, $hours ) * HOUR_IN_SECONDS ) );
            update_option( 'bap_under_attack_until', $until, false );
            $notice = '<div class="notice notice-error inline"><p><strong>&#9888; MODO BAJO ATAQUE ACTIVADO:</strong> Challenge JS forzado en toda la web, registros cerrados y bloqueo estricto de Tor/FireHOL/AbuseIPDB.</p></div>';
        }
    }

    $active   = bap_is_under_attack_active();
    $until    = (int) get_option( 'bap_under_attack_until', 0 );
    $card_css = $active
        ? 'max-width:960px;padding:18px 24px;margin-top:20px;border:2px solid #dc2626;background:rgba(220,38,38,0.08);'
        : 'max-width:960px;padding:18px 24px;margin-top:20px;';
    ?>
    <div class="card" style="<?php echo esc_attr( $card_css ); ?>">
        <h2 style="margin-top:0;<?php echo $active ? 'color:#dc2626;' : ''; ?>">Boton de Panico — Modo &laquo;Bajo Ataque&raquo; (DDoS / Capa 7)</h2>
        <p class="description">Activa con un clic la maxima defensa perimetral: fuerza el <strong>Challenge JS SHA-256</strong> en toda la web publica, cierra registros abiertos, bloquea User-Agents vacios y corta trafico de Tor, FireHOL y AbuseIPDB (&ge; 25%).</p>
        <?php echo wp_kses_post( $notice ); ?>

        <form method="post" style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_panic_mode_action', 'bap_panic_mode_nonce' ); ?>
            <?php if ( ! $active ) : ?>
                <label><strong>Auto-desactivar tras:</strong>
                    <select name="bap_panic_hours">
                        <option value="1">1 hora</option>
                        <option value="3" selected>3 horas</option>
                        <option value="6">6 horas</option>
                        <option value="12">12 horas</option>
                        <option value="24">24 horas</option>
                        <option value="-1">Manual (hasta que lo desactive)</option>
                    </select>
                </label>
                <button type="submit" name="bap_toggle_under_attack" class="button button-primary" style="background:#dc2626;border-color:#b91c1c;">ACTIVAR MODO BAJO ATAQUE</button>
            <?php else : ?>
                <span style="font-weight:600;">
                    <?php echo ( -1 === $until ) ? 'Activo de forma manual.' : 'Se desactivara automaticamente el: <code>' . esc_html( wp_date( 'Y-m-d H:i:s', $until ) ) . '</code>'; ?>
                </span>
                <button type="submit" name="bap_toggle_under_attack" class="button button-secondary">Desactivar Modo Bajo Ataque</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
}