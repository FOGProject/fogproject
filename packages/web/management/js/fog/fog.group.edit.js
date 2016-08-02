$(function() {
    $('#resetSecData').val('Reset Encryption Data');
    $('#delAllPM').val('Delete all power management for group');
    $('#resetSecData').click(function() {
        $('#resetSecDataBox').html('Are you sure you wish to reset this groups hosts encryption data?');
        $('#resetSecDataBox').dialog({
            resizable: false,
            modal: true,
            title: 'Clear Encryption',
            buttons: {
                'Yes': function() {
                    $.post('../management/index.php',{sub: 'clearAES',groupid: $_GET.id});
                    $('#resetSecData').hide();
                    $(this).dialog('close');
                },
                'No': function() {
                    $(this).dialog('close');
                }
            }
        });
    });
    $('#delAllPM').click(function() {
        $('#delAllPMBox').html('Are you sure you wish to remove all power management tasks with this group?');
        $('#delAllPMBox').dialog({
            resizable: false,
            modal: true,
            title: 'Remove Power Management Tasks',
            buttons: {
                'Yes': function() {
                    $.post('../management/index.php',{sub: 'clearPMTasks',groupid: $_GET.id});
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
    checkboxAssociations('#rempowerselectors:checkbox','.rempoweritems:checkbox');
    // Show hide based on checked state.
    $('#hostMeShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNotInMe').show();
        else $('#hostNotInMe').hide();
        e.preventDefault();
    });
    $('#hostMeShow:checkbox').trigger('change');
    $('#hostNoShow:checkbox').change(function(e) {
        if ($(this).is(':checked')) $('#hostNoGroup').show();
        else $('#hostNoGroup').hide();
        e.preventDefault();
    });
    $('#hostNoShow:checkbox').trigger('change');
    result = true;
    $('#scheduleOnDemand').change(function() {
        if ($(this).is(':checked') === true) {
            $(this).parents('form').each(function() {
                $("input[name^='scheduleCron']",this).each(function() {
                    $(this).val('').prop('readonly',true).hide().parents('tr').hide();
                });
            });
        } else {
            $(this).parents('form').each(function() {
                $("input[name^='scheduleCron']",this).each(function() {
                    $(this).val('').prop('readonly',false).show().parents('tr').show();
                });
            });
        }
    });
    $("form.deploy-container").submit(function() {
        if ($('#scheduleOnDemand').is(':checked')) {
            $("p#cronOptions > input[name^='scheduleCron']",$(this)).each(function() {
                $(this).val('').prop('disabled',true);
                console.log('here');
            });
            return true;
        } else {
            $("p#cronOptions > input[name^='scheduleCron']",$(this)).each(function() {
                result = validateCronInputs($(this));
                if (result === false) return false;
            });
        }
        return result;
    }).each(function() {
        $("input[name^='scheduleCron']",this).each(function(id,value) {
            if (!validateCronInputs($(this))) $(this).addClass('error');
        }).blur(function() {
            if (!validateCronInputs($(this))) $(this).addClass('error');
        });
    });
});
