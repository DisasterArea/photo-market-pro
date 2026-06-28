<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.30;

    public static function init() {
        add_filter( 'wp_handle_upload', [ __CLASS__, 'apply' ], 10, 2 );
    }

    public static function apply( $upload, $context = 'upload' ) {
        if ( $context === 'sideload' ) return $upload;

        $mime = $upload['type'] ?? '';
        if ( ! in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) return $upload;

        $file = $upload['file'];

        if ( class_exists( 'Imagick' ) ) {
            self::apply_imagick( $file, $mime );
        } else {
            self::apply_gd( $file, $mime );
        }

        return $upload;
    }

    private static function apply_imagick( $file, $mime ) {
        try {
            $img    = new Imagick( $file );
            $w      = $img->getImageWidth();
            $h      = $img->getImageHeight();
            $s      = min( $w, $h );
            $margin = intval( $s * 0.05 );

            // Scale font so text width = diagonal length from (margin,s-margin) to (s-margin,margin)
            $diag_len  = ( $s - 2 * $margin ) * sqrt( 2 );
            $draw_ref  = new ImagickDraw();
            $draw_ref->setFontSize( 40 );
            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw_ref->setFont( $f ); break; } catch ( Exception $e ) { }
            }
            $metrics   = $img->queryFontMetrics( $draw_ref, self::TEXT );
            $ref_tw    = $metrics['textWidth'] ?? 400;
            $font_size = max( 14, intval( 40 * $diag_len / $ref_tw ) );

            $draw = new ImagickDraw();
            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );
            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { }
            }

            // Imagick annotateImage origin: top-left of text bounding box + angle CW
            // Place center at (s/2, s/2)
            $img->annotateImage( $draw, intval( $s / 2 ), intval( $s / 2 ), -45, self::TEXT );

            if ( $mime === 'image/jpeg' ) $img->setImageCompressionQuality( 92 );
            $img->writeImage( $file );
            $img->destroy();
        } catch ( Exception $e ) { }
    }

    private static function apply_gd( $file, $mime ) {
        $font = self::find_font();
        if ( ! $font || ! file_exists( $font ) ) return;

        if ( $mime === 'image/jpeg' ) {
            $src = @imagecreatefromjpeg( $file );
        } else {
            $src = @imagecreatefrompng( $file );
        }
        if ( ! $src ) return;

        $w      = imagesx( $src );
        $h      = imagesy( $src );
        $s      = min( $w, $h );
        $margin = intval( $s * 0.05 );

        // Diagonal length from (margin, s-margin) → (s-margin, margin)
        $diag_len = ( $s - 2 * $margin ) * sqrt( 2 );

        // Measure text width at reference size (0° = no rotation distortion)
        $b_ref = imagettfbbox( 40, 0, $font, self::TEXT );
        $tw_ref = abs( $b_ref[2] - $b_ref[0] );
        $font_size = max( 14, intval( 40 * $diag_len / $tw_ref ) );

        // Measure actual text at final size
        $b_act = imagettfbbox( $font_size, 0, $font, self::TEXT );
        $tw    = abs( $b_act[2] - $b_act[0] );
        $th    = abs( $b_act[7] - $b_act[1] );

        /*
         * GD imagettftext with angle=45 (CCW):
         * Baseline starts at (tx, ty) and goes upper-right.
         * We want start of visible text near (margin, s-margin)
         * and end near (s-margin, margin).
         *
         * At angle=45, the baseline origin (tx,ty) is slightly below
         * the visual text due to descenders — offset by ~th/2 downward
         * perpendicular to baseline direction.
         *
         * Simple: set tx = margin, ty = s - margin
         * This anchors the START of the text in the lower-left corner of the s×s square.
         */
        $tx = $margin;
        $ty = $s - $margin;

        $alpha  = intval( 127 * ( 1 - self::OPACITY ) );
        $white  = imagecolorallocatealpha( $src, 255, 255, 255, $alpha );
        $shadow = imagecolorallocatealpha( $src, 0, 0, 0, min( 127, $alpha + 25 ) );

        imagealphablending( $src, true );
        imagettftext( $src, $font_size, 45, $tx + 1, $ty + 1, $shadow, $font, self::TEXT );
        imagettftext( $src, $font_size, 45, $tx,     $ty,     $white,  $font, self::TEXT );

        if ( $mime === 'image/jpeg' ) {
            imagejpeg( $src, $file, 92 );
        } else {
            imagesavealpha( $src, true );
            imagepng( $src, $file, 9 );
        }
        imagedestroy( $src );
    }

    private static function find_font() {
        $candidates = [
            PMP_DIR . 'assets/fonts/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];
        foreach ( $candidates as $f ) {
            if ( file_exists( $f ) ) return $f;
        }
        return '';
    }
}
