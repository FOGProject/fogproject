$(function() {
    // Show hide based on checked state.
    $('#hostNotInMe,#hostNoSnapin').hide();
    $('#hostMeShow').click(function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').click(function() {
        $('#hostNoSnapin').toggle();
    });
    $('.toggle-checkbox1').click(function() {
        $('input.toggle-host1:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkbox2').click(function() {
        $('input.toggle-host2:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('#groupNotInMe').hide();
    $('#groupNoSnapin').hide();
    $('#groupMeShow').click(function() {
        $('#groupNotInMe').toggle();
    });
    $('#groupNoShow').click(function() {
        $('#groupNoSnapin').toggle();
    });
    $('.toggle-checkbox1').click(function() {
        $('input.toggle-snapin1:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkbox2').click(function() {
        $('input.toggle-snapin2:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
});
