<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$email_heading = sprintf( 'Ordine #%s completato', $order->get_order_number() );

// Use WC email object for header/footer hooks
$wc_email = WC()->mailer()->get_emails()['WC_Email_Customer_Completed_Order'] ?? WC()->mailer()->get_emails()['WC_Email_New_Order'] ?? null;

do_action( 'woocommerce_email_header', $email_heading, $wc_email );
?>

<p><?php printf( 'Ciao %s,', esc_html( $name ?: 'Cliente' ) ); ?></p>
<p>Grazie per il tuo acquisto! Il tuo ordine è stato completato. Di seguito trovi il riepilogo e i link per scaricare le tue foto.</p>

<!-- Riepilogo ordine -->
<h2 style="color:#333;font-size:16px;margin:24px 0 12px;">Riepilogo ordine</h2>
<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
        <tr>
            <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #e0e0e0;color:#555;">Prodotto</th>
            <th style="text-align:center;padding:8px 12px;border-bottom:2px solid #e0e0e0;color:#555;">Qtà</th>
            <th style="text-align:right;padding:8px 12px;border-bottom:2px solid #e0e0e0;color:#555;">Prezzo</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $order->get_items() as $item ) : ?>
        <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;vertical-align:top;">
                <?php echo esc_html( $item->get_name() ); ?>
                <?php
                $opts = $item->get_meta( 'Szerkesztési opciók' );
                $note = $item->get_meta( 'Megjegyzés' );
                if ( $opts ) echo '<br><small style="color:#888;">Editing: ' . esc_html( $opts ) . '</small>';
                if ( $note ) echo '<br><small style="color:#888;">Note: ' . esc_html( $note ) . '</small>';
                ?>
            </td>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:center;"><?php echo (int) $item->get_quantity(); ?></td>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:right;"><?php echo wc_price( $item->get_total() ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ( $order->get_shipping_total() > 0 ) : ?>
        <tr>
            <td colspan="2" style="padding:10px 12px;border-bottom:1px solid #f0f0f0;">Spedizione</td>
            <td style="padding:10px 12px;border-bottom:1px solid #f0f0f0;text-align:right;"><?php echo wc_price( $order->get_shipping_total() ); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="2" style="padding:14px 12px;font-weight:700;font-size:15px;border-top:2px solid #e0e0e0;">Totale</td>
            <td style="padding:14px 12px;font-weight:700;font-size:15px;border-top:2px solid #e0e0e0;text-align:right;"><?php echo wc_price( $order->get_total() ); ?></td>
        </tr>
    </tbody>
</table>

<!-- Link di download -->
<h2 style="color:#333;font-size:16px;margin:32px 0 12px;">📥 Link per il download</h2>

<?php foreach ( $tokens as $item ) :
    $label = ! empty( $item['label'] ) && $item['label'] !== 'Eredeti' ? $item['label'] : '';
?>
<table cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e0e0e0;border-radius:6px;margin:10px 0;font-size:14px;">
    <tr>
        <td style="padding:16px 20px;">
            <strong style="font-size:15px;color:#333;"><?php echo esc_html( $item['photo_title'] ); ?><?php if ( $label ) echo ' <span style="font-weight:normal;color:#888;font-size:13px;">– ' . esc_html( $label ) . '</span>'; ?></strong><br>
            <span style="color:#777;font-size:13px;">⏱ Valido per: <?php echo intval( $item['expires_hours'] ); ?> ore &nbsp;|&nbsp; 📥 Download massimi: <?php echo intval( $item['max_downloads'] ); ?></span><br><br>
            <a href="<?php echo esc_url( $item['download_url'] ); ?>" style="display:inline-block;padding:10px 24px;background:#e8a020;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:700;font-size:14px;">Scarica la foto →</a>
        </td>
    </tr>
</table>
<?php endforeach; ?>

<!-- Avviso limiti -->
<table cellspacing="0" cellpadding="0" style="width:100%;margin:24px 0;">
    <tr>
        <td style="padding:14px 18px;background:#fff8e1;border-left:4px solid #f0a500;border-radius:0 4px 4px 0;font-size:13px;color:#555;">
            ⚠️ <strong>Attenzione:</strong> I link di download scadono dopo <strong><?php echo intval( $expiry_h ); ?> ore</strong> e possono essere utilizzati al massimo <strong><?php echo intval( $max_dl ); ?> volte</strong>. Salvare le foto immediatamente dopo il download. Per qualsiasi problema non esitate a contattarci.
        </td>
    </tr>
</table>

<?php do_action( 'woocommerce_email_footer', $wc_email ); ?>
