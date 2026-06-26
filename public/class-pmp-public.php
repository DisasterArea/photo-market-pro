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
        wp_enqueue_script( 'pmp-public', PMP_URL . 'public/js/gallery.js', [ 'jquery' ], PMP_VERSION, true );
        wp_localize_script( 'pmp-public', 'PMP_Public', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pmp_public_nonce' ),
        ] );
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
                <label class="pmp-filter-label">📍 Helyszín</label>
                <div class="pmp-select-wrap">
                  <select id="pmp-f-location" class="pmp-filter-select">
                    <option value="">Minden helyszín</option>
                  </select>
                </div>
              </div>
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">🏷 Kategória</label>
                <div class="pmp-select-wrap">
                  <select id="pmp-f-category" class="pmp-filter-select">
                    <option value="">Minden kategória</option>
                  </select>
                </div>
              </div>
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">📅 Dátumtól</label>
                <input type="date" id="pmp-f-date-from" class="pmp-filter-select pmp-date-input">
              </div>
              <div class="pmp-filter-group">
                <label class="pmp-filter-label">📅 Dátumig</label>
                <input type="date" id="pmp-f-date-to" class="pmp-filter-select pmp-date-input">
              </div>
              <div class="pmp-filter-group pmp-filter-btns">
                <label class="pmp-filter-label" aria-hidden="true">&nbsp;</label>
                <button class="pmp-btn-reset" id="pmp-reset-filter">✕ Törlés</button>
              </div>
            </div>
            <div class="pmp-active-filters" id="pmp-active-filters" style="display:none;"></div>
          </div>

          <div class="pmp-masonry" id="pmp-masonry">
            <?php foreach ( $photos as $p ) echo self::render_card( $p ); ?>
            <?php if ( empty( $photos ) ) echo '<p class="pmp-no-results">Még nincsenek fotók.</p>'; ?>
          </div>
          <div class="pmp-gallery-loading" id="pmp-gallery-loading" style="display:none;">
            <div class="pmp-spinner-wrap"><span class="pmp-spinner"></span><span>Betöltés...</span></div>
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
                <?php if ( $photo['shot_date'] ): ?><span class="pmp-tag" data-filter="date" data-value="<?php echo esc_attr( $photo['shot_date'] ); ?>" title="Szűrés dátumra">📅 <?php echo esc_html( date_i18n( 'Y.m.d', strtotime( $photo['shot_date'] ) ) ); ?></span><?php endif; ?>
              </div>
            </div>
            <div class="pmp-card-bottom">
              <?php if ( $price > 0 ): ?>
                <span class="pmp-card-price"><?php echo number_format( (float) $price, 0, ',', '.' ); ?> Ft</span>
              <?php endif; ?>
              <span class="pmp-card-cta">Megvásárolom →</span>
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
        $count     = max( 1, intval( $_POST['count'] ?? 6 ) );

        $photos = self::query_photos( compact( 'location', 'category', 'date_from', 'date_to' ), $count );

        $html = '';
        foreach ( $photos as $p ) $html .= self::render_card( $p );
        if ( ! $html ) $html = '<p class="pmp-no-results">Nincs találat a megadott szűrőkre.</p>';

        wp_send_json_success( [ 'html' => $html, 'count' => count( $photos ) ] );
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

    private static function query_photos( $filters, $count ) {
        global $wpdb;
        $where = "WHERE 1=1";
        if ( ! empty( $filters['location'] ) )  $where .= $wpdb->prepare( " AND location = %s",   $filters['location'] );
        if ( ! empty( $filters['category'] ) )  $where .= $wpdb->prepare( " AND category = %s",   $filters['category'] );
        if ( ! empty( $filters['date_from'] ) ) $where .= $wpdb->prepare( " AND shot_date >= %s", $filters['date_from'] );
        if ( ! empty( $filters['date_to'] ) )   $where .= $wpdb->prepare( " AND shot_date <= %s", $filters['date_to'] );
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pmp_photos $where ORDER BY RAND() LIMIT %d", $count ),
            ARRAY_A
        );
    }

    /* ── Order downloads ────────────────────────────────────── */

    public static function show_order_downloads( $order_id ) {
        $tokens = PMP_Download::get_tokens_for_order( $order_id );
        if ( empty( $tokens ) ) return;
        echo '<section class="pmp-order-downloads"><h2>📷 Fotó letöltések</h2>';
        echo '<table class="woocommerce-table shop_table"><thead><tr><th>Fotó</th><th>Lejárat</th><th>Letöltve</th><th></th></tr></thead><tbody>';
        foreach ( $tokens as $t ) {
            $expired   = strtotime( $t['expires_at'] ) < time();
            $exhausted = intval( $t['download_count'] ) >= intval( $t['max_downloads'] );
            echo '<tr>';
            echo '<td>' . esc_html( $t['photo_title'] ?: '–' ) . '</td>';
            echo '<td>' . esc_html( wp_date( 'Y.m.d H:i', strtotime( $t['expires_at'] ) ) ) . '</td>';
            echo '<td>' . intval( $t['download_count'] ) . '/' . intval( $t['max_downloads'] ) . '</td>';
            echo '<td>';
            if      ( $expired )   echo '<span style="color:#dc3232;">Lejárt</span>';
            elseif  ( $exhausted ) echo '<span style="color:#dc3232;">Kimerült</span>';
            else                   echo '<a href="' . esc_url( PMP_Download::get_download_url( $t['token'] ) ) . '" class="button">Letöltés</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></section>';
    }
}
