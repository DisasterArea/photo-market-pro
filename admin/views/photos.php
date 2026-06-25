<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap pmp-photos-page">
    <h1 class="wp-heading-inline">Fotók</h1>
    <hr class="wp-header-end">

    <div class="tablenav top" style="background:#fff; padding:15px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:15px;">
        <form method="get" style="display:inline-block; margin:0;">
            <input type="hidden" name="page" value="photo-market-pro">
            <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="Keresés..." style="height:32px; vertical-align:middle;">
            
            <select name="loc" style="height:32px; vertical-align:middle;">
                <option value="">– Minden helyszín –</option>
                <?php foreach($locations as $l): ?>
                    <option value="<?php echo esc_attr($l); ?>" <?php selected($_GET['loc'] ?? '', $l); ?>><?php echo esc_html($l); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="cat" style="height:32px; vertical-align:middle;">
                <option value="">– Minden kategória –</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?php echo esc_attr($c); ?>" <?php selected($_GET['cat'] ?? '', $c); ?>><?php echo esc_html($c); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="button action" value="Szűrés" style="vertical-align:middle;">
            <a href="<?php echo admin_url('admin.php?page=photo-market-pro'); ?>" class="button secondary" style="vertical-align:middle;">Visszaállítás</a>
        </form>

        <div style="float:right; display:flex; gap:10px;">
            <button type="button" class="button button-primary" id="pmp-add-photo-btn">+ Új fotó</button>
            <button type="button" class="button button-primary" id="pmp-bulk-upload-btn">
                <span class="dashicons dashicons-upload" style="vertical-align:middle; margin-top:-3px;"></span> Tömeges feltöltés
            </button>
        </div>
        <div style="clear:both;"></div>
    </div>

    <div class="pmp-bulk-manage-bar" style="background:#f6f7f7; padding:10px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:20px; display:flex; align-items:center; gap:20px;">
        <label style="cursor:pointer; font-weight:600; user-select:none;">
            <input type="checkbox" id="pmp-select-all-photos" style="margin-top:-3px; vertical-align:middle;"> Összes kijelölése
        </label>
        <button type="button" id="pmp-bulk-delete-btn" class="button" style="background:#d63638; color:#fff; border-color:#b32d2e; display:none;">
            <span class="dashicons dashicons-trash" style="vertical-align:middle; margin-top:-3px;"></span> Kijelöltek tömeges törlése (<span id="pmp-selected-count">0</span>)
        </button>
    </div>

    <div class="pmp-photo-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:20px;">
        <?php if ( ! empty( $photos ) ) : ?>
            <?php foreach ( $photos as $p ) : ?>
                <?php $thumb = $p['preview_image_id'] ? wp_get_attachment_image_url( $p['preview_image_id'], 'medium' ) : ''; ?>
                <div class="pmp-photo-card" id="photo-card-<?php echo esc_attr($p['id']); ?>" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:10px; position:relative; display:flex; flex-direction:column; justify-content:space-between;">
                    <input type="checkbox" class="pmp-photo-selector" value="<?php echo esc_attr($p['id']); ?>" style="position:absolute; top:15px; left:15px; z-index:10; transform: scale(1.2); cursor:pointer;">
                    <div style="height:140px; background:#f0f0f1; display:flex; align-items:center; justify-content:center; border-radius:4px; overflow:hidden; margin-bottom:10px;">
                        <?php if ( $thumb ) : ?>
                            <img src="<?php echo esc_url($thumb); ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else : ?>
                            <span class="dashicons dashicons-format-image" style="font-size:48px; width:48px; height:48px; color:#a7aaad;"></span>
                        <?php endif; ?>
                    </div>
                    <div style="flex-grow:1;">
                        <h3 style="margin:5px 0; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($p['title']); ?></h3>
                        <p style="margin:2px 0; font-size:12px; color:#646970;"><?php echo esc_html($p['location'] . ' • ' . $p['category']); ?></p>
                        <p style="margin:2px 0; font-size:11px; color:#8c8f94;"><?php echo esc_html($p['shot_date']); ?></p>
                    </div>
                    <div style="margin-top:10px; display:flex; gap:5px;">
                        <!-- FIX: class=pmp-edit-photo-btn matches admin.js -->
                        <button type="button" class="button button-small pmp-edit-photo-btn" data-id="<?php echo esc_attr($p['id']); ?>">Szerkesztés</button>
                        <button type="button" class="button button-small pmp-quick-delete" data-id="<?php echo esc_attr($p['id']); ?>" style="color:#d63638;">Törlés</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p style="grid-column: 1 / -1; text-align:center; padding:30px; color:#646970;">Nincsenek feltöltött fotók.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ ADD / EDIT MODAL ═══ -->
