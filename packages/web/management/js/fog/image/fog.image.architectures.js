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
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        // Classic paging, not the virtual scroller. Scroller sizes its
        // viewport from a UNIFORM row height, and these tables deliberately
        // carry taller rows (the muted "Not yet seen" spans) alongside short
        // ones; it also renders into a fixed-height viewport, which is wrong
        // for a report whose whole job is to be read top to bottom.
        scroller: false
    };
    // Ordered by the first column, which is the name in both tables -- the
    // order the PHP already emitted, so the first paint does not reshuffle.
    $('#architectures-images').registerTable(null, $.extend({}, opts, {
        order: [[0, 'asc']]
    }));
    $('#architectures-hosts').registerTable(null, $.extend({}, opts, {
        order: [[0, 'asc']]
    }));
})(jQuery);
