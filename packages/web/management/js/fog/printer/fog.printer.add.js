(function($) {
    // The type-section and copy-from-existing wiring now lives in
    // $.fn.initPrinterFormUI (fog.common.js). It was duplicated verbatim here
    // and in fog.printer.list.js, and had to become root-scoped anyway so the
    // create-and-associate modal on association tabs can reuse it.
    $('#printer-create-form')
        .initPrinterFormUI()
        .wireCreateForm({selector: ':input:visible'});
})(jQuery);
