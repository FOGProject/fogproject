$(function() {
    checkboxToggleSearchListPages();
    $('.toggle-checkboxgroup')
    .click(function() {
        $('input.toggle-group[type="checkbox"]')
        .not(':hidden')
        .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkboxAction')
    .click(function() {
        $('input.toggle-host[type="checkbox"]')
        .not(':hidden')
        .prop('checked',$(this).is(':checked'));
    });
    $('#action-box,#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var hostIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) hostIDArray[hostIDArray.length] = checked.eq(i).prop('value');
        $('input[name="hostIDArray"]')
        .val(hostIDArray.join(','));
    });
});
