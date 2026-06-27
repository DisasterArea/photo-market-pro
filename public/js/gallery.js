/* Photo Market Pro – Frontend Gallery JS */
jQuery(function($){
    var cfg     = PMP_Public;
    var ajaxurl = cfg.ajaxurl;
    var nonce   = cfg.nonce;
    var $wrap   = $('.pmp-gallery-wrap');
    if ( !$wrap.length ) return;
    var count = parseInt( $wrap.data('count') ) || 6;

    /* ── Boot: load dropdowns ──────────────────────── */
    refreshOptions();

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
        $( '#pmp-f-category' ).val('');
        $( '#pmp-f-category' ).siblings( '.aurel_select' ).text(
            $( '#pmp-f-category option:first' ).text()
        );
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

    /* ── doFilter: reload masonry cards ────────────────────── */
    function doFilter() {
        var location  = $( '#pmp-f-location'  ).val() || '';
        var category  = $( '#pmp-f-category'  ).val() || '';
        var dateFrom  = $( '#pmp-f-date-from' ).val() || '';
        var dateTo    = $( '#pmp-f-date-to'   ).val() || '';

        var tags = [];
        if ( location ) tags.push( '📍 ' + location );
        if ( category ) tags.push( '🏷 ' + category );
        if ( dateFrom ) tags.push( 'Dal: ' + dateFrom );
        if ( dateTo   ) tags.push( 'Al: '  + dateTo );

        var $af = $( '#pmp-active-filters' );
        if ( tags.length ) {
            $af.html( tags.map( function(t){ return '<span class="pmp-active-tag">'+t+'</span>'; }).join('') ).show();
        } else {
            $af.hide().empty();
        }

        $( '#pmp-gallery-loading' ).show();
        $( '#pmp-masonry' ).css( 'opacity', .35 );

        $.ajax({
            url:  ajaxurl,
            type: 'POST',
            data: {
                action: 'pmp_v2_gallery_filter',
                nonce:     nonce,
                location:  location,
                category:  category,
                date_from: dateFrom,
                date_to:   dateTo,
                count:     count,
            },
            success: function( res ) {
                $( '#pmp-gallery-loading' ).hide();
                $( '#pmp-masonry' ).css( 'opacity', 1 );
                if ( res && res.success ) {
                    $( '#pmp-masonry' ).html( res.data.html );
                } else {
                    $( '#pmp-masonry' ).html( '<p class="pmp-no-results">Errore durante il filtraggio.</p>' );
                }
            },
            error: function( xhr, status, err ) {
                $( '#pmp-gallery-loading' ).hide();
                $( '#pmp-masonry' ).css( 'opacity', 1 );
                console.error( '[PMP] doFilter error:', status, err, xhr.responseText );
            }
        });
    }
});
