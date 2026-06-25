<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rendelés visszaigazolás</title>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  .header { background: #1a1a2e; color: #fff; padding: 30px 40px; }
  .header h1 { margin: 0; font-size: 22px; }
  .header p { margin: 6px 0 0; opacity: .75; font-size: 14px; }
  .body { padding: 32px 40px; }
  .greeting { font-size: 16px; color: #333; margin-bottom: 24px; }
  h2 { font-size: 15px; color: #1a1a2e; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; margin: 24px 0 12px; }
  /* Order summary table */
  .order-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .order-table th { text-align: left; padding: 8px 6px; color: #555; border-bottom: 1px solid #eee; font-weight: normal; }
  .order-table td { padding: 10px 6px; border-bottom: 1px solid #f5f5f5; color: #333; vertical-align: top; }
  .order-table .total-row td { font-weight: bold; font-size: 15px; border-top: 2px solid #eee; border-bottom: none; padding-top: 14px; }
  /* Download cards */
  .download-card { border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px 20px; margin: 10px 0; }
  .download-card h3 { margin: 0 0 6px; font-size: 15px; color: #1a1a2e; }
  .download-card .meta { font-size: 13px; color: #777; margin: 3px 0; }
  .download-btn { display: inline-block; margin-top: 12px; padding: 10px 22px; background: #e94560; color: #fff !important; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; }
  .notice { background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; margin: 24px 0; font-size: 13px; border-radius: 0 4px 4px 0; }
  .footer { background: #f8f8f8; padding: 20px 40px; font-size: 12px; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <h1>📷 <?php echo esc_html( get_bloginfo('name') ); ?></h1>
    <p>Rendelés #<?php echo esc_html( $order->get_order_number() ); ?> – <?php echo esc_html( wp_date( 'Y. m. d.', strtotime( $order->get_date_created() ) ) ); ?></p>
  </div>

  <div class="body">
    <p class="greeting">
      Kedves <?php echo esc_html( $name ?: 'Vásárló' ); ?>!<br><br>
      Köszönjük a vásárlást! Rendelésed teljesítve, az alábbiakban megtalálod az összesítőt és a letöltési linkeket.
    </p>

    <!-- Order summary -->
    <h2>Rendelési összesítő</h2>
    <table class="order-table">
      <thead>
        <tr>
          <th>Termék</th>
          <th style="text-align:center;">Db</th>
          <th style="text-align:right;">Ár</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $order->get_items() as $item ) : ?>
        <tr>
          <td>
            <?php echo esc_html( $item->get_name() ); ?>
            <?php
            $opts = $item->get_meta( 'Szerkesztési opciók' );
            $note = $item->get_meta( 'Megjegyzés' );
            if ( $opts ) echo '<br><span style="font-size:12px;color:#888;">Szerkesztés: ' . esc_html( $opts ) . '</span>';
            if ( $note ) echo '<br><span style="font-size:12px;color:#888;">Megjegyzés: ' . esc_html( $note ) . '</span>';
            ?>
          </td>
          <td style="text-align:center;"><?php echo (int) $item->get_quantity(); ?></td>
          <td style="text-align:right;"><?php echo wc_price( $item->get_total() ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ( $order->get_shipping_total() > 0 ) : ?>
        <tr>
          <td colspan="2">Szállítás</td>
          <td style="text-align:right;"><?php echo wc_price( $order->get_shipping_total() ); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
          <td colspan="2">Összesen</td>
          <td style="text-align:right;"><?php echo wc_price( $order->get_total() ); ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Download links -->
    <h2>📥 Letöltési linkek</h2>

    <?php foreach ( $tokens as $item ) :
      $label = ! empty( $item['label'] ) && $item['label'] !== 'Eredeti' ? $item['label'] : '';
    ?>
    <div class="download-card">
      <h3>
        <?php echo esc_html( $item['photo_title'] ); ?>
        <?php if ( $label ) echo '<span style="font-size:13px;font-weight:normal;color:#888;"> – ' . esc_html( $label ) . '</span>'; ?>
      </h3>
      <p class="meta">⏱ Érvényes: <?php echo intval( $item['expires_hours'] ); ?> óra</p>
      <p class="meta">📥 Max. letöltés: <?php echo intval( $item['max_downloads'] ); ?>×</p>
      <a class="download-btn" href="<?php echo esc_url( $item['download_url'] ); ?>">
        Letöltés →
      </a>
    </div>
    <?php endforeach; ?>

    <div class="notice">
      ⚠️ A letöltési linkek <?php echo intval( $expiry_h ); ?> óra elteltével lejárnak és <?php echo intval( $max_dl ); ?> alkalommal használhatók.
      Problémák esetén vegye fel velünk a kapcsolatot.
    </div>
  </div>

  <div class="footer">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html( get_bloginfo('name') ); ?> &bull;
    <a href="<?php echo home_url(); ?>" style="color:#999;"><?php echo home_url(); ?></a>
  </div>

</div>
</body>
</html>
