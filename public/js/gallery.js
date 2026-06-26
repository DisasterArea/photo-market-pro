/* Photo Market Pro – Frontend Gallery JS */
jQuery(function($){
    var cfg     = PMP_Public;
    var ajaxurl = cfg.ajaxurl;
    var nonce   = cfg.nonce;
    var $wrap   = $('.pmp-gallery-wrap');
    if ( !$wrap.length ) return;
    var count = parseInt( $wrap.data('count') ) || 6;

    /* ── Boot: load dropdowns immediately ─────────────────── */
    refreshOptions();

    /* ── Chained: location changes → reload categories ─────── */
    $( document ).on( 'change', '#pmp-f-location', function(){
        $( '#pmp-f-category' ).val('');
        refreshOptions();
    });

    /* ── Buttons ────────────────────────────────────────────── */
    $( document ).on( 'click', '#pmp-apply-filter', function(){ doFilter(); });
    $( document ).on( 'click', '#pmp-reset-filter', function(){
        $( '#pmp-f-location, #pmp-f-category' ).val('');
        $( '#pmp-f-date-from, #pmp-f-date-to' ).val('');
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

                // Rebuild location select
                var $loc = $( '#pmp-f-location' );
                $loc.find( 'option:not(:first)' ).remove();
                ( d.locations || [] ).forEach( function( l ) {
                    $loc.append( new Option( l, l, false, l === curLoc ) );
                });

                // Rebuild category select
                var $cat = $( '#pmp-f-category' );
                $cat.find( 'option:not(:first)' ).remove();
                ( d.categories || [] ).forEach( function( c ) {
                    $cat.append( new Option( c, c, false, c === curCat ) );
                });

                // Set date input limits if dates available
                if ( d.date_min ) $( '#pmp-f-date-from' ).attr( 'min', d.date_min );
                if ( d.date_max ) $( '#pmp-f-date-to'   ).attr( 'max', d.date_max );
            },
            error: function( xhr, status, err ) {
                console.error( '[PMP] refreshOptions error:', status, err, xhr.responseText );
            }
        });
    }

    /* ── doFilter: reload masonry cards ────────────────────── */
    function doFilter() {
        var location  = $( '#pmp-f-location'  ).val() || '';
        var category  = $( '#pmp-f-category'  ).val() || '';
        var dateFrom  = $( '#pmp-f-date-from' ).val() || '';
        var dateTo    = $( '#pmp-f-date-to'   ).val() || '';

        // Show active filter tags
        var tags = [];
        if ( location ) tags.push( '📍 ' + location );
        if ( category ) tags.push( '🏷 ' + category );
        if ( dateFrom ) tags.push( 'Tól: ' + dateFrom );
        if ( dateTo   ) tags.push( 'Ig: '  + dateTo );

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
                    $( '#pmp-masonry' ).html( '<p class="pmp-no-results">Hiba a szűrés közben.</p>' );
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
