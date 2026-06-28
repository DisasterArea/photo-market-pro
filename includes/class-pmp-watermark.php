<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.20;
    const ANGLE   = 35; // GD rotates CCW, +35 = bottom-left→top-right diagonal

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
            $size = max( 32, intval( $w * 0.058 ) );

            $draw = new ImagickDraw();
            $draw->setFontSize( $size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );

            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { /* next */ }
            }

            $h = $img->getImageHeight();
            $x = intval( $w * 0.03 );
            $y = intval( $h * 0.44 );

            $img->annotateImage( $draw, $x, $y, -self::ANGLE, self::TEXT );

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

        $w         = imagesx( $src );
        $h         = imagesy( $src );
        $font_size = max( 24, intval( $w * 0.058 ) );

        // Measure text
        $bbox = imagettfbbox( $font_size, 0, $font, self::TEXT );
        $tw   = abs( $bbox[4] - $bbox[0] ) + 20;
        $th   = abs( $bbox[5] - $bbox[1] ) + 20;

        // Text layer (transparent background)
        $layer = imagecreatetruecolor( $tw, $th );
        imagealphablending( $layer, false );
        imagesavealpha( $layer, true );
        $bg = imagecolorallocatealpha( $layer, 0, 0, 0, 127 ); // fully transparent
        imagefill( $layer, 0, 0, $bg );
        imagealphablending( $layer, true );

        // 20% opacity = alpha 102 (GD: 0=opaque, 127=transparent)
        $white  = imagecolorallocatealpha( $layer, 255, 255, 255, 102 );
        $shadow = imagecolorallocatealpha( $layer, 0, 0, 0, 120 );

        // Draw with 1px shadow for legibility
        imagettftext( $layer, $font_size, 0, 11, $th - 6, $shadow, $font, self::TEXT );
        imagettftext( $layer, $font_size, 0, 10, $th - 7, $white,  $font, self::TEXT );

        // Rotate (GD CCW, +35 = our bottom-left→top-right diagonal)
        $rotated = imagerotate( $layer, self::ANGLE, $bg, 1 );
        imagesavealpha( $rotated, true );

        $rw = imagesx( $rotated );
        $rh = imagesy( $rotated );

        // Composite onto photo
        $dx = intval( $w * 0.02 );
        $dy = intval( $h * 0.28 );
        imagealphablending( $src, true );
        imagecopy( $src, $rotated, $dx, $dy, 0, 0, $rw, $rh );

        if ( $mime === 'image/jpeg' ) {
            imagejpeg( $src, $file, 92 );
        } else {
            imagesavealpha( $src, true );
            imagepng( $src, $file, 9 );
        }

        imagedestroy( $src );
        imagedestroy( $layer );
        imagedestroy( $rotated );
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
