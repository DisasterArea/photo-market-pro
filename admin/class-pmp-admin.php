<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

        add_action( 'wp_ajax_pmp_save_photo',         [ __CLASS__, 'ajax_save_photo' ] );
        add_action( 'wp_ajax_pmp_delete_photo',       [ __CLASS__, 'ajax_delete_photo' ] );
        add_action( 'wp_ajax_pmp_bulk_delete_photos', [ __CLASS__, 'ajax_bulk_delete_photos' ] );
        add_action( 'wp_ajax_pmp_get_photo',          [ __CLASS__, 'ajax_get_photo' ] );
        add_action( 'wp_ajax_pmp_bulk_upload',        [ __CLASS__, 'ajax_bulk_upload' ] );
        add_action( 'wp_ajax_pmp_get_filter_data',    [ __CLASS__, 'ajax_get_filter_data' ] );

        add_action( 'wp_ajax_pmp_save_edit_option',     [ __CLASS__, 'ajax_save_edit_option' ] );
        add_action( 'wp_ajax_pmp_delete_edit_option',   [ __CLASS__, 'ajax_delete_edit_option' ] );
        add_action( 'wp_ajax_pmp_reorder_edit_options', [ __CLASS__, 'ajax_reorder_edit_options' ] );

        add_action( 'wp_ajax_pmp_save_settings',          [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_pmp_r2_test',               [ __CLASS__, 'ajax_r2_test' ] );
        add_action( 'wp_ajax_pmp_resend_links',          [ __CLASS__, 'ajax_resend_links' ] );
        add_action( 'wp_ajax_pmp_extend_token',          [ __CLASS__, 'ajax_extend_token' ] );
        add_action( 'wp_ajax_pmp_delete_token',          [ __CLASS__, 'ajax_delete_token' ] );
        add_action( 'wp_ajax_pmp_clean_orphaned_tokens',  [ __CLASS__, 'ajax_clean_orphaned_tokens' ] );
        add_action( 'wp_ajax_pmp_upload_edited_photo',    [ __CLASS__, 'ajax_upload_edited_photo' ] );
        add_action( 'wp_ajax_pmp_delete_preupload',       [ __CLASS__, 'ajax_delete_preupload' ] );
        add_action( 'wp_ajax_pmp_send_order_email',       [ __CLASS__, 'ajax_send_order_email' ] );
    }

    public static function admin_menu() {
        add_menu_page( 'Photo Market Pro', 'Photo Market', 'manage_woocommerce', 'photo-market-pro',
            [ __CLASS__, 'page_photos' ], 'dashicons-format-gallery', 56 );
        add_submenu_page( 'photo-market-pro', 'Fotók',               'Fotók',               'manage_woocommerce', 'photo-market-pro',  [ __CLASS__, 'page_photos' ] );
        add_submenu_page( 'photo-market-pro', 'Szerkesztési opciók', 'Szerkesztési opciók', 'manage_woocommerce', 'pmp-edit-options',  [ __CLASS__, 'page_edit_options' ] );
        add_submenu_page( 'photo-market-pro', 'Letöltési linkek',    'Letöltési linkek',    'manage_woocommerce', 'pmp-downloads',     [ __CLASS__, 'page_downloads' ] );
        add_submenu_page( 'photo-market-pro', 'Beállítások',         'Beállítások',         'manage_woocommerce', 'pmp-settings',      [ __CLASS__, 'page_settings' ] );
    }

    public static function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'photo-market' ) === false && strpos( $hook, 'pmp-' ) === false ) return;
        wp_enqueue_media();
        wp_enqueue_style(  'pmp-admin', PMP_URL . 'admin/admin.css', [], PMP_VERSION );
        wp_enqueue_script( 'pmp-admin', PMP_URL . 'admin/admin.js',  [ 'jquery', 'jquery-ui-sortable' ], PMP_VERSION, true );
        wp_localize_script( 'pmp-admin', 'PMP', [
            'nonce'   => wp_create_nonce( 'pmp_admin_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ]);
    }

    /* ── Pages ──────────────────────────────────────────────── */

    public static function page_photos() {
        global $wpdb;
        $per_page = 20;
        $page     = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_GET['s'] ?? '' );
        $loc_f    = sanitize_text_field( $_GET['loc'] ?? '' );
        $cat_f    = sanitize_text_field( $_GET['cat'] ?? '' );

        $where = 'WHERE 1=1';
        if ( $search ) $where .= $wpdb->prepare( " AND (title LIKE %s OR location LIKE %s)", "%$search%", "%$search%" );
        if ( $loc_f )  $where .= $wpdb->prepare( " AND location = %s", $loc_f );
        if ( $cat_f )  $where .= $wpdb->prepare( " AND category = %s", $cat_f );

        $total  = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pmp_photos $where" );
        $photos = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}pmp_photos $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset", ARRAY_A );
        $edit_options = PMP_Photo::get_all_edit_options();
        $locations    = PMP_Photo::get_locations();
        $categories   = PMP_Photo::get_categories();

        include PMP_DIR . 'admin/views/photos.php';
    }

    public static function page_edit_options() {
        $options = PMP_Edit_Options::get_all();
        include PMP_DIR . 'admin/views/edit-options.php';
    }

    public static function page_downloads() {
        global $wpdb;
        $per_page = 30;
        $page     = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_GET['s'] ?? '' );
        $where    = $search ? $wpdb->prepare( "WHERE t.customer_email LIKE %s OR p.title LIKE %s", "%$search%", "%$search%" ) : '';
        $total    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pmp_download_tokens t LEFT JOIN {$wpdb->prefix}pmp_photos p ON t.photo_id=p.id $where" );
        $tokens   = $wpdb->get_results( "SELECT t.*, p.title as photo_title FROM {$wpdb->prefix}pmp_download_tokens t LEFT JOIN {$wpdb->prefix}pmp_photos p ON t.photo_id=p.id $where ORDER BY t.created_at DESC LIMIT $per_page OFFSET $offset", ARRAY_A );
        include PMP_DIR . 'admin/views/downloads.php';
    }

    public static function page_settings() {
        include PMP_DIR . 'admin/views/settings.php';
    }

    /* ── AJAX: Photos ────────────────────────────────────────── */

    public static function ajax_save_photo() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        $photo_id = intval( $_POST['photo_id'] ?? 0 );

        // R2 file upload when a photo file is attached
        if ( ! empty( $_FILES['photo_file'] ) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $file      = $_FILES['photo_file'];
            $attach_id = media_handle_sideload( [
                'name'     => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'type'     => $file['type'],
                'error'    => $file['error'],
                'size'     => $file['size'],
            ], 0 );

            if ( ! is_wp_error( $attach_id ) ) {
                $r2_key   = 'eredeti/' . time() . '_' . $file['name'];
                $path     = get_attached_file( $attach_id );
                $size     = filesize( $path );
                $put_url  = self::generate_r2_put_url( $r2_key, $file['type'], $size );
                $host     = get_option( 'pmp_r2_account_id' ) . '.r2.cloudflarestorage.com';

                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL,            $put_url );
                curl_setopt( $ch, CURLOPT_CUSTOMREQUEST,  'PUT' );
                curl_setopt( $ch, CURLOPT_POSTFIELDS,     file_get_contents( $path ) );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $ch, CURLOPT_HEADER,         false );
                curl_setopt( $ch, CURLOPT_HTTPHEADER, [
                    'Host: '           . $host,
                    'Content-Type: '   . $file['type'],
                    'Content-Length: ' . $size,
                ] );
                curl_exec( $ch );
                $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
                curl_close( $ch );

                if ( $http_code === 200 ) {
                    $_POST['use_external']     = 1;
                    $_POST['external_key']     = $r2_key;
                    // Use the sideloaded image as preview if none set
                    if ( empty( $_POST['preview_image_id'] ) ) {
                        $_POST['preview_image_id'] = $attach_id;
                    }
                } else {
                    wp_delete_attachment( $attach_id, true );
                    wp_send_json_error( 'R2 feltöltés sikertelen (HTTP ' . $http_code . ')' );
                }
            } else {
                wp_send_json_error( 'Fájl feldolgozási hiba: ' . $attach_id->get_error_message() );
            }
        }

        $photo_id = PMP_Photo::save( $_POST, $photo_id );
        wp_send_json_success( [ 'photo_id' => $photo_id ] );
    }

    public static function ajax_delete_photo() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        PMP_Photo::delete( intval( $_POST['photo_id'] ?? 0 ) );
        wp_send_json_success();
    }

    public static function ajax_bulk_delete_photos() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        $photo_ids = array_map( 'intval', $_POST['photo_ids'] ?? [] );
        if ( ! empty( $photo_ids ) ) {
            foreach ( $photo_ids as $id ) PMP_Photo::delete( $id );
            wp_send_json_success( 'A kijelölt képek sikeresen törölve.' );
        }
        wp_send_json_error( 'Nincsenek kijelölt képek.' );
    }

    public static function ajax_get_photo() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        $photo = PMP_Photo::get_by_id( intval( $_POST['photo_id'] ?? 0 ) );
        if ( ! $photo ) wp_send_json_error( 'Not found' );
        $photo['edit_option_ids']   = PMP_Photo::get_photo_edit_option_ids( $photo['id'] );
        $photo['preview_url_thumb'] = $photo['preview_image_id'] ? wp_get_attachment_image_url( $photo['preview_image_id'], 'thumbnail' ) : '';
        $photo['price']             = $photo['product_id'] ? (float) get_post_meta( $photo['product_id'], '_price', true ) : 0;
        wp_send_json_success( $photo );
    }

    private static function generate_r2_put_url( $object_key, $mime_type, $file_size ) {
        $account_id = get_option( 'pmp_r2_account_id' );
        $access_key = get_option( 'pmp_r2_access_key' );
        $secret_key = get_option( 'pmp_r2_secret_key' );
        $bucket     = get_option( 'pmp_r2_bucket' );

        $region   = 'auto';
        $host     = $account_id . '.r2.cloudflarestorage.com';
        $endpoint = 'https://' . $host . '/' . $bucket . '/' . ltrim( $object_key, '/' );

        $datetime = gmdate( 'Ymd\THis\Z' );
        $date     = gmdate( 'Ymd' );
        $scope    = "$date/$region/s3/aws4_request";

        $parsed = parse_url( $endpoint );
        $path   = $parsed['path'];

        $query_params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "$access_key/$scope",
            'X-Amz-Date'          => $datetime,
            'X-Amz-Expires'       => 300,
            'X-Amz-SignedHeaders'  => 'content-length;content-type;host',
        ];
        ksort( $query_params );
        $query_string = http_build_query( $query_params );

        $canonical_headers = "content-length:$file_size\ncontent-type:$mime_type\nhost:$host\n";

        $canonical = implode( "\n", [
            'PUT',
            $path,
            $query_string,
            $canonical_headers,
            'content-length;content-type;host',
            'UNSIGNED-PAYLOAD',
        ]);

        $string_to_sign = implode( "\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $scope,
            hash( 'sha256', $canonical ),
        ]);

        $signing_key = hash_hmac( 'sha256', "aws4_request",
            hash_hmac( 'sha256', "s3",
                hash_hmac( 'sha256', $region,
                    hash_hmac( 'sha256', $date, "AWS4$secret_key", true ),
                true ), true ), true );

        $signature = bin2hex( hash_hmac( 'sha256', $string_to_sign, $signing_key, true ) );

        return $endpoint . '?' . $query_string . '&X-Amz-Signature=' . $signature;
    }

    public static function ajax_bulk_upload() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        if ( empty( $_FILES['photos'] ) ) wp_send_json_error( 'Nincsenek fájlok.' );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $price   = floatval( $_POST['bulk_price'] ?? 0 );
        $opt_ids = array_map( 'intval', $_POST['bulk_edit_options'] ?? [] );
        $created = [];
        $errors  = [];
        $files   = $_FILES['photos'];
        $count   = count( $files['name'] );

        global $wpdb;

        for ( $i = 0; $i < $count; $i++ ) {
            if ( $files['error'][$i] !== UPLOAD_ERR_OK ) {
                $errors[] = $files['name'][$i] . ': feltöltési hiba';
                continue;
            }

            $filename  = pathinfo( $files['name'][$i], PATHINFO_FILENAME );
            $parts     = explode( '_', $filename );
            $location  = isset( $parts[0] ) ? ucfirst( str_replace( '-', ' ', $parts[0] ) ) : '';
            $category  = isset( $parts[1] ) ? ucfirst( str_replace( '-', ' ', $parts[1] ) ) : '';
            $date_raw  = $parts[2] ?? '';
            $shot_date = '';
            if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $date_raw, $m ) ) {
                $shot_date = "{$m[1]}-{$m[2]}-{$m[3]}";
            } elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_raw ) ) {
                $shot_date = $date_raw;
            }

            // 1. WP médiatár – előnézeti kép
            $file_array = [
                'name'     => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'type'     => $files['type'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $attach_id = media_handle_sideload( $file_array, 0 );
            if ( is_wp_error( $attach_id ) ) {
                $errors[] = $files['name'][$i] . ': ' . $attach_id->get_error_message();
                continue;
            }

            // 2. R2 feltöltés
            $r2_file_key = 'eredeti/' . time() . '_' . $files['name'][$i];
            $file_path   = get_attached_file( $attach_id );
            $file_size   = filesize( $file_path );
            $upload_url  = self::generate_r2_put_url( $r2_file_key, $files['type'][$i], $file_size );
            $file_data   = file_get_contents( $file_path );
            $host        = get_option( 'pmp_r2_account_id' ) . '.r2.cloudflarestorage.com';

            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL,           $upload_url );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
            curl_setopt( $ch, CURLOPT_POSTFIELDS,    $file_data );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_HEADER,        false );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, [
                'Host: '           . $host,
                'Content-Type: '   . $files['type'][$i],
                'Content-Length: ' . $file_size,
            ]);
            $response  = curl_exec( $ch );
            $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            curl_close( $ch );

            if ( $http_code !== 200 ) {
                $errors[] = $files['name'][$i] . ': R2 hiba (HTTP ' . $http_code . ')';
                wp_delete_attachment( $attach_id, true );
                continue;
            }

            // 3. PMP mentés
            $title    = trim( "$location $category" ) ?: $filename;
            $photo_id = PMP_Photo::save( [
                'title'            => $title,
                'location'         => $location,
                'category'         => $category,
                'shot_date'        => $shot_date,
                'price'            => $price,
                'preview_image_id' => $attach_id,
                'use_external'     => 1,
                'external_key'     => $r2_file_key,
                'download_url'     => '',
                'edit_option_ids'  => $opt_ids,
            ] );

            // 4. Előzetes letöltési token (importhoz)
            $secure_token = bin2hex( random_bytes( 32 ) );
            $wpdb->insert( $wpdb->prefix . 'pmp_download_tokens', [
                'token'          => $secure_token,
                'order_id'       => 0,
                'order_item_id'  => 0,
                'photo_id'       => $photo_id,
                'customer_email' => 'import@system.local',
                'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + 315360000 ),
                'max_downloads'  => 20,
            ] );

            $created[] = [ 'photo_id' => $photo_id, 'title' => $title ];
        }

        wp_send_json_success( [ 'created' => $created, 'errors' => $errors ] );
    }

    public static function ajax_get_filter_data() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        wp_send_json_success( [
            'locations'  => PMP_Photo::get_locations(),
            'categories' => PMP_Photo::get_categories(),
        ] );
    }

    /* ── AJAX: Edit options ─────────────────────────────────── */

    public static function ajax_save_edit_option() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        global $wpdb;
        $id    = intval( $_POST['id'] ?? 0 );
        $name  = sanitize_text_field( $_POST['name'] ?? '' );
        $desc  = sanitize_textarea_field( $_POST['description'] ?? '' );
        $price = floatval( str_replace( ',', '.', $_POST['price'] ?? 0 ) );
        $active= intval( $_POST['active'] ?? 1 );
        if ( ! $name ) wp_send_json_error( 'Név kötelező.' );
        $data = [ 'name' => $name, 'description' => $desc, 'price' => $price, 'active' => $active ];
        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'pmp_edit_options', $data, [ 'id' => $id ] );
            wp_send_json_success( [ 'action' => 'updated', 'id' => $id ] );
        } else {
            $data['sort_order'] = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$wpdb->prefix}pmp_edit_options" ) + 1;
            $wpdb->insert( $wpdb->prefix . 'pmp_edit_options', $data );
            wp_send_json_success( [ 'action' => 'created', 'id' => $wpdb->insert_id ] );
        }
    }

    public static function ajax_delete_edit_option() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        $wpdb->delete( $wpdb->prefix . 'pmp_edit_options', [ 'id' => $id ] );
        $wpdb->delete( $wpdb->prefix . 'pmp_photo_edit_options', [ 'edit_option_id' => $id ] );
        wp_send_json_success();
    }

    public static function ajax_reorder_edit_options() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        global $wpdb;
        foreach ( array_map( 'intval', $_POST['order'] ?? [] ) as $sort => $id ) {
            $wpdb->update( $wpdb->prefix . 'pmp_edit_options', [ 'sort_order' => $sort ], [ 'id' => $id ] );
        }
        wp_send_json_success();
    }

    /* ── AJAX: Settings & tokens ────────────────────────────── */

    public static function ajax_save_settings() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        $map = [
            'expiry_hours'    => 'pmp_download_expiry_hours',
            'max_downloads'   => 'pmp_download_max_count',
            'r2_enabled'      => 'pmp_r2_enabled',
            'r2_account_id'   => 'pmp_r2_account_id',
            'r2_access_key'   => 'pmp_r2_access_key',
            'r2_bucket'       => 'pmp_r2_bucket',
            'r2_custom_domain'=> 'pmp_r2_custom_domain',
            'gallery_count'   => 'pmp_gallery_count',
        ];
        foreach ( $map as $post_key => $opt_key ) {
            if ( isset( $_POST[ $post_key ] ) ) update_option( $opt_key, sanitize_text_field( $_POST[ $post_key ] ) );
        }
        if ( ! empty( $_POST['r2_secret_key'] ) && $_POST['r2_secret_key'] !== '••••••••' ) {
            update_option( 'pmp_r2_secret_key', sanitize_text_field( $_POST['r2_secret_key'] ) );
        }
        wp_send_json_success( 'Mentve.' );
    }

    public static function ajax_r2_test() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        wp_send_json( PMP_R2::test_connection() );
    }

    public static function ajax_resend_links() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        $order_id = intval( $_POST['order_id'] ?? 0 );
        delete_post_meta( $order_id, '_pmp_tokens_sent' );
        do_action( 'woocommerce_order_status_completed', $order_id );
        wp_send_json_success( 'Újraküldve.' );
    }

    public static function ajax_extend_token() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        PMP_Download::extend_token( sanitize_text_field( $_POST['token'] ?? '' ) );
        wp_send_json_success();
    }

    public static function ajax_delete_token() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        global $wpdb;
        $token = sanitize_text_field( $_POST['token'] ?? '' );
        if ( ! empty( $token ) ) {
            $wpdb->delete( $wpdb->prefix . 'pmp_download_tokens', [ 'token' => $token ] );
            wp_send_json_success( 'A letöltési link törölve.' );
        }
        wp_send_json_error( 'Hiányzó token.' );
    }

    public static function ajax_upload_edited_photo() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        $order_id    = intval( $_POST['order_id'] ?? 0 );
        $item_id     = intval( $_POST['order_item_id'] ?? 0 );
        $label       = sanitize_text_field( $_POST['label'] ?? '' );

        if ( ! $order_id || ! $item_id || ! $label ) wp_send_json_error( 'Hiányzó adatok.' );

        if ( empty( $_FILES['edited_file'] ) || $_FILES['edited_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'Fájl feltöltési hiba.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file      = $_FILES['edited_file'];
        $attach_id = media_handle_sideload( [
            'name'     => $file['name'],
            'tmp_name' => $file['tmp_name'],
            'type'     => $file['type'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        ], 0 );

        if ( is_wp_error( $attach_id ) ) {
            wp_send_json_error( 'Fájl feldolgozási hiba: ' . $attach_id->get_error_message() );
        }

        $r2_key  = 'szerkesztett/' . time() . '_' . sanitize_file_name( $file['name'] );
        $path    = get_attached_file( $attach_id );
        $size    = filesize( $path );
        $host    = get_option( 'pmp_r2_account_id' ) . '.r2.cloudflarestorage.com';
        $put_url = self::generate_r2_put_url( $r2_key, $file['type'], $size );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL,            $put_url );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST,  'PUT' );
        curl_setopt( $ch, CURLOPT_POSTFIELDS,     file_get_contents( $path ) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HEADER,         false );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Host: '           . $host,
            'Content-Type: '   . $file['type'],
            'Content-Length: ' . $size,
        ] );
        curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        wp_delete_attachment( $attach_id, true );

        if ( $http_code !== 200 ) {
            wp_send_json_error( 'R2 feltöltés sikertelen (HTTP ' . $http_code . ')' );
        }

        // Store as pre-upload on order meta
        $uploads   = get_post_meta( $order_id, '_pmp_edited_uploads', true ) ?: [];
        $uploads[] = [
            'label'         => $label,
            'r2_key'        => $r2_key,
            'order_item_id' => $item_id,
        ];
        update_post_meta( $order_id, '_pmp_edited_uploads', $uploads );

        wp_send_json_success( [ 'r2_key' => $r2_key, 'label' => $label ] );
    }

    public static function ajax_delete_preupload() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        $order_id = intval( $_POST['order_id'] ?? 0 );
        $idx      = intval( $_POST['idx'] ?? -1 );
        $uploads  = get_post_meta( $order_id, '_pmp_edited_uploads', true ) ?: [];

        if ( isset( $uploads[ $idx ] ) ) {
            array_splice( $uploads, $idx, 1 );
            update_post_meta( $order_id, '_pmp_edited_uploads', $uploads );
        }
        wp_send_json_success();
    }

    public static function ajax_send_order_email() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();

        $order_id = intval( $_POST['order_id'] ?? 0 );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) wp_send_json_error( 'Rendelés nem található.' );

        $tokens = PMP_Download::get_tokens_for_order( $order_id );
        if ( empty( $tokens ) ) wp_send_json_error( 'Nincsenek letöltési linkek.' );

        $tokens_for_email = [];
        foreach ( $tokens as $t ) {
            $tokens_for_email[] = [
                'photo_title'   => $t['photo_title'] ?: '–',
                'label'         => $t['label'] ?: 'Eredeti',
                'download_url'  => PMP_Download::get_download_url( $t['token'] ),
                'expires_hours' => get_option( 'pmp_download_expiry_hours', 48 ),
                'max_downloads' => get_option( 'pmp_download_max_count', 3 ),
            ];
        }

        PMP_Order::send_download_email_public( $order, $tokens_for_email );
        wp_send_json_success( 'Email elküldve.' );
    }

    public static function ajax_clean_orphaned_tokens() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die();
        global $wpdb;
        $deleted = $wpdb->query( "
            DELETE t FROM {$wpdb->prefix}pmp_download_tokens t
            LEFT JOIN {$wpdb->prefix}pmp_photos p ON t.photo_id = p.id
            WHERE p.id IS NULL
        " );
        wp_send_json_success( sprintf( 'Sikeresen kitakarítva %d db elárvult letöltési link.', $deleted ) );
    }
}
