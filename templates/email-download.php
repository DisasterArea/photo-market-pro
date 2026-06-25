<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Letöltési linkek</title>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header { background: #1a1a2e; color: #fff; padding: 30px 40px; }
  .header h1 { margin: 0; font-size: 22px; }
  .header p { margin: 6px 0 0; opacity: .75; font-size: 14px; }
  .body { padding: 32px 40px; }
  .greeting { font-size: 16px; color: #333; margin-bottom: 20px; }
  .download-card { border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px 20px; margin: 12px 0; }
  .download-card h3 { margin: 0 0 8px; font-size: 15px; color: #1a1a2e; }
  .download-card .meta { font-size: 13px; color: #777; margin: 4px 0; }
  .download-btn { display: inline-block; margin-top: 12px; padding: 10px 22px; background: #e94560; color: #fff !important; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; }
  .notice { background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; margin: 24px 0; font-size: 13px; border-radius: 0 4px 4px 0; }
  .footer { background: #f8f8f8; padding: 20px 40px; font-size: 12px; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <h1>📷 <?php echo esc_html( get_bloginfo('name') ); ?></h1>
    <p>Rendelés #<?php echo esc_html( $order->get_order_number() ); ?> – Letöltési linkek</p>
  </div>

  <div class="body">
    <p class="greeting">
      Kedves <?php echo esc_html( $name ?: 'Vásárló' ); ?>!<br><br>
      Köszönjük a vásárlást! Az alábbi gombokra kattintva töltheti le a megvásárolt fotó(ka)t.
    </p>

    <?php foreach ( $tokens as $item ) :
        $label = ! empty( $item['label'] ) && $item['label'] !== 'Eredeti' ? $item['label'] : '';
    ?>
    <div class="download-card">
      <h3><?php echo esc_html( $item['photo_title'] ); ?><?php if ( $label ) echo ' <span style="font-size:13px;font-weight:normal;color:#888;">– ' . esc_html( $label ) . '</span>'; ?></h3>
      <p class="meta">⏱ Érvényes: <?php echo intval( $item['expires_hours'] ); ?> óra</p>
      <p class="meta">📥 Max. letöltés: <?php echo intval( $item['max_downloads'] ); ?>×</p>
      <a class="download-btn" href="<?php echo esc_url( $item['download_url'] ); ?>">
        Letöltés →
      </a>
    </div>
    <?php endforeach; ?>

    <div class="notice">
      ⚠️ A letöltési linkek <?php echo intval( $expiry_h ); ?> óra elteltével lejárnak, és <?php echo intval( $max_dl ); ?> alkalommal használhatók.
      Problémák esetén vegye fel velünk a kapcsolatot.
    </div>

    <?php
    // Show edit requests if any
    $has_requests = false;
    foreach ( $order->get_items() as $item_obj ) {
        $opts    = $item_obj->get_meta( 'Szerkesztési opciók' );
        $request = $item_obj->get_meta( 'Megjegyzés' );
        if ( $opts || $request ) {
            if ( ! $has_requests ) {
                echo '<hr style="margin:24px 0;border:none;border-top:1px solid #eee;">';
                echo '<h3 style="color:#1a1a2e;font-size:15px;">Szerkesztési megrendelés</h3>';
                $has_requests = true;
            }
            echo '<p><strong>' . esc_html( $item_obj->get_name() ) . '</strong></p>';
            if ( $opts ) echo '<p>Opciók: ' . esc_html( $opts ) . '</p>';
            if ( $request ) echo '<p>Megjegyzés: <em>' . esc_html( $request ) . '</em></p>';
        }
    }
    ?>
  </div>

  <div class="footer">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html( get_bloginfo('name') ); ?> &bull;
    <a href="<?php echo home_url(); ?>" style="color:#999;"><?php echo home_url(); ?></a>
  </div>

</div>
</body>
</html>
