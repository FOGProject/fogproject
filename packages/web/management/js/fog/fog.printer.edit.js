$(function() {
    $('#hostNotInMe,#hostNoPrinter').hide();
    $('#hostMeShow').change(function(e) {
        $('#hostNotInMe').toggle();
        e.preventDefault();
    });
    $('#hostNoShow').change(function(e) {
        $('#hostNoPrinter').toggle();
        e.preventDefault();
    });
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
    checkboxAssociations('.toggle-actiondef:checkbox','.default:checkbox');
});
