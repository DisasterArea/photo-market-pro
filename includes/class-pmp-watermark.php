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

    /* ── Imagick path ─────────────────────────────────── */
    private static function apply_imagick( $file, $mime ) {
        try {
            $img = new Imagick( $file );
            $w   = $img->getImageWidth();
            $h   = $img->getImageHeight();
            $s   = min( $w, $h );

            $font_size = max( 20, intval( $s * 0.06 ) );

            // Center on 45° line: distance s/2 from corner → x=y= s/2/sqrt(2) = s*0.354
            $cx = intval( $s * 0.354 );
            $cy = intval( $s * 0.354 );

            $draw = new ImagickDraw();
            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );

            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { }
            }

            $img->annotateImage( $draw, $cx, $cy, -45, self::TEXT );

            if ( $mime === 'image/jpeg' ) $img->setImageCompressionQuality( 92 );
            $img->writeImage( $file );
            $img->destroy();
        } catch ( Exception $e ) { }
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

        // Target center: on the 45° line, distance s/2 from corner → x = y = s * 0.354
        $cx = intval( $s * 0.354 );
        $cy = intval( $s * 0.354 );

        // Step 1: measure text at 0° to get real pixel dimensions
        $bbox = imagettfbbox( $font_size, 0, $font, self::TEXT );
        $tw   = abs( $bbox[2] - $bbox[0] ); // width
        $th   = abs( $bbox[7] - $bbox[1] ); // height
        $pad  = intval( $th * 0.6 );

        // Step 2: draw text on its own canvas (horizontal, centered)
        $lw    = $tw + $pad * 2;
        $lh    = $th + $pad * 2;
        $layer = imagecreatetruecolor( $lw, $lh );
        imagealphablending( $layer, false );
        imagesavealpha( $layer, true );
        $trans = imagecolorallocatealpha( $layer, 0, 0, 0, 127 );
        imagefill( $layer, 0, 0, $trans );
        imagealphablending( $layer, true );

        $alpha  = intval( 127 * ( 1 - self::OPACITY ) );
        $white  = imagecolorallocatealpha( $layer, 255, 255, 255, $alpha );
        $shadow = imagecolorallocatealpha( $layer, 0,   0,   0,   min( 127, $alpha + 25 ) );

        // baseline y: pad from bottom of layer
        $bx = $pad;
        $by = $lh - $pad;
        imagettftext( $layer, $font_size, 0, $bx + 2, $by + 1, $shadow, $font, self::TEXT );
        imagettftext( $layer, $font_size, 0, $bx,     $by,     $white,  $font, self::TEXT );

        // Step 3: rotate 45° CCW (GD convention: positive = CCW)
        $rotated = imagerotate( $layer, 45, $trans, 1 );
        imagesavealpha( $rotated, true );
        imagedestroy( $layer );

        $rw = imagesx( $rotated );
        $rh = imagesy( $rotated );

        // Step 4: place so CENTER of rotated layer = (cx, cy)
        $dx = $cx - intval( $rw / 2 );
        $dy = $cy - intval( $rh / 2 );

        imagealphablending( $src, true );
        imagecopy( $src, $rotated, $dx, $dy, 0, 0, $rw, $rh );
        imagedestroy( $rotated );

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
