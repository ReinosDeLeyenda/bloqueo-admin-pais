<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_cron_resolve_hook_origin( $hook ) {
    global $wp_filter;
    if ( empty( $wp_filter[ $hook ] ) ) {
        return [ 'origin' => 'Sin callback (Huerfano)', 'suspicious' => true ];
    }
    $root    = wp_normalize_path( WP_CONTENT_DIR );
    $origins = [];
    foreach ( $wp_filter[ $hook ]->callbacks as $prio => $items ) {
        foreach ( $items as $cb ) {
            $fn   = $cb['function'] ?? null;
            $file = '';
            try {
                if ( is_string( $fn ) && function_exists( $fn ) ) {
                    $file = ( new ReflectionFunction( $fn ) )->getFileName();
                } elseif ( is_array( $fn ) && count( $fn ) === 2 ) {
                    $file = ( new ReflectionMethod( $fn[0], $fn[1] ) )->getFileName();
                } elseif ( $fn instanceof Closure ) {
                    $file = ( new ReflectionFunction( $fn ) )->getFileName();
                }
            } catch ( Throwable $e ) {}
            if ( '' !== $file ) {
                $norm = wp_normalize_path( $file );
                if ( preg_match( '#/plugins/([^/]+)/#', $norm, $m ) ) { $origins[] = 'Plugin: ' . $m[1]; }
                elseif ( preg_match( '#/themes/([^/]+)/#', $norm, $m ) ) { $origins[] = 'Tema: ' . $m[1]; }
                elseif ( 0 === strpos( $norm, $root . '/mu-plugins/' ) ) { $origins[] = 'MU-Plugin: ' . basename( $norm ); }
                else { $origins[] = 'WordPress Core'; }
            }
        }
    }
    $origins = array_values( array_unique( $origins ) );
    $bad_pat = (bool) preg_match( '/(?:eval|base64|shell|backdoor|inject|hack|miner)/i', (string) $hook );
    return [
        'origin'     => ! empty( $origins ) ? implode( ', ', $origins ) : 'Callback desconocido',
        'suspicious' => ( empty( $origins ) || $bad_pat ),
    ];
}

