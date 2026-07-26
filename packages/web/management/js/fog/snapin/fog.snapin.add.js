(function($) {
    // Shared command-builder UI (fog.common.js), scoped to this form. Add form
    // wires packTypes but has no .packhide, so packHide stays off.
    $('#snapin-create-form')
        .initSnapinCommandUI({wirePackTypes: true})
        .wireCreateForm();
})(jQuery);
