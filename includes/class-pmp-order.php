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

        // Send email
        self::send_download_email( $order, $tokens_created );

        update_post_meta( $order_id, '_pmp_tokens_sent', true );
        update_post_meta( $order_id, '_pmp_tokens_data', $tokens_created );
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
        $tokens   = PMP_Download::get_tokens_for_order( $order_id );

        if ( empty( $tokens ) ) {
            echo '<p style="color:#999; padding:4px 0;">Ehhez a rendeléshez még nem tartoznak letöltési linkek.<br>';
            echo '<em>Linkek generálásához állítsd a rendelést <strong>Teljesített</strong> státuszba.</em></p>';
            return;
        }

        echo '<table class="widefat striped" style="margin-top:8px;">';
        echo '<thead><tr><th>Fotó</th><th>Lejárat</th><th>Letöltések</th><th>Link</th></tr></thead><tbody>';
        foreach ( $tokens as $t ) {
            $expired   = strtotime( $t['expires_at'] ) < time();
            $exhausted = (int) $t['download_count'] >= (int) $t['max_downloads'];
            $url       = PMP_Download::get_download_url( $t['token'] );
            $status    = $expired ? '<span style="color:#d63638;">lejárt</span>'
                       : ( $exhausted ? '<span style="color:#d63638;">kimerült</span>' : '<span style="color:#00a32a;">aktív</span>' );
            echo '<tr>';
            echo '<td>' . esc_html( $t['photo_title'] ?: '–' ) . '</td>';
            echo '<td>' . esc_html( wp_date( 'Y.m.d H:i', strtotime( $t['expires_at'] ) ) ) . ' ' . $status . '</td>';
            echo '<td>' . (int) $t['download_count'] . ' / ' . (int) $t['max_downloads'] . '</td>';
            echo '<td><input type="text" value="' . esc_attr( $url ) . '" readonly style="width:100%;font-size:11px;" onclick="this.select()"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p style="margin-top:8px; font-size:12px; color:#646970;">A linkeket a vevőnek kézzel is elküldhetjük, vagy a rendelés teljesítésekor automatikusan megy az email.</p>';
    }
}
