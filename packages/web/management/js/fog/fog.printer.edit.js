$(function() {
    $('#hostNotInMe,#hostNoPrinter').hide();
    $('#hostMeShow').change(function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').change(function() {
        $('#hostNoPrinter').toggle();
    });
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
    checkboxAssociations('.toggle-actiondef:checkbox','.default:checkbox');
});
