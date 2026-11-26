<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_get_mu_emergency_loader_path() {
    $mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' );
    return trailingslashit( $mu_dir ) . 'bap-emergency-loader.php';
}

function bap_is_mu_emergency_loader_installed() {
    return file_exists( bap_get_mu_emergency_loader_path() );
}

function bap_install_mu_emergency_loader() {
    $mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( WP_CONTENT_DIR . '/mu-plugins' );
    if ( ! is_dir( $mu_dir ) && ! wp_mkdir_p( $mu_dir ) ) {
        return new WP_Error( 'mu_mkdir_fail', 'No se pudo crear la carpeta wp-content/mu-plugins/.' );
    }

    $payload = <<<'PHP'
<?php
/**
 * Plugin Name: Bloqueo Admin - Cargador de Emergencia (Anti-Crash)
 * Description: Protege el acceso a wp-admin ante errores fatales de cualquier plugin, permite desactivarlo por enlace firmado y entrar en Modo Seguro.
 * Version: 1.1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

(function () {
    $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'bap-emergency-fallback-salt';
    $cookie_name = 'bap_safe_mode_' . substr( hash( 'sha256', $salt ), 0, 12 );

    if ( isset( $_GET['bap_exit_safe_mode'] ) ) {
        if ( ! headers_sent() ) {
            setcookie( $cookie_name, '', time() - 3600, '/', '', is_ssl(), true );
        }
        unset( $_COOKIE[ $cookie_name ] );
        if ( function_exists( 'wp_safe_redirect' ) ) {
            wp_safe_redirect( remove_query_arg( 'bap_exit_safe_mode' ) );
            exit;
        }
    }

    $valid_sig = false;
    if ( ! empty( $_GET['bap_em_ts'] ) && ! empty( $_GET['bap_em_sig'] ) && ctype_digit( (string) $_GET['bap_em_ts'] ) ) {
        $ts  = (int) $_GET['bap_em_ts'];
        $sig = (string) $_GET['bap_em_sig'];
        if ( abs( time() - $ts ) <= 1800 && hash_equals( hash_hmac( 'sha256', 'bap_em|' . $ts, $salt ), $sig ) ) {
            $valid_sig = true;
        }
    }

    if ( $valid_sig && ! empty( $_GET['bap_em_kill'] ) ) {
        $culprit_slug = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $_GET['bap_em_kill'] );
        if ( '' !== $culprit_slug ) {
            $active = (array) get_option( 'active_plugins', [] );
            $clean  = array_values( array_filter( $active, static function ( $p ) use ( $culprit_slug ) {
                return ( 0 !== strpos( (string) $p, $culprit_slug . '/' ) && (string) $p !== $culprit_slug . '.php' );
            } ) );
            update_option( 'active_plugins', $clean );
            if ( function_exists( 'wp_safe_redirect' ) ) {
                wp_safe_redirect( add_query_arg( 'bap_em_killed', rawurlencode( $culprit_slug ), site_url( '/wp-login.php' ) ) );
                exit;
            }
        }
    }

    if ( ! empty( $_GET['bap_em_killed'] ) ) {
        add_filter( 'login_message', static function ( $msg ) {
            $slug = htmlspecialchars( (string) $_GET['bap_em_killed'], ENT_QUOTES, 'UTF-8' );
            return $msg . '<div class="message"><strong>Modo Emergencia:</strong> El plugin <code>' . $slug . '</code> ha sido desactivado correctamente. Ya puedes iniciar sesion con normalidad.</div>';
        } );
    }

    $activate_safe = $valid_sig;
    if ( ! $activate_safe && isset( $_GET['bap_safe_mode'] ) && '1' === (string) $_GET['bap_safe_mode'] ) {
        $secret = trim( (string) get_option( 'bap_secret_key', '' ) );
        if ( '' !== $secret && array_key_exists( $secret, $_GET ) ) {
            $activate_safe = true;
        }
    }

    $expected_cookie = hash_hmac( 'sha256', 'active_safe_session', $salt );
    if ( $activate_safe ) {
        if ( ! headers_sent() ) {
            setcookie( $cookie_name, $expected_cookie, time() + 3600, '/', '', is_ssl(), true );
        }
        $_COOKIE[ $cookie_name ] = $expected_cookie;
    }

    $in_safe_mode = ( isset( $_COOKIE[ $cookie_name ] ) && hash_equals( $expected_cookie, (string) $_COOKIE[ $cookie_name ] ) );

    if ( $in_safe_mode ) {
        add_filter( 'option_active_plugins', '__return_empty_array', 9999 );
        add_filter( 'site_option_active_sitewide_plugins', '__return_empty_array', 9999 );
        add_action( 'admin_notices', function () {
            $exit_url = esc_url( add_query_arg( 'bap_exit_safe_mode', '1' ) );
            echo '<div class="notice notice-warning" style="border-left-color:#d63638;"><p>'
               . '<strong>MODO EMERGENCIA ACTIVO (Bloqueo Admin):</strong> Todos los plugins normales estan desactivados temporalmente solo en tu sesion para que puedas desactivar el plugin que causo el error fatal. '
               . '<a href="' . $exit_url . '" class="button button-secondary" style="margin-left:8px;">Salir del Modo Emergencia</a>'
               . '</p></div>';
        } );
        return;
    }

    register_shutdown_function( function () use ( $salt ) {
        $err = error_get_last();
        if ( ! is_array( $err ) || ! in_array( $err['type'] ?? 0, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
            return;
        }

        $uri      = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        $err_file = str_replace( '\\', '/', (string) ( $err['file'] ?? '' ) );
        $is_admin = ( false !== strpos( $uri, '/wp-admin' ) || false !== strpos( $uri, '/wp-login.php' ) );
        $is_plug  = ( false !== strpos( $err_file, '/wp-content/plugins/' ) );

        if ( ! $is_admin && ! $is_plug ) {
            return;
        }

        if ( function_exists( 'get_transient' ) && false !== get_transient( 'bap_em_crash_lock' ) ) {
            return;
        }
        if ( function_exists( 'set_transient' ) ) {
            set_transient( 'bap_em_crash_lock', '1', 300 );
        }

        $ts         = (string) time();
        $sig        = hash_hmac( 'sha256', 'bap_em|' . $ts, $salt );
        $base_login = function_exists( 'site_url' ) ? site_url( '/wp-login.php' ) : '/wp-login.php';
        $magic_url  = $base_login . '?bap_em_ts=' . $ts . '&bap_em_sig=' . $sig;

        $plugin_culprit = '';
        if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $err_file, $m ) ) {
            $plugin_culprit = $m[1];
        }
        $kill_url = ( '' !== $plugin_culprit ) ? ( $magic_url . '&bap_em_kill=' . rawurlencode( $plugin_culprit ) ) : '';

        $msg_detail = sprintf(
            "Plugin afectado: %s\nArchivo: %s (Linea %d)\nError: %s",
            '' !== $plugin_culprit ? $plugin_culprit : 'Desconocido / Nucleo',
            basename( $err_file ),
            (int) ( $err['line'] ?? 0 ),
            substr( (string) ( $err['message'] ?? '' ), 0, 300 )
        );

        $tg_token = trim( (string) get_option( 'bap_tg_bot_token', '' ) );
        $tg_chats = trim( (string) get_option( 'bap_tg_chat_ids', '' ) );
        if ( '' !== $tg_token && '' !== $tg_chats && function_exists( 'wp_remote_post' ) ) {
            $text = "ERROR FATAL EN WORDPRESS\n\n" . htmlspecialchars( $msg_detail, ENT_QUOTES, 'UTF-8' ) . "\n\n";
            if ( '' !== $kill_url ) {
                $text .= "Desactivar plugin culpable en 1 clic:\n" . htmlspecialchars( $kill_url, ENT_QUOTES, 'UTF-8' ) . "\n\n";
            }
            $text .= "Entrar en Modo Emergencia (sin plugins):\n" . htmlspecialchars( $magic_url, ENT_QUOTES, 'UTF-8' );

            foreach ( array_unique( array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', $tg_chats ) ) ) ) as $cid ) {
                wp_remote_post( 'https://api.' . 'telegram.org/bot' . rawurlencode( $tg_token ) . '/sendMessage', [
                    'timeout' => 5,
                    'body'    => [ 'chat_id' => $cid, 'text' => $text, 'parse_mode' => 'HTML' ],
                ] );
            }
        }

        $to = trim( (string) get_option( 'bap_notify_emails', '' ) );
        if ( '' === $to ) {
            $to = (string) get_option( 'admin_email', '' );
        }
        if ( '' !== $to && function_exists( 'wp_mail' ) ) {
            $body = "Se ha detectado un Error Fatal en WordPress:\n\n{$msg_detail}\n\n";
            if ( '' !== $kill_url ) {
                $body .= "1) Desactivar permanentemente el plugin culpable en 1 clic:\n{$kill_url}\n\n";
            }
            $body .= "2) Entrar en Modo Emergencia aislando todos los plugins:\n{$magic_url}\n";
            wp_mail( $to, '[EMERGENCIA] Error Fatal en WordPress (' . ( $plugin_culprit ?: 'Core' ) . ')', $body );
        }
    } );
})();
PHP;

    $target = bap_get_mu_emergency_loader_path();
    if ( false === @file_put_contents( $target, $payload, LOCK_EX ) ) {
        return new WP_Error( 'mu_write_fail', 'No se pudo escribir en wp-content/mu-plugins/bap-emergency-loader.php.' );
    }
    return true;
}
function bap_uninstall_mu_emergency_loader() {
    $target = bap_get_mu_emergency_loader_path();
    if ( file_exists( $target ) ) {
        return @unlink( $target );
    }
    return true;
}

function bap_render_emergency_mode_box() {
    $notice = '';
    if ( isset( $_POST['bap_toggle_mu_emergency'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_em_loader_action', 'bap_em_loader_nonce' ) ) {
        if ( bap_is_mu_emergency_loader_installed() ) {
            $ok     = bap_uninstall_mu_emergency_loader();
            $notice = $ok
                ? '<div class="notice notice-warning inline"><p>Cargador de emergencia desinstalado de wp-content/mu-plugins/.</p></div>'
                : '<div class="notice notice-error inline"><p>No se pudo eliminar el archivo de mu-plugins/.</p></div>';
        } else {
            $res    = bap_install_mu_emergency_loader();
            $notice = is_wp_error( $res )
                ? '<div class="notice notice-error inline"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
                : '<div class="notice notice-success inline"><p><strong>Modo Emergencia Anti-Crash instalado.</strong> Si algun plugin provoca un error fatal, recibiras enlaces firmados por Telegram/Email para desactivar el plugin culpable o entrar aislando plugins.</p></div>';
        }
    }

    $installed = bap_is_mu_emergency_loader_installed();
    $secret    = trim( (string) get_option( 'bap_secret_key', '' ) );
    ?>
    <div class="card" style="max-width:920px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Modo Emergencia Anti-Crash (mu-plugins)</h2>
        <p class="description">Instala un cargador ultraligero en wp-content/mu-plugins/ que se ejecuta antes que cualquier plugin. Si un plugin (incluso este mismo) provoca una pantalla blanca o error fatal, te envia al instante por Telegram y Email un enlace para <strong>desactivar el plugin culpable en 1 clic</strong> y otro para entrar aislando plugins.</p>

        <?php echo wp_kses_post( $notice ); ?>

        <p style="margin:12px 0;">
            <strong>Estado actual:</strong>
            <?php if ( $installed ) : ?>
                <?php echo bap_render_status_badge( 'ACTIVO E INSTALADO', '#00a32a' ); ?>
            <?php else : ?>
                <?php echo bap_render_status_badge( 'NO INSTALADO', '#646970' ); ?>
            <?php endif; ?>
        </p>

        <?php if ( '' !== $secret ) : ?>
            <p class="description" style="margin-bottom:12px;">Acceso manual directo de emergencia con tu llave secreta: <code><?php echo esc_html( site_url( '/wp-login.php?' . $secret . '&bap_safe_mode=1' ) ); ?></code></p>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'bap_em_loader_action', 'bap_em_loader_nonce' ); ?>
            <button type="submit" name="bap_toggle_mu_emergency" class="button <?php echo $installed ? 'button-secondary' : 'button-primary'; ?>">
                <?php echo $installed ? 'Desinstalar cargador de emergencia' : 'Instalar Modo Emergencia en 1 clic'; ?>
            </button>
        </form>
    </div>
    <?php
}

function bap_mu_emergency_needs_update() {
    if ( ! bap_is_mu_emergency_loader_installed() ) {
        return false;
    }
    $content = (string) @file_get_contents( bap_get_mu_emergency_loader_path() );
    if ( preg_match( '/Version:\s*([0-9.]+)/i', $content, $m ) ) {
        return version_compare( $m[1], '1.1.0', '<' );
    }
    return true;
}

add_action( 'admin_init', function() {
    if ( isset( $_POST['bap_upgrade_mu_emergency_now'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_em_loader_action', 'bap_em_loader_nonce' ) ) {
        $res = bap_install_mu_emergency_loader();
        set_transient( 'bap_mu_upgraded_notice', is_wp_error( $res ) ? $res->get_error_message() : 'ok', 60 );
        wp_safe_redirect( remove_query_arg( '_wp_http_referer' ) );
        exit;
    }
} );

add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $done = get_transient( 'bap_mu_upgraded_notice' );
    if ( false !== $done ) {
        delete_transient( 'bap_mu_upgraded_notice' );
        if ( 'ok' === $done ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Modo Emergencia Anti-Crash actualizado a v1.1.0</strong> en <code>wp-content/mu-plugins/</code>.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error al actualizar mu-plugin:</strong> ' . esc_html( $done ) . '</p></div>';
        }
    }
    if ( bap_mu_emergency_needs_update() ) {
        echo '<div class="notice notice-error" style="border-left-width:6px;background:#fef2f2;"><form method="post" style="margin:8px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
        wp_nonce_field( 'bap_em_loader_action', 'bap_em_loader_nonce' );
        echo '<span><strong>&#9888; Actualización requerida (Modo Emergencia Anti-Crash):</strong> El archivo <code>wp-content/mu-plugins/bap-emergency-loader.php</code> está desactualizado y no incluye la desactivación en 1 clic del plugin culpable.</span>';
        echo '<button type="submit" name="bap_upgrade_mu_emergency_now" class="button button-primary" style="background:#dc2626;border-color:#b91c1c;">Actualizar cargador ahora en 1 clic</button>';
        echo '</form></div>';
    }
} );

add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
    if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) && bap_is_mu_emergency_loader_installed() ) {
        bap_install_mu_emergency_loader();
    }
}, 10, 2 );
