/* Photo Market Pro – Frontend Gallery JS */
jQuery(function($){
    var cfg     = PMP_Public;
    var ajaxurl = cfg.ajaxurl;
    var nonce   = cfg.nonce;
    var $wrap   = $('.pmp-gallery-wrap');
    if ( !$wrap.length ) return;
    var count    = parseInt( $wrap.data('count') ) || 6;
    var pageSize = 25;
    var curPage  = 0;
    var curFilters = {};

    /* ── Boot: load dropdowns ──────────────────────── */
    refreshOptions();

    /* ── Lightbox ───────────────────────────────────── */
    if ( ! $( '#pmp-lightbox' ).length ) {
        $( 'body' ).append(
            '<div id="pmp-lightbox" role="dialog" aria-modal="true">' +
              '<button id="pmp-lb-close" aria-label="Chiudi">✕</button>' +
              '<button id="pmp-lb-prev" aria-label="Precedente">&#8249;</button>' +
              '<div id="pmp-lb-img-wrap">' +
                '<img id="pmp-lb-img" src="" alt="">' +
                '<div id="pmp-lb-overlay">' +
                  '<div id="pmp-lb-tags"></div>' +
                  '<div id="pmp-lb-bottom">' +
                    '<span id="pmp-lb-title"></span>' +
                    '<a id="pmp-lb-buy" href="#"><span class="pmp-lb-cart">🛒</span><span class="pmp-lb-buy-text"> Acquista</span></a>' +
                  '</div>' +
                '</div>' +
              '</div>' +
              '<button id="pmp-lb-next" aria-label="Successivo">&#8250;</button>' +
            '</div>'
        );
    }

    var lbList  = [];
    var lbIndex = 0;

    function buildLbList() {
        lbList = [];
        $( '.pmp-lightbox-trigger' ).each( function() {
            var $t = $( this );
            lbList.push({
                img:      $t.data('img'),
                title:    $t.data('title'),
                product:  $t.data('product'),
                price:    $t.data('price'),
                location: $t.data('location'),
                category: $t.data('category'),
                date:     $t.data('date'),
                dateFmt:  $t.data('date-fmt'),
            });
        });
    }

    function showLbPhoto( idx ) {
        if ( ! lbList.length ) return;
        idx = ( idx + lbList.length ) % lbList.length;
        lbIndex = idx;
        var p = lbList[ idx ];
        $( '#pmp-lb-img' ).attr( { src: p.img, alt: p.title } );
        $( '#pmp-lb-title' ).text( p.price ? p.title + '  –  ' + p.price : p.title );
        $( '#pmp-lb-buy' ).attr( 'href', p.product );

        var tags = '';
        if ( p.location ) tags += '<span class="pmp-lb-tag" data-filter="location" data-value="' + p.location + '">📍 ' + p.location + '</span>';
        if ( p.category ) tags += '<span class="pmp-lb-tag" data-filter="category" data-value="' + p.category + '">🏷 ' + p.category + '</span>';
        if ( p.dateFmt  ) tags += '<span class="pmp-lb-tag" data-filter="date" data-value="' + p.date + '">📅 ' + p.dateFmt + '</span>';
        $( '#pmp-lb-tags' ).html( tags );

        $( '#pmp-lb-prev' ).toggle( lbList.length > 1 );
        $( '#pmp-lb-next' ).toggle( lbList.length > 1 );
    }

    function openLightbox( idx ) {
        buildLbList();
        showLbPhoto( idx );
        $( '#pmp-lightbox' ).addClass( 'open' );
        $( 'body' ).css( 'overflow', 'hidden' );
    }
    function closeLightbox() {
        $( '#pmp-lightbox' ).removeClass( 'open' );
        $( '#pmp-lb-img' ).attr( 'src', '' );
        $( 'body' ).css( 'overflow', '' );
    }

    $( document ).on( 'click', '.pmp-lightbox-trigger', function(e) {
        if ( $( e.target ).closest( '.pmp-card-cta, .pmp-tag' ).length ) return;
        e.preventDefault();
        buildLbList();
        var $t  = $( this );
        var img = $t.data('img');
        var idx = 0;
        lbList.forEach( function( p, i ) { if ( p.img === img ) idx = i; } );
        openLightbox( idx );
    });

    $( document ).on( 'click', '#pmp-lb-close', closeLightbox );
    $( document ).on( 'click', '#pmp-lightbox', function(e) {
        if ( e.target === this ) closeLightbox();
    });
    $( document ).on( 'click', '#pmp-lb-prev', function(e) { e.stopPropagation(); showLbPhoto( lbIndex - 1 ); } );
    $( document ).on( 'click', '#pmp-lb-next', function(e) { e.stopPropagation(); showLbPhoto( lbIndex + 1 ); } );

    $( document ).on( 'keydown', function(e) {
        if ( ! $( '#pmp-lightbox' ).hasClass('open') ) return;
        if ( e.key === 'Escape'     ) closeLightbox();
        if ( e.key === 'ArrowLeft'  ) showLbPhoto( lbIndex - 1 );
        if ( e.key === 'ArrowRight' ) showLbPhoto( lbIndex + 1 );
    });

    /* ── Swipe support + block background scroll ────── */
    var swipeX = 0;
    var swipeY = 0;
    $( document ).on( 'touchstart', '#pmp-lightbox', function(e) {
        swipeX = e.originalEvent.touches[0].clientX;
        swipeY = e.originalEvent.touches[0].clientY;
    });
    $( document ).on( 'touchmove', '#pmp-lightbox', function(e) {
        e.preventDefault();
    }, { passive: false } );
    $( document ).on( 'touchend', '#pmp-lightbox', function(e) {
        var dx = e.originalEvent.changedTouches[0].clientX - swipeX;
        var dy = e.originalEvent.changedTouches[0].clientY - swipeY;
        if ( Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50 ) {
            dx < 0 ? showLbPhoto( lbIndex + 1 ) : showLbPhoto( lbIndex - 1 );
        }
    });

    /* ── Lightbox tag click → filter + close ────────── */
    $( document ).on( 'click', '.pmp-lb-tag', function(e) {
        e.stopPropagation();
        var type = $( this ).data('filter');
        var val  = $( this ).data('value');
        closeLightbox();
        if ( type === 'location' ) $( '#pmp-f-location' ).val( val ).trigger('change');
        if ( type === 'category' ) $( '#pmp-f-category' ).val( val ).trigger('change');
        if ( type === 'date' ) {
            $( '#pmp-f-date-from' ).val( val );
            $( '#pmp-f-date-to'   ).val( val );
            doFilter();
        }
        $( 'html, body' ).animate({ scrollTop: $( '#pmp-filters' ).offset().top - 20 }, 300 );
    });

    /* ── Clickable card tags ────────────────────────── */
    $( document ).on( 'click', '.pmp-tag[data-filter]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var type = $( this ).data('filter');
        var val  = $( this ).data('value');
        if ( type === 'location' ) $( '#pmp-f-location' ).val( val ).trigger('change');
        if ( type === 'category' ) $( '#pmp-f-category' ).val( val ).trigger('change');
        if ( type === 'date' ) {
            $( '#pmp-f-date-from' ).val( val );
            $( '#pmp-f-date-to'   ).val( val );
            doFilter();
        }
        $( 'html, body' ).animate({ scrollTop: $( '#pmp-filters' ).offset().top - 20 }, 300 );
    });

    /* ── Chained: location changes → reload categories + auto filter ── */
    $( document ).on( 'change', '#pmp-f-location', function(){
        refreshOptions();
        doFilter();
    });

    /* ── Auto filter on category / date change ──────────────── */
    $( document ).on( 'change', '#pmp-f-category, #pmp-f-date-from, #pmp-f-date-to', function(){
        doFilter();
    });

    /* ── Reset button ───────────────────────────────────────── */
    $( document ).on( 'click', '#pmp-reset-filter', function(){
        $( '#pmp-f-location, #pmp-f-category' ).val('');
        $( '#pmp-f-date-from, #pmp-f-date-to' ).val('');
        $( '#pmp-f-location' ).siblings( '.aurel_select' ).text(
            $( '#pmp-f-location option:first' ).text()
        );
        $( '#pmp-f-category' ).siblings( '.aurel_select' ).text(
            $( '#pmp-f-category option:first' ).text()
        );
        $( '#pmp-active-filters' ).hide().empty();
        refreshOptions();
        doFilter();
    });

    /* ── refreshOptions: fill dropdowns via AJAX ───────────── */
    function refreshOptions() {
        var location = $( '#pmp-f-location' ).val() || '';
        var category = $( '#pmp-f-category' ).val() || '';

        $.ajax({
            url:  ajaxurl,
            type: 'POST',
            data: {
                action: 'pmp_v2_gallery_opts',
                nonce:    nonce,
                location: location,
                category: category,
            },
            success: function( res ) {
                if ( !res || !res.success || !res.data ) {
                    console.warn( '[PMP] Filter options response:', res );
                    return;
                }
                var d = res.data;
                var curLoc = $( '#pmp-f-location' ).val();
                var curCat = $( '#pmp-f-category' ).val();

                // Rebuild location select + aurel_select custom UI
                var $loc = $( '#pmp-f-location' );
                $loc.find( 'option:not(:first)' ).remove();
                ( d.locations || [] ).forEach( function( l ) {
                    $loc.append( new Option( l, l, false, l === curLoc ) );
                });
                pmpRebuildAurel( $loc, d.locations, curLoc );

                // Rebuild category select + aurel_select custom UI
                var $cat = $( '#pmp-f-category' );
                $cat.find( 'option:not(:first)' ).remove();
                ( d.categories || [] ).forEach( function( c ) {
                    $cat.append( new Option( c, c, false, c === curCat ) );
                });
                pmpRebuildAurel( $cat, d.categories, curCat );

                // Set date input limits if dates available
                if ( d.date_min ) $( '#pmp-f-date-from' ).attr( 'min', d.date_min );
                if ( d.date_max ) $( '#pmp-f-date-to'   ).attr( 'max', d.date_max );
            },
            error: function( xhr, status, err ) {
                console.error( '[PMP] refreshOptions error:', status, err, xhr.responseText );
            }
        });
    }

    /* ── aurel_select: rebuild custom <ul> after options change ─ */
    function pmpRebuildAurel( $sel, items, curVal ) {
        var $ul = $sel.siblings( 'ul.select-options' );
        if ( ! $ul.length ) return;
        $ul.find( 'li[data-pmp]' ).remove();
        ( items || [] ).forEach( function( v ) {
            var $li = $( '<li>' ).attr( 'rel', v ).attr( 'data-pmp', '1' ).text( v );
            if ( v === curVal ) $li.addClass( 'selected' );
            $ul.append( $li );
        });
        if ( curVal ) $sel.siblings( '.aurel_select' ).text( curVal );
    }

    /* ── aurel_select: handle clicks on our dynamic items ───── */
    $( document ).on( 'click', '.pmp-select-wrap ul.select-options li[data-pmp]', function(e) {
        e.stopPropagation();
        var $li  = $( this );
        var val  = $li.attr( 'rel' );
        var $sel = $li.closest( '.pmp-select-wrap' ).find( 'select' );
        var $div = $li.closest( '.pmp-select-wrap' ).find( '.aurel_select' );
        $li.siblings().removeClass( 'selected' );
        $li.addClass( 'selected' );
        $div.text( $li.text() );
        $ul = $li.closest( 'ul' );
        $ul.hide();
        $sel.val( val ).trigger( 'change' );
    });

    /* ── Active filter tag remove ───────────────────────────── */
    $( document ).on( 'click', '.pmp-active-rm', function() {
        var field = $( this ).data('clear');
        if ( field === 'location' ) {
            $( '#pmp-f-location' ).val('');
            $( '#pmp-f-location' ).siblings( '.aurel_select' ).text( $( '#pmp-f-location option:first' ).text() );
            refreshOptions();
        } else if ( field === 'category' ) {
            $( '#pmp-f-category' ).val('');
            $( '#pmp-f-category' ).siblings( '.aurel_select' ).text( $( '#pmp-f-category option:first' ).text() );
        } else if ( field === 'date_from' ) {
            $( '#pmp-f-date-from' ).val('');
        } else if ( field === 'date_to' ) {
            $( '#pmp-f-date-to' ).val('');
        }
        doFilter();
    });

    /* ── Format date YYYY-MM-DD → DD/MM/YYYY ───────────────── */
    function pmpFmtDate( d ) {
        if ( !d ) return d;
        var p = d.split('-');
        return p.length === 3 ? p[2]+'/'+p[1]+'/'+p[0] : d;
    }

    /* ── doFilter: reload masonry cards (reset to page 1) ──── */
    function doFilter() {
        curFilters = {
            location:  $( '#pmp-f-location'  ).val() || '',
            category:  $( '#pmp-f-category'  ).val() || '',
            date_from: $( '#pmp-f-date-from' ).val() || '',
            date_to:   $( '#pmp-f-date-to'   ).val() || '',
        };
        curPage = 0;

        var $af = $( '#pmp-active-filters' );
        var html = '';
        if ( curFilters.location ) html += '<span class="pmp-active-tag pmp-active-rm" data-clear="location">📍 ' + curFilters.location + ' ✕</span>';
        if ( curFilters.category ) html += '<span class="pmp-active-tag pmp-active-rm" data-clear="category">🏷 ' + curFilters.category + ' ✕</span>';
        if ( curFilters.date_from ) html += '<span class="pmp-active-tag pmp-active-rm" data-clear="date_from">Dal: ' + pmpFmtDate( curFilters.date_from ) + ' ✕</span>';
        if ( curFilters.date_to )   html += '<span class="pmp-active-tag pmp-active-rm" data-clear="date_to">Al: '  + pmpFmtDate( curFilters.date_to )   + ' ✕</span>';
        html ? $af.html( html ).show() : $af.hide().empty();

        $( '#pmp-masonry' ).empty();
        $( '#pmp-load-more-wrap' ).remove();
        loadPage( false );
    }

    /* ── loadPage: fetch next batch and append ──────────────── */
    function loadPage( append ) {
        var offset = curPage * pageSize;
        $( '#pmp-gallery-loading' ).show();

        $.ajax({
            url:  ajaxurl,
            type: 'POST',
            data: $.extend( {}, curFilters, {
                action: 'pmp_v2_gallery_filter',
                nonce:   nonce,
                count:   pageSize,
                offset:  offset,
            }),
            success: function( res ) {
                $( '#pmp-gallery-loading' ).hide();
                if ( !res || !res.success ) {
                    if ( !append ) $( '#pmp-masonry' ).html( '<p class="pmp-no-results">Errore durante il filtraggio.</p>' );
                    return;
                }
                var d = res.data;
                if ( append ) {
                    $( '#pmp-masonry' ).append( d.html );
                } else {
                    $( '#pmp-masonry' ).html( d.html );
                }
                $( '#pmp-load-more-wrap' ).remove();
                if ( d.has_more ) {
                    $( '#pmp-masonry' ).after(
                        '<div id="pmp-load-more-wrap" style="text-align:center;margin:24px 0;">' +
                        '<button class="pmp-btn-load-more" id="pmp-load-more-btn">Carica altri →</button>' +
                        '</div>'
                    );
                }
                curPage++;
            },
            error: function() {
                $( '#pmp-gallery-loading' ).hide();
            }
        });
    }

    /* ── Load more button ───────────────────────────────────── */
    $( document ).on( 'click', '#pmp-load-more-btn', function() {
        loadPage( true );
    });
});
