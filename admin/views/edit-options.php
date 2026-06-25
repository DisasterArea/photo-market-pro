<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap pmp-wrap">
  <h1>Szerkesztési opciók kezelése</h1>
  <p>Húzd át az opciókat a kívánt sorrendbe. Ezek az opciók jelennek meg a fotó termékek oldalain.</p>

  <div class="pmp-two-col">

    <!-- Option list -->
    <div class="pmp-col">
      <h2>Meglévő opciók</h2>
      <ul id="pmp-options-list" class="pmp-sortable">
        <?php foreach ( $options as $opt ) : ?>
        <li class="pmp-option-row" data-id="<?php echo esc_attr( $opt['id'] ); ?>">
          <span class="pmp-drag-handle">☰</span>
          <div class="pmp-option-info">
            <strong><?php echo esc_html( $opt['name'] ); ?></strong>
            <span class="pmp-option-price"><?php echo number_format( $opt['price'], 0, ',', '.' ); ?> Ft</span>
            <?php if ( ! $opt['active'] ) echo '<span class="pmp-badge-inactive">Inaktív</span>'; ?>
            <?php if ( $opt['description'] ) : ?>
              <p class="pmp-option-desc"><?php echo esc_html( $opt['description'] ); ?></p>
            <?php endif; ?>
          </div>
          <div class="pmp-option-actions">
            <button class="button button-small pmp-edit-option-btn"
              data-id="<?php echo esc_attr( $opt['id'] ); ?>"
              data-name="<?php echo esc_attr( $opt['name'] ); ?>"
              data-description="<?php echo esc_attr( $opt['description'] ); ?>"
              data-price="<?php echo esc_attr( $opt['price'] ); ?>"
              data-active="<?php echo esc_attr( $opt['active'] ); ?>">Szerkesztés</button>
            <button class="button button-small button-link-delete pmp-delete-option-btn" data-id="<?php echo esc_attr( $opt['id'] ); ?>">Törlés</button>
          </div>
        </li>
        <?php endforeach; ?>
        <?php if ( empty( $options ) ) : ?>
        <li class="pmp-empty">Nincsenek még opciók.</li>
        <?php endif; ?>
      </ul>
      <button class="button" id="pmp-save-order-btn" style="margin-top:10px;">Sorrend mentése</button>
    </div>

    <!-- Add / edit form -->
    <div class="pmp-col">
      <div class="pmp-card">
        <h2 id="pmp-form-title">Új opció hozzáadása</h2>
        <input type="hidden" id="pmp-option-id" value="">

        <label>Neve *</label>
        <input type="text" id="pmp-option-name" class="widefat" placeholder="pl. Retusálás">

        <label>Leírás</label>
        <textarea id="pmp-option-desc" class="widefat" rows="2" placeholder="Rövid leírás a vevőnek..."></textarea>

        <label>Ár (Ft) *</label>
        <input type="number" id="pmp-option-price" class="widefat" min="0" step="100" placeholder="2990">

        <label>
          <input type="checkbox" id="pmp-option-active" checked> Aktív (látható a termékeken)
        </label>

        <div style="margin-top:16px;">
          <button class="button button-primary" id="pmp-save-option-btn">Mentés</button>
          <button class="button" id="pmp-reset-form-btn">Visszaállítás</button>
        </div>
        <div id="pmp-form-msg" style="margin-top:10px;"></div>
      </div>
    </div>

  </div>
</div>
