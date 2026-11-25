<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function bap_seo_spam_analyze_html( $html, $site_host ) {
    $html = (string) $html;
    if ( '' === trim( $html ) || false === stripos( $html, '<a' ) ) { return []; }
    $hits = [];
    if ( preg_match_all( '/<a\b[^>]*style\s*=\s*["\x27][^"\x27]*(?:display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*0(?:px|em|rem)?\b|opacity\s*:\s*0\b|(?:left|top|text-indent)\s*:\s*-\d{3,}px)[^"\x27]*["\x27][^>]*href\s*=\s*["\x27](https?:\/\/[^"\x27]+)["\x27]/i', $html, $m ) ) {
        foreach ( $m[1] as $url ) {
            $h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
            if ( '' !== $h && $h !== $site_host ) { $hits[] = [ 'type' => 'Enlace oculto por CSS', 'url' => $url ]; }
        }
    }
    if ( preg_match_all( '/<a\b[^>]*href\s*=\s*["\x27](https?:\/\/[^"\x27]+)["\x27][^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER ) ) {
        $spam_kw  = '/\b(?:casino|poker|slot|betting|viagra|cialis|levitra|pharmacy|payday\s*loan|porn|xxx|escort|replica\s*watch)\b/i';
        $bad_tlds = '/\.(?:tk|ml|ga|cf|gq|top|xyz|icu|buzz|click|loan|win|bid|stream|gdn|pw)$/i';
        foreach ( $links as $lnk ) {
            $url    = trim( (string) $lnk[1] );
            $anchor = trim( wp_strip_all_tags( (string) $lnk[2] ) );
            $host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
            if ( '' === $host || $host === $site_host || substr( $host, -strlen( '.' . $site_host ) ) === '.' . $site_host ) { continue; }
            if ( preg_match( '/[\x{3040}-\x{30FF}\x{4E00}-\x{9FAF}]{3,}/u', $anchor ) ) { $hits[] = [ 'type' => 'Japanese Keyword Hack', 'url' => $url ]; continue; }
            if ( preg_match( $spam_kw, $url ) || preg_match( $spam_kw, $anchor ) ) { $hits[] = [ 'type' => 'SEO Spam (Pharma / Casino)', 'url' => $url ]; continue; }
            if ( preg_match( $bad_tlds, $host ) ) { $hits[] = [ 'type' => 'Dominio saliente sospechoso (TLD)', 'url' => $url ]; }
            elseif ( '' === $anchor && false === stripos( $lnk[2], '<img' ) ) { $hits[] = [ 'type' => 'Enlace saliente vacio / invisible', 'url' => $url ]; }
        }
    }
    return $hits;
}

function bap_scan_seo_spam_and_malicious_links() {
    global $wpdb;
    $site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $findings  = [];
    $posts     = $wpdb->get_results( "SELECT ID, post_title, post_type, post_content FROM `{$wpdb->posts}` WHERE post_status IN ('publish','draft','private','future') AND post_content LIKE '%<a %http%' ORDER BY ID DESC LIMIT 600" );
    foreach ( (array) $posts as $p ) {
        foreach ( bap_seo_spam_analyze_html( $p->post_content, $site_host ) as $hit ) {
            $findings[] = [ 'source' => 'Post #' . (int) $p->ID . ' (' . $p->post_type . ')', 'target' => $p->post_title ?: '(Sin titulo)', 'type' => $hit['type'], 'url' => mb_substr( $hit['url'], 0, 140 ) ];
        }
    }
    $comments = $wpdb->get_results( "SELECT comment_ID, comment_post_ID, comment_author, comment_content FROM `{$wpdb->comments}` WHERE comment_approved = '1' AND comment_content LIKE '%http%' ORDER BY comment_ID DESC LIMIT 400" );
    foreach ( (array) $comments as $c ) {
        foreach ( bap_seo_spam_analyze_html( $c->comment_content, $site_host ) as $hit ) {
            $findings[] = [ 'source' => 'Comentario #' . (int) $c->comment_ID . ' (Post #' . (int) $c->comment_post_ID . ')', 'target' => $c->comment_author ?: 'Anonimo', 'type' => $hit['type'], 'url' => mb_substr( $hit['url'], 0, 140 ) ];
        }
    }
    return [ 'posts_checked' => count( (array) $posts ), 'comments_checked' => count( (array) $comments ), 'findings' => $findings ];
}

function bap_render_seo_spam_scanner_box() {
    $scan = null;
    if ( isset( $_POST['bap_run_seo_spam_scan'] ) && current_user_can( 'manage_options' ) && check_admin_referer( 'bap_seo_spam_action', 'bap_seo_spam_nonce' ) ) {
        $scan = bap_scan_seo_spam_and_malicious_links();
    }
    echo '<div class="card" style="max-width:980px;margin-top:20px;padding:18px 22px;"><h2 style="margin-top:0;">Escaner de SEO Spam y Enlaces Salientes Maliciosos</h2>';
    echo '<p class="description">Analiza entradas, paginas y comentarios aprobados en busca de enlaces ocultos por CSS, Japanese Keyword Hack y spam Pharma/Casino.</p>';
    echo '<form method="post" style="margin:14px 0;">';
    wp_nonce_field( 'bap_seo_spam_action', 'bap_seo_spam_nonce' );
    echo '<button type="submit" name="bap_run_seo_spam_scan" class="button button-primary">Analizar SEO Spam y enlaces ahora</button></form>';
    if ( is_array( $scan ) ) {
        if ( empty( $scan['findings'] ) ) {
            echo '<div class="notice notice-success inline"><p><strong>Sin enlaces maliciosos ni SEO Spam.</strong> Revisados ' . (int) $scan['posts_checked'] . ' posts y ' . (int) $scan['comments_checked'] . ' comentarios.</p></div>';
        } else {
            echo '<div class="notice notice-error inline"><p><strong>Atencion: Se han detectado ' . count( $scan['findings'] ) . ' enlace(s) sospechoso(s):</strong></p></div><ul>';
            foreach ( $scan['findings'] as $f ) {
                echo '<li><code>' . esc_html( $f['source'] ) . '</code> - b>' . esc_html( $f['type'] ) . '</b>: <code>' . esc_html( $f['url'] ) . '</code></li>';
            }
            echo '</ul>';
        }
    }
    echo '</div>';
}