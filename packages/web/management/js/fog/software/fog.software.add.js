(function($) {
    // Version policy select shows the pinned-version input only when
    // "Pinned" is chosen; see fog.software.edit.js for the identical wiring
    // on the General tab. Kept inline on each form rather than centralized
    // in fog.common.js -- it is three lines and only ever wired here.
    var createForm = $('#software-create-form');

    function toggleVersionPolicy() {
        var pinned = createForm.find('[name="versionPolicy"]').val() === 'pinned';
        createForm.find('.softwareversion-pinned').toggleClass('d-none', !pinned);
    }

    toggleVersionPolicy();
    createForm.on('change', '[name="versionPolicy"]', toggleVersionPolicy);

    createForm.wireCreateForm();
})(jQuery);
