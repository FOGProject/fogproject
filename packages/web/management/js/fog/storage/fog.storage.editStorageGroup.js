(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#name').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        e.text(text);
    };

    var generalForm = $('#storagegroup-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.on('submit', function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function(e) {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#name').val());
            originalName = $('#name').val();
        });
    });

    // ----------------------------------------------------
    // MEMBERSHIP TAB
    var membershipForm = $('#storagegroup-membership-form'),
        membershipAddBtn = $('#membership-add'),
        membershipRemoveBtn = $('#membership-remove'),
        membershipMasterBtn = $('#membership-master'),
        MASTER_NODE_ID = -1;

    membershipAddBtn.prop('disabled', true);
    membershipRemoveBtn.prop('disabled', true);

    function onMembershipSelect(selected) {
        var disabled = selected.count() == 0;
        membershipAddBtn.prop('disabled', disabled);
        membershipRemoveBtn.prop('disabled', disabled);
    }

    var membershipTable = Common.registerTable($('#storagegroup-membership-table'), onMembershipSelect, {
        order: [
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
                    return '<a href="../management/index.php?node=storage&sub=edit&id='+row.id+'">'+data+'</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 20000,
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.isMaster > 0 && row.origID == Common.id) {
                        checkval = ' checked';
                    }
                    return '<div class="radio">'
                        + '<input belongsto="isMasterNode' + row.origID +'" type="radio" class="master" name="master" id="node_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + ' wasoriginaldefault="'
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="storagenodeAssoc_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + checkval
                        + '/>'
                        + '</div>';
                },
                targets: 2
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=getStorageNodesList&id='+Common.id,
            type: 'post'
        }
    });
    membershipTable.on('draw', function() {
        Common.iCheck('#storagegroup-membership input');
        $('#storagegroup-membership-table input.master'+Common.id).on('ifClicked', onRadioSelect);
        $('#storagegroup-membership-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    membershipMasterBtn.prop('disabled', true);

    var onRadioSelect = function(event) {
        var id = parseInt($(this).attr('value'));
        if ($(this).attr('belongsto') === 'isMasterNode'+Common.id) {
            if (MASTER_NODE_ID === -1 && $(this).attr('wasoriginaldefault') === ' checked') {
                MASTER_NODE_ID = id;
            }
            if (id === MASTER_NODE_ID) {
                MASTER_NODE_ID = 0;
            } else {
                MASTER_NODE_ID = id;
            }
            membershipMasterBtn.prop('disabled', false);
        }
    };
    var onCheckboxSelect = function(event) {
    }

    // Setup master node watcher
    $('.master'+Common.id).on('ifClicked', onRadioSelect);
    $('.associated').on('ifClicked', onCheckboxSelect);

    membershipMasterBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        membershipMasterBtn.prop('disabled', true);
        membershipRemoveBtn.prop('disabled', true);
        var opts = {
            'mastersel': '1',
            'master': MASTER_NODE_ID
        }
        Common.apiCall(membershipMasterBtn.attr('method'), membershipMasterBtn.attr('action'), opts, function(err) {
            membershipMasterBtn.prop('disabled', !err);
            onMembershipSelect(membershipTable.rows({selected: true}));
        });
    });

    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disable', true);

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
                'membershipRemove': toRemove
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
})(jQuery);
