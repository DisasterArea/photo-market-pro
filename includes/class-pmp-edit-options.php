<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Edit_Options {

    public static function init() {
        // Admin AJAX for saving options
        add_action( 'wp_ajax_pmp_save_edit_option',   [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_pmp_delete_edit_option', [ __CLASS__, 'ajax_delete' ] );
        add_action( 'wp_ajax_pmp_reorder_edit_options', [ __CLASS__, 'ajax_reorder' ] );
    }

    public static function ajax_save() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        global $wpdb;
        $table = $wpdb->prefix . 'pmp_edit_options';

        $id    = intval( $_POST['id'] ?? 0 );
        $name  = sanitize_text_field( $_POST['name'] ?? '' );
        $desc  = sanitize_textarea_field( $_POST['description'] ?? '' );
        $price = floatval( str_replace( ',', '.', $_POST['price'] ?? 0 ) );
        $active= intval( $_POST['active'] ?? 1 );

        if ( empty( $name ) ) {
            wp_send_json_error( 'Név megadása kötelező.' );
        }

        $data = [ 'name' => $name, 'description' => $desc, 'price' => $price, 'active' => $active ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            wp_send_json_success( [ 'action' => 'updated', 'id' => $id ] );
        } else {
            $max_order = $wpdb->get_var( "SELECT MAX(sort_order) FROM $table" ) ?? 0;
            $data['sort_order'] = $max_order + 1;
            $wpdb->insert( $table, $data );
            wp_send_json_success( [ 'action' => 'created', 'id' => $wpdb->insert_id ] );
        }
    }

    public static function ajax_delete() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Hibás ID.' );

        $wpdb->delete( $wpdb->prefix . 'pmp_edit_options', [ 'id' => $id ] );
        $wpdb->delete( $wpdb->prefix . 'pmp_photo_edit_options', [ 'edit_option_id' => $id ] );
        wp_send_json_success();
    }

    public static function ajax_reorder() {
        check_ajax_referer( 'pmp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        global $wpdb;
        $order = array_map( 'intval', $_POST['order'] ?? [] );
        foreach ( $order as $sort => $id ) {
            $wpdb->update( $wpdb->prefix . 'pmp_edit_options', [ 'sort_order' => $sort ], [ 'id' => $id ] );
        }
        wp_send_json_success();
    }

    public static function get_all( $active_only = false ) {
        global $wpdb;
        $where = $active_only ? 'WHERE active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}pmp_edit_options $where ORDER BY sort_order ASC",
            ARRAY_A
        );
    }
}
