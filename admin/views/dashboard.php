<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap pmp-wrap">
  <h1>📷 Photo Market Pro – Áttekintés</h1>

  <div class="pmp-stats-row">
    <div class="pmp-stat-card">
      <span class="pmp-stat-number"><?php echo intval( $total_photos ); ?></span>
      <span class="pmp-stat-label">Feltöltött fotó</span>
    </div>
    <div class="pmp-stat-card">
      <span class="pmp-stat-number"><?php echo intval( $total_tokens ); ?></span>
      <span class="pmp-stat-label">Kiadott letöltési link</span>
    </div>
    <div class="pmp-stat-card pmp-stat-green">
      <span class="pmp-stat-number"><?php echo intval( $active_tokens ); ?></span>
      <span class="pmp-stat-label">Aktív letöltési link</span>
    </div>
    <div class="pmp-stat-card">
      <span class="pmp-stat-number"><?php echo intval( $total_sales ); ?></span>
      <span class="pmp-stat-label">Összes letöltés</span>
    </div>
  </div>

  <div class="pmp-quick-links">
    <h2>Gyors műveletek</h2>
    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">+ Új fotó termék</a>
    <a href="<?php echo admin_url('admin.php?page=pmp-edit-options'); ?>" class="button">Szerkesztési opciók kezelése</a>
    <a href="<?php echo admin_url('admin.php?page=pmp-downloads'); ?>" class="button">Letöltési linkek</a>
    <a href="<?php echo admin_url('admin.php?page=pmp-settings'); ?>" class="button">Beállítások</a>
  </div>

  <div class="pmp-info-box">
    <h3>Hogyan adjak hozzá új fotót?</h3>
    <ol>
      <li>Kattints a <strong>"+ Új fotó termék"</strong> gombra</li>
      <li>Termék típusnál válaszd: <strong>Fotó (Photo Market Pro)</strong></li>
      <li>Töltsd ki az árat, leírást, és a <strong>"📷 Fotó adatok"</strong> fülön az előnézeti képet és a letöltési forrást</li>
      <li>Jelöld be, milyen szerkesztési opciók elérhetők ehhez a fotóhoz</li>
      <li>Publikáld a terméket</li>
    </ol>
  </div>
</div>
