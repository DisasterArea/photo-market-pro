<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Order {

    public static function init() {
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'handle_completed_order' ] );
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'handle_completed_order' ] ); // for instant payment
        add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email' ] );
    }

    public static function handle_completed_order( $order_id ) {
        // Only run once
        if ( get_post_meta( $order_id, '_pmp_tokens_sent', true ) ) return;

        $order  = wc_get_order( $order_id );
        if ( ! $order ) return;

        $tokens_created = [];

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product || $product->get_type() !== 'pmp_photo' ) continue;

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
        return $emails; // Future: custom WC email class
    }
}
