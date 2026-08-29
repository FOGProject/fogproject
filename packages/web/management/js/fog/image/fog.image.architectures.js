(function($) {
    // The images report on Image Management -> Architectures.
    //
    // WHY THIS FILE EXISTS AT ALL: Page::_setJavascripts() resolves a page's
    // script purely by name -- ?node=image&sub=architectures loads
    // js/fog/image/fog.image.architectures.js if it is there, and falls back
    // to fog.image.list.js if it is not. Until this file existed the fallback
    // ran, and it looks for #dataTable, which this page does not have. So the
    // page loaded a script, that script found nothing, and the table stayed
    // plain HTML with no error anywhere.
    //
    // Enhancing the SERVER-RENDERED DOM rather than fetching JSON is
    // deliberate. This table carries a host count joined onto each image and
    // no Route class models it, so a server-side grid would need a bespoke
    // endpoint for one page. Reading the rows PHP already wrote costs nothing
    // and cannot disagree with the summary tiles above, which are counted off
    // those same rows.
    //
    // Only ONE table is enhanced. The mismatch card above it is deliberately
    // left plain: it is an exception list that is usually absent and never
    // long, and DataTables removes paged-away rows from the DOM -- which for
    // a list whose whole purpose is "read every one of these" is a loss.
    //
    // registerTable() rather than a bare .DataTable(): it is what applies the
    // admin's own FOG_VIEW_DEFAULT_SCREEN row count and FOG_TABLE_SCROLL_MODE
    // paging style, plus column resizing, exactly as every other grid in FOG
    // does. That consistency is the point of using it here.
    $('#architectures-images').registerTable(null, {
        // A read-only report. registerTable() defaults to multi-select with
        // Select All / Deselect All buttons, which offer an action that does
        // not exist on this page.
        select: false,
        buttons: [],
        // Ordered by the first column, the image name -- the order the PHP
        // already emitted, so the first paint does not reshuffle.
        order: [[0, 'asc']]
        // Paging style is NOT set here. registerTable() derives it from the
        // admin's FOG_TABLE_SCROLL_MODE, and this table follows that setting
        // like every other grid in FOG.
    });
})(jQuery);
