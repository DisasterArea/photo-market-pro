<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.30;
    const ANGLE   = 45; // fixed 45°

    /*
     * Watermark center is always at (s, s) where s = min(w,h) * 0.38
     * This places it on the 45° line from the top-left corner,
     * proportionally at the same relative distance on every image.
     * Font size is proportional to min(w,h).
     */

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
            $s    = min( $w, $h );

            $font_size = max( 20, intval( $s * 0.06 ) );
            $cx        = intval( $s * 0.38 );
            $cy        = intval( $s * 0.38 );

            $draw = new ImagickDraw();
            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );

            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { /* next */ }
            }

            // Imagick annotate: angle is CW, -45 = bottom-left→top-right
            $img->annotateImage( $draw, $cx, $cy, -self::ANGLE, self::TEXT );

            if ( $mime === 'image/jpeg' ) $img->setImageCompressionQuality( 92 );
            $img->writeImage( $file );
            $img->destroy();
        } catch ( Exception $e ) {
            // silent – never break upload
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
        $s = min( $w, $h );

        $font_size = max( 20, intval( $s * 0.06 ) );

        // Center point on 45° line from top-left
        $cx = intval( $s * 0.38 );
        $cy = intval( $s * 0.38 );

        // GD: angle is CCW, +45 = bottom-left→top-right
        $gd_angle = self::ANGLE;
        $rad      = deg2rad( $gd_angle );

        // Measure at 0° to get true text dimensions
        $bbox0 = imagettfbbox( $font_size, 0, $font, self::TEXT );
        $tw    = abs( $bbox0[4] - $bbox0[0] ); // text width
        $th    = abs( $bbox0[5] - $bbox0[1] ); // text height

        // Baseline origin so that text CENTER lands on (cx, cy)
        // For rotated text: center_x = tx + (tw/2)*cos(rad) - (th/2)*sin(rad)
        //                   center_y = ty - (tw/2)*sin(rad) - (th/2)*cos(rad)  [GD Y-down]
        // Solving for tx, ty:
        $tx = intval( $cx - ( $tw / 2 ) * cos( $rad ) + ( $th / 2 ) * sin( $rad ) );
        $ty = intval( $cy + ( $tw / 2 ) * sin( $rad ) + ( $th / 2 ) * cos( $rad ) );

        // 30% opacity → alpha = 127 * 0.70 = 89
        $alpha  = intval( 127 * ( 1 - self::OPACITY ) );
        $white  = imagecolorallocatealpha( $src, 255, 255, 255, $alpha );
        $shadow = imagecolorallocatealpha( $src, 0, 0, 0, min( 127, $alpha + 25 ) );

        imagealphablending( $src, true );
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