function bap_cron_describe_hook( $hook ) {
    $known = [
        'wp_version_check'                      => 'Comprueba si hay nuevas versiones del nucleo de WordPress.',
        'wp_update_plugins'                     => 'Busca actualizaciones disponibles para los plugins instalados.',
        'wp_update_themes'                      => 'Busca actualizaciones disponibles para los temas instalados.',
        'wp_scheduled_delete'                   => 'Vacia la papelera de entradas, paginas y comentarios antiguos.',
        'wp_scheduled_auto_draft_delete'        => 'Elimina borradores automaticos antiguos sin guardar.',
        'wp_privacy_delete_old_export_files'    => 'Borra archivos temporales de exportacion de datos personales (RGPD).',
        'delete_expired_transients'             => 'Limpia de wp_options los transients caducados.',
        'recovery_mode_clean_expired_keys'      => 'Elimina claves caducadas del Modo de Recuperacion de WordPress.',
        'wp_site_health_scheduled_check'        => 'Ejecuta las pruebas automaticas de Salud del Sitio.',
        'do_pings'                              => 'Envia pingbacks y trackbacks pendientes a enlaces externos.',
        'publish_future_post'                   => 'Publica una entrada o pagina programada para una fecha futura.',
        'wp_https_detection'                    => 'Verifica si el sitio soporta HTTPS correctamente.',
        'bap_auto_update_dbs_event'             => 'Actualiza automaticamente las bases de datos GeoIP, ASN, AbuseIPDB, Tor y FireHOL.',
        'bap_daily_logs_cleanup'                => 'Purga registros antiguos de bloqueos segun los dias de retencion.',
        'bap_purge_autobans_event'              => 'Libera IPs de la Lista Negra cuya expulsion temporal ha caducado.',
        'bap_scheduled_security_scan_event'     => 'Ejecuta la auditoria automatica de seguridad (CVEs, malware, integridad y SSL).',
        'bap_send_telegram_digest_event'        => 'Envia por Telegram el resumen agrupado de ataques bloqueados.',
        'bap_send_digest_email_event'           => 'Envia por correo electronico el resumen agrupado de ataques bloqueados.',
        'bap_auto_db_backup_telegram_event'     => 'Genera una copia SQL comprimida (.sql.gz) y la envia a Telegram.',
        'action_scheduler_run_queue'            => 'Procesa tareas en segundo plano (WooCommerce / plugins con Action Scheduler).',
        'woocommerce_cleanup_sessions'          => 'Elimina sesiones y carritos caducados de clientes en WooCommerce.',
        'woocommerce_cleanup_logs'              => 'Borra archivos de registro antiguos de WooCommerce.',
        'woocommerce_scheduled_sales'           => 'Activa o finaliza precios rebajados programados en productos.',
    ];
    if ( isset( $known[ $hook ] ) ) {
        return $known[ $hook ];
    }
    global $wp_filter;
    if ( empty( $wp_filter[ $hook ] ) ) {
        return 'Evento huerfano: ningun plugin o tema activo escucha esta tarea (seguro de borrar).';
    }
    foreach ( $wp_filter[ $hook ]->callbacks as $items ) {
        foreach ( $items as $cb ) {
            $fn = $cb['function'] ?? null;
            try {
                $ref = null;
                if ( is_string( $fn ) && function_exists( $fn ) ) { $ref = new ReflectionFunction( $fn ); }
                elseif ( is_array( $fn ) && count( $fn ) === 2 ) { $ref = new ReflectionMethod( $fn[0], $fn[1] ); }
                if ( $ref && ( $doc = $ref->getDocComment() ) ) {
                    $clean = trim( preg_replace( '/^\s*\/\*+|\*+\/\s*$|\s*\*\s?/m', ' ', $doc ) );
                    $first = trim( strtok( $clean, "\r\n." ) );
                    if ( '' !== $first && 0 !== strpos( $first, '@' ) ) {
                        return mb_substr( $first, 0, 140 ) . '.';
                    }
                }
            } catch ( Throwable $e ) {}
        }
    }
    return 'Tarea programada personalizada registrada por el componente indicado en Origen.';
}

function bap_get_scheduled_cron_events_list() {
    $crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
    if ( ! is_array( $crons ) ) { return []; }
    $list = [];
    foreach ( $crons as $ts => $hooks ) {
        if ( ! is_array( $hooks ) ) { continue; }
        foreach ( $hooks as $hook => $instances ) {
            $info = bap_cron_resolve_hook_origin( $hook );
            $desc = bap_cron_describe_hook( $hook );
            foreach ( (array) $instances as $sig => $cfg ) {
                $list[] = [
                    'ts'          => (int) $ts,
                    'next_run'    => wp_date( 'Y-m-d H:i:s', (int) $ts ),
                    'hook'        => (string) $hook,
                    'description' => $desc,
                    'sig'         => (string) $sig,
                    'args'        => (array) ( $cfg['args'] ?? [] ),
                    'schedule'    => (string) ( $cfg['schedule'] ?: 'Una vez' ),
                    'origin'      => $info['origin'],
                    'suspicious'  => $info['suspicious'],
                ];
            }
        }
    }
    usort( $list, fn( $a, $b ) => $a['ts'] <=> $b['ts'] );
    return $list;
}
function bap_cron_inspect_hook_details( $hook ) {
    global $wp_filter;
    if ( empty( $wp_filter
[
 $hook 
]
 ) ) {
        return "Hook sin callbacks registrados en PHP (evento huerfano en tabla cron).";
    }
    $lines = [];
    foreach ( $wp_filter
[
 $hook 
]
->callbacks as $prio => $items ) {
        foreach ( $items as $cb ) {
            $fn = $cb
[
'function'
]
 ?? null;
            try {
                if ( is_string( $fn ) && function_exists( $fn ) ) {
                    $ref = new ReflectionFunction( $fn );
                    $lines[] = "Prioridad {$prio}: funcion {$fn}() -> " . wp_normalize_path( $ref->getFileName() ) . ":" . $ref->getStartLine();
                } elseif ( is_array( $fn ) && count( $fn ) === 2 ) {
                    $cls = is_object( $fn
[
0
]
 ) ? get_class( $fn
[
0
]
 ) : (string) $fn
[
0
]
;
                    $ref = new ReflectionMethod( $fn
[
0
]
, $fn
[
1
]
 );
                    $lines[] = "Prioridad {$prio}: {$cls}::" . $fn
[
1
]
 . "() -> " . wp_normalize_path( $ref->getFileName() ) . ":" . $ref->getStartLine();
                } elseif ( $fn instanceof Closure ) {
                    $ref = new ReflectionFunction( $fn );
                    $lines[] = "Prioridad {$prio}: Closure -> " . wp_normalize_path( $ref->getFileName() ) . ":" . $ref->getStartLine();
                }
            } catch ( Throwable $e ) {}
        }
    }
    return ! empty( $lines ) ? implode( "\n", $lines ) : "Callback dinamico no inspeccionable.";
}

