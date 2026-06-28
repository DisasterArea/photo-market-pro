<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Photo {

    public static function init() {
        // Cart: edit options injected into simple products that are PMP photos
        add_action( 'woocommerce_before_add_to_cart_button', [ __CLASS__, 'render_edit_options_frontend' ] );
        add_filter( 'woocommerce_add_cart_item_data',        [ __CLASS__, 'add_cart_item_data' ], 10, 3 );
        add_filter( 'woocommerce_get_item_data',             [ __CLASS__, 'display_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'add_order_item_meta' ], 10, 4 );
        add_action( 'woocommerce_before_calculate_totals',   [ __CLASS__, 'recalculate_cart_item_price' ] );
        add_action( 'woocommerce_product_thumbnails',        [ __CLASS__, 'render_image_overlay' ] );
        add_action( 'woocommerce_after_add_to_cart_button',  [ __CLASS__, 'render_back_button' ] );
    }

    /* ── Image overlay: title + price + lightbox trigger ─── */

    public static function render_image_overlay() {
        global $product;
        if ( ! $product ) return;
        $photo = self::get_by_product( $product->get_id() );
        if ( ! $photo ) return;

        $title    = get_the_title( $product->get_id() );
        $price    = wc_price( $product->get_price() );
        $img_url  = wp_get_attachment_image_url( $product->get_image_id(), 'full' ) ?: wc_placeholder_img_src( 'full' );
        ?>
        <div class="pmp-product-img-overlay">
            <div class="pmp-product-img-bottom">
                <span class="pmp-product-img-title"><?php echo esc_html( $title ); ?></span>
                <span class="pmp-product-img-price"><?php echo wp_kses_post( $price ); ?></span>
            </div>
        </div>
        <script>
        (function(){
            function initProductLightbox() {
                var galleryImg = document.querySelector('.woocommerce-product-gallery__image img');
                if ( ! galleryImg ) return;
                var wrap = galleryImg.closest('.woocommerce-product-gallery__image') || galleryImg.parentElement;
                wrap.style.cursor = 'zoom-in';
                wrap.addEventListener('click', function(e) {
                    e.preventDefault();
                    var src = '<?php echo esc_js( $img_url ); ?>';
                    var lb = document.getElementById('pmp-lightbox');
                    if ( ! lb ) {
                        lb = document.createElement('div');
                        lb.id = 'pmp-lightbox';
                        lb.setAttribute('role','dialog');
                        lb.innerHTML = '<button id="pmp-lb-close">✕</button>' +
                            '<div id="pmp-lb-img-wrap"><img id="pmp-lb-img" src="" alt="">' +
                            '<div id="pmp-lb-overlay"><div id="pmp-lb-tags"></div>' +
                            '<div id="pmp-lb-bottom"><span id="pmp-lb-title"></span>' +
                            '<a id="pmp-lb-buy" href="#"></a></div></div></div>';
                        document.body.appendChild(lb);
                        lb.addEventListener('click', function(ev) { if(ev.target===lb) closeLb(); });
                        document.getElementById('pmp-lb-close').addEventListener('click', closeLb);
                        document.addEventListener('keydown', function(ev){ if(ev.key==='Escape') closeLb(); });
                        lb.addEventListener('touchmove', function(ev){ ev.preventDefault(); }, { passive: false });
                    }
                    document.getElementById('pmp-lb-img').src = src;
                    document.getElementById('pmp-lb-prev') && (document.getElementById('pmp-lb-prev').style.display='none');
                    document.getElementById('pmp-lb-next') && (document.getElementById('pmp-lb-next').style.display='none');
                    document.getElementById('pmp-lb-tags').innerHTML = '';
                    document.getElementById('pmp-lb-title').textContent = '<?php echo esc_js( $title ); ?>';
                    document.getElementById('pmp-lb-buy').style.display = 'none';
                    lb.classList.add('open');
                    document.body.style.overflow = 'hidden';
                });
            }
            function closeLb() {
                var lb = document.getElementById('pmp-lightbox');
                if (lb) { lb.classList.remove('open'); document.body.style.overflow=''; }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initProductLightbox);
            } else {
                initProductLightbox();
            }
        })();
        </script>
        <?php
    }

    /* ── Frontend edit options ────────────────────────────── */

    public static function render_edit_options_frontend() {
        global $product;
        if ( ! $product ) return;

        // Only show on PMP photo products
        $photo = self::get_by_product( $product->get_id() );
        if ( ! $photo ) return;

        $edit_options = self::get_photo_edit_options( $photo['id'] );
        if ( empty( $edit_options ) ) return;

        echo '<div class="pmp-edit-options">';
        echo '<h4 class="pmp-edit-title">Opzioni di editing (opzionale)</h4>';
        foreach ( $edit_options as $opt ) {
            echo '<label class="pmp-edit-card">';
            echo '<input type="checkbox" name="pmp_edit_option[]" value="' . esc_attr( $opt['id'] ) . '" class="pmp-edit-option-cb" data-price="' . esc_attr( $opt['price'] ) . '">';
            echo '<div class="pmp-edit-card-body">';
            echo '<span class="pmp-edit-card-name">' . esc_html( $opt['name'] ) . '</span>';
            if ( $opt['description'] ) echo '<span class="pmp-edit-card-desc">' . esc_html( $opt['description'] ) . '</span>';
            echo '</div>';
            echo '<span class="pmp-edit-price">+' . number_format( $opt['price'], 2, ',', '.' ) . ' €</span>';
            echo '</label>';
        }
        echo '<div class="pmp-edit-note-wrap" id="pmp-edit-note-wrap" style="display:none;">';
        echo '<label class="pmp-edit-note-label">Note / richiesta:</label>';
        echo '<textarea name="pmp_edit_request" rows="3" class="pmp-edit-textarea" placeholder="Descrivi nel dettaglio cosa desideri..."></textarea>';
        echo '</div>';
        echo '<p id="pmp-price-preview" class="pmp-edit-preview"></p>';
        echo '</div>';
        ?>
        <script>
        (function(){
            var base = <?php echo floatval( $product->get_price() ); ?>;
            function fmt(n){ return n.toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2}); }
            function update(){
                var extra = 0;
                var checked = document.querySelectorAll('.pmp-edit-option-cb:checked');
                checked.forEach(function(cb){ extra += parseFloat(cb.dataset.price)||0; });
                var el = document.getElementById('pmp-price-preview');
                el.innerHTML = extra > 0
                    ? 'Opzioni: <strong>+' + fmt(extra) + ' €</strong> &nbsp;→&nbsp; Totale: <strong>' + fmt(base+extra) + ' €</strong>'
                    : '';
                var noteWrap = document.getElementById('pmp-edit-note-wrap');
                if ( noteWrap ) noteWrap.style.display = checked.length > 0 ? 'block' : 'none';
            }
            document.querySelectorAll('.pmp-edit-option-cb').forEach(function(cb){ cb.addEventListener('change', update); });
        })();
        </script>
        <?php
    }

    /* ── Cart ─────────────────────────────────────────────── */

    public static function add_cart_item_data( $data, $product_id, $variation_id ) {
        $photo = self::get_by_product( $product_id );
        if ( ! $photo ) return $data;

        $selected = array_map( 'intval', (array)( $_POST['pmp_edit_option'] ?? [] ) );
        $request  = sanitize_textarea_field( $_POST['pmp_edit_request'] ?? '' );

        $extra  = 0;
        $labels = [];
        foreach ( $selected as $id ) {
            $opt = self::get_edit_option( $id );
            if ( $opt ) { $extra += floatval( $opt['price'] ); $labels[] = $opt['name']; }
        }

        if ( ! empty( $labels ) || $request ) {
            $data['pmp_edit_options'] = $selected;
            $data['pmp_edit_labels']  = $labels;
            $data['pmp_edit_request'] = $request;
            $data['pmp_extra_price']  = $extra;
            $data['unique_key']       = md5( microtime() . rand() );
        }
        return $data;
    }

    public static function render_back_button() {
        global $product;
        if ( ! $product ) return;
        if ( ! self::get_by_product( $product->get_id() ) ) return;

        $referer  = wp_get_referer();
        if ( $referer && strpos( $referer, home_url() ) !== false ) {
            $back_url = $referer;
        } else {
            $back_url = home_url( '/' );
        }
        // Strip existing fragment, add #search
        $back_url = preg_replace( '/#.*$/', '', $back_url ) . '#search';

        echo '<a href="' . esc_url( $back_url ) . '" class="pmp-back-btn">← Torna alla ricerca</a>';
    }

    public static function recalculate_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        foreach ( $cart->get_cart() as $item ) {
            if ( isset( $item['pmp_extra_price'] ) && floatval( $item['pmp_extra_price'] ) > 0 ) {
                $base = floatval( $item['data']->get_regular_price() );
                $item['data']->set_price( $base + floatval( $item['pmp_extra_price'] ) );
            }
        }
    }

    public static function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['pmp_edit_labels'] ) )
            $item_data[] = [ 'key' => 'Szerkesztési opciók', 'value' => implode( ', ', $cart_item['pmp_edit_labels'] ) ];
        if ( ! empty( $cart_item['pmp_edit_request'] ) )
            $item_data[] = [ 'key' => 'Megjegyzés', 'value' => $cart_item['pmp_edit_request'] ];
        return $item_data;
    }

    public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['pmp_edit_labels'] ) )
            $item->add_meta_data( 'Szerkesztési opciók', implode( ', ', $values['pmp_edit_labels'] ) );
        if ( ! empty( $values['pmp_edit_request'] ) )
            $item->add_meta_data( 'Megjegyzés', $values['pmp_edit_request'] );
        if ( ! empty( $values['pmp_edit_options'] ) )
            $item->add_meta_data( '_pmp_edit_option_ids', implode( ',', $values['pmp_edit_options'] ), true );
    }

    /* ── Save photo + WC product ──────────────────────────── */

    /**
     * Generate unique title: location_date_N
     * e.g. budapest_20240315_1, budapest_20240315_2 ...
     */
    public static function generate_title( $location, $shot_date, $existing_photo_id = 0, $img_id = 0 ) {
        // Build slug-style base
        $loc_slug  = strtolower( sanitize_title( $location ) );
        $date_slug = $shot_date ? date( 'dmY', strtotime( $shot_date ) ) : date( 'dmY' );
        $base      = $loc_slug . '_' . $date_slug;

        // Use attachment ID as unique suffix when available (avoids race condition on bulk upload)
        if ( $img_id ) {
            return $base . '_' . $img_id;
        }

        // Fallback: find MAX existing suffix number
        global $wpdb;
        $exclude = $existing_photo_id ? $wpdb->prepare( ' AND id != %d', $existing_photo_id ) : '';
        $max     = (int) $wpdb->get_var(
            "SELECT MAX(CAST(SUBSTRING_INDEX(title, '_', -1) AS UNSIGNED)) FROM {$wpdb->prefix}pmp_photos WHERE title LIKE '" . esc_sql( $base ) . "_%'" . $exclude
        );

        return $base . '_' . ( $max + 1 );
    }

    public static function save( $data, $photo_id = 0 ) {
        global $wpdb;

        $location    = sanitize_text_field( $data['location'] ?? '' );
        $category    = sanitize_text_field( $data['category'] ?? '' );
        $shot_date   = sanitize_text_field( $data['shot_date'] ?? '' );
        $price       = floatval( str_replace( ',', '.', $data['price'] ?? 0 ) );
        $img_id      = intval( $data['preview_image_id'] ?? 0 );
        $preview_url = esc_url_raw( $data['preview_url'] ?? '' );
        $use_ext     = intval( $data['use_external'] ?? 0 );
        $ext_key     = sanitize_text_field( $data['external_key'] ?? '' );
        $dl_url      = esc_url_raw( $data['download_url'] ?? '' );
        $opt_ids     = array_map( 'intval', (array)( $data['edit_option_ids'] ?? [] ) );

        // Title: always auto-generate from location + date + attachment ID (unique)
        $title = self::generate_title( $location, $shot_date, $photo_id, $img_id );

        // ── WC Product ──────────────────────────────────────
        $product_id = 0;
        if ( $photo_id ) {
            $existing   = self::get_by_id( $photo_id );
            $product_id = $existing ? intval( $existing['product_id'] ) : 0;
        }

        if ( $product_id && ! get_post( $product_id ) ) $product_id = 0;

        if ( $product_id ) {
            wp_update_post( [ 'ID' => $product_id, 'post_title' => $title, 'post_status' => 'publish' ] );
        } else {
            $product_id = wp_insert_post( [
                'post_title'  => $title,
                'post_type'   => 'product',
                'post_status' => 'publish',
            ] );
        }

        if ( is_wp_error( $product_id ) || ! $product_id ) return false;

        // Use WC Product object for reliable type + price setting
        $product = new WC_Product_Simple( $product_id );
        $product->set_name( $title );
        $product->set_status( 'publish' );
        $product->set_virtual( true );
        $product->set_downloadable( true );
        $product->set_catalog_visibility( 'visible' );
        $product->set_stock_status( 'instock' );
        if ( $price > 0 ) {
            $product->set_price( $price );
            $product->set_regular_price( $price );
        }
        if ( $img_id ) $product->set_image_id( $img_id );

        // Store PMP flag on product
        $product->update_meta_data( '_pmp_photo', '1' );
        $product->update_meta_data( '_pmp_location', $location );
        $product->update_meta_data( '_pmp_category', $category );
        $product->update_meta_data( '_pmp_shot_date', $shot_date );

        $product->save();

        // ── PMP photos table ────────────────────────────────
        $photo_data = [
            'product_id'       => $product_id,
            'title'            => $title,
            'location'         => $location,
            'category'         => $category,
            'shot_date'        => $shot_date ?: null,
            'preview_image_id' => $img_id ?: null,
            'preview_url'      => $preview_url ?: null,
            'use_external'     => $use_ext,
            'external_key'     => $ext_key,
            'download_url'     => $dl_url,
        ];
        $table = $wpdb->prefix . 'pmp_photos';

        if ( $photo_id ) {
            $wpdb->update( $table, $photo_data, [ 'id' => $photo_id ] );
        } else {
            $wpdb->insert( $table, $photo_data );
            $photo_id = $wpdb->insert_id;
        }

        // ── Edit options ────────────────────────────────────
        $wpdb->delete( $wpdb->prefix . 'pmp_photo_edit_options', [ 'photo_id' => $photo_id ] );
        foreach ( $opt_ids as $oid ) {
            if ( $oid ) $wpdb->insert( $wpdb->prefix . 'pmp_photo_edit_options', [
                'photo_id'       => $photo_id,
                'edit_option_id' => $oid,
            ] );
        }

        return $photo_id;
    }

    public static function delete( $photo_id ) {
        global $wpdb;
        $photo = self::get_by_id( $photo_id );
        if ( ! $photo ) return;
        if ( $photo['product_id'] ) wp_delete_post( $photo['product_id'], true );
        $wpdb->delete( $wpdb->prefix . 'pmp_photos', [ 'id' => $photo_id ] );
        $wpdb->delete( $wpdb->prefix . 'pmp_photo_edit_options', [ 'photo_id' => $photo_id ] );
    }

    /* ── DB helpers ───────────────────────────────────────── */

    public static function get_by_product( $product_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pmp_photos WHERE product_id = %d", $product_id
        ), ARRAY_A );
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pmp_photos WHERE id = %d", $id
        ), ARRAY_A );
    }

    public static function get_all_edit_options() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}pmp_edit_options WHERE active=1 ORDER BY sort_order ASC", ARRAY_A
        );
    }

    public static function get_edit_option( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pmp_edit_options WHERE id=%d", $id
        ), ARRAY_A );
    }

    public static function get_photo_edit_options( $photo_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT o.* FROM {$wpdb->prefix}pmp_edit_options o
             INNER JOIN {$wpdb->prefix}pmp_photo_edit_options po ON o.id=po.edit_option_id
             WHERE po.photo_id=%d AND o.active=1 ORDER BY o.sort_order ASC", $photo_id
        ), ARRAY_A );
    }

    public static function get_photo_edit_option_ids( $photo_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT edit_option_id FROM {$wpdb->prefix}pmp_photo_edit_options WHERE photo_id=%d", $photo_id
        ) );
    }

    public static function get_locations() {
        global $wpdb;
        return $wpdb->get_col(
            "SELECT DISTINCT location FROM {$wpdb->prefix}pmp_photos WHERE location != '' ORDER BY location ASC"
        );
    }

    public static function get_categories() {
        global $wpdb;
        return $wpdb->get_col(
            "SELECT DISTINCT category FROM {$wpdb->prefix}pmp_photos WHERE category != '' ORDER BY category ASC"
        );
    }
}
