<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'notify-general-fields.php';

function bap_render_notificaciones_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $test_notice = function_exists( 'bap_handle_test_email_post' ) ? bap_handle_test_email_post() : '';
    wp_enqueue_script( 'bap-admin-bloqueos', plugins_url( 'backend/bloqueoa/admin-bloqueos.js', dirname( __DIR__, 2 ) . '/bloqueo-admin-pais.php' ), [], '12.4', true );
    ?>
    <div class="wrap">
        <h1>Control de Acceso al Panel</h1>
        <?php bap_render_tabs( 'bap-notificaciones' ); ?>
        <?php settings_errors(); ?>
        <?php echo wp_kses_post( $test_notice ); ?>

        <div class="card" style="max-width:960px;padding:16px 24px;margin-top:8px;">
            <h2 style="margin-top:0;">Canales de Alerta y Avisos de Conexión</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'bap_notify_settings_group' ); ?>
                <table class="form-table">
                    <?php bap_render_only_notify_fields(); ?>
                </table>
                <?php submit_button( 'Guardar configuración de notificaciones' ); ?>
            </form>
        </div>

        <script>
document.addEventListener("DOMContentLoaded", function() {
  var testForm = document.getElementById("bap_test_email_form");
  var mainForm = document.querySelector("form[action=\"options.php\"]");
  if (!testForm || !mainForm) return;
  testForm.addEventListener("submit", function() {
    testForm.querySelectorAll(".bap-live-cloned").forEach(function(el){ el.remove(); });
    var fd = new FormData(mainForm);
    fd.forEach(function(val, key) {
      if (key === "_wpnonce" || key === "_wp_http_referer" || key === "option_page" || key === "action") return;
      var inp = document.createElement("input");
      inp.type = "hidden"; inp.name = key; inp.value = val; inp.className = "bap-live-cloned";
      testForm.appendChild(inp);
    });
  });
});
</script>
<form id="bap_test_email_form" method="post" style="display:none;">
            <?php wp_nonce_field( 'bap_test_email_action' ); ?>
        </form>
    </div>
    <?php
}