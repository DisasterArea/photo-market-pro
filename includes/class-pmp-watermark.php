<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.30;

    public static function init() {
        add_filter( 'wp_handle_upload', [ __CLASS__, 'apply' ], 10, 2 );
    }

    public static function apply( $upload, $context = 'upload' ) {
        $log = date('H:i:s') . " apply() context=$context mime=" . ($upload['type']??'-') . " imagick=" . (class_exists('Imagick')?'yes':'no') . "\n";
        file_put_contents( PMP_DIR . 'wm-debug.log', $log, FILE_APPEND );

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

            // Set up draw object with font
            $draw = new ImagickDraw();
            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { }
            }
            $draw->setTextAntialias( true );

            // Measure text at reference size 40
            $draw->setFontSize( 40 );
            $metrics = $img->queryFontMetrics( $draw, self::TEXT );
            $ref_tw  = $metrics['textWidth'] ?? 0;
            $ref_th  = $metrics['textHeight'] ?? 0;

            // Target: text fills 40% of the diagonal of the s×s square
            $diag_len  = ( $s - 2 * $margin ) * sqrt( 2 ) * 0.40;
            $font_size = ( $ref_tw > 0 ) ? intval( 40 * $diag_len / $ref_tw ) : 60;
            $font_size = max( 14, $font_size );

            // Safety loop: shrink until text fits, then center on diagonal
            $tw = 0; $th = 0;
            for ( $i = 0; $i < 15; $i++ ) {
                $draw->setFontSize( $font_size );
                $m      = $img->queryFontMetrics( $draw, self::TEXT );
                $tw     = $m['textWidth']  ?? $font_size * 8;
                $th     = $m['textHeight'] ?? $font_size * 1.3;
                $extent = intval( ( $tw + $th ) * 0.707 );
                $log    = date('H:i:s') . " imagick iter=$i font=$font_size tw=$tw th=$th extent=$extent diag_avail=" . intval($s-2*$margin) . "\n";
                file_put_contents( PMP_DIR . 'wm-debug.log', $log, FILE_APPEND );
                if ( $extent <= ( $s - 2 * $margin ) ) break;
                $font_size = intval( $font_size * 0.85 );
            }

            // Position center of text at 75% along diagonal from lower-left (= upper-left area)
            $diag_avail = $s - 2 * $margin;
            $cx = $margin + $diag_avail * 0.75;
            $cy = ( $s - $margin ) - $diag_avail * 0.75;
            $tx = intval( $cx - $tw / ( 2.0 * sqrt(2) ) );
            $ty = intval( $cy + $tw / ( 2.0 * sqrt(2) ) );
            file_put_contents( PMP_DIR . 'wm-debug.log', date('H:i:s') . " FINAL font=$font_size tw=$tw th=$th tx=$tx ty=$ty s=$s w=$w h=$h\n", FILE_APPEND );

            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );

            // annotateImage: angle = CW degrees; -45 = 45°CCW = lower-left to upper-right
            $img->annotateImage( $draw, $tx, $ty, -45, self::TEXT );

            if ( $mime === 'image/jpeg' ) $img->setImageCompressionQuality( 92 );
            $img->writeImage( $file );
            $img->destroy();
        } catch ( Exception $e ) {
            file_put_contents( PMP_DIR . 'wm-debug.log', date('H:i:s') . " imagick exception: " . $e->getMessage() . "\n", FILE_APPEND );
        }
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

        // Anchor: text starts at lower-left of the s×s square, goes 45° upper-right
        $tx = $margin;
        $ty = $s - $margin;

        // Safety loop: shrink font until the full rotated bounding box fits within image
        for ( $i = 0; $i < 15; $i++ ) {
            $b_act  = imagettfbbox( $font_size, 0, $font, self::TEXT );
            $tw     = abs( $b_act[2] - $b_act[0] );
            $th     = abs( $b_act[7] - $b_act[1] );
            $extent = intval( ( $tw + $th ) * 0.707 );
            $log = date('H:i:s') . " iter=$i font=$font_size tw=$tw th=$th extent=$extent need_x=" . ($tx+$extent) . "<=" . ($w-$margin) . " need_y=" . ($ty-$extent) . ">=$margin\n";
            file_put_contents( PMP_DIR . 'wm-debug.log', $log, FILE_APPEND );
            if ( $tx + $extent <= ( $w - $margin ) && $ty - $extent >= $margin ) break;
            $font_size = intval( $font_size * 0.85 );
        }
        $log = date('H:i:s') . " FINAL font=$font_size image={$w}x{$h} s=$s margin=$margin tx=$tx ty=$ty\n";
        file_put_contents( PMP_DIR . 'wm-debug.log', $log, FILE_APPEND );

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
