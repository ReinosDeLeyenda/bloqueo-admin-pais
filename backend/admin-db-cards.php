<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_single_db_card( $title, $is_active, $info_line, $updated, $update_btn_name, $update_btn_label, $form_id, $file_input_name, $accept, $upload_btn_name, $del_type, $del_confirm ) {
    ?>
    <div class="card" style="flex:1;min-width:300px;max-width:380px;margin:0;">
        <h2><?php echo esc_html( $title ); ?></h2>
        <p><strong>Estado:</strong>
            <?php if ( $is_active ) : ?>
                <span style="color:#008a20;font-weight:bold;">Activa</span> (<?php echo esc_html( $info_line ); ?>)<br />
                <small style="color:#50575e;">Actualizada: <code><?php echo esc_html( $updated ); ?></code></small>
            <?php else : ?>
                <span style="color:#d63638;font-weight:bold;">No instalada</span>
            <?php endif; ?>
        </p>
        <form method="post" style="margin-bottom:10px;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="submit" name="<?php echo esc_attr( $update_btn_name ); ?>" class="button button-primary" value="<?php echo esc_attr( $update_btn_label ); ?>" />
        </form>
        <form id="<?php echo esc_attr( $form_id ); ?>" method="post" enctype="multipart/form-data" style="margin-bottom:8px;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="file" name="<?php echo esc_attr( $file_input_name ); ?>" accept="<?php echo esc_attr( $accept ); ?>" required />
        </form>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="submit" form="<?php echo esc_attr( $form_id ); ?>" name="<?php echo esc_attr( $upload_btn_name ); ?>" class="button button-secondary" value="Subir manual" />
            <?php if ( $is_active ) : ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( $del_confirm ); ?>');">
                    <?php wp_nonce_field( 'bap_db_action' ); ?>
                    <input type="hidden" name="bap_delete_db" value="<?php echo esc_attr( $del_type ); ?>" />
                    <input type="submit" class="button button-secondary" style="color:#d63638;" value="Borrar DB" />
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function bap_render_json_backup_card() {
    ?>
    <div class="card" style="max-width:760px;padding:16px 24px;margin-top:24px;">
        <h2>Exportar / Importar Configuración (.json)</h2>
        <p class="description" style="margin-bottom:14px;">Guarda una copia de seguridad de todas tus reglas de <strong>Bloqueos Admin</strong> y <strong>Bloqueos Web</strong> o clónalas en otro sitio WordPress.</p>

        <form method="post" style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #3c434a;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="bap_export_secrets" value="1" />
                Incluir también la <strong>Llave secreta URL</strong> y la <strong>API Key AbuseIPDB</strong> en el archivo exportado
            </label>
            <input type="submit" name="bap_export_json" class="button button-primary" value="Descargar configuración (.json)" />
        </form>

        <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field( 'bap_db_action' ); ?>
            <input type="file" name="bap_json_file" accept=".json" required />
            <input type="submit" name="bap_import_json" class="button button-secondary" value="Importar configuración (.json)" onclick="return confirm('Esto sobrescribirá tus reglas actuales con las del archivo JSON. ¿Continuar?');" />
        </form>
    </div>
    <?php
}
