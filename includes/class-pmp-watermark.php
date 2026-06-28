<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.20; // 20%
    const ANGLE   = -35;  // degrees, bottom-left → top-right diagonal

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
            $size = max( 28, intval( $w * 0.042 ) );

            $draw = new ImagickDraw();
            $draw->setFontSize( $size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );

            // Try common system fonts
            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { /* try next */ }
            }

            // Position: upper-left third, below where card tags would sit
            $x = intval( $w * 0.10 );
            $y = intval( $h * 0.34 );

            $img->annotateImage( $draw, $x, $y, self::ANGLE, self::TEXT );

            if ( $mime === 'image/jpeg' ) {
                $img->setImageCompressionQuality( 92 );
            }
            $img->writeImage( $file );
            $img->destroy();
        } catch ( Exception $e ) {
            // silent – never break upload
        }
    }

    /* ── GD path ──────────────────────────────────────── */
    private static function apply_gd( $file, $mime ) {
        try {
            if ( $mime === 'image/jpeg' ) {
                $src = imagecreatefromjpeg( $file );
            } else {
                $src = imagecreatefrompng( $file );
                imagesavealpha( $src, true );
            }
            if ( ! $src ) return;

            $w = imagesx( $src );
            $h = imagesy( $src );

            $font      = self::find_font();
            $font_size = max( 20, intval( $w * 0.042 ) );

            // Render text onto a temp canvas, then rotate + composite
            $bbox = imagettfbbox( $font_size, 0, $font, self::TEXT );
            $tw   = abs( $bbox[4] - $bbox[0] );
            $th   = abs( $bbox[5] - $bbox[1] );

            // Create text layer (RGBA)
            $layer = imagecreatetruecolor( $tw + 10, $th + 10 );
            imagealphablending( $layer, false );
            imagesavealpha( $layer, true );
            $transparent = imagecolorallocatealpha( $layer, 0, 0, 0, 127 );
            imagefill( $layer, 0, 0, $transparent );
            imagealphablending( $layer, true );

            // White text at 20% opacity → alpha 0–127 where 0=opaque, 127=transparent
            // 20% opacity = 80% transparent → alpha = intval(127 * 0.80) = 102
            $alpha    = intval( 127 * ( 1 - self::OPACITY ) );
            $color    = imagecolorallocatealpha( $layer, 255, 255, 255, $alpha );
            $shadow_c = imagecolorallocatealpha( $layer, 0, 0, 0, min( 127, $alpha + 20 ) );

            // Shadow (+1,+1) for legibility
            imagettftext( $layer, $font_size, 0, 6, $th + 1, $shadow_c, $font, self::TEXT );
            imagettftext( $layer, $font_size, 0, 5, $th,     $color,    $font, self::TEXT );

            // Rotate text layer
            $rotated = imagerotate( $layer, -self::ANGLE, $transparent );
            imagesavealpha( $rotated, true );

            $rw = imagesx( $rotated );
            $rh = imagesy( $rotated );

            // Destination: upper-left third (below tag area)
            $dx = intval( $w * 0.08 );
            $dy = intval( $h * 0.22 );

            // Composite onto source
            imagealphablending( $src, true );
            imagecopy( $src, $rotated, $dx, $dy, 0, 0, $rw, $rh );

            if ( $mime === 'image/jpeg' ) {
                imagejpeg( $src, $file, 92 );
            } else {
                imagepng( $src, $file, 9 );
            }

            imagedestroy( $src );
            imagedestroy( $layer );
            imagedestroy( $rotated );
        } catch ( Exception $e ) {
            // silent
        }
    }

    private static function find_font() {
        $candidates = [
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];
        foreach ( $candidates as $f ) {
            if ( file_exists( $f ) ) return $f;
        }
        // Last resort: bundle a minimal font with the plugin
        return PMP_DIR . 'assets/fonts/LiberationSans-Regular.ttf';
    }
}
