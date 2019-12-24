$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#site').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#site-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#site').val());
            originalName = $('#site').val();
        });
    });
    generalDeleteBtn.on('click', function() {
        generalDeleteModal.modal('show');
    });
    generalDeleteModalConfirm.on('click', function() {
        var method = 'post',
            action = '../management/index.php?node='
                + Common.node
                + '&sub=delete&id='
                + Common.id;
        $.apiCall(method, action, null, function(err) {
            if (err) {
                return;
            }
            setTimeout(function() {
                window.location = '../management/index.php?node='
                    + Common.node
                    + '&sub=list';
            }, 2000);
        });
    });
    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    var siteHostUpdateBtn = $('#site-host-send'),
        siteHostRemoveBtn = $('#site-host-remove'),
        siteHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        siteHostUpdateBtn.prop('disabled', disable);
        siteHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    siteHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = siteHostsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(siteHostsTable),
            opts = {
                updatehosts: 1,
                hosts: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            siteHostsTable.rows({selected: true}).deselect();
            siteHostsTable.draw(false);
        });
    });

    siteHostRemoveBtn.on('click', function(e) {
        $('#hostDelModal').modal('show');
    });

    var siteHostsTable = $('#site-host-table').registerTable(onHostSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=host&sub=edit&id='
                    + row.id
                    + '">'
                    + row.name
                    + '</a>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                    + '<input type="checkbox" class="associated" name="associate[]" id="siteHostAssoc_'
                    + row.id
                    + '" value="' + row.id + '"'
                    + checkval
                    + '/>'
                    + '</div>';
                },
                targets: 1
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getHostsList&id='
            + Common.id,
            type: 'post'
        }
    });

    siteHostsTable.on('draw', function() {
        Common.iCheck('#site-host-table input');
        $('#site-host-table input.associated').on('ifClicked', onSiteHostCheckboxSelect);
        onHostSelect(siteHostsTable.rows({selected: true}));
    });

    siteHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(siteHostsTable, siteHostRemoveBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            siteHostsTable.draw(false);
            siteHostsTable.rows({selected: true}).deselect();
        });
    });
    var onSiteHostCheckboxSelect = function(e) {
        $(this).iCheck('update');
        var method = siteHostUpdateBtn.attr('method'),
            action = siteHostUpdateBtn.attr('action'),
            opts = {};
        if (this.checked) {
            opts = {
                addusers: 1,
                users: [e.target.value]
            };
        } else {
            opts = {
                confirmdel: 1,
                remitems: [e.target.value]
            };
        }
        $.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            siteHostsTable.draw(false);
            siteHostsTable.rows({selected: true}).deselect();
        });
    };

    // ---------------------------------------------------------------
    // USER ASSOCIATION TAB
    var siteUserUpdateBtn = $('#site-user-send'),
        siteUserRemoveBtn = $('#site-user-remove'),
        siteUserDeleteConfirmBtn = $('#confirmuserDeleteModal');

    function disableUserButtons(disable) {
        siteUserUpdateBtn.prop('disabled', disable);
        siteUserRemoveBtn.prop('disabled', disable);
    }

    function onUserSelect(selected) {
        var disabled = selected.count() == 0;
        disableUserButtons(disabled);
    }

    siteUserUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = siteUsersTable.rows({selected: true}),
            toAdd = $.getSelectedIds(siteUsersTable),
            opts = {
                addusers: 1,
                users: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableUserButtons(false);
            if (err) {
                return;
            }
            siteUsersTable.rows({selected: true}).deselect();
            siteUsersTable.draw(false);
        });
    });

    siteUserRemoveBtn.on('click', function(e) {
        $('#userDelModal').modal('show');
    });

    var siteUsersTable = $('#site-user-table').registerTable(onUserSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=user&sub=edit&id='
                    + row.id
                    + '">'
                    + row.name
                    + '</a>';
                },
                targets: 0
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                    + '<input type="checkbox" class="associated" name="associate[]" id="siteUserAssoc_'
                    + row.id
                    + '" value="' + row.id + '"'
                    + checkval
                    + '/>'
                    + '</div>';
                },
                targets: 1
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=getUsersList&id='
            + Common.id,
            type: 'post'
        }
    });

    siteUserDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(siteUsersTable, siteUserRemoveBtn.attr('action'), function(err) {
            $('#userDelModal').modal('hide');
            if (err) {
                return;
            }
            siteUsersTable.draw(false);
            siteUsersTable.rows({selected: true}).deselect();
        });
    });

    siteUsersTable.on('draw', function() {
        Common.iCheck('#site-user-table input');
        $('#site-user-table input.associated').on('ifChanged', onSiteUserCheckboxSelect);
        onUserSelect(siteUsersTable.rows({selected: true}));
    });

    var onSiteUserCheckboxSelect = function(e) {
        $(this).iCheck('update');
        var method = siteUserUpdateBtn.attr('method'),
            action = siteUserUpdateBtn.attr('action'),
            opts = {};
        if (this.checked) {
            opts = {
                addusers: 1,
                users: [e.target.value]
            };
        } else {
            opts = {
                confirmdel: 1,
                remitems: [e.target.value]
            };
        }
        $.apiCall(method, action, opts, function(err) {
            if (err) {
                return;
            }
            siteUsersTable.draw(false);
            siteUsersTable.rows({selected: true}).deselect();
        });
    };

    if (Common.search && Common.search.length > 0) {
        siteHostsTable.search(Common.search).draw();
        siteUsersTable.search(Common.search).draw();
    }
});
