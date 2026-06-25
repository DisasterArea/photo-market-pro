<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap pmp-wrap">
  <h1>Beállítások</h1>
  <div class="pmp-settings-form">

    <h2>Galéria</h2>
    <table class="form-table">
      <tr>
        <th><label for="gallery_count">Főoldali képek száma</label></th>
        <td>
          <input type="number" id="gallery_count" min="1" max="50" value="<?php echo esc_attr(get_option('pmp_gallery_count',6)); ?>">
          <p class="description">Ennyi véletlenszerű fotó jelenik meg a <code>[pmp_gallery]</code> shortcode-dal. Felülírható: <code>[pmp_gallery count="12"]</code></p>
        </td>
      </tr>
    </table>

    <h2>Letöltési linkek</h2>
    <table class="form-table">
      <tr>
        <th><label for="expiry_hours">Lejárat (óra)</label></th>
        <td><input type="number" id="expiry_hours" min="1" max="8760" value="<?php echo esc_attr(get_option('pmp_download_expiry_hours',48)); ?>"></td>
      </tr>
      <tr>
        <th><label for="max_downloads">Max. letöltések száma</label></th>
        <td><input type="number" id="max_downloads" min="1" max="100" value="<?php echo esc_attr(get_option('pmp_download_max_count',3)); ?>"></td>
      </tr>
    </table>

    <hr>
    <h2>☁️ Cloudflare R2 (opcionális)</h2>
    <table class="form-table">
      <tr>
        <th>R2 bekapcsolva</th>
        <td><label><input type="checkbox" id="r2_enabled" value="1" <?php checked(1,get_option('pmp_r2_enabled',0)); ?>> Cloudflare R2 használata</label></td>
      </tr>
      <tr>
        <th><label for="r2_account_id">Account ID</label></th>
        <td><input type="text" id="r2_account_id" class="regular-text" value="<?php echo esc_attr(get_option('pmp_r2_account_id','')); ?>"></td>
      </tr>
      <tr>
        <th><label for="r2_bucket">Bucket neve</label></th>
        <td><input type="text" id="r2_bucket" class="regular-text" value="<?php echo esc_attr(get_option('pmp_r2_bucket','')); ?>"></td>
      </tr>
      <tr>
        <th><label for="r2_access_key">Access Key ID</label></th>
        <td><input type="text" id="r2_access_key" class="regular-text" value="<?php echo esc_attr(get_option('pmp_r2_access_key','')); ?>"></td>
      </tr>
      <tr>
        <th><label for="r2_secret_key">Secret Access Key</label></th>
        <td><input type="password" id="r2_secret_key" class="regular-text" value="<?php echo get_option('pmp_r2_secret_key') ? '••••••••' : ''; ?>" placeholder="Változatlanul hagyáshoz üresen"></td>
      </tr>
      <tr>
        <th><label for="r2_custom_domain">Egyedi domain</label></th>
        <td><input type="url" id="r2_custom_domain" class="regular-text" value="<?php echo esc_attr(get_option('pmp_r2_custom_domain','')); ?>" placeholder="https://cdn.példa.hu"></td>
      </tr>
    </table>
    <button class="button" id="pmp-test-r2-btn" style="margin:10px 0;">R2 kapcsolat tesztelése</button>
    <span id="pmp-r2-test-result"></span>
    <br><br>

    <button class="button button-primary" id="pmp-save-settings-btn">Beállítások mentése</button>
    <span id="pmp-settings-msg" style="margin-left:10px;"></span>

    <hr>
    <h2>Shortcode használata</h2>
    <div class="pmp-info-box">
      <p>Illeszd be ezt a shortcode-ot bármelyik oldalra vagy widgetbe:</p>
      <p><code>[pmp_gallery]</code> – alapértelmezett számú kép (<?php echo get_option('pmp_gallery_count',6); ?> db)</p>
      <p><code>[pmp_gallery count="12"]</code> – egyedi darabszám</p>
      <p>A galéria automatikusan tartalmazza a helyszín/kategória/dátum szűrőket.</p>
    </div>
  </div>
</div>
