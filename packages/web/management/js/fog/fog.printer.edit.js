$(function() {
    // Show hide based on checked state.
    $('#hostNotInMe,#hostNoPrinter').hide();
    $('#hostMeShow').click(function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').click(function() {
        $('#hostNoPrinter').toggle();
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
    $('.toggle-actiondef').click(function() {
        $('.default')
        .not(':hidden')
        .prop('checked',$(this).is(':checked'));
    });
});
