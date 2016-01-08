$(function() {
    // Show hide based on checked state.
    $('#hostNotInMe').hide();
    $('#hostNoImage').hide();
    $('#hostMeShow').click(function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').click(function() {
        $('#hostNoImage').toggle();
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
    $('#groupNoImage').hide();
    $('#groupMeShow').click(function() {
        $('#groupNotInMe').toggle();
    });
    $('#groupNoShow').click(function() {
        $('#groupNoImage').toggle();
    });
    $('.toggle-checkbox1').click(function() {
        $('input.toggle-group1:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkbox2').click(function() {
        $('input.toggle-group2:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
});
