$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolIDArray[accesscontrolIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolIDArray"]').val(accesscontrolIDArray.join(','));
    });
    $('#action-box').submit(function() {
        var checked = $('input.toggle-action:checked');
        var accesscontrolruleIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            accesscontrolruleIDArray[accesscontrolruleIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="accesscontrolruleIDArray"]').val(accesscontrolruleIDArray.join(','));
    });
    $('#hostMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNotInMe').show();
        else $('#hostNotInMe').hide();
        e.preventDefault();
    });
    $('#hostMeShow:checkbox').trigger('change');
    checkboxAssociations('.toggle-checkboxuser:checkbox','.toggle-user:checkbox');
    checkboxAssociations('.toggle-checkboxhost:checkbox','.toggle-host:checkbox');
});
