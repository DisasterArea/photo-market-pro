/* Photo Market Pro – Admin JS */
jQuery(function($){
    var nonce   = PMP.nonce;
    var ajaxurl = PMP.ajaxurl;
    var mediaFrame;

    /* ── Helpers ──────────────────────────────────────────── */
    function msg(sel, text, ok) {
        $(sel).text(text).css('color', ok ? '#46b450' : ok === false ? '#dc3232' : '#555');
    }
    function openModal(id)  { $(id).fadeIn(150); $('body').addClass('pmp-modal-open'); }
    function closeModal(id) { $(id).fadeOut(150); $('body').removeClass('pmp-modal-open'); }

    // Global functions referenced by old inline scripts
    window.pmp_open_photo_modal = function() {
        resetPhotoForm();
        $('#pmp-modal-title').text('Fotó hozzáadása');
        openModal('#pmp-photo-modal');
    };
    window.pmp_open_bulk_modal = function() {
        openModal('#pmp-bulk-modal');
    };

    $(document).on('click', '.pmp-modal-close', function(){ closeModal('.pmp-modal'); });
    $(document).on('keydown', function(e){ if(e.key==='Escape') closeModal('.pmp-modal'); });

    /* ── Photo modal: open for ADD ───────────────────────── */
    $('#pmp-add-photo-btn').on('click', function(){
        resetPhotoForm();
        $('#pmp-modal-title').text('Fotó hozzáadása');
        openModal('#pmp-photo-modal');
    });

    /* ── Photo modal: open for EDIT ─────────────────────── */
    // Handle BOTH old class (pmp-edit-photo) and new (pmp-edit-photo-btn)
    $(document).on('click', '.pmp-edit-photo-btn, .pmp-edit-photo', function(){
        var id = $(this).data('id');
        $.post(ajaxurl, {action:'pmp_get_photo', nonce:nonce, photo_id:id}, function(res){
            if (!res.success) return;
            var p = res.data;
            resetPhotoForm();
            $('#pmp-modal-title').text('Fotó szerkesztése');
            $('#pmp-edit-photo-id').val(p.id);
            $('#pmp-field-title').val(p.title);
            $('#pmp-field-location').val(p.location);
            $('#pmp-field-category').val(p.category);
            $('#pmp-field-shot-date').val(p.shot_date || '');
            $('#pmp-field-price').val(p.price > 0 ? p.price : '');
            $('#pmp-field-use-external').prop('checked', p.use_external == 1).trigger('change');
            $('#pmp-field-external-key').val(p.external_key || '');
            $('#pmp-field-download-url').val(p.download_url || '');

            if (p.preview_image_id && p.preview_url_thumb) {
                $('#pmp-preview-image-id').val(p.preview_image_id);
                $('#pmp-preview-img').attr('src', p.preview_url_thumb).show();
                $('#pmp-preview-placeholder').hide();
                $('#pmp-clear-image-btn').show();
            }

            var optIds = p.edit_option_ids || [];
            $('.pmp-opt-cb').each(function(){
                $(this).prop('checked', optIds.indexOf($(this).val()) !== -1 || optIds.indexOf(parseInt($(this).val())) !== -1);
            });

            openModal('#pmp-photo-modal');
        });
    });

    function resetPhotoForm() {
        $('#pmp-edit-photo-id').val('');
        $('#pmp-field-title, #pmp-field-location, #pmp-field-category, #pmp-field-shot-date, #pmp-field-price, #pmp-field-external-key, #pmp-field-download-url').val('');
        $('#pmp-field-photo-file').val('');
        $('#pmp-field-use-external').prop('checked', false).trigger('change');
        $('#pmp-preview-image-id').val('');
        $('#pmp-preview-img').attr('src','').hide();
        $('#pmp-preview-placeholder').show();
        $('#pmp-clear-image-btn').hide();
        $('.pmp-opt-cb').prop('checked', false);
        msg('#pmp-save-msg', '', null);
    }

    /* ── External toggle ─────────────────────────────────── */
    $('#pmp-field-use-external').on('change', function(){
        $('#pmp-external-fields').toggle(this.checked);
        $('#pmp-direct-url-field').toggle(!this.checked);
    }).trigger('change');

    /* ── WP Media picker ─────────────────────────────────── */
    $(document).on('click', '#pmp-pick-image-btn, #pmp-preview-thumb', function(){
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({ title:'Előnézeti kép kiválasztása', button:{text:'Kiválasztás'}, multiple:false, library:{type:'image'} });
        mediaFrame.on('select', function(){
            var att = mediaFrame.state().get('selection').first().toJSON();
            $('#pmp-preview-image-id').val(att.id);
            var src = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            $('#pmp-preview-img').attr('src', src).show();
            $('#pmp-preview-placeholder').hide();
            $('#pmp-clear-image-btn').show();
        });
        mediaFrame.open();
    });

    $(document).on('click', '#pmp-clear-image-btn', function(){
        $('#pmp-preview-image-id').val('');
        $('#pmp-preview-img').attr('src','').hide();
        $('#pmp-preview-placeholder').show();
        $(this).hide();
    });

    /* ── Save photo ──────────────────────────────────────── */
    $(document).on('click', '#pmp-save-photo-btn', function(){
        var optIds = [];
        $('.pmp-opt-cb:checked').each(function(){ optIds.push($(this).val()); });

        var location = $('#pmp-field-location').val();
        var category = $('#pmp-field-category').val();
        if (!location) { msg('#pmp-save-msg','Helyszín kötelező!',false); return; }
        if (!category) { msg('#pmp-save-msg','Kategória kötelező!',false); return; }

        msg('#pmp-save-msg','Mentés...', null);

        var photoFile = $('#pmp-field-photo-file')[0].files[0];
        var formData  = new FormData();
        formData.append('action',           'pmp_save_photo');
        formData.append('nonce',            nonce);
        formData.append('photo_id',         $('#pmp-edit-photo-id').val());
        formData.append('title',            $('#pmp-field-title').val());
        formData.append('location',         location);
        formData.append('category',         category);
        formData.append('shot_date',        $('#pmp-field-shot-date').val());
        formData.append('price',            $('#pmp-field-price').val());
        formData.append('preview_image_id', $('#pmp-preview-image-id').val());
        formData.append('use_external',     $('#pmp-field-use-external').is(':checked') ? 1 : 0);
        formData.append('external_key',     $('#pmp-field-external-key').val());
        formData.append('download_url',     $('#pmp-field-download-url').val());
        optIds.forEach(function(id){ formData.append('edit_option_ids[]', id); });
        if (photoFile) {
            formData.append('photo_file', photoFile);
            msg('#pmp-save-msg','Feltöltés R2-re...', null);
        }

        $.ajax({
            url: ajaxurl, type: 'POST', data: formData,
            processData: false, contentType: false,
            success: function(res){
                if (res.success) {
                    msg('#pmp-save-msg','✅ Mentve!', true);
                    setTimeout(function(){ location.reload(); }, 900);
                } else {
                    msg('#pmp-save-msg','❌ Hiba: '+(res.data||''), false);
                }
            }
        });
    });

    /* ── Delete photo (both class names) ─────────────────── */
    $(document).on('click', '.pmp-delete-photo-btn, .pmp-quick-delete', function(){
        if (!confirm('Biztosan törlöd ezt a fotót és a hozzá tartozó terméket?')) return;
        var id = $(this).data('id');
        $.post(ajaxurl, {action:'pmp_delete_photo', nonce:nonce, photo_id:id}, function(res){
            if (res.success) {
                $('#photo-card-'+id).fadeOut(400, function(){ $(this).remove(); });
            }
        });
    });

    /* ── Bulk select & delete ────────────────────────────── */
    $('#pmp-select-all-photos').on('change', function(){
        $('.pmp-photo-selector').prop('checked', $(this).is(':checked')).trigger('change');
    });

    $(document).on('change', '.pmp-photo-selector', function(){
        var n = $('.pmp-photo-selector:checked').length;
        $('#pmp-selected-count').text(n);
        $('#pmp-bulk-delete-btn').toggle(n > 0);
        if (!n) $('#pmp-select-all-photos').prop('checked', false);
    });

    $('#pmp-bulk-delete-btn').on('click', function(){
        var ids = [];
        $('.pmp-photo-selector:checked').each(function(){ ids.push($(this).val()); });
        if (!ids.length) return;
        if (!confirm('Biztosan törlöd a kijelölt '+ids.length+' db fotót?')) return;
        var $btn = $(this).prop('disabled', true).text('Törlés...');
        $.post(ajaxurl, {action:'pmp_bulk_delete_photos', nonce:nonce, photo_ids:ids}, function(res){
            if (res.success) {
                ids.forEach(function(id){ $('#photo-card-'+id).remove(); });
                $('#pmp-bulk-delete-btn').hide();
                $('#pmp-select-all-photos').prop('checked', false);
            }
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:middle;margin-top:-3px;"></span> Kijelöltek törlése (<span id="pmp-selected-count">0</span>)');
        });
    });

    /* ── Bulk upload modal ───────────────────────────────── */
    $('#pmp-bulk-upload-btn').on('click', function(){ openModal('#pmp-bulk-modal'); });

    $('#pmp-bulk-files').on('change', function(){
        var $preview = $('#pmp-bulk-preview').empty();
        Array.from(this.files).forEach(function(f){
            var reader = new FileReader();
            var $item = $('<div class="pmp-bulk-preview-item">');
            var $img  = $('<img>');
            var $name = $('<span>').text(f.name);
            reader.onload = function(e){ $img.attr('src', e.target.result); };
            reader.readAsDataURL(f);
            $preview.append($item.append($img).append($name));
        });
    });

    $(document).on('click', '#pmp-bulk-upload-submit', function(){
        var files = document.getElementById('pmp-bulk-files').files;
        if (!files.length) { alert('Válassz ki legalább egy fájlt!'); return; }

        var price  = $('#pmp-bulk-price').val();
        var optIds = [];
        $('.pmp-bulk-opt-cb:checked').each(function(){ optIds.push($(this).val()); });

        var fileArr = Array.from(files);
        var total   = fileArr.length;
        var done    = 0;
        var created = [];
        var errors  = [];

        $('#pmp-bulk-progress').show();
        $('#pmp-bulk-status').text('0 / ' + total + ' feltöltve...');
        $('.pmp-progress-fill').css('width', '0%');

        // Generate a small JPEG thumbnail via Canvas (max 400px wide)
        function makeThumbnail(file, callback) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = new Image();
                img.onload = function() {
                    var maxW = 400, maxH = 400;
                    var w = img.width, h = img.height;
                    if (w > maxW) { h = Math.round(h * maxW / w); w = maxW; }
                    if (h > maxH) { w = Math.round(w * maxH / h); h = maxH; }
                    var canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    canvas.toBlob(function(blob) { callback(blob); }, 'image/jpeg', 0.82);
                };
                img.onerror = function() { callback(null); };
                img.src = e.target.result;
            };
            reader.onerror = function() { callback(null); };
            reader.readAsDataURL(file);
        }

        // Sequential upload: process one file at a time to avoid duplicate titles
        function uploadNext(index) {
            if (index >= total) {
                var txt = '✅ ' + created.length + ' fotó feltöltve.';
                if (errors.length) txt += ' ⚠️ ' + errors.length + ' hiba: ' + errors.join(', ');
                $('#pmp-bulk-status').text(txt);
                setTimeout(function(){ location.reload(); }, 1800);
                return;
            }

            var file = fileArr[index];
            $('#pmp-bulk-status').text((index + 1) + ' / ' + total + ' – ' + file.name);
            $('.pmp-progress-fill').css('width', Math.round((index / total) * 100) + '%');

            function onError(msg) {
                errors.push(file.name + ': ' + msg);
                uploadNext(index + 1);
            }

            // Step 1: get presigned PUT URL
            $.post(ajaxurl, {
                action: 'pmp_get_r2_presigned_put', nonce: nonce,
                file_name: file.name, file_type: file.type, file_size: file.size,
            }, function(res) {
                if (!res.success) { onError(res.data || 'presign hiba'); return; }

                var putUrl = res.data.put_url;
                var r2Key  = res.data.r2_key;

                // Step 2: PUT full file directly to R2
                var xhr = new XMLHttpRequest();
                xhr.open('PUT', putUrl, true);
                xhr.setRequestHeader('Content-Type', file.type);
                xhr.onload = function() {
                    if (xhr.status !== 200) { onError('R2 hiba (HTTP ' + xhr.status + ')'); return; }

                    // Step 3: generate thumbnail in browser, send to WP
                    makeThumbnail(file, function(thumbBlob) {
                        var saveData = new FormData();
                        saveData.append('action',      'pmp_bulk_upload');
                        saveData.append('nonce',       nonce);
                        saveData.append('bulk_price',  price);
                        saveData.append('file_name',   file.name);
                        saveData.append('r2_key',      r2Key);
                        if (thumbBlob) saveData.append('thumb_file', thumbBlob, file.name);
                        optIds.forEach(function(id){ saveData.append('bulk_edit_options[]', id); });

                        $.ajax({
                            url: ajaxurl, type: 'POST', data: saveData,
                            processData: false, contentType: false,
                            success: function(r) {
                                if (r.success) { created.push(file.name); } else { errors.push(file.name + ': ' + (r.data || 'mentési hiba')); }
                                uploadNext(index + 1);
                            },
                            error: function() { onError('mentési hiba'); }
                        });
                    });
                };
                xhr.onerror = function() { onError('hálózati hiba'); };
                xhr.send(file);
            }).fail(function() { onError('presign kérés sikertelen'); });
        }

        uploadNext(0);
    });

    /* ── Edit options page ───────────────────────────────── */
    if ($('#pmp-options-list').length) {
        $('#pmp-options-list').sortable({ handle:'.pmp-drag-handle' });
    }

    $('#pmp-save-order-btn').on('click', function(){
        var order = [];
        $('#pmp-options-list .pmp-option-row').each(function(){ order.push($(this).data('id')); });
        $.post(ajaxurl, {action:'pmp_reorder_edit_options', nonce:nonce, order:order}, function(res){
            if (res.success) msg('#pmp-form-msg','Sorrend mentve.',true);
        });
    });

    $(document).on('click', '.pmp-edit-option-btn', function(){
        var $b = $(this);
        $('#pmp-option-id').val($b.data('id'));
        $('#pmp-option-name').val($b.data('name'));
        $('#pmp-option-desc').val($b.data('description'));
        $('#pmp-option-price').val($b.data('price'));
        $('#pmp-option-active').prop('checked', $b.data('active')==1);
        $('#pmp-form-title').text('Opció szerkesztése');
    });

    $('#pmp-reset-form-btn').on('click', function(){
        $('#pmp-option-id').val(''); $('#pmp-option-name,#pmp-option-desc,#pmp-option-price').val('');
        $('#pmp-option-active').prop('checked',true); $('#pmp-form-title').text('Új opció hozzáadása');
    });

    $('#pmp-save-option-btn').on('click', function(){
        $.post(ajaxurl, {
            action:'pmp_save_edit_option', nonce:nonce,
            id:$('#pmp-option-id').val(), name:$('#pmp-option-name').val(),
            description:$('#pmp-option-desc').val(), price:$('#pmp-option-price').val(),
            active:$('#pmp-option-active').is(':checked')?1:0
        }, function(res){
            if (res.success) { msg('#pmp-form-msg','Mentve!',true); setTimeout(function(){location.reload();},900); }
            else msg('#pmp-form-msg', res.data||'Hiba',false);
        });
    });

    $(document).on('click', '.pmp-delete-option-btn', function(){
        if (!confirm('Törlöd ezt az opciót?')) return;
        $.post(ajaxurl, {action:'pmp_delete_edit_option', nonce:nonce, id:$(this).data('id')}, function(res){
            if (res.success) location.reload();
        });
    });

    /* ── Settings page ───────────────────────────────────── */
    $('#pmp-save-settings-btn').on('click', function(){
        $.post(ajaxurl, {
            action:'pmp_save_settings', nonce:nonce,
            expiry_hours:$('#expiry_hours').val(), max_downloads:$('#max_downloads').val(),
            gallery_count:$('#gallery_count').val(),
            r2_enabled:$('#r2_enabled').is(':checked')?1:0,
            r2_account_id:$('#r2_account_id').val(), r2_bucket:$('#r2_bucket').val(),
            r2_access_key:$('#r2_access_key').val(), r2_secret_key:$('#r2_secret_key').val(),
            r2_custom_domain:$('#r2_custom_domain').val()
        }, function(res){ msg('#pmp-settings-msg', res.success?'✅ Mentve!':'❌ Hiba', res.success); });
    });

    $('#pmp-test-r2-btn').on('click', function(){
        msg('#pmp-r2-test-result','Tesztelés...',null);
        $.post(ajaxurl,{action:'pmp_r2_test',nonce:nonce},function(res){
            msg('#pmp-r2-test-result',(res.success?'✅ ':'❌ ')+res.message, res.success);
        });
    });

    /* ── Downloads page ──────────────────────────────────── */
    $(document).on('click', '.pmp-copy-link', function(){
        var url = $(this).data('url');
        if (navigator.clipboard) navigator.clipboard.writeText(url).then(function(){ alert('Link másolva!'); });
        else prompt('Link:', url);
    });

    $(document).on('click', '.pmp-extend-token', function(){
        if (!confirm('Meghosszabbítod?')) return;
        $.post(ajaxurl,{action:'pmp_extend_token',nonce:nonce,token:$(this).data('token')},function(res){
            if(res.success){alert('Meghosszabbítva!');location.reload();}
        });
    });

    $('#pmp-resend-btn').on('click', function(){
        var id = $('#pmp-resend-order-id').val();
        if (!id) { msg('#pmp-resend-msg','Add meg a rendelés számát!',false); return; }
        $.post(ajaxurl,{action:'pmp_resend_links',nonce:nonce,order_id:id},function(res){
            msg('#pmp-resend-msg',res.success?'✅ Elküldve!':'❌ Hiba',res.success);
        });
    });
});
