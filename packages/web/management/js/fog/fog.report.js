$(function() {
    checkboxAssociations('.toggle-checkboxAction:checkbox','.toggle-action:checkbox');
    $('#csvsub,#pdfsub').click(function(e) {
        e.preventDefault();
        exportDialog($(this).prop('href'));
    });
});