<div id="pmp-photo-modal" class="pmp-modal" style="display:none;">
  <div class="pmp-modal-box">
    <div class="pmp-modal-header">
      <h2 id="pmp-modal-title">Fotó hozzáadása</h2>
      <button class="pmp-modal-close" type="button">✕</button>
    </div>
    <div class="pmp-modal-body">
      <input type="hidden" id="pmp-edit-photo-id" value="">
      <div class="pmp-form-row">
        <div class="pmp-form-col">
          <label>Előnézeti kép</label>
          <div id="pmp-preview-thumb" class="pmp-thumb-picker">
            <img id="pmp-preview-img" src="" style="display:none;max-width:100%;max-height:156px;border-radius:4px;">
            <span id="pmp-preview-placeholder">Kattints a kép kiválasztásához</span>
          </div>
          <input type="hidden" id="pmp-preview-image-id">
          <button class="button" id="pmp-pick-image-btn" type="button" style="margin-top:8px;">Kép kiválasztása</button>
          <button class="button" id="pmp-clear-image-btn" type="button" style="margin-top:8px;display:none;">✕ Kép törlése</button>
        </div>
        <div class="pmp-form-col">
          <label>Cím (elhagyható – auto-generált)</label>
          <input type="text" id="pmp-field-title" class="widefat" placeholder="pl. Budapest – Autó 2024">
          <label>Helyszín *</label>
          <input type="text" id="pmp-field-location" class="widefat" list="pmp-location-list" placeholder="pl. Budapest">
          <datalist id="pmp-location-list">
            <?php foreach($locations as $l): ?><option value="<?php echo esc_attr($l); ?>"><?php endforeach; ?>
          </datalist>
          <label>Kategória *</label>
          <input type="text" id="pmp-field-category" class="widefat" list="pmp-category-list" placeholder="pl. Autó">
          <datalist id="pmp-category-list">
            <?php foreach($categories as $c): ?><option value="<?php echo esc_attr($c); ?>"><?php endforeach; ?>
          </datalist>
          <label>Fénykép dátuma</label>
          <input type="date" id="pmp-field-shot-date" class="widefat">
          <label>Ár (Ft) *</label>
          <input type="number" id="pmp-field-price" class="widefat" min="0" step="100" placeholder="4990">
        </div>
      </div>
      <div class="pmp-form-section">
        <h3>Letöltési forrás</h3>
        <label><input type="checkbox" id="pmp-field-use-external"> Külső szerver (Cloudflare R2)</label>
        <div id="pmp-external-fields" style="display:none;margin-top:8px;">
          <label>Fájl feltöltése R2-re</label>
          <input type="file" id="pmp-field-photo-file" accept="image/*" style="margin-bottom:8px;">
          <label>Vagy R2 fájl kulcs kézzel (pl. eredeti/foto.jpg)</label>
          <input type="text" id="pmp-field-external-key" class="widefat">
        </div>
        <div id="pmp-direct-url-field" style="margin-top:8px;">
          <label>Közvetlen letöltési URL (ha nem R2)</label>
          <input type="url" id="pmp-field-download-url" class="widefat" placeholder="https://...">
        </div>
      </div>
      <div class="pmp-form-section">
        <h3>Elérhető szerkesztési opciók</h3>
        <div class="pmp-edit-options-checkboxes">
          <?php foreach($edit_options as $opt): ?>
          <label class="pmp-opt-check">
            <input type="checkbox" class="pmp-opt-cb" value="<?php echo esc_attr($opt['id']); ?>">
            <?php echo esc_html($opt['name']); ?> <small>(+<?php echo number_format($opt['price'],0,',','.'); ?> Ft)</small>
          </label>
          <?php endforeach; ?>
          <?php if(empty($edit_options)): ?><p style="color:#999;">Nincsenek opciók. <a href="<?php echo admin_url('admin.php?page=pmp-edit-options'); ?>">Hozzáad</a></p><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="pmp-modal-footer">
      <button class="button button-primary" id="pmp-save-photo-btn" type="button">Mentés</button>
      <button class="button pmp-modal-close" type="button">Mégse</button>
      <span id="pmp-save-msg" style="margin-left:10px;"></span>
    </div>
  </div>
</div>

<!-- ═══ BULK UPLOAD MODAL ═══ -->
<div id="pmp-bulk-modal" class="pmp-modal" style="display:none;">
  <div class="pmp-modal-box">
    <div class="pmp-modal-header">
      <h2>Tömeges feltöltés</h2>
      <button class="pmp-modal-close" type="button">✕</button>
    </div>
    <div class="pmp-modal-body">
      <div class="pmp-info-box" style="margin-bottom:16px;">
        <strong>Fájlnév formátum:</strong> <code>helyszin_kategoria_YYYYMMDD.jpg</code><br>
        Példa: <code>budapest_auto_20240315.jpg</code> → Budapest / Auto / 2024.03.15
      </div>
      <label>Képek kiválasztása (több fájl egyszerre)</label>
      <input type="file" id="pmp-bulk-files" multiple accept="image/*" style="display:block;margin:8px 0 16px;">
      <div id="pmp-bulk-preview" class="pmp-bulk-preview-list"></div>
      <div class="pmp-form-row" style="margin-top:16px;">
        <div class="pmp-form-col">
          <label>Alap ár (Ft)</label>
          <input type="number" id="pmp-bulk-price" class="widefat" min="0" step="100" placeholder="4990">
        </div>
        <div class="pmp-form-col">
          <label><input type="checkbox" id="pmp-bulk-use-external" checked> Cloudflare R2 feltöltés</label>
          <p style="font-size:12px;color:#777;margin-top:4px;">A kép feltöltődik R2-re is és onnan töltődik le vásárlás után.</p>
        </div>
      </div>
      <label style="margin-top:12px;display:block;">Szerkesztési opciók (minden képre)</label>
      <div class="pmp-edit-options-checkboxes">
        <?php foreach($edit_options as $opt): ?>
        <label class="pmp-opt-check">
          <input type="checkbox" class="pmp-bulk-opt-cb" value="<?php echo esc_attr($opt['id']); ?>">
          <?php echo esc_html($opt['name']); ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="pmp-modal-footer">
      <button class="button button-primary" id="pmp-bulk-upload-submit" type="button">Feltöltés indítása</button>
      <button class="button pmp-modal-close" type="button">Mégse</button>
      <div id="pmp-bulk-progress" style="display:none;margin-top:12px;width:100%;">
        <div class="pmp-progress-bar"><div class="pmp-progress-fill" style="width:0%"></div></div>
        <p id="pmp-bulk-status" style="margin:8px 0 0;font-size:13px;"></p>
      </div>
    </div>
  </div>
</div>
