<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PMP_Watermark {

    const TEXT    = '© ArcoScatto.it';
    const OPACITY = 0.30;
    const MARGIN  = 0.05; // 5% margin from edges

    /*
     * Text spans the 45° diagonal from near the left edge to near the top edge.
     * Font size is calculated so the text fills that diagonal length.
     * Center of text = center of image's short-side diagonal = (s/2, s/2).
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
            $img = new Imagick( $file );
            $w   = $img->getImageWidth();
            $h   = $img->getImageHeight();
            $s   = min( $w, $h );

            $margin      = intval( $s * self::MARGIN );
            $target_len  = ( $s - 2 * $margin ) * sqrt( 2 );

            // Measure text at reference size, then scale to fill diagonal
            $draw_ref = new ImagickDraw();
            $draw_ref->setFontSize( 40 );
            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw_ref->setFont( $f ); break; } catch ( Exception $e ) { }
            }
            $metrics   = $img->queryFontMetrics( $draw_ref, self::TEXT );
            $ref_tw    = $metrics['textWidth'] ?? 400;
            $font_size = max( 12, intval( 40 * $target_len / $ref_tw ) );

            $draw = new ImagickDraw();
            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( 'rgba(255,255,255,' . self::OPACITY . ')' ) );
            $draw->setTextAntialias( true );
            foreach ( [ 'Arial', 'DejaVu-Sans', 'Liberation-Sans', 'Helvetica' ] as $f ) {
                try { $draw->setFont( $f ); break; } catch ( Exception $e ) { }
            }

            // Center of diagonal
            $cx = intval( $s / 2 );
            $cy = intval( $s / 2 );

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

        $margin     = intval( $s * self::MARGIN );
        $target_len = ( $s - 2 * $margin ) * sqrt( 2 ); // diagonal length to fill

        // Measure text at reference size to calculate needed font size
        $bbox_ref  = imagettfbbox( 40, 0, $font, self::TEXT );
        $ref_tw    = abs( $bbox_ref[2] - $bbox_ref[0] );
        $font_size = max( 12, intval( 40 * $target_len / $ref_tw ) );

        // Measure actual text at final font size
        $bbox = imagettfbbox( $font_size, 0, $font, self::TEXT );
        $tw   = abs( $bbox[2] - $bbox[0] );
        $th   = abs( $bbox[7] - $bbox[1] );
        $pad  = intval( $th * 0.4 );

        // Draw text on its own horizontal canvas, centered
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

        $bx = $pad;
        $by = $lh - $pad;
        imagettftext( $layer, $font_size, 0, $bx + 2, $by + 1, $shadow, $font, self::TEXT );
        imagettftext( $layer, $font_size, 0, $bx,     $by,     $white,  $font, self::TEXT );

        // Rotate 45° CCW
        $rotated = imagerotate( $layer, 45, $trans, 1 );
        imagesavealpha( $rotated, true );
        imagedestroy( $layer );

        $rw = imagesx( $rotated );
        $rh = imagesy( $rotated );

        // Center of diagonal = (s/2, s/2)
        $cx = intval( $s / 2 );
        $cy = intval( $s / 2 );
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
