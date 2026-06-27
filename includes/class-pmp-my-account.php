<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_My_Account {

    const ENDPOINT = 'le-mie-foto';

    public static function init() {
        add_action( 'init',                                    [ __CLASS__, 'register_endpoint' ] );
        add_filter( 'woocommerce_account_menu_items',          [ __CLASS__, 'menu_items' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ __CLASS__, 'page_content' ] );
        add_filter( 'woocommerce_get_query_vars',              [ __CLASS__, 'query_vars' ] );
    }

    public static function register_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function query_vars( $vars ) {
        $vars[ self::ENDPOINT ] = self::ENDPOINT;
        return $vars;
    }

    public static function menu_items( $items ) {
        // Remove default WC downloads tab
        unset( $items['downloads'] );

        // Insert "Le mie foto" before "orders" or at start
        $new = [];
        foreach ( $items as $key => $label ) {
            if ( $key === 'orders' ) {
                $new[ self::ENDPOINT ] = '📷 Le mie foto';
            }
            $new[ $key ] = $label;
        }
        // Fallback: if "orders" wasn't found, prepend
        if ( ! isset( $new[ self::ENDPOINT ] ) ) {
            $new = array_merge( [ self::ENDPOINT => '📷 Le mie foto' ], $items );
        }
        return $new;
    }

    public static function page_content() {
        if ( ! is_user_logged_in() ) {
            echo '<p>' . esc_html__( 'Devi essere connesso per vedere le tue foto.', 'photo-market-pro' ) . '</p>';
            return;
        }

        $user  = wp_get_current_user();
        $email = $user->user_email;

        global $wpdb;
        $tokens = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*, p.title as photo_title
             FROM {$wpdb->prefix}pmp_download_tokens t
             LEFT JOIN {$wpdb->prefix}pmp_photos p ON t.photo_id = p.id
             WHERE t.customer_email = %s
             ORDER BY t.expires_at DESC",
            $email
        ), ARRAY_A );

        if ( empty( $tokens ) ) {
            echo '<p>' . esc_html__( 'Non hai ancora acquistato nessuna foto.', 'photo-market-pro' ) . '</p>';
            return;
        }

        $expiry_h = get_option( 'pmp_download_expiry_hours', 48 );
        $max_dl   = get_option( 'pmp_download_max_count', 3 );

        // Group by order
        $by_order = [];
        foreach ( $tokens as $t ) {
            $by_order[ $t['order_id'] ][] = $t;
        }

        echo '<p style="font-size:14px;color:#666;margin-bottom:20px;">';
        printf(
            esc_html__( 'I link di download sono validi per %d ore e possono essere utilizzati al massimo %d volte.', 'photo-market-pro' ),
            intval( $expiry_h ),
            intval( $max_dl )
        );
        echo '</p>';

        foreach ( $by_order as $order_id => $order_tokens ) {
            $order = wc_get_order( $order_id );
            $order_num = $order ? '#' . $order->get_order_number() : '#' . $order_id;
            echo '<h3 style="font-size:15px;margin:24px 0 10px;border-bottom:1px solid #eee;padding-bottom:6px;">';
            echo esc_html( 'Ordine ' . $order_num );
            echo '</h3>';

            echo '<table class="woocommerce-table shop_table" style="width:100%;margin-bottom:10px;">';
            echo '<thead><tr>';
            echo '<th style="text-align:left;">Foto</th>';
            echo '<th style="text-align:left;">Scadenza</th>';
            echo '<th style="text-align:left;">Download</th>';
            echo '<th></th>';
            echo '</tr></thead><tbody>';

            foreach ( $order_tokens as $t ) {
                $expired   = strtotime( $t['expires_at'] ) < time();
                $exhausted = intval( $t['download_count'] ) >= intval( $t['max_downloads'] );
                $label     = ! empty( $t['label'] ) && $t['label'] !== 'Eredeti' ? ' – ' . $t['label'] : '';

                echo '<tr>';
                echo '<td>' . esc_html( ( $t['photo_title'] ?: '–' ) . $label ) . '</td>';
                echo '<td>' . esc_html( wp_date( 'd/m/Y H:i', strtotime( $t['expires_at'] ) ) ) . '</td>';
                echo '<td>' . intval( $t['download_count'] ) . '/' . intval( $t['max_downloads'] ) . '</td>';
                echo '<td>';
                if ( $expired ) {
                    echo '<span style="color:#dc3232;">Scaduto</span>';
                } elseif ( $exhausted ) {
                    echo '<span style="color:#dc3232;">Esaurito</span>';
                } else {
                    echo '<a href="' . esc_url( PMP_Download::get_download_url( $t['token'] ) ) . '" class="button wc-forward">Scarica</a>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
    }
}
