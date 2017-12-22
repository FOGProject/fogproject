(function($) { var table = $('#dataTable').DataTable({
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
            cleanIds[i] = v.replace('user-', '');
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
            url: '../management/index.php?node=user&sub=deletemulti',
            async: true,
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
    };
    new $.fn.dataTable.Buttons(table, {
        buttons: [{
            text: 'Delete Selected',
            className: 'btn-danger',
            action: function(e, dt, node, config) {
                table.button(1,0).enable(false);
                massDelete();
            },
            enabled: false,
            init: function(api, node, config) {
                $(node).removeClass('btn-default');
            }
        }]
    });
    table.buttons(1, null).container().appendTo(
        table.table().container()
    );
    table.on('select', function() {
        var selectedRows = table.rows({
            selected: true
        }).count();
        table.button(1,0).enable(selectedRows > 0);
    }).on('deselect', function() {
        var selectedRows = table.rows({
            selected: true
        }).count();
    });
})(jQuery);
