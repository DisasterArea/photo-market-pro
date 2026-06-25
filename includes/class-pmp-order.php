<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Order {

    public static function init() {
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'handle_completed_order' ] );
        add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_order_meta_box' ] );
    }

    /**
     * Fires when order is marked Completed.
     * Creates download tokens for all PMP photos in the order,
     * including any pre-uploaded edited versions the admin added,
     * then sends ONE email with all download links.
     */
    public static function handle_completed_order( $order_id ) {
        if ( get_post_meta( $order_id, '_pmp_tokens_sent', true ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Pre-uploaded edited versions stored before completion
        $pre_uploads = get_post_meta( $order_id, '_pmp_edited_uploads', true ) ?: [];

        $tokens_created = [];

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product || ! $product->get_meta( '_pmp_photo' ) ) continue;

            $photo = PMP_Photo::get_by_product( $product->get_id() );
            if ( ! $photo ) continue;

            $qty        = $item->get_quantity();
            $base_title = $photo['title'] ?: $product->get_name();

            for ( $i = 0; $i < $qty; $i++ ) {
                // Original photo token
                $token = PMP_Download::create_token(
                    $order_id, $item_id, $photo['id'],
                    $order->get_billing_email(),
                    'Eredeti'
                );
                $tokens_created[] = [
                    'photo_title'   => $base_title,
                    'label'         => 'Eredeti',
                    'download_url'  => PMP_Download::get_download_url( $token ),
                    'expires_hours' => get_option( 'pmp_download_expiry_hours', 48 ),
                    'max_downloads' => get_option( 'pmp_download_max_count', 3 ),
                ];

                // Edited versions pre-uploaded by admin for this order item
                foreach ( $pre_uploads as $pu ) {
                    if ( (int) $pu['order_item_id'] !== (int) $item_id ) continue;
                    $token = PMP_Download::create_token(
                        $order_id, $item_id, $photo['id'],
                        $order->get_billing_email(),
                        $pu['label'],
                        $pu['r2_key']
                    );
                    $tokens_created[] = [
                        'photo_title'   => $base_title,
                        'label'         => $pu['label'],
                        'download_url'  => PMP_Download::get_download_url( $token ),
                        'expires_hours' => get_option( 'pmp_download_expiry_hours', 48 ),
                        'max_downloads' => get_option( 'pmp_download_max_count', 3 ),
                    ];
                }
            }
        }

        if ( empty( $tokens_created ) ) return;

        update_post_meta( $order_id, '_pmp_tokens_data', $tokens_created );
        self::send_download_email( $order, $tokens_created );
        update_post_meta( $order_id, '_pmp_tokens_sent', true );
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

    /* ── Admin meta box ── */

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
        if ( ! $order ) return;

        $is_completed = $order->get_status() === 'completed';
        $nonce        = wp_create_nonce( 'pmp_admin_nonce' );
        $pre_uploads  = get_post_meta( $order_id, '_pmp_edited_uploads', true ) ?: [];

        // Collect PMP order items and edit requests
        $pmp_items     = [];
        $edit_requests = [];
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product || ! $product->get_meta( '_pmp_photo' ) ) continue;
            $pmp_items[ $item_id ] = $item;
            $opts = $item->get_meta( 'Szerkesztési opciók' );
            $note = $item->get_meta( 'Megjegyzés' );
            if ( $opts || $note ) {
                $edit_requests[ $item_id ] = [ 'name' => $item->get_name(), 'options' => $opts, 'note' => $note ];
            }
        }

        if ( empty( $pmp_items ) ) {
            echo '<p style="color:#999;">Ez a rendelés nem tartalmaz fotót.</p>';
            return;
        }

        // ── Edit requests warning ──
        if ( ! empty( $edit_requests ) ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #ffb900;padding:8px 12px;margin-bottom:12px;">';
            echo '<strong>⚠ Szerkesztési kérések:</strong><ul style="margin:6px 0 0 16px;">';
            foreach ( $edit_requests as $req ) {
                echo '<li><strong>' . esc_html( $req['name'] ) . ':</strong> ';
                if ( $req['options'] ) echo esc_html( $req['options'] );
                if ( $req['note'] )    echo ' – <em>' . esc_html( $req['note'] ) . '</em>';
                echo '</li>';
            }
            echo '</ul></div>';
        }

        // ── If completed: show generated tokens ──
        if ( $is_completed ) {
            $tokens = PMP_Download::get_tokens_for_order( $order_id );
            if ( ! empty( $tokens ) ) {
                echo '<table class="widefat striped" style="margin-bottom:12px;">';
                echo '<thead><tr><th>Fotó</th><th>Változat</th><th>Lejárat</th><th>Letöltve</th><th>Link</th></tr></thead><tbody>';
                foreach ( $tokens as $t ) {
                    $expired   = strtotime( $t['expires_at'] ) < time();
                    $exhausted = (int) $t['download_count'] >= (int) $t['max_downloads'];
                    $url       = PMP_Download::get_download_url( $t['token'] );
                    $status    = $expired ? '<span style="color:#d63638;">lejárt</span>'
                               : ( $exhausted ? '<span style="color:#d63638;">kimerült</span>' : '<span style="color:#00a32a;">aktív</span>' );
                    echo '<tr>';
                    echo '<td>' . esc_html( $t['photo_title'] ?: '–' ) . '</td>';
                    echo '<td>' . esc_html( $t['label'] ?: 'Eredeti' ) . '</td>';
                    echo '<td>' . esc_html( wp_date( 'Y.m.d H:i', strtotime( $t['expires_at'] ) ) ) . ' ' . $status . '</td>';
                    echo '<td>' . (int) $t['download_count'] . '/' . (int) $t['max_downloads'] . '</td>';
                    echo '<td><input type="text" value="' . esc_attr( $url ) . '" readonly style="width:100%;font-size:11px;" onclick="this.select()"></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            $sent = get_post_meta( $order_id, '_pmp_tokens_sent', true );
            echo '<p style="font-size:12px;color:#646970;">';
            echo $sent ? '✓ Email elküldve a vásárlónak.' : '⚠ Email még nem ment ki.';
            if ( $sent ) {
                echo ' <button type="button" class="button button-small" id="pmp-resend-email-btn" data-order="' . esc_attr( $order_id ) . '">Újraküldés</button>';
                echo ' <span id="pmp-resend-status" style="margin-left:6px;"></span>';
            }
            echo '</p>';
        }

        // ── Pre-upload section (visible even before completion) ──
        if ( ! $is_completed ) {
            echo '<p style="color:#646970;font-size:12px;margin-bottom:10px;">A rendelés teljesítésekor egy emailt kap a vásárló az összes letöltési linkkel. Ha szerkesztett változatot is szeretnél küldeni, töltsd fel itt mielőtt <strong>Teljesítettre</strong> állítod.</p>';
        }

        echo '<div id="pmp-edited-uploads-wrap">';

        // List existing pre-uploads
        if ( ! empty( $pre_uploads ) && ! $is_completed ) {
            echo '<table class="widefat striped" style="margin-bottom:10px;">';
            echo '<thead><tr><th>Megnevezés</th><th>Termék</th><th>Fájl (R2 kulcs)</th><th></th></tr></thead><tbody>';
            foreach ( $pre_uploads as $idx => $pu ) {
                $item_name = isset( $pmp_items[ $pu['order_item_id'] ] ) ? $pmp_items[ $pu['order_item_id'] ]->get_name() : '–';
                echo '<tr id="pmp-preupload-row-' . (int) $idx . '">';
                echo '<td>' . esc_html( $pu['label'] ) . '</td>';
                echo '<td>' . esc_html( $item_name ) . '</td>';
                echo '<td style="font-size:11px;color:#888;">' . esc_html( $pu['r2_key'] ) . '</td>';
                echo '<td><button type="button" class="button button-small pmp-delete-preupload" data-order="' . esc_attr( $order_id ) . '" data-idx="' . (int) $idx . '">✕</button></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        if ( ! $is_completed ) {
            // Upload form
            echo '<div style="background:#f9f9f9;border:1px solid #ddd;padding:12px;border-radius:4px;">';
            echo '<strong style="display:block;margin-bottom:8px;">+ Szerkesztett változat hozzáadása</strong>';

            // Product selector (if multiple PMP items)
            if ( count( $pmp_items ) > 1 ) {
                echo '<label style="font-size:12px;">Termék:<br><select id="pmp-upload-item-id" style="width:100%;margin-bottom:8px;">';
                foreach ( $pmp_items as $iid => $item ) {
                    echo '<option value="' . esc_attr( $iid ) . '">' . esc_html( $item->get_name() ) . '</option>';
                }
                echo '</select></label>';
            } else {
                $first_item_id = array_key_first( $pmp_items );
                echo '<input type="hidden" id="pmp-upload-item-id" value="' . esc_attr( $first_item_id ) . '">';
            }

            echo '<label style="font-size:12px;">Megnevezés (pl. Fekete-fehér, Háttércsere):<br>';
            echo '<input type="text" id="pmp-upload-label" placeholder="pl. Fekete-fehér változat" style="width:100%;margin-bottom:8px;"></label>';
            echo '<label style="font-size:12px;">Fájl kiválasztása:<br>';
            echo '<input type="file" id="pmp-upload-file" accept="image/*,.pdf" style="margin-bottom:8px;"></label>';
            echo '<br><button type="button" class="button button-primary" id="pmp-add-edited-btn" data-order="' . esc_attr( $order_id ) . '">Feltöltés R2-re</button>';
            echo ' <span id="pmp-upload-status" style="font-size:12px;margin-left:8px;"></span>';
            echo '</div>';
        }

        echo '</div>'; // #pmp-edited-uploads-wrap

        // Inline JS
        echo '<script>
(function($){
    var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';
    var nonce   = ' . json_encode( $nonce ) . ';

    // Upload edited photo
    $(document).on("click", "#pmp-add-edited-btn", function(){
        var $btn    = $(this).prop("disabled", true).text("Feltöltés...");
        var orderId = $btn.data("order");
        var itemId  = $("#pmp-upload-item-id").val();
        var label   = $("#pmp-upload-label").val().trim();
        var file    = $("#pmp-upload-file")[0].files[0];
        var $status = $("#pmp-upload-status");

        if (!label) { $status.text("Add meg a megnevezést!").css("color","#d63638"); $btn.prop("disabled",false).text("Feltöltés R2-re"); return; }
        if (!file)  { $status.text("Válassz fájlt!").css("color","#d63638"); $btn.prop("disabled",false).text("Feltöltés R2-re"); return; }

        $status.text("Feltöltés...").css("color","#555");
        var fd = new FormData();
        fd.append("action",        "pmp_upload_edited_photo");
        fd.append("nonce",         nonce);
        fd.append("order_id",      orderId);
        fd.append("order_item_id", itemId);
        fd.append("label",         label);
        fd.append("edited_file",   file);

        $.ajax({
            url: ajaxurl, type:"POST", data: fd, processData:false, contentType:false,
            success: function(res){
                $btn.prop("disabled",false).text("Feltöltés R2-re");
                if (res.success) {
                    $status.text("✓ Feltöltve!").css("color","#00a32a");
                    $("#pmp-upload-label").val("");
                    $("#pmp-upload-file").val("");
                    // Refresh meta box
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    $status.text("❌ " + (res.data||"hiba")).css("color","#d63638");
                }
            },
            error: function(){ $btn.prop("disabled",false).text("Feltöltés R2-re"); $status.text("❌ Szerver hiba").css("color","#d63638"); }
        });
    });

    // Delete pre-upload
    $(document).on("click", ".pmp-delete-preupload", function(){
        if (!confirm("Törlöd ezt a feltöltött változatot?")) return;
        var $btn = $(this);
        $.post(ajaxurl, {action:"pmp_delete_preupload", nonce:nonce, order_id:$btn.data("order"), idx:$btn.data("idx")}, function(res){
            if (res.success) location.reload();
        });
    });

    // Resend email
    $(document).on("click", "#pmp-resend-email-btn", function(){
        var $btn = $(this).prop("disabled",true).text("Küldés...");
        $.post(ajaxurl, {action:"pmp_send_order_email", nonce:nonce, order_id:$btn.data("order")}, function(res){
            $btn.prop("disabled",false).text("Újraküldés");
            $("#pmp-resend-status").text(res.success ? "✅ Elküldve!" : "❌ " + (res.data||"hiba"))
                .css("color", res.success ? "#00a32a" : "#d63638");
        });
    });
})(jQuery);
</script>';
    }
}
