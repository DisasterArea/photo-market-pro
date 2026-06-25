<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Order {

    public static function init() {
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'handle_completed_order' ] );
        add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_order_meta_box' ] );
    }

    public static function handle_completed_order( $order_id ) {
        // Only run once
        if ( get_post_meta( $order_id, '_pmp_tokens_sent', true ) ) return;

        $order  = wc_get_order( $order_id );
        if ( ! $order ) return;

        $tokens_created = [];

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product || ! $product->get_meta( '_pmp_photo' ) ) continue;

            $photo = PMP_Photo::get_by_product( $product->get_id() );
            if ( ! $photo ) continue;

            $qty = $item->get_quantity();
            for ( $i = 0; $i < $qty; $i++ ) {
                $token = PMP_Download::create_token(
                    $order_id,
                    $item_id,
                    $photo['id'],
                    $order->get_billing_email()
                );
                $tokens_created[] = [
                    'photo_title'   => $photo['title'] ?: $product->get_name(),
                    'download_url'  => PMP_Download::get_download_url( $token ),
                    'expires_hours' => get_option( 'pmp_download_expiry_hours', 48 ),
                    'max_downloads' => get_option( 'pmp_download_max_count', 3 ),
                ];
            }
        }

        if ( empty( $tokens_created ) ) return;

        update_post_meta( $order_id, '_pmp_tokens_data', $tokens_created );

        // Only auto-send email if no edit requests in the order
        $has_edit_request = false;
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( 'Szerkesztési opciók' ) || $item->get_meta( 'Megjegyzés' ) ) {
                $has_edit_request = true;
                break;
            }
        }

        if ( ! $has_edit_request ) {
            self::send_download_email( $order, $tokens_created );
            update_post_meta( $order_id, '_pmp_tokens_sent', true );
        }
        // If edit request: tokens exist, admin sends email manually from order page
    }

    public static function send_download_email_public( $order, $tokens ) {
        self::send_download_email( $order, $tokens );
    }

    private static function send_download_email( $order, $tokens ) {
        $to      = $order->get_billing_email();
        $name    = $order->get_billing_first_name();
        $subject = sprintf( __( 'Fotó letöltési link – #%s rendelés', 'photo-market-pro' ), $order->get_order_number() );

        $expiry_h = get_option( 'pmp_download_expiry_hours', 48 );
        $max_dl   = get_option( 'pmp_download_max_count', 3 );

        ob_start();
        include PMP_DIR . 'templates/email-download.php';
        $message = ob_get_clean();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        wp_mail( $to, $subject, $message, $headers );
    }

    public static function register_email( $emails ) {
        return $emails;
    }

    /* ── Admin meta box: letöltési linkek a rendelés oldalon ── */

    public static function register_order_meta_box() {
        $screen = wc_get_page_screen_id( 'shop-order' );
        add_meta_box(
            'pmp_order_downloads',
            '📷 Fotó letöltési linkek',
            [ __CLASS__, 'render_order_meta_box' ],
            $screen ?: 'shop_order',
            'normal',
            'default'
        );
    }

    public static function render_order_meta_box( $post_or_order ) {
        $order_id = $post_or_order instanceof WP_Post ? $post_or_order->ID : $post_or_order->get_id();
        $order    = wc_get_order( $order_id );
        $tokens   = PMP_Download::get_tokens_for_order( $order_id );

        if ( empty( $tokens ) ) {
            echo '<p style="color:#999; padding:4px 0;">Ehhez a rendeléshez még nem tartoznak letöltési linkek.<br>';
            echo '<em>Linkek generálásához állítsd a rendelést <strong>Teljesített</strong> státuszba.</em></p>';
            return;
        }

        // Collect edit requests from order items
        $edit_requests = [];
        if ( $order ) {
            foreach ( $order->get_items() as $item ) {
                $opts = $item->get_meta( 'Szerkesztési opciók' );
                $note = $item->get_meta( 'Megjegyzés' );
                if ( $opts || $note ) {
                    $edit_requests[] = [
                        'product' => $item->get_name(),
                        'options' => $opts,
                        'note'    => $note,
                    ];
                }
            }
        }

        if ( ! empty( $edit_requests ) ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #ffb900;padding:8px 12px;margin-bottom:12px;">';
            echo '<strong>⚠ Szerkesztési kérések:</strong><ul style="margin:6px 0 0 16px;">';
            foreach ( $edit_requests as $req ) {
                echo '<li><strong>' . esc_html( $req['product'] ) . ':</strong> ';
                if ( $req['options'] ) echo esc_html( $req['options'] );
                if ( $req['note'] )    echo ' – <em>' . esc_html( $req['note'] ) . '</em>';
                echo '</li>';
            }
            echo '</ul></div>';
        }

        $already_sent = get_post_meta( $order_id, '_pmp_tokens_sent', true );

        echo '<table class="widefat striped" style="margin-top:4px;">';
        echo '<thead><tr><th>Fotó</th><th>Lejárat / státusz</th><th>Letöltések</th><th>Szerkesztett változat</th><th>Link</th></tr></thead><tbody>';
        foreach ( $tokens as $t ) {
            $expired   = strtotime( $t['expires_at'] ) < time();
            $exhausted = (int) $t['download_count'] >= (int) $t['max_downloads'];
            $url       = PMP_Download::get_download_url( $t['token'] );
            $status    = $expired ? '<span style="color:#d63638;">lejárt</span>'
                       : ( $exhausted ? '<span style="color:#d63638;">kimerült</span>' : '<span style="color:#00a32a;">aktív</span>' );
            $has_edited = ! empty( $t['edited_key'] );
            echo '<tr>';
            echo '<td>' . esc_html( $t['photo_title'] ?: '–' ) . '</td>';
            echo '<td>' . esc_html( wp_date( 'Y.m.d H:i', strtotime( $t['expires_at'] ) ) ) . '<br>' . $status . '</td>';
            echo '<td>' . (int) $t['download_count'] . ' / ' . (int) $t['max_downloads'] . '</td>';
            echo '<td>';
            if ( $has_edited ) {
                echo '<span style="color:#00a32a;">✓ feltöltve</span><br>';
            }
            echo '<label class="pmp-upload-label" style="font-size:12px;cursor:pointer;">';
            echo '<input type="file" class="pmp-edited-file" data-token="' . esc_attr( $t['token'] ) . '" accept="image/*,.jpg,.jpeg,.png,.tif,.tiff,.pdf" style="display:none;">';
            echo $has_edited ? 'Csere' : 'Feltöltés';
            echo '</label>';
            echo '<span class="pmp-upload-status" style="font-size:11px;display:block;"></span>';
            echo '</td>';
            echo '<td><input type="text" value="' . esc_attr( $url ) . '" readonly style="width:100%;font-size:11px;" onclick="this.select()"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<p style="margin-top:10px;">';
        if ( $already_sent ) {
            echo '<span style="color:#00a32a;margin-right:10px;">✓ Email már elküldve</span>';
        }
        echo '<button type="button" class="button button-primary" id="pmp-send-order-email" data-order="' . esc_attr( $order_id ) . '">';
        echo $already_sent ? 'Email újraküldése' : 'Letöltési email küldése';
        echo '</button> <span id="pmp-email-send-status" style="font-size:12px;margin-left:8px;"></span>';
        echo '</p>';

        // Inline JS for this meta box (only loaded on order pages)
        $nonce = wp_create_nonce( 'pmp_admin_nonce' );
        echo '<script>
(function($){
    var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';
    var nonce   = ' . json_encode( $nonce ) . ';

    // File input → upload edited photo
    $(document).on("change", ".pmp-edited-file", function(){
        var $inp    = $(this);
        var token   = $inp.data("token");
        var $status = $inp.closest("td").find(".pmp-upload-status");
        var file    = $inp[0].files[0];
        if (!file) return;

        $status.text("Feltöltés...").css("color","#555");
        var fd = new FormData();
        fd.append("action",     "pmp_upload_edited_photo");
        fd.append("nonce",      nonce);
        fd.append("token",      token);
        fd.append("edited_file", file);

        $.ajax({
            url: ajaxurl, type:"POST", data: fd,
            processData:false, contentType:false,
            success: function(res){
                if (res.success) {
                    $status.text("✓ Kész").css("color","#00a32a");
                    $inp.closest("td").find(".pmp-upload-label").html("Csere " +
                        \'<input type="file" class="pmp-edited-file" data-token="\' + token + \'" accept="image/*,.jpg,.jpeg,.png,.tif,.tiff,.pdf" style="display:none;">\');
                } else {
                    $status.text("❌ " + (res.data||"hiba")).css("color","#d63638");
                }
            },
            error: function(){
                $status.text("❌ Szerver hiba").css("color","#d63638");
            }
        });
    });

    // Send email button
    $(document).on("click", "#pmp-send-order-email", function(){
        var $btn    = $(this).prop("disabled", true).text("Küldés...");
        var orderId = $btn.data("order");
        var $status = $("#pmp-email-send-status");
        $.post(ajaxurl, {action:"pmp_send_order_email", nonce:nonce, order_id:orderId}, function(res){
            $btn.prop("disabled", false).text("Email újraküldése");
            if (res.success) {
                $status.text("✅ Email elküldve!").css("color","#00a32a");
            } else {
                $status.text("❌ " + (res.data||"hiba")).css("color","#d63638");
            }
        });
    });
})(jQuery);
</script>';
    }
}
