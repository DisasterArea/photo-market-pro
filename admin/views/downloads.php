<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1>Letöltési linkek</h1>
    
    <div class="tablenav top" style="margin: 20px 0;">
        <form method="get" style="display:inline-block;">
            <input type="hidden" name="page" value="pmp-downloads">
            <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="Keresés...">
            <input type="submit" class="button" value="Keresés">
        </form>
        
        <button type="button" id="pmp-clean-orphaned-links" class="button button-secondary" style="float:right; background:#d63638; color:#fff; border-color:#b32d2e;">
            <span class="dashicons dashicons-trash" style="vertical-align:middle; margin-top:-3px;"></span> Árva/Régi linkek takarítása
        </button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Fotó</th>
                <th>Vevő e-mail</th>
                <th>Rendelés</th>
                <th>Lejárt</th>
                <th>Letöltések</th>
                <th>Állapot</th>
                <th>Műveletek</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $tokens ) ) : ?>
                <?php foreach ( $tokens as $t ) : ?>
                    <tr id="token-row-<?php echo esc_attr($t['token']); ?>">
                        <td><strong><?php echo esc_html( $t['photo_title'] ?: '— (Törölt fotó)' ); ?></strong></td>
                        <td><?php echo esc_html( $t['customer_email'] ); ?></td>
                        <td><a href="<?php echo admin_url('post.php?post=' . $t['order_id'] . '&action=edit'); ?>">#<?php echo esc_html( $t['order_id'] ); ?></a></td>
                        <td><?php echo esc_html( $t['expires_at'] ); ?></td>
                        <td><?php echo esc_html( $t['download_count'] . ' / ' . $t['max_downloads'] ); ?></td>
                        <td><span class="badge" style="background:#e7f6ec; color:#2e7d32; padding:3px 8px; border-radius:4px;">Aktív</span></td>
                        <td>
                            <button type="button" class="button pmp-copy-link" data-link="<?php echo home_url( '/pmp-download/' . $t['token'] . '/' ); ?>">Link másolása</button>
                            <button type="button" class="button pmp-extend-link" data-token="<?php echo esc_attr($t['token']); ?>">Meghosszabbítás</button>
                            <button type="button" class="button pmp-delete-link" data-token="<?php echo esc_attr($t['token']); ?>" style="color:#d63638;">Törlés</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="7">Nincsenek letöltési linkek.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    $('.pmp-delete-link').on('click', function() {
        if (!confirm('Biztosan törölni szeretnéd ezt a letöltési linket?')) return;
        var btn = $(this);
        var token = btn.data('token');
        btn.prop('disabled', true).text('Törlés...');
        $.post(PMP.ajaxurl, {
            action: 'pmp_delete_token',
            nonce: PMP.nonce,
            token: token
        }, function(res) {
            if (res.success) {
                $('#token-row-' + token).fadeOut(400, function() { $(this).remove(); });
            } else {
                alert('Hiba történt: ' + res.data);
                btn.prop('disabled', false).text('Törlés');
            }
        });
    });

    $('#pmp-clean-orphaned-links').on('click', function() {
        if (!confirm('Biztosan ki szeretnél takarítani minden olyan linket, aminek az eredeti fotója már nem létezik?')) return;
        var btn = $(this);
        btn.prop('disabled', true).text('Takarítás folyamatban...');
        $.post(PMP.ajaxurl, {
            action: 'pmp_clean_orphaned_tokens',
            nonce: PMP.nonce
        }, function(res) {
            if (res.success) {
                alert(res.data);
                location.reload();
            } else {
                alert('Hiba történt.');
                btn.prop('disabled', false).text('Árva/Régi linkek takarítása');
            }
        });
    });

    $('.pmp-copy-link').on('click', function() {
        var link = $(this).data('link');
        navigator.clipboard.writeText(link).then(function() {
            alert('Link sikeresen a vágólapra másolva!');
        });
    });

    $('.pmp-extend-link').on('click', function() {
        if (!confirm('Meghosszabbítod ezt a letöltési linket?')) return;
        var btn = $(this);
        var token = btn.data('token');
        $.post(PMP.ajaxurl, {
            action: 'pmp_extend_token',
            nonce: PMP.nonce,
            token: token
        }, function(res) {
            if (res.success) {
                alert('Meghosszabbítva!');
                location.reload();
            }
        });
    });
});
</script>
