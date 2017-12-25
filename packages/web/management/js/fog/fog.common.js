var table = $('#dataTable')
    .DataTable({
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
    }),
    $_GET = getQueryParams(document.location.search),
    node = $_GET['node'],
    sub = $_GET['sub'],
    id = $_GET['id'],
    tab = $_GET['tab'],
    getSelectedIds,
    massDelete;
(function($) {
    if (typeof table != 'undefined' && table.length > 0) {
        getSelectedIds = function() {
            var itemIds = table.rows({
                selected: true
            }).ids().toArray();
            console.log(itemIds);
            return itemIds;
        }
        massDelete = function(password) {
            var opts = {
                fogguipass: password,
                remitems: getSelectedIds()
            };
            $.ajax('', {
                type: 'POST',
                url: '../management/index.php?node='+node+'&sub=deletemulti',
                async: true,
                data: opts,
                success: function(res) {
                    console.log(res);
                    table.rows({
                        selected: true
                    }).remove().draw();
                    if (node == 'host') {
                        table.button(1,1).enable(true);
                    } else {
                        table.button(1,0).enable(true);
                    }
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
        if (node == 'host') {
            new $.fn.dataTable.Buttons(table, {
                buttons: [
                    {
                        text: 'Add selected to group',
                        action: function(e, dt, bnode, config) {
                            alert('Added selected hosts to group!');
                        },
                        enabled: false,
                        init: function(api, bnode, config) {
                            $(bnode).attr('data-toggle', 'modal');
                            $(bnode).attr('data-target', '#modal-group');
                        }
                    },
                    {
                        text: 'Delete Selected',
                        className: 'btn-danger',
                        action: function(e, dt, bnode, config) {
                            table.button(1,1).enable(false);
                            massDelete();
                        },
                        enabled: false,
                        init: function(api, bnode, config) {
                            $(bnode).removeClass('btn-default');
                        }
                    }
                ]
            });
        } else {
            new $.fn.dataTable.Buttons(table, {
                buttons: [
                    {
                        text: 'Delete Selected',
                        className: 'btn-danger',
                        action: function(e, dt, bnode, config) {
                            table.button(1,0).enable(false);
                            massDelete();
                        },
                        enabled: false,
                        init: function(api, bnode, config) {
                            $(bnode).removeClass('btn-default');
                        }
                    }
                ]
            });
        }
        table.buttons(1, null).container().appendTo(
            table.table().container()
        );
        table.on('select', function() {
            var selectedRows = table.rows({
                selected: true
            }).count();
            table.button(1,0).enable(selectedRows > 0);
            if (node == 'host') {
                table.button(1,1).enable(selectedRows > 0);
            }
        }).on('deselect', function() {
            var selectedRows = table.rows({
                selected: true
            }).count();
            table.button(1,0).enable(selectedRows > 0);
            if (node == 'host') {
                table.button(1,1).enable(selectedRows > 0);
            }
        });
    }
    //Initialize Select2 Elements
    $('.select2').select2({
        width: '100%'
    });
    // iCheck elements.
    $('input').iCheck({
        checkboxClass: 'icheckbox_square-blue',
        radioClass: 'iradio_square-blue',
        increaseArea: '20%' // optional
    });
})(jQuery);
/**
 * Gets the GET params from the URL.
 */
function getQueryParams(qs) {
    qs = qs.split("+").join(" ");
    var params = {},tokens,re = /[?&]?([^=]+)=([^&]*)/g;
    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
    }
    return params;
}
