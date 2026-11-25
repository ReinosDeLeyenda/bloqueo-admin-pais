<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$base_url = admin_url( 'admin.php?page=bap-plantillas&subtab=telegram' );
$types    = bap_get_tg_template_types();

$tokens_by_type = [
    'single' => [ '{badge}', '{site_name}', '{ip}', '{country}', '{asn}', '{risk}', '{reason}', '{uri}', '{time}' ],
    'digest' => [ '{badge}', '{site_name}', '{period}', '{total_hits}', '{unique_ips}', '{top_countries_list}', '{events_table}', '{top_asns_text}' ],
    'login'  => [ '{status_icon}', '{badge}', '{site_name}', '{asn_alert}', '{user_login}', '{user_email}', '{ip}', '{country}', '{asn}', '{last_asn}', '{active_asns}', '{time}', '{user_agent}' ],
];
$tokens = $tokens_by_type[ $active_type ] ?? $tokens_by_type['single'];
?>
<div>
    <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach ( $types as $t_key => $t_label ) :
            $is_cur = ( $active_type === $t_key );
        ?>
            <a href="<?php echo esc_url( add_query_arg( 'tpl_type', $t_key, $base_url ) ); ?>" class="button <?php echo $is_cur ? 'button-primary' : 'button-secondary'; ?>">
                <?php echo esc_html( $t_label ); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;">
        <div class="card" style="flex:1.4;min-width:440px;max-width:740px;padding:18px 22px;margin:0;">
            <h2 style="margin-top:0;">Diseñador Rich Text Telegram: <?php echo esc_html( $types[ $active_type ] ); ?></h2>
            <p class="description">Da formato visual con la barra de herramientas o inserta variables dinámicas con un clic. Se renderiza como <strong>Rich Article HTML</strong> en Telegram (con respaldo automático).</p>

            <div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:8px 10px;margin:12px 0;">
                <div style="font-size:11px;font-weight:700;color:#50575e;margin-bottom:6px;">Variables disponibles (clic para insertar en el cursor):</div>
                <div style="display:flex;flex-wrap:wrap;gap:5px;">
                    <?php foreach ( $tokens as $tok ) : ?>
                        <button type="button" class="button button-small bap-tg-insert-var" data-var="<?php echo esc_attr( $tok ); ?>"><code><?php echo esc_html( $tok ); ?></code></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="bap-tg-toolbar" style="display:flex;flex-wrap:wrap;gap:4px;background:#f0f0f1;border:1px solid #c3c4c7;border-bottom:none;padding:6px 8px;border-radius:4px 4px 0 0;">
                <button type="button" class="button button-small" data-cmd="bold" title="Negrita"><b>B</b></button>
                <button type="button" class="button button-small" data-cmd="italic" title="Cursiva"><i>I</i></button>
                <button type="button" class="button button-small" data-cmd="underline" title="Subrayado"><u>U</u></button>
                <button type="button" class="button button-small" data-cmd="strikeThrough" title="Tachado"><s>S</s></button>
                <button type="button" class="button button-small" data-tag="h2" title="Título H2">H2</button>
                <button type="button" class="button button-small" data-tag="h3" title="Subtítulo H3">H3</button>
                <button type="button" class="button button-small" data-tag="code" title="Código monoespaciado">&lt;code&gt;</button>
                <button type="button" class="button button-small" data-tag="quote" title="Cita desplegable">Cita Expandible</button>
                <button type="button" class="button button-small" data-tag="spoiler" title="Spoiler Telegram">Spoiler</button>
                <button type="button" class="button button-small" data-cmd="insertUnorderedList" title="Lista">&bull; Lista</button>
                <button type="button" class="button button-small" id="bap-tg-insert-table" title="Insertar tabla">+ Tabla</button>
                <button type="button" class="button button-small" id="bap-tg-toggle-code" style="margin-left:auto;">Ver HTML</button>
            </div>

            <form id="bap-tg-builder-form" method="post" action="<?php echo esc_url( add_query_arg( 'tpl_type', $active_type, $base_url ) ); ?>">
                <?php wp_nonce_field( 'bap_tg_builder_action' ); ?>
                <input type="hidden" name="bap_tg_tpl_type" id="bap_tg_tpl_type" value="<?php echo esc_attr( $active_type ); ?>" />
                <textarea name="bap_tg_tpl_html" id="bap_tg_tpl_html" rows="12" class="large-text code" style="display:none;border-radius:0 0 4px 4px;"><?php echo esc_textarea( $current_html ); ?></textarea>

                <div id="bap-tg-wysiwyg" contenteditable="true" style="min-height:240px;max-height:420px;overflow-y:auto;background:#ffffff;color:#1d2327;border:1px solid #c3c4c7;border-radius:0 0 4px 4px;padding:12px 14px;font-size:13px;line-height:1.55;">
                    <?php echo wp_kses_post( $current_html ); ?>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:14px;">
                    <button type="submit" name="bap_save_tg_tpl" class="button button-primary">Guardar plantilla Telegram</button>
                    <button type="submit" name="bap_test_tg_tpl" class="button button-secondary">Enviar prueba a Telegram</button>
                    <button type="submit" name="bap_reset_tg_tpl" class="button button-link-delete" style="margin-left:auto;" onclick="return confirm('¿Restaurar el diseño por defecto de esta plantilla?');">Restaurar por defecto</button>
                </div>
            </form>
        </div>

        <div class="card" style="flex:1;min-width:340px;max-width:480px;padding:16px 20px;margin:0;background:#0e1621;color:#f5f5f5;border:1px solid #232e3c;">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #232e3c;padding-bottom:8px;margin-bottom:12px;">
                <strong style="color:#6ab2f2;">Vista previa en Telegram (Rich Article)</strong>
                <span style="font-size:11px;color:#7f91a4;">En vivo</span>
            </div>
            <div id="bap-tg-live-preview" style="background:#182533;border-radius:10px;padding:14px 16px;font-size:13px;line-height:1.5;color:#f5f5f5;overflow-x:auto;"></div>
        </div>
    </div>
</div>
