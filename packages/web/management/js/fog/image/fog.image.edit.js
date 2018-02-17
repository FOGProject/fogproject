(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#image').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(': ' + originalName, ': ' + newName);
        e.text(text);
    };

    var generalForm = $('#image-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.submit(function(e) {
        e.preventDefault();
    });

    generalFormBtn.click(function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err)
                return;
            updateName($('#image').val());
            originalName = $('#image').val();
        });
    });

    generalDeleteBtn.click(function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.massDelete(null, function(err) {
            if (err) {
                generalDeleteBtn.prop('disabled', false);
                generalFormBtn.prop('disabled', false);
                return;
            }
            window.location = '../management/index.php?node='+Common.node+'&sub=list';
        });
    });

    // ---------------------------------------------------------------
    // STORAGEGROUPS TAB
    var storagegroupsAddBtn = $('#storagegroups-add'),
        storagegroupsRemoveBtn = $('#storagegroups-remove'),
        storagegroupsPrimaryBtn = $('#storagegroups-primary'),
        PRIMARY_GROUP_ID = -1;

    storagegroupsAddBtn.prop('disabled', true);
    storagegroupsRemoveBtn.prop('disabled', true);
    storagegroupsPrimaryBtn.prop('disabled', true);

    function onStoragegroupsSelect(selected) {
        var disabled = selected.count() == 0;
        storagegroupsAddBtn.prop('disabled', disabled);
        storagegroupsRemoveBtn.prop('disabled', disabled);
    }

    var storagegroupsTable = Common.registerTable($('#image-storagegroups-table'), onStoragegroupsSelect, {
        columns: [
            {data: 'name'},
            {data: 'primary'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=storagegroup&sub=edit&id='
                        + row.id
                        + '">'
                        + data
                        + '</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 20000,
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.primary > 0 && row.origID == Common.id) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="isPrimaryGroup' + row.origID + '" type="radio" class="primary'
                        + row.origID
                        + '" name="primary" id="group_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + ' wasoriginalprimary="'
                        + checkval
                        + '" '
                        + checkval
                        + (row.origID != Common.id ? ' disabled' : '')
                        + '/>'
                        + '</div>';
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.association === 'associated') {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="storagegroupsAssoc_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 2
            }
        ],
        processing: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getStoragegroupsList&id='+Common.id,
            type: 'post'
        }
    });
    storagegroupsTable.on('draw', function() {
        Common.iCheck('#image-storagegroups-table input');
        $('#image-storagegroups-table input.primary'+Common.id).on('ifClicked', onRadioSelect);
        $('#image-storagegroups-table input.associated').on('ifClicked', onCheckboxSelect);
    });

    var onRadioSelect = function(event) {
        var id = parseInt($(this).attr('value'));
        if ($(this).attr('belongsto') === 'isPrimaryGroup'+Common.id) {
            if (PRIMARY_GROUP_ID === -1 && $(this).attr('wasoriginalprimary') === ' checked') {
                PRIMARY_GROUP_ID = id;
            }
            if (id === PRIMARY_GROUP_ID) {
                PRIMARY_GROUP_ID = id;
            } else {
                PRIMARY_GROUP_ID = id;
            }
            storagegroupsPrimaryBtn.prop('disabled', false);
        }
    };

    var onCheckboxSelect = function(event) {
    };

    // Setup primary group watcher
    $('.primary'+Common.id).on('ifClicked', onRadioSelect);
    $('.associated').on('ifClicked', onCheckboxSelect);

    storagegroupsPrimaryBtn.on('click', function() {
        storagegroupsAddBtn.prop('disabled', true);
        storagegroupsRemoveBtn.prop('disabled', true);
        storagegroupsPrimaryBtn.prop('disabled', true);
        var opts = {
            'primarysel': '1',
            'primary': PRIMARY_GROUP_ID
        }
        Common.apiCall(storagegroupsPrimaryBtn.attr('method'), storagegroupsPrimaryBtn.attr('action'), opts, function(err) {
            storagegroupsPrimaryBtn.prop('disabled', !err);
            onStoragegroupsSelect(storagegroupsTable.rows({selected: true}));
        });
    });

    storagegroupsAddBtn.on('click', function() {
        storagegroupsAddBtn.prop('disabled', true);

        var rows = storagegroupsTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(storagegroupsTable),
            opts = {
                'updatestoragegroups': '1',
                'storagegroups': toAdd
            };
        Common.apiCall(storagegroupsAddBtn.attr('method'), storagegroupsAddBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data(),
                        id = this.id();
                    $(this).iCheck('check');
                });
                storagegroupsTable.draw(false);
                storagegroupsTable.rows({selected: true}).deselect();
            } else {
                storagegroupsAddBtn.prop('disable', false);
            }
        });
    });

    storagegroupsRemoveBtn.on('click', function() {
        storagegroupsRemoveBtn.prop('disabled', true);
        var rows = storagegroupsTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(storagegroupsTable),
            opts = {
                'storagegroupdel': '1',
                'storagegroupRemove' : toRemove
            };
        Common.apiCall(storagegroupsRemoveBtn.attr('method'), storagegroupsRemoveBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data();
                    $(this).iCheck('uncheck');
                });
                storagegroupsTable.draw(false);
                storagegroupsTable.rows({selected: true}).deselect();
            } else {
                storagegroupsRemoveBtn.prop('disabled', false);
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        storagegroupsTable.search(Common.search).draw();
    }

    // ---------------------------------------------------------------
    // MEMBERSHIP TAB
    var membershipAddBtn = $('#membership-add'),
        membershipRemoveBtn = $('#membership-remove');

    membershipAddBtn.prop('disabled', true);
    membershipRemoveBtn.prop('disabled', true);

    function onMembershipSelect(selected) {
        var disabled = selected.count() == 0;
        membershipAddBtn.prop('disabled', disabled);
        membershipRemoveBtn.prop('disabled', disabled);
    }

    var membershipTable = Common.registerTable($('#image-membership-table'), onMembershipSelect, {
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
                        + data
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="memberAssoc_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 1
            }
        ],
        processing: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getHostsList&id='+Common.id,
            type: 'post'
        }
    });
    membershipTable.on('draw', function() {
        Common.iCheck('#image-membership-table input');
        $('#image-membership-table input.associated').on('ifClicked', onCheckboxSelect);
    });

    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);

        var rows = membershipTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(membershipTable),
            opts = {
                'updatemembership': '1',
                'membership': toAdd
            };
        Common.apiCall(membershipAddBtn.attr('method'), membershipAddBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data(),
                        id = this.id();
                    $(this).iCheck('check');
                });
                membershipTable.draw(false);
                membershipTable.rows({selected: true}).deselect();
            } else {
                membershipAddBtn.prop('disable', false);
            }
        });
    });

    membershipRemoveBtn.on('click', function() {
        membershipRemoveBtn.prop('disabled', true);
        var rows = membershipTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(membershipTable),
            opts = {
                'membershipdel': '1',
                'membershipRemove' : toRemove
            };
        Common.apiCall(membershipRemoveBtn.attr('method'), membershipRemoveBtn.attr('action'), opts, function(err) {
            if (!err) {
                rows.every(function(idx, tableLoop, rowLoop) {
                    var data = this.data();
                    $(this).iCheck('uncheck');
                });
                membershipTable.draw(false);
                membershipTable.rows({selected: true}).deselect();
            } else {
                membershipRemoveBtn.prop('disabled', false);
            }
        });
    });

    if (Common.search && Common.search.length > 0) {
        membershipTable.search(Common.search).draw();
    }

    $('.slider').slider();
})(jQuery);
