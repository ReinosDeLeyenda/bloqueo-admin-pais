<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bap_render_email_builder_sidebar( array $presets, array $customs, $active_tpl, $base_url ) {
    ?>
    <div style="flex:1;min-width:290px;max-width:400px;display:flex;flex-direction:column;gap:16px;">
        <div class="card" style="padding:16px 20px;margin:0;">
            <h3 style="margin-top:0;">Plantillas predefinidas (Base)</h3>
            <ul style="margin:0;padding:0;list-style:none;">
                <?php foreach ( $presets as $pkey => $pdata ) : ?>
                    <li style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #dcdcde;">
                        <div>
                            <strong><?php echo esc_html( $pdata['name'] ); ?></strong>
                            <?php if ( $active_tpl === $pkey ) : ?>
                                <span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;">ACTIVA</span>
                            <?php endif; ?>
                            <br><small style="color:#646970;"><?php echo esc_html( $pdata['desc'] ); ?></small>
                        </div>
                        <a href="<?php echo esc_url( add_query_arg( 'clone_preset', $pkey, $base_url ) ); ?>" class="button button-small">Clonar</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card" style="padding:16px 20px;margin:0;">
            <h3 style="margin-top:0;">Mis plantillas personalizadas</h3>
            <?php if ( empty( $customs ) ) : ?>
                <p class="description">Aún no has creado ninguna. Usa el editor o pulsa <strong>Clonar</strong> sobre una predefinida.</p>
            <?php else : ?>
                <ul style="margin:0;padding:0;list-style:none;">
                    <?php foreach ( $customs as $cid => $ctpl ) : ?>
                        <li style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #dcdcde;">
                            <div>
                                <strong><?php echo esc_html( $ctpl['name'] ); ?></strong>
                                <?php if ( $active_tpl === 'custom_' . $cid ) : ?>
                                    <span style="background:#00a32a;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;">ACTIVA</span>
                                <?php endif; ?>
                                <br><small style="color:#646970;"><?php echo esc_html( strtoupper( $ctpl['format'] ) . ' · ' . count( $ctpl['modules'] ) . ' módulos' ); ?></small>
                            </div>
                            <a href="<?php echo esc_url( add_query_arg( 'edit_tpl', $cid, $base_url ) ); ?>" class="button button-small">Editar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card" style="padding:16px 20px;margin:0;">
            <h3 style="margin-top:0;">Importar plantilla (.json)</h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'bap_tpl_builder_action' ); ?>
                <input type="file" name="bap_tpl_json_file" accept=".json" required style="margin-bottom:10px;width:100%;" />
                <button type="submit" name="bap_import_tpl_json" class="button button-secondary">Importar plantilla JSON</button>
            </form>
        </div>
    </div>
    <?php
}
