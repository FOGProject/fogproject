$(function() {
    checkboxToggleSearchListPages();
    $('.action-boxes.del').submit(function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolIDArray[accesscontrolIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolIDArray"]').val(accesscontrolIDArray.join(','));
    });
    $('.action-boxes').show();
    $('.action-boxes.host').submit(function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolruleIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolruleIDArray[accesscontrolruleIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolruleIDArray"]').val(accesscontrolruleIDArray.join(','));
    });
    $('#accesscontrolruleMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#accesscontrolruleNotInMe').show();
        else $('#accesscontrolruleNotInMe').hide();
        e.preventDefault();
    });
    $('#accesscontrolruleMeShow:checkbox').trigger('change');
    checkboxAssociations('.toggle-checkboxuser:checkbox','.toggle-user:checkbox');
});
