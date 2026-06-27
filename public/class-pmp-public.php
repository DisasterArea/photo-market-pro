<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Public {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
        add_shortcode( 'pmp_gallery', [ __CLASS__, 'shortcode_gallery' ] );

        add_action( 'wp_ajax_pmp_v2_gallery_filter',         [ __CLASS__, 'ajax_filter_gallery' ] );
        add_action( 'wp_ajax_nopriv_pmp_v2_gallery_filter',  [ __CLASS__, 'ajax_filter_gallery' ] );
        add_action( 'wp_ajax_pmp_v2_gallery_opts',           [ __CLASS__, 'ajax_get_filter_options' ] );
        add_action( 'wp_ajax_nopriv_pmp_v2_gallery_opts',    [ __CLASS__, 'ajax_get_filter_options' ] );

        add_action( 'woocommerce_view_order', [ __CLASS__, 'show_order_downloads' ], 15 );
        add_action( 'woocommerce_thankyou',   [ __CLASS__, 'show_order_downloads' ], 15 );
    }

    public static function enqueue_scripts() {
        wp_enqueue_style(  'pmp-public', PMP_URL . 'public/css/gallery.css', [], PMP_VERSION );
        wp_add_inline_style( 'pmp-public', self::override_css() );
        wp_enqueue_script( 'pmp-public', PMP_URL . 'public/js/gallery.js', [ 'jquery' ], PMP_VERSION, true );
        wp_localize_script( 'pmp-public', 'PMP_Public', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pmp_public_nonce' ),
        ] );
    }

    /* ── High-specificity inline CSS overrides ─────────────── */

    private static function override_css() {
        return '
#pmp-filters input[type="date"].pmp-filter-select,
#pmp-filters input[type="date"].pmp-date-input {
    height: 40px !important;
    padding: 0 12px !important;
    background: #2a2a2a !important;
    border: 1px solid #2e2e2e !important;
    border-radius: 10px !important;
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
    opacity: 1 !important;
    color-scheme: dark !important;
    font-size: 13px !important;
    line-height: 1 !important;
    padding: 0 12px !important;
    box-sizing: border-box !important;
    width: 100% !important;
}
#pmp-filters input[type="date"]::-webkit-datetime-edit,
#pmp-filters input[type="date"]::-webkit-datetime-edit-fields-wrapper,
#pmp-filters input[type="date"]::-webkit-datetime-edit-day-field,
#pmp-filters input[type="date"]::-webkit-datetime-edit-month-field,
#pmp-filters input[type="date"]::-webkit-datetime-edit-year-field {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
    opacity: 1 !important;
    background: transparent !important;
}
#pmp-filters input[type="date"]::-webkit-datetime-edit-text {
    color: #aaa !important;
    -webkit-text-fill-color: #aaa !important;
    opacity: 1 !important;
}
#pmp-filters .pmp-btn-reset {
    height: 40px !important;
    padding: 0 16px !important;
    margin: 0 !important;
    background: transparent !important;
    border: 1px solid #2e2e2e !important;
    border-radius: 10px !important;
    color: #888 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
}
#pmp-filters .pmp-btn-reset:hover {
    color: #fff !important;
    border-color: #555 !important;
    background: #1e1e1e !important;
}
        ';
    }

    /* ── Shortcode ──────────────────────────────────────────── */

    public static function shortcode_gallery( $atts ) {
        $atts  = shortcode_atts( [ 'count' => get_option( 'pmp_gallery_count', 6 ) ], $atts );
        $count = max( 1, intval( $atts['count'] ) );
        $photos = self::query_photos( [], $count );

        ob_start(); ?>
        <div class="pmp-gallery-wrap" data-count="<?php echo esc_attr( $count ); ?>">

          <div class="pmp-filters" id="pmp-filters">
            <div class="pmp-filter-row">
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">📍 Località</label>
                <div class="pmp-select-wrap">
                  <select id="pmp-f-location" class="pmp-filter-select">
                    <option value="">Scegli</option>
                  </select>
                </div>
              </div>
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">🏷 Categoria</label>
                <div class="pmp-select-wrap">
                  <select id="pmp-f-category" class="pmp-filter-select">
                    <option value="">Scegli</option>
                  </select>
                </div>
              </div>
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">📅 Data da</label>
                <input type="date" id="pmp-f-date-from" class="pmp-filter-select pmp-date-input">
              </div>
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">📅 Data a</label>
                <input type="date" id="pmp-f-date-to" class="pmp-filter-select pmp-date-input">
              </div>
              <div class="pmp-filter-group pmp-filter-btns">
                <button class="pmp-btn-reset" id="pmp-reset-filter">✕ Cancella</button>
              </div>
            </div>
            <div class="pmp-active-filters" id="pmp-active-filters" style="display:none;"></div>
          </div>

          <div class="pmp-masonry" id="pmp-masonry">
            <?php foreach ( $photos as $p ) echo self::render_card( $p ); ?>
            <?php if ( empty( $photos ) ) echo '<p class="pmp-no-results">Nessuna foto disponibile.</p>'; ?>
          </div>
          <div class="pmp-gallery-loading" id="pmp-gallery-loading" style="display:none;">
            <div class="pmp-spinner-wrap"><span class="pmp-spinner"></span><span>Caricamento...</span></div>
          </div>
        </div>
        <?php return ob_get_clean();
    }

    public static function render_card( $photo ) {
        $url   = $photo['product_id'] ? get_permalink( (int) $photo['product_id'] ) : '#';
        $thumb = '';
        if ( ! empty( $photo['preview_image_id'] ) )
            $thumb = wp_get_attachment_image_url( (int) $photo['preview_image_id'], 'large' );
        if ( ! $thumb && ! empty( $photo['preview_url'] ) )
            $thumb = $photo['preview_url'];

        $price = $photo['product_id'] ? get_post_meta( (int) $photo['product_id'], '_price', true ) : '';

        ob_start(); ?>
        <div class="pmp-card">
          <a href="<?php echo esc_url( $url ); ?>" class="pmp-card-link">
            <?php if ( $thumb ): ?>
              <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $photo['title'] ); ?>" class="pmp-card-img" loading="lazy">
            <?php else: ?>
              <div class="pmp-card-no-img">📷</div>
            <?php endif; ?>
            <div class="pmp-card-overlay">
              <div class="pmp-card-tags">
                <?php if ( $photo['location'] ): ?><span class="pmp-tag" data-filter="location" data-value="<?php echo esc_attr( $photo['location'] ); ?>" title="Szűrés helyszínre">📍 <?php echo esc_html( $photo['location'] ); ?></span><?php endif; ?>
                <?php if ( $photo['category'] ): ?><span class="pmp-tag" data-filter="category" data-value="<?php echo esc_attr( $photo['category'] ); ?>" title="Szűrés kategóriára">🏷 <?php echo esc_html( $photo['category'] ); ?></span><?php endif; ?>
                <?php if ( $photo['shot_date'] ): ?><span class="pmp-tag" data-filter="date" data-value="<?php echo esc_attr( $photo['shot_date'] ); ?>" title="Szűrés dátumra">📅 <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $photo['shot_date'] ) ) ); ?></span><?php endif; ?>
              </div>
            </div>
            <div class="pmp-card-bottom">
              <?php if ( $price > 0 ): ?>
                <span class="pmp-card-price"><?php echo number_format( (float) $price, 2, ',', '.' ); ?> €</span>
              <?php endif; ?>
              <span class="pmp-card-cta">🛒</span>
            </div>
          </a>
        </div>
        <?php return ob_get_clean();
    }

    /* ── AJAX: filter gallery ───────────────────────────────── */

    public static function ajax_filter_gallery() {
        // No strict nonce check - use loose verification
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'pmp_public_nonce' ) ) {
            wp_send_json_error( 'Nonce error' );
        }
        global $wpdb;

        $location  = sanitize_text_field( $_POST['location']  ?? '' );
        $category  = sanitize_text_field( $_POST['category']  ?? '' );
        $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_POST['date_to']   ?? '' );
        $count     = max( 1, intval( $_POST['count'] ?? 25 ) );
        $offset    = max( 0, intval( $_POST['offset'] ?? 0 ) );

        $filters = compact( 'location', 'category', 'date_from', 'date_to' );
        $photos  = self::query_photos( $filters, $count, $offset );

        $html = '';
        foreach ( $photos as $p ) $html .= self::render_card( $p );
        if ( ! $html && $offset === 0 ) $html = '<p class="pmp-no-results">Nessun risultato per i filtri selezionati.</p>';

        wp_send_json_success( [ 'html' => $html, 'count' => count( $photos ), 'has_more' => count( $photos ) === $count ] );
    }

    /* ── AJAX: chained filter options ───────────────────────── */

    public static function ajax_get_filter_options() {
        global $wpdb;

        $location = sanitize_text_field( $_POST['location'] ?? '' );
        $category = sanitize_text_field( $_POST['category'] ?? '' );

        // All locations – no filter
        $locations = $wpdb->get_col(
            "SELECT DISTINCT location FROM {$wpdb->prefix}pmp_photos
             WHERE location != '' ORDER BY location ASC"
        );

        // Categories – filtered by location if set
        $cat_sql = "SELECT DISTINCT category FROM {$wpdb->prefix}pmp_photos WHERE category != ''";
        if ( $location ) $cat_sql .= $wpdb->prepare( " AND location = %s", $location );
        $cat_sql .= " ORDER BY category ASC";
        $categories = $wpdb->get_col( $cat_sql );

        // Date range available – filtered by both
        $date_sql = "SELECT MIN(shot_date), MAX(shot_date) FROM {$wpdb->prefix}pmp_photos WHERE shot_date IS NOT NULL";
        if ( $location ) $date_sql .= $wpdb->prepare( " AND location = %s", $location );
        if ( $category ) $date_sql .= $wpdb->prepare( " AND category = %s", $category );
        $date_row = $wpdb->get_row( $date_sql, ARRAY_N );

        wp_send_json_success( [
            'locations'  => array_values( $locations ),
            'categories' => array_values( $categories ),
            'date_min'   => $date_row[0] ?? '',
            'date_max'   => $date_row[1] ?? '',
        ] );
    }

    /* ── Query helper ───────────────────────────────────────── */

    private static function query_photos( $filters, $count, $offset = 0 ) {
        global $wpdb;
        $where = "WHERE 1=1";
        if ( ! empty( $filters['location'] ) )  $where .= $wpdb->prepare( " AND location = %s",   $filters['location'] );
        if ( ! empty( $filters['category'] ) )  $where .= $wpdb->prepare( " AND category = %s",   $filters['category'] );
        if ( ! empty( $filters['date_from'] ) ) $where .= $wpdb->prepare( " AND shot_date >= %s", $filters['date_from'] );
        if ( ! empty( $filters['date_to'] ) )   $where .= $wpdb->prepare( " AND shot_date <= %s", $filters['date_to'] );
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pmp_photos $where ORDER BY shot_date DESC, id DESC LIMIT %d OFFSET %d", $count, $offset ),
            ARRAY_A
        );
    }

    /* ── Order downloads ────────────────────────────────────── */

    public static function show_order_downloads( $order_id ) {
        $tokens = PMP_Download::get_tokens_for_order( $order_id );
        if ( empty( $tokens ) ) return;

        $expiry_h = get_option( 'pmp_download_expiry_hours', 48 );
        $max_dl   = get_option( 'pmp_download_max_count', 3 );

        echo '<section class="pmp-order-downloads">';
        echo '<h2>📷 Download delle foto</h2>';
        echo '<p style="font-size:14px;color:#aaa;margin-bottom:12px;">Hai ricevuto anche una email con i link di download. I link sono validi per <strong>' . intval( $expiry_h ) . ' ore</strong> e possono essere utilizzati al massimo <strong>' . intval( $max_dl ) . ' volte</strong>.</p>';
        echo '<table class="woocommerce-table shop_table"><thead><tr><th>Foto</th><th>Scadenza</th><th>Scaricato</th><th></th></tr></thead><tbody>';
        foreach ( $tokens as $t ) {
            $expired   = strtotime( $t['expires_at'] ) < time();
            $exhausted = intval( $t['download_count'] ) >= intval( $t['max_downloads'] );
            echo '<tr>';
            echo '<td>' . esc_html( $t['photo_title'] ?: '–' ) . '</td>';
            echo '<td>' . esc_html( wp_date( 'd/m/Y H:i', strtotime( $t['expires_at'] ) ) ) . '</td>';
            echo '<td>' . intval( $t['download_count'] ) . '/' . intval( $t['max_downloads'] ) . '</td>';
            echo '<td>';
            if      ( $expired )   echo '<span style="color:#dc3232;">Scaduto</span>';
            elseif  ( $exhausted ) echo '<span style="color:#dc3232;">Esaurito</span>';
            else                   echo '<a href="' . esc_url( PMP_Download::get_download_url( $t['token'] ) ) . '" class="button">Scarica</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></section>';
    }
}
