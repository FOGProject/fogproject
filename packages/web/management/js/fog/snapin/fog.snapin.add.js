(function($) {
    $('#snapin-create-form').wireCreateForm();
    // Shared command-builder UI (fog.common.js). Add form wires #packTypes but
    // has no .packhide, so packHide stays off.
    $.initSnapinCommandUI({wirePackTypes: true});
})(jQuery);