function bap_render_wp_cron_inspector_box() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $notice = '';
    if ( isset( $_POST['bap_delete_cron_event'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {
        $hook = sanitize_text_field( wp_unslash( $_POST['bap_cron_hook'] ?? '' ) );
        $ts   = (int) ( $_POST['bap_cron_ts'] ?? 0 );
        $args = json_decode( (string) wp_unslash( $_POST['bap_cron_args'] ?? '[]' ), true );
        if ( '' !== $hook && $ts > 0 && is_array( $args ) ) {
            wp_unschedule_event( $ts, $hook, $args );
            $notice = '<div class="notice notice-warning inline"><p>Tarea cron <code>' . esc_html( $hook ) . '</code> eliminada.</p></div>';
        }
    }
     $inspect_output = '';     $inspect_hook   = '';     if ( isset( $_POST['bap_view_cron_detail'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {         $inspect_hook   = sanitize_text_field( wp_unslash( $_POST['bap_cron_hook'] ?? '' ) );         $raw_args       = (string) wp_unslash( $_POST['bap_cron_args'] ?? '[]' );         $inspect_output = bap_cron_inspect_hook_details( $inspect_hook ) . "\nArgumentos: " . $raw_args;     }     if ( isset( $_POST['bap_pause_all_crons'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {         $all_evs = bap_get_scheduled_cron_events_list();         $paused  = [];         foreach ( $all_evs as $ev_item ) {             if ( in_array( $ev_item['hook'], $core_protected, true ) || 0 === strpos( $ev_item['hook'], 'bap_' ) ) { continue; }             $paused[] = $ev_item;             wp_unschedule_event( $ev_item['ts'], $ev_item['hook'], $ev_item['args'] );         }         if ( ! empty( $paused ) ) { update_option( 'bap_paused_crons_backup', $paused, false ); }         $notice = '<div class="notice notice-warning inline"><p><strong>Tareas detenidas:</strong> Se han pausado <strong>' . count( $paused ) . '</strong> tarea(s) no esenciales.</p></div>';     }     if ( isset( $_POST['bap_resume_paused_crons'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {         $paused = (array) get_option( 'bap_paused_crons_backup', [] );         $rest   = 0;         foreach ( $paused as $p_item ) {             $hk = (string) ( $p_item['hook'] ?? '' );             $sc = (string) ( $p_item['schedule'] ?? '' );             $ar = (array) ( $p_item['args'] ?? [] );             if ( '' !== $hk && ! wp_next_scheduled( $hk, $ar ) ) {                 if ( '' !== $sc && 'Una vez' !== $sc ) { wp_schedule_event( time() + 60, $sc, $hk, $ar ); }                 else { wp_schedule_single_event( time() + 60, $hk, $ar ); }                 $rest++;             }         }         delete_option( 'bap_paused_crons_backup' );         $notice = '<div class="notice notice-success inline"><p><strong>Tareas reanudadas:</strong> Se han reprogramado <strong>' . (int) $rest . '</strong> tarea(s).</p></div>';     }     if ( isset( $_POST['bap_delete_all_crons'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {         $all_evs = bap_get_scheduled_cron_events_list();         $del_c   = 0;         foreach ( $all_evs as $ev_item ) {             if ( in_array( $ev_item['hook'], $core_protected, true ) || 0 === strpos( $ev_item['hook'], 'bap_' ) ) { continue; }             wp_unschedule_event( $ev_item['ts'], $ev_item['hook'], $ev_item['args'] );             $del_c++;         }         delete_option( 'bap_paused_crons_backup' );         $notice = '<div class="notice notice-success inline"><p><strong>Limpieza masiva completada:</strong> Se han eliminado <strong>' . (int) $del_c . '</strong> tarea(s) cron no esenciales.</p></div>';     }         if ( isset( $_POST['bap_run_all_crons_now'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {
        $all_evs = bap_get_scheduled_cron_events_list();
        $ran     = 0;
        foreach ( $all_evs as $ev_item ) {
            if ( '' !== $ev_item['hook'] && is_array( $ev_item['args'] ) ) {
                do_action_ref_array( $ev_item['hook'], $ev_item['args'] );
                $ran++;
            }
        }
        $notice = '<div class="notice notice-success inline"><p><strong>Se han ejecutado ' . (int) $ran . ' tarea(s) programada(s) correctamente.</strong></p></div>';
    }
    if ( isset( $_POST['bap_run_cron_now'] ) && check_admin_referer( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ) ) {
        $hook = sanitize_text_field( wp_unslash( $_POST['bap_cron_hook'] ?? '' ) );
        $args = json_decode( (string) wp_unslash( $_POST['bap_cron_args'] ?? '[]' ), true );
        if ( '' !== $hook && is_array( $args ) ) {
            do_action_ref_array( $hook, $args );
            $notice = '<div class="notice notice-success inline"><p>Hook cron <code>' . esc_html( $hook ) . '</code> ejecutado manualmente.</p></div>';
        }
    }

    $events = bap_get_scheduled_cron_events_list();

    ?>
    <div id="bap-box-wp-cron-inspector" class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;">
        <h2 style="margin-top:0;">Inspector de Tareas Programadas (WP-Cron)</h2>
        <p class="description">Audita todas las tareas programadas en WordPress, identifica que plugin o tema las ejecuta y elimina eventos huerfanos o maliciosos ocultos.</p>
        <?php $paused_list = (array) get_option( 'bap_paused_crons_backup', [] ); ?>
        <form method="post" style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <?php wp_nonce_field( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ); ?>
            <button type="submit" name="bap_run_all_crons_now" class="button button-primary" onclick="return confirm('¿Ejecutar todas las tareas programadas ahora?');">Ejecutar todas</button>
            <button type="submit" name="bap_pause_all_crons" class="button button-secondary" onclick="return confirm('¿Detener temporalmente todas las tareas no esenciales?');">Detener todas</button>
            <?php if ( ! empty( $paused_list ) ) : ?>
                <button type="submit" name="bap_resume_paused_crons" class="button button-secondary" style="border-color:#00a32a;color:#008a20;">Reanudar pausadas (<?php echo count( $paused_list ); ?>)</button>
            <?php endif; ?>
            <button type="submit" name="bap_delete_all_crons" class="button button-secondary" style="color:#d63638;border-color:#d63638;" onclick="return confirm('¿BORRAR TODAS las tareas programadas no esenciales (huerfanas y de plugins)?');">Borrar todas</button>
        </form>
        <?php if ( '' !== $inspect_output ) : ?>
            <div style="margin:12px 0;padding:12px 14px;background:#1d2327;color:#e5e7eb;border:1px solid #3c434a;border-radius:6px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <strong style="color:#72aee6;">Inspeccion de Hook: <?php echo esc_html( $inspect_hook ); ?></strong>
                    <a href="#" onclick="this.parentElement.parentElement.remove();return false;" style="color:#f87171;text-decoration:none;font-weight:700;">Cerrar &times;</a>
                </div>
                <pre style="margin:0;white-space:pre-wrap;word-break:break-all;font-size:12px;"><?php echo esc_html( $inspect_output ); ?></pre>
            </div>
        <?php endif; ?>
        <?php echo wp_kses_post( $notice ); ?>
        <div style="overflow-x:auto;margin-top:12px;">
            <table class="widefat striped">
                <thead><tr><th>Hook / Evento</th><th>Origen</th><th>Frecuencia</th><th>Proxima ejecucion</th><th style="width:70px;">Ver</th><th style="width:155px;">Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ( $events as $ev ) :
                        $is_prot = in_array( $ev['hook'], $core_protected, true ) || 0 === strpos( $ev['hook'], 'bap_' );
                        $args_j  = wp_json_encode( $ev['args'] );
                    ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html( $ev['hook'] ); ?></code>
                                <?php if ( $ev['suspicious'] ) : ?>
                                    <span style="background:#d63638;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-left:4px;font-weight:700;">REVISAR</span>
                                <?php endif; ?>
                                <?php if ( ! empty( $ev['description'] ) ) : ?>
                                    <br><small style="color:#50575e;"><?php echo esc_html( $ev['description'] ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo esc_html( $ev['origin'] ); ?></small></td>
                            <td><code><?php echo esc_html( $ev['schedule'] ); ?></code></td>
                            <td><code><?php echo esc_html( $ev['next_run'] ); ?></code></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ); ?>
                                    <input type="hidden" name="bap_cron_hook" value="<?php echo esc_attr( $ev['hook'] ); ?>" />
                                    <input type="hidden" name="bap_cron_args" value="<?php echo esc_attr( $args_j ); ?>" />
                                    <button type="submit" name="bap_view_cron_detail" class="button button-small">Ver</button>
                                </form>
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'bap_cron_insp_action', 'bap_cron_insp_nonce' ); ?>
                                    <input type="hidden" name="bap_cron_hook" value="<?php echo esc_attr( $ev['hook'] ); ?>" />
                                    <input type="hidden" name="bap_cron_ts" value="<?php echo (int) $ev['ts']; ?>" />
                                    <input type="hidden" name="bap_cron_args" value="<?php echo esc_attr( $args_j ); ?>" />
                                    <button type="submit" name="bap_run_cron_now" class="button button-small">Ejecutar</button>
                                    <?php if ( ! $is_prot ) : ?>
                                        <button type="submit" name="bap_delete_cron_event" class="button button-small" style="color:#d63638;" onclick="return confirm('Eliminar esta tarea programada?');">Borrar</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    (function() {
      var box = document.getElementById("bap-box-wp-cron-inspector");
      if (!box || !window.fetch || box.dataset.bapAjaxBound) return;
      box.dataset.bapAjaxBound = "1";
      var lastBtn = null;
      box.addEventListener("click", function(e){ var b = e.target.closest("button[type=submit]"); if (b) lastBtn = b; });
      box.addEventListener("submit", function(e) {
        var form = e.target;
        if (!form || form.tagName !== "FORM") return;
        e.preventDefault();
        var y = window.scrollY;
        var btn = lastBtn || form.querySelector("button[type=submit]");
        var fd = new FormData(form);
        if (btn && btn.name) fd.append(btn.name, btn.value || "1");
        var orig = btn ? btn.textContent : "";
        if (btn) { btn.disabled = true; btn.textContent = "Procesando..."; }
        fetch(window.location.href, { method: "POST", body: fd, credentials: "same-origin" })
          .then(function(r){ return r.text(); })
          .then(function(html){
            var doc = new DOMParser().parseFromString(html, "text/html");
            var fresh = doc.getElementById("bap-box-wp-cron-inspector");
            if (fresh) { box.innerHTML = fresh.innerHTML; window.scrollTo(0, y); }
          })
          .catch(function(){ if (btn) { btn.disabled = false; btn.textContent = orig; } form.submit(); });
      });
    })();
    </script>
    <?php
}