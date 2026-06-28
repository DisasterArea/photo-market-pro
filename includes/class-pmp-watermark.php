<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.25;

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

    /* ── Imagick path ─────────────────────────────────── */
    private static function apply_imagick( $file, $mime ) {
        try {
            $img  = new Imagick( $file );
            $w    = $img->getImageWidth();
            $h    = $img->getImageHeight();

            $diag      = sqrt( $w * $w + $h * $h );
            $diag_deg  = rad2deg( atan2( $h, $w ) ); // angle of image diagonal
            $font_size = max( 24, intval( $diag * 0.038 ) );

            $draw = new ImagickDraw();
            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );

            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { /* next */ }
            }

            // Place at 1/3 along diagonal from top-left
            $x = intval( $w * 0.10 );
            $y = intval( $h * 0.38 );

            $img->annotateImage( $draw, $x, $y, -$diag_deg, self::TEXT );

            if ( $mime === 'image/jpeg' ) $img->setImageCompressionQuality( 92 );
            $img->writeImage( $file );
            $img->destroy();
        } catch ( Exception $e ) {
            // silent
        }
    }

    /* ── GD path ──────────────────────────────────────── */
    private static function apply_gd( $file, $mime ) {
        $font = self::find_font();
        if ( ! $font || ! file_exists( $font ) ) return;

        if ( $mime === 'image/jpeg' ) {
            $src = @imagecreatefromjpeg( $file );
        } else {
            $src = @imagecreatefrompng( $file );
        }
        if ( ! $src ) return;

        $w = imagesx( $src );
        $h = imagesy( $src );

        // Diagonal-based sizing and angle
        $diag      = sqrt( $w * $w + $h * $h );
        $diag_deg  = rad2deg( atan2( $h, $w ) ); // e.g. ~34° landscape, ~56° portrait
        $font_size = max( 20, intval( $diag * 0.038 ) );

        // GD imagettftext: angle is CCW, positive = CCW, negative = CW
        // We want text going bottom-left → top-right (matching the diagonal)
        // That's +diag_deg CCW from horizontal
        $gd_angle = $diag_deg;

        // Measure text bounding box at this angle
        $bbox = imagettfbbox( $font_size, $gd_angle, $font, self::TEXT );

        // Center of the bbox
        $cx_bbox = ( $bbox[0] + $bbox[4] ) / 2;
        $cy_bbox = ( $bbox[1] + $bbox[5] ) / 2;

        // We want text centered at 1/3 of the diagonal (upper-left third)
        $target_x = intval( $w / 3 );
        $target_y = intval( $h / 3 );

        // imagettftext baseline origin: offset so bbox center lands on target
        $tx = intval( $target_x - $cx_bbox );
        $ty = intval( $target_y - $cy_bbox );

        // 25% opacity → alpha 95 (GD: 0=opaque, 127=transparent)
        $alpha  = intval( 127 * ( 1 - self::OPACITY ) );
        $white  = imagecolorallocatealpha( $src, 255, 255, 255, $alpha );
        $shadow = imagecolorallocatealpha( $src, 0, 0, 0, min( 127, $alpha + 25 ) );

        imagealphablending( $src, true );
        // Shadow +1,+1
        imagettftext( $src, $font_size, $gd_angle, $tx + 2, $ty + 2, $shadow, $font, self::TEXT );
        imagettftext( $src, $font_size, $gd_angle, $tx,     $ty,     $white,  $font, self::TEXT );

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
