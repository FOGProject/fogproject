$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#site',
        formSel: '#site-general-form'
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
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            siteHostsTable.draw(false);
            siteHostsTable.rows({selected: true}).deselect();
        });
    });

    siteHostRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var siteHostsTable = $('#site-host-table').registerTable(onHostSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
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

    siteHostsTable.on('draw', function() {
        Common.iCheck('#site-host-table input');
        $('#site-host-table input.associated').on('change', onSiteHostCheckboxSelect);
        onHostSelect(siteHostsTable.rows({selected: true}));
    });

    var onSiteHostCheckboxSelect = function(e) {
        $.checkItemUpdate(siteHostsTable, this, e, siteHostUpdateBtn);
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
                confirmadd: 1,
                additems: toAdd
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
        e.preventDefault();
        $('#userDelModal').modal('show');
    });

    var siteUsersTable = $('#site-user-table').registerTable(onUserSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
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
        $('#site-user-table input.associated').on('change', onSiteUserCheckboxSelect);
        onUserSelect(siteUsersTable.rows({selected: true}));
    });

    var onSiteUserCheckboxSelect = function(e) {
        $.checkItemUpdate(siteUsersTable, this, e, siteUserUpdateBtn);
    };

    // ---------------------------------------------------------------
    // GROUP ASSOCIATION TAB
    var siteGroupUpdateBtn = $('#site-group-send'),
        siteGroupRemoveBtn = $('#site-group-remove'),
        siteGroupDeleteConfirmBtn = $('#confirmgroupDeleteModal');

    function disableGroupButtons(disable) {
        siteGroupUpdateBtn.prop('disabled', disable);
        siteGroupRemoveBtn.prop('disabled', disable);
    }

    function onGroupSelect(selected) {
        var disabled = selected.count() == 0;
        disableGroupButtons(disabled);
    }

    siteGroupUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(siteGroupsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableGroupButtons(false);
            if (err) {
                return;
            }
            siteGroupsTable.draw(false);
            siteGroupsTable.rows({selected: true}).deselect();
        });
    });

    siteGroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#groupDelModal').modal('show');
    });

    var siteGroupsTable = $('#site-group-table').registerTable(onGroupSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="siteGroupAssoc_'
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
                + '&sub=getGroupsList&id='
                + Common.id,
            type: 'post'
        }
    });

    siteGroupDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(siteGroupsTable, siteGroupRemoveBtn.attr('action'), function(err) {
            $('#groupDelModal').modal('hide');
            if (err) {
                return;
            }
            siteGroupsTable.draw(false);
            siteGroupsTable.rows({selected: true}).deselect();
        });
    });

    siteGroupsTable.on('draw', function() {
        Common.iCheck('#site-group-table input');
        $('#site-group-table input.associated').on('change', onSiteGroupCheckboxSelect);
        onGroupSelect(siteGroupsTable.rows({selected: true}));
    });

    var onSiteGroupCheckboxSelect = function(e) {
        $.checkItemUpdate(siteGroupsTable, this, e, siteGroupUpdateBtn);
    };

    // ---------------------------------------------------------------
    // USER GROUP ASSOCIATION TAB
    var siteUserGroupUpdateBtn = $('#site-usergroup-send'),
        siteUserGroupRemoveBtn = $('#site-usergroup-remove'),
        siteUserGroupDeleteConfirmBtn = $('#confirmusergroupDeleteModal');

    function disableUserGroupButtons(disable) {
        siteUserGroupUpdateBtn.prop('disabled', disable);
        siteUserGroupRemoveBtn.prop('disabled', disable);
    }

    function onUserGroupSelect(selected) {
        var disabled = selected.count() == 0;
        disableUserGroupButtons(disabled);
    }

    siteUserGroupUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            toAdd = $.getSelectedIds(siteUserGroupsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableUserGroupButtons(false);
            if (err) {
                return;
            }
            siteUserGroupsTable.draw(false);
            siteUserGroupsTable.rows({selected: true}).deselect();
        });
    });

    siteUserGroupRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#usergroupDelModal').modal('show');
    });

    var siteUserGroupsTable = $('#site-usergroup-table').registerTable(onUserGroupSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'mainLink'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="siteUserGroupAssoc_'
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
                + '&sub=getUserGroupsList&id='
                + Common.id,
            type: 'post'
        }
    });

    siteUserGroupDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(siteUserGroupsTable, siteUserGroupRemoveBtn.attr('action'), function(err) {
            $('#usergroupDelModal').modal('hide');
            if (err) {
                return;
            }
            siteUserGroupsTable.draw(false);
            siteUserGroupsTable.rows({selected: true}).deselect();
        });
    });

    siteUserGroupsTable.on('draw', function() {
        Common.iCheck('#site-usergroup-table input');
        $('#site-usergroup-table input.associated').on('change', onSiteUserGroupCheckboxSelect);
        onUserGroupSelect(siteUserGroupsTable.rows({selected: true}));
    });

    var onSiteUserGroupCheckboxSelect = function(e) {
        $.checkItemUpdate(siteUserGroupsTable, this, e, siteUserGroupUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        siteHostsTable.search(Common.search).draw();
        siteUsersTable.search(Common.search).draw();
        siteGroupsTable.search(Common.search).draw();
        siteUserGroupsTable.search(Common.search).draw();
    }
});
