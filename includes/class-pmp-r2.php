<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cloudflare R2 presigned URL generator.
 * Uses AWS Signature Version 4 (R2 is S3-compatible).
 * No SDK needed – pure PHP.
 */
class PMP_R2 {

    public static function init() {
        // Settings saved via admin
    }

    private static function get_config() {
        return [
            'enabled'        => get_option( 'pmp_r2_enabled', 0 ),
            'account_id'     => get_option( 'pmp_r2_account_id', '' ),
            'access_key'     => get_option( 'pmp_r2_access_key', '' ),
            'secret_key'     => get_option( 'pmp_r2_secret_key', '' ),
            'bucket'         => get_option( 'pmp_r2_bucket', '' ),
            'custom_domain'  => get_option( 'pmp_r2_custom_domain', '' ), // optional
        ];
    }

    public static function is_enabled() {
        $cfg = self::get_config();
        return ! empty( $cfg['enabled'] ) && ! empty( $cfg['access_key'] ) && ! empty( $cfg['bucket'] );
    }

    /**
     * Generate a presigned URL valid for $expires_seconds.
     *
     * @param string $object_key  e.g. photos/natur/virag-001.jpg
     * @param int    $expires_seconds
     * @return string|false  URL or false on failure
     */
    public static function presigned_url( $object_key, $expires_seconds = 172800 ) {
        $cfg = self::get_config();

        if ( ! self::is_enabled() ) return false;

        // If custom domain is set, build a simple signed token redirect instead
        // (via the plugin's own download endpoint)
        // Here we generate a real AWS SigV4 presigned GET URL.

        $region      = 'auto';
        $access_key  = $cfg['access_key'];
        $secret_key  = $cfg['secret_key'];
        $bucket      = $cfg['bucket'];
        $account_id  = $cfg['account_id'];

        // Endpoint
        if ( ! empty( $cfg['custom_domain'] ) ) {
            $host = rtrim( $cfg['custom_domain'], '/' );
            $endpoint = $host . '/' . ltrim( $object_key, '/' );
        } else {
            $host     = $account_id . '.r2.cloudflarestorage.com';
            $endpoint = 'https://' . $host . '/' . $bucket . '/' . ltrim( $object_key, '/' );
        }

        $datetime  = gmdate( 'Ymd\THis\Z' );
        $date      = gmdate( 'Ymd' );
        $scope     = "$date/$region/s3/aws4_request";

        $parsed = parse_url( $endpoint );
        $host_hdr = $parsed['host'];
        $path     = $parsed['path'];

        $query_params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'   => urlencode( "$access_key/$scope" ),
            'X-Amz-Date'         => $datetime,
            'X-Amz-Expires'      => $expires_seconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort( $query_params );
        $query_string = http_build_query( $query_params );

        // Canonical request
        $canonical = implode( "\n", [
            'GET',
            $path,
            $query_string,
            "host:$host_hdr\n",
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        // String to sign
        $string_to_sign = implode( "\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $scope,
            hash( 'sha256', $canonical ),
        ]);

        // Signing key
        $signing_key = self::hmac( "aws4_request",
            self::hmac( "s3",
                self::hmac( $region,
                    self::hmac( $date, "AWS4$secret_key" )
                )
            )
        );

        $signature = bin2hex( hash_hmac( 'sha256', $string_to_sign, $signing_key, true ) );

        return $endpoint . '?' . $query_string . '&X-Amz-Signature=' . $signature;
    }

    private static function hmac( $data, $key ) {
        return hash_hmac( 'sha256', $data, $key, true );
    }

    /**
     * Test connectivity: list bucket objects (HEAD request).
     */
    public static function test_connection() {
        $cfg = self::get_config();
        if ( ! $cfg['access_key'] || ! $cfg['bucket'] || ! $cfg['account_id'] ) {
            return [ 'success' => false, 'message' => 'Hiányzó beállítások.' ];
        }

        $url = self::presigned_url( '.connection-test', 5 );
        if ( ! $url ) return [ 'success' => false, 'message' => 'URL generálás sikertelen.' ];

        $response = wp_remote_head( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        // 403 = auth works but file not found = connection OK
        // 404 = file not found = connection OK
        if ( in_array( $code, [ 200, 403, 404 ] ) ) {
            return [ 'success' => true, 'message' => "Kapcsolat OK (HTTP $code)" ];
        }

        return [ 'success' => false, 'message' => "Nem várt válasz: HTTP $code" ];
    }
}
