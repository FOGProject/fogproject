(function($) {
    var table = $('#dataTable').DataTable({
        paging: true,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        dom: 'Bfrtip',
        buttons: [
            'selected',
            'selectAll',
            'selectNone'
        ],
        select: true,
    });
    var getSelectedIds = function() {
        var itemIds = table.rows({
            selected: true
        }).ids();
        var cleanIds = [];
        $.each(itemIds, function(i,v) {
            cleanIds[i] = v.replace('host-', '');
        });
        console.log(cleanIds);
        return cleanIds;
    };
    var massDelete = function(password) {
        var opts = {
            fogguipass: password,
            remitems: getSelectedIds()
        };
        $.ajax('', {
            type: 'POST',
            url: "../management/index.php?node=host&sub=deletemulti",
            async:true,
            data: opts,
            success: function(res) {
                console.log(res);
                table.rows({
                    selected: true
                }).remove();
            },
            error: function(res) {
                if (res.status == 401) {
                    bootbox.prompt({
                        title: 'Please re-enter your password',
                        inputType: 'password',
                        callback: function(result) {
                            if (result !== null) {
                                massDelete(result);
                            }
                        }
                    });
                }
            }
        });
    }
    new $.fn.dataTable.Buttons( table, {
        buttons: [{
            text: 'Add selected to group',
            action: function(e, dt, node, config) {
                alert("Added selected hosts to group!");
            },
            enabled: false,
            init: function(api, node, config) {
                $(node).attr('data-toggle','modal');
                $(node).attr('data-target','#modal-group');
            }
        },
        {
            text: 'Delete selected',
            className: 'btn-danger',
            action: function(e, dt, node, config) {
                table.button(1,1).enable(false);
                massDelete();
            },
            enabled: false,
            init: function(api, node, config) {
                $(node).removeClass('btn-default');
            }
        }]
    });
    table.buttons( 1, null ).container().appendTo(
        table.table().container()
    );
    table.on('select', function() {
        var selectedRows = table.rows( { selected: true } ).count();
        table.button(1,0).enable( selectedRows > 0 );
        table.button(1,1).enable( selectedRows > 0 );

    }).on('deselect', function() {
        var selectedRows = table.rows( { selected: true } ).count();
    });

    /*
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
    */
})(jQuery);
