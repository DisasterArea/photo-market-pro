<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Download {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_rewrite' ] );
        add_filter( 'query_vars', [ __CLASS__, 'query_vars' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_download' ] );
    }

    // ── Rewrite ─────────────────────────────────────────────────────

    public static function register_rewrite() {
        add_rewrite_rule( '^pmp-download/([a-f0-9]{64})/?$', 'index.php?pmp_download_token=$matches[1]', 'top' );
    }

    public static function query_vars( $vars ) {
        $vars[] = 'pmp_download_token';
        return $vars;
    }

    public static function handle_download() {
        $token = get_query_var( 'pmp_download_token' );
        if ( ! $token ) return;

        $row = self::get_token( $token );

        if ( ! $row ) {
            wp_die( __( 'Érvénytelen letöltési link.', 'photo-market-pro' ), 404 );
        }

        if ( strtotime( $row['expires_at'] ) < time() ) {
            wp_die( __( 'A letöltési link lejárt. Kérjük vegye fel velünk a kapcsolatot.', 'photo-market-pro' ), 410 );
        }

        if ( $row['download_count'] >= $row['max_downloads'] ) {
            wp_die( __( 'Elérte a maximális letöltési számot.', 'photo-market-pro' ), 403 );
        }

        $photo = PMP_Photo::get_by_id( $row['photo_id'] );
        if ( ! $photo ) {
            wp_die( __( 'A fájl nem található.', 'photo-market-pro' ), 404 );
        }

        // Increment download count
        self::increment_count( $token );

        // Redirect to R2 presigned URL or direct URL (edited version takes priority)
        $download_url = self::resolve_download_url( $photo, $row['edited_key'] ?? '' );
        if ( ! $download_url ) {
            wp_die( __( 'A fájl letöltési útvonala nincs beállítva.', 'photo-market-pro' ), 500 );
        }

        wp_redirect( $download_url );
        exit;
    }

    private static function resolve_download_url( $photo, $edited_key = '' ) {
        $expires = intval( get_option( 'pmp_download_expiry_hours', 48 ) ) * 3600;
        // Edited version takes priority
        if ( ! empty( $edited_key ) && PMP_R2::is_enabled() ) {
            return PMP_R2::presigned_url( $edited_key, $expires );
        }
        if ( $photo['use_external'] && ! empty( $photo['external_key'] ) && PMP_R2::is_enabled() ) {
            return PMP_R2::presigned_url( $photo['external_key'], $expires );
        }
        if ( ! empty( $photo['download_url'] ) ) {
            return $photo['download_url'];
        }
        return false;
    }

    // ── Token creation ───────────────────────────────────────────────

    public static function create_token( $order_id, $order_item_id, $photo_id, $customer_email ) {
        global $wpdb;

        $token       = bin2hex( random_bytes( 32 ) ); // 64-char hex
        $expiry_h    = intval( get_option( 'pmp_download_expiry_hours', 48 ) );
        $max_dl      = intval( get_option( 'pmp_download_max_count', 3 ) );
        $expires_at  = gmdate( 'Y-m-d H:i:s', time() + $expiry_h * 3600 );

        $wpdb->insert( $wpdb->prefix . 'pmp_download_tokens', [
            'token'          => $token,
            'order_id'       => $order_id,
            'order_item_id'  => $order_item_id,
            'photo_id'       => $photo_id,
            'customer_email' => $customer_email,
            'expires_at'     => $expires_at,
            'max_downloads'  => $max_dl,
        ]);

        return $token;
    }

    public static function get_download_url( $token ) {
        return home_url( '/pmp-download/' . $token . '/' );
    }

    private static function get_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pmp_download_tokens WHERE token = %s",
            $token
        ), ARRAY_A );
    }

    private static function increment_count( $token ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}pmp_download_tokens SET download_count = download_count + 1 WHERE token = %s",
            $token
        ));
    }

    public static function get_tokens_for_order( $order_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*, p.title as photo_title FROM {$wpdb->prefix}pmp_download_tokens t
             LEFT JOIN {$wpdb->prefix}pmp_photos p ON t.photo_id = p.id
             WHERE t.order_id = %d",
            $order_id
        ), ARRAY_A );
    }

    /**
     * Regenerate (extend) a token's expiry – useful from admin.
     */
    public static function extend_token( $token ) {
        global $wpdb;
        $expiry_h = intval( get_option( 'pmp_download_expiry_hours', 48 ) );
        $new_exp  = gmdate( 'Y-m-d H:i:s', time() + $expiry_h * 3600 );
        $wpdb->update(
            $wpdb->prefix . 'pmp_download_tokens',
            [ 'expires_at' => $new_exp, 'download_count' => 0 ],
            [ 'token' => $token ]
        );
    }
}
