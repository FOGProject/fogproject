$(function() {
    $('#hostMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNotInMe').show();
        else $('#hostNotInMe').hide();
        e.preventDefault();
    });
    $('#hostMeShow:checkbox').trigger('change');
    $('#hostNoShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNoPrinter').show();
        else $('#hostNoPrinter').hide();
        e.preventDefault();
    });
    $('#hostNoShow:checkbox').trigger('change');
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
    checkboxAssociations('.toggle-actiondef:checkbox','.default:checkbox');
});
