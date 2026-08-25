(function($) {
    // The two report tables on Image Management -> Architectures.
    //
    // WHY THIS FILE EXISTS AT ALL: Page::_setJavascripts() resolves a page's
    // script purely by name -- ?node=image&sub=architectures loads
    // js/fog/image/fog.image.architectures.js if it is there, and falls back
    // to fog.image.list.js if it is not. Until this file existed the fallback
    // ran, and it looks for #dataTable, which this page does not have. So the
    // page loaded a script, that script found nothing, and the tables stayed
    // plain HTML with no error anywhere.
    //
    // Enhancing the SERVER-RENDERED DOM rather than fetching JSON is
    // deliberate. These tables are a join across hosts and images that no
    // Route class models -- there is no 'host and its image's architecture'
    // entity to list -- so a server-side grid would need a bespoke endpoint
    // for one page. Reading the rows PHP already wrote costs nothing, keeps
    // the mismatch row highlighting (tr.table-danger) that the report is
    // largely for, and cannot disagree with the summary tiles above, which
    // are counted off those same rows.
    //
    // registerTable() rather than a bare .DataTable(): it is what applies the
    // admin's own FOG_VIEW_DEFAULT_SCREEN row count and FOG_TABLE_SCROLL_MODE
    // paging style, plus column resizing, exactly as every other grid in FOG
    // does. That consistency is the point of using it here.
    var opts = {
        // Read-only reports. registerTable() defaults to multi-select with
        // Select All / Deselect All buttons, which offer an action that does
        // not exist on this page.
        select: false,
        buttons: [],
        // Ordered by the first column, which is the name in both tables --
        // the order the PHP already emitted, so the first paint does not
        // reshuffle.
        order: [[0, 'asc']],
        // Paging style is NOT set here. registerTable() derives it from the
        // admin's FOG_TABLE_SCROLL_MODE, and these two tables follow that
        // setting like every other grid in FOG. An earlier version passed
        // scroller:false to force classic paging, on the theory that Scroller
        // needs a uniform row height these tables do not have -- they do: the
        // muted "Not yet seen" spans are ordinary inline text at the same line
        // height as every other cell. Opting a table out of a global display
        // preference needs a real reason, and that was not one.
        rowCallback: function(row, data) {
            // Re-apply the mismatch highlight on every draw.
            //
            // The class is written by PHP onto the <tr>, but a table that
            // pages -- virtually or classically -- redraws its rows, and the
            // red row is the single thing this report exists to show. Keying
            // off a data attribute rather than trusting the class to survive
            // means it cannot quietly stop appearing on page two, which is a
            // failure nobody would notice until it mattered.
            if ($(row).attr('data-mismatch') === '1') {
                $(row).addClass('table-danger');
            }
        }
    };
    $('#architectures-images').registerTable(null, $.extend({}, opts));
    $('#architectures-hosts').registerTable(null, $.extend({}, opts));
})(jQuery);
