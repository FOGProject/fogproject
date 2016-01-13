$(function() {
    $('#resetSecData').val('Reset Encryption Data');
    $('#resetSecData').click(function() {
        $('#resetSecDataBox').html('Are you sure you wish to reset this groups hosts encryption data?');
        $('#resetSecDataBox').dialog({
            resizable: false,
            modal: true,
            title: 'Clear Encryption',
            buttons: {
                'Yes': function() {
                    $.post('../management/index.php',{sub: 'clearAES',groupid: $_GET.id});
                    $(this).dialog('close');
                },
                'No': function() {
                    $(this).dialog('close');
                }
            }
        });
    });
    // Checkbox toggles
    checkboxAssociations('.toggle-checkbox1:checkbox','.toggle-host1:checkbox');
    checkboxAssociations('.toggle-checkbox2:checkbox','.toggle-host2:checkbox');
    checkboxAssociations('.toggle-checkboxprint:checkbox','.toggle-print:checkbox');
    checkboxAssociations('.toggle-checkboxprintrm:checkbox','.toggle-printrm:checkbox');
    checkboxAssociations('.toggle-checkboxsnapin:checkbox','.toggle-snapin:checkbox');
    checkboxAssociations('.toggle-checkboxsnapinrm:checkbox','.toggle-snapinrm:checkbox');
    // Show hide based on checked state.
    $('#hostNotInMe').hide();
    $('#hostNoGroup').hide();
    $('#hostMeShow').change(function() {
        $('#hostNotInMe').toggle();
    });
    $('#hostNoShow').change(function() {
        $('#hostNoGroup').toggle();
    });
});
