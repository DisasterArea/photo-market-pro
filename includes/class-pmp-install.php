<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Install {

    public static function init() {
        if ( ! get_option( 'pmp_wc_category_created' ) ) {
            if ( ! term_exists( 'Fotók', 'product_cat' ) ) {
                wp_insert_term( 'Fotók', 'product_cat' );
            }
            update_option( 'pmp_wc_category_created', 1 );
        }
        // DB upgrade: add new columns if missing
        self::maybe_upgrade();
    }

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pmp_edit_options (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(200)    NOT NULL,
            description TEXT            DEFAULT NULL,
            price       DECIMAL(10,2)   NOT NULL DEFAULT '0.00',
            sort_order  INT             NOT NULL DEFAULT 0,
            active      TINYINT(1)      NOT NULL DEFAULT 1,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pmp_photos (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id          BIGINT UNSIGNED NOT NULL,
            title               VARCHAR(300)    NOT NULL DEFAULT '',
            location            VARCHAR(200)    NOT NULL DEFAULT '',
            category            VARCHAR(200)    NOT NULL DEFAULT '',
            shot_date           DATE            DEFAULT NULL,
            preview_image_id    BIGINT UNSIGNED DEFAULT NULL,
            preview_url         TEXT            DEFAULT NULL,
            download_url        TEXT            DEFAULT NULL,
            use_external        TINYINT(1)      NOT NULL DEFAULT 0,
            external_key        VARCHAR(500)    DEFAULT NULL,
            width_px            INT             DEFAULT NULL,
            height_px           INT             DEFAULT NULL,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY location (location),
            KEY category (category),
            KEY shot_date (shot_date)
        ) $charset_collate;";

        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pmp_photo_edit_options (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            photo_id        BIGINT UNSIGNED NOT NULL,
            edit_option_id  BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY photo_option (photo_id, edit_option_id)
        ) $charset_collate;";

        $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pmp_download_tokens (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token           VARCHAR(64)     NOT NULL,
            order_id        BIGINT UNSIGNED NOT NULL,
            order_item_id   BIGINT UNSIGNED NOT NULL,
            photo_id        BIGINT UNSIGNED NOT NULL,
            customer_email  VARCHAR(200)    NOT NULL,
            expires_at      DATETIME        NOT NULL,
            download_count  INT             NOT NULL DEFAULT 0,
            max_downloads   INT             NOT NULL DEFAULT 3,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY order_id (order_id),
            KEY photo_id (photo_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );

        // Seed default edit options if empty
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}pmp_edit_options" );
        if ( $count == 0 ) {
            $defaults = [
                [ 'Retusálás',              'Bőrhibák, pattanások, árnyékok eltüntetése.',   2990 ],
                [ 'Színkorrekció',           'Professzionális színhangolás, tónusbeállítás.',  1990 ],
                [ 'Objektum eltávolítás',   'Zavaró elemek, emberek, tárgyak törlése.',       3990 ],
                [ 'Háttércsere',            'Eredeti háttér cseréje tetszőleges háttérre.',   4990 ],
                [ 'Fekete-fehér konverzió', 'Művészi fekete-fehér feldolgozás.',              1490 ],
            ];
            foreach ( $defaults as $i => $row ) {
                $wpdb->insert( $wpdb->prefix . 'pmp_edit_options', [
                    'name' => $row[0], 'description' => $row[1], 'price' => $row[2], 'sort_order' => $i, 'active' => 1,
                ] );
            }
        }

        if ( ! get_option( 'pmp_version' ) ) {
            update_option( 'pmp_download_expiry_hours', 48 );
            update_option( 'pmp_download_max_count', 3 );
            update_option( 'pmp_r2_enabled', 0 );
            update_option( 'pmp_gallery_count', 6 );
        }
        update_option( 'pmp_version', PMP_VERSION );
        flush_rewrite_rules();
    }

    private static function maybe_upgrade() {
        global $wpdb;
        // Add new columns to existing installs that had the old schema
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}pmp_photos", 0 );
        if ( ! in_array( 'location', $cols ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}pmp_photos ADD COLUMN location VARCHAR(200) NOT NULL DEFAULT '' AFTER title" );
        }
        if ( ! in_array( 'category', $cols ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}pmp_photos ADD COLUMN category VARCHAR(200) NOT NULL DEFAULT '' AFTER location" );
        }
        if ( ! in_array( 'shot_date', $cols ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}pmp_photos ADD COLUMN shot_date DATE DEFAULT NULL AFTER category" );
        }
        if ( ! in_array( 'preview_image_id', $cols ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}pmp_photos ADD COLUMN preview_image_id BIGINT UNSIGNED DEFAULT NULL AFTER shot_date" );
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
