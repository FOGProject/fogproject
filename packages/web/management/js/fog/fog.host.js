(function($) {
    setADFields();
    clearADFields();
    advancedTaskLink();
    checkboxToggleSearchListPages();
    checkboxAssociations('.toggle-checkboxgroup:checkbox', '.toggle-group:checkbox');
    MACUpdate();
    ProductUpdate();
    $('#process').on('click', function(e) {
        checkedIDs = getChecked();
        group_new = $('#group_new').val().trim();
        group_sel = $('select[name="group"]').val();
        if (typeof group_sel != 'undefined') {
            group_sel = group_sel.trim();
        }
        if (checkedIDs.length < 1) {
            return;
        }
        if (group_new.length < 1 && group_sel.length < 1) {
            return;
        }
        url = $(this).parents('form').prop('action');
        postdata = {
            hostIDArray: checkedIDs.join(',')
        };
        if (group_new) {
            $.extend(
                postdata,
                {
                    group_new: group_new
                }
            );
        } else {
            $.extend(
                postdata,
                {
                    group: group_sel
                }
            );
        }
        $.post(
            url,
            postdata
        );
    });
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            host: {
                required: true,
                minlength: 1,
                maxlength: 15,
                regex: /^[\w!@#$%^()'{}\.~-]{1,15}$/
            },
            mac: {
                required: true,
                minlength: 12,
                maxlength: 17,
                regex: /^(?:[0-9A-Fa-f]{2}([-:]))(?:[0-9A-Fa-f]{2}\1){4}[0-9A-Fa-f]{2}$|^(?:[0-9A-Fa-f]{12})$|^(?:[0-9A-Fa-f]{4}([.])){2}[0-9A-Fa-f]{4}$/
            }
        }
    };
    setupTimeoutElement('#approvependhost, #delete, #process, #host-add, #all, #pmsubmit, #pmupdate, #pmdelete, #updategen, #levelup, #updateprinters, #defaultsel, #printdel, #updatesnapins, #snapdel, #updatestatus, #updatedisplay, #updatealo, #updateinv, #host-edit', '.hostname-input, .macaddr', 1000);
})(jQuery);
