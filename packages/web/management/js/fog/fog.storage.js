$(function() {
    $('#del-storage').on('click', function(e) {
        var checked = getChecked();
        $('input[name="'+node+'IDArray"]').val(checked.join(','));
        this.form.submit();
    });
    checkboxAssociations('.toggle-checkboxAction:checkbox','.toggle-action:checkbox');
});
