(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#storagegroup').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        e.text(text);
    };

    var generalForm = $('#storagegroup-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit', function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function(e) {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#storagegroup').val());
            originalName = $('#storagegroup').val();
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

    // ----------------------------------------------------
    // STORAGENODES TAB
    var membershipForm = $('#storagegroup-membership-form'),
        membershipAddBtn = $('#membership-add'),
        membershipRemoveBtn = $('#membership-remove'),
        membershipMasterBtn = $('#membership-master'),
        MASTER_NODE_ID = -1;

    membershipAddBtn.prop('disabled', true);
    membershipRemoveBtn.prop('disabled', true);
    membershipMasterBtn.prop('disabled', true);

    function onMembershipSelect(selected) {
        var disabled = selected.count() == 0;
        membershipAddBtn.prop('disabled', disabled);
        membershipRemoveBtn.prop('disabled', disabled);
    }

    var membershipTable = $('#storagegroup-membership-table').registerTable(onMembershipSelect, {
        order: [
            [2, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'isMaster'},
            {data: 'association'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                render: function(data, type, row) {
                    return '<a href="../management/index.php?node=storagenode&sub=edit&id='+row.id+'">'+data+'</a>';
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
                        + '<input belongsto="isMasterNode'
                        + row.origID
                        +'" type="radio" class="master" name="master" id="node_'
                        + row.id
                        + '" value="' + row.id + '"'
                        + ' wasoriginalmaster="'
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
        $('#storagegroup-membership-table input.master').on('ifClicked', onRadioSelect);
        $('#storagegroup-membership-table input.associated').on('ifClicked', onMembershipSelect);
    });

    var onRadioSelect = function(event) {
        var id = parseInt($(this).attr('value'));
        if ($(this).attr('belongsto') === 'isMasterNode'+Common.id) {
            if (MASTER_NODE_ID === -1 && $(this).attr('wasoriginalmaster') === ' checked') {
                MASTER_NODE_ID = id;
            }
            if (id === MASTER_NODE_ID) {
                MASTER_NODE_ID = id;
            } else {
                MASTER_NODE_ID = id;
            }
            membershipMasterBtn.prop('disabled', false);
        }
    };

    // Setup master node watcher
    $('.master').on('ifClicked', onRadioSelect);
    $('.associated').on('ifClicked', onMembershipSelect);

    membershipMasterBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        membershipMasterBtn.prop('disabled', true);
        membershipRemoveBtn.prop('disabled', true);
        var opts = {
            mastersel: '1',
            master: MASTER_NODE_ID
        },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            membershipMasterBtn.prop('disabled', false);
            if (err) {
                return;
            }
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        var rows = membershipTable.rows({selected: true}),
            toAdd = $.getSelectedIds(membershipTable),
            opts = {
                updatemembership: '1',
                membership: toAdd
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            membershipAddBtn.prop('disabled', false);
            if (err) {
                return;
            }
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    membershipRemoveBtn.on('click', function() {
        membershipRemoveBtn.prop('disabled', true);
        var method = membershipRemoveBtn.attr('method'),
            action = membershipRemoveBtn.attr('action'),
            rows = membershipTable.rows({selected: true}),
            toRemove = $.getSelectedIds(membershipTable),
            opts = {
                membershipdel: '1',
                membershipRemove: toRemove
            };
        $.apiCall(method, action, opts, function(err) {
            membershipRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });

    // ----------------------------------------------------
    // IMAGES TAB
    var imageAddBtn = $('#image-add'),
        imageRemoveBtn = $('#image-remove'),
        imagePrimaryBtn = $('#image-set-primary'),
        imageRemPrimaryBtn = $('#image-rem-primary');
    var imageTable = $('#image-membership-table').registerTable('', {
        order: [
            [2, 'asc'],
            [0, 'asc']
        ],
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
                    return '<a href="../management/index.php?node=image&sub=edit&id='+row.id+'">'+data+'</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 20000,
                render: function(data, type, row) {
                    var checkval = '';
                    if (data > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="primary" name="primary[]" id="image_'
                        + row.id
                        + '" value="' + row.id + '" '
                        + checkval
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="imageGroupAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getImagesList&id='+Common.id,
            type: 'post'
        }
    });
    imageTable.on('draw', function() {
        Common.iCheck('#image-membership input');
    });
    imageButtonsDisable = function(selected) {
        var disabled = selected.count() == 0;
        imageAddBtn.prop('disabled', disabled);
        imageRemoveBtn.prop('disabled', disabled);
        imageRemPrimaryBtn.prop('disabled', disabled);
        imagePrimaryBtn.prop('disabled', disabled);
    };
    imagePrimaryBtn.on('click', function() {
        imagePrimaryBtn.prop('disabled', true);
        var rows = imageTable.rows({selected: true}),
            toAdd = $.getSelectedIds(imageTable),
            opts = {
                primarysel: '1',
                primary: toAdd
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            imagePrimaryBtn.prop('disabled', false);
            if (err) {
                return;
            }
            imageTable.draw(false);
            imageTable.rows({selected: true}).deselect();
        });
    });
    imageRemPrimaryBtn.on('click', function() {
        imageRemPrimaryBtn.prop('disabled', true);
        var rows = imageTable.rows({selected: true}),
            toRem = $.getSelectedIds(imageTable),
            opts = {
                primaryrem: '1',
                primary: toRem
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            imageRemPrimaryBtn.prop('disabled', false);
            if (err) {
                return;
            }
            imageTable.draw(false);
            imageTable.rows({selected: true}).deselect();
        });
    });
    imageAddBtn.on('click', function() {
        imageAddBtn.prop('disabled', true);
        var rows = imageTable.rows({selected: true}),
            toAdd = $.getSelectedIds(imageTable),
            opts = {
                updateimage: '1',
                image: toAdd
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            imageAddBtn.prop('disabled', false);
            if (err) {
                return;
            }
            imageTable.draw(false);
            imageTable.rows({selected: true}).deselect();
        });
    });
    imageRemoveBtn.on('click', function() {
        imageRemoveBtn.prop('disabled', true);
        var method = imageRemoveBtn.attr('method'),
            action = imageRemoveBtn.attr('action'),
            rows = imageTable.rows({selected: true}),
            toRemove = $.getSelectedIds(imageTable),
            opts = {
                imagedel: '1',
                imageRemove: toRemove
            };
        $.apiCall(method, action, opts, function(err) {
            imageRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            imageTable.draw(false);
            imageTable.rows({selected: true}).deselect();
        });
    });

    // ----------------------------------------------------
    // SNAPINS TAB
    var snapinAddBtn = $('#snapin-add'),
        snapinRemoveBtn = $('#snapin-remove'),
        snapinPrimaryBtn = $('#snapin-set-primary'),
        snapinRemPrimaryBtn = $('#snapin-rem-primary');
    var snapinTable = $('#snapin-membership-table').registerTable('', {
        order: [
            [2, 'asc'],
            [0, 'asc']
        ],
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
                    return '<a href="../management/index.php?node=snapin&sub=edit&id='+row.id+'">'+data+'</a>';
                },
                targets: 0
            },
            {
                responsivePriority: 20000,
                render: function(data, type, row) {
                    var checkval = '';
                    if (row.primary > 0) {
                        checkval = ' checked';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="primary" name="primary[]" id="snapin_'
                        + row.id
                        + '" value="' + row.id + '" '
                        + checkval
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="snapinGroupAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getSnapinsList&id='+Common.id,
            type: 'post'
        }
    });
    snapinTable.on('draw', function() {
        Common.iCheck('#snapin-membership input');
    });
    snapinButtonsDisable = function(selected) {
        var disabled = selected.count() == 0;
        snapinAddBtn.prop('disabled', disabled);
        snapinRemoveBtn.prop('disabled', disabled);
        snapinPrimaryBtn.prop('disabled', disabled);
        snapinRemPrimaryBtn.prop('disabled', disabled);
    };
    snapinPrimaryBtn.on('click', function() {
        snapinPrimaryBtn.prop('disabled', true);
        var rows = snapinTable.rows({selected: true}),
            toAdd = $.getSelectedIds(snapinTable),
            opts = {
                primarysel: '1',
                primary: toAdd
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            snapinPrimaryBtn.prop('disabled', false);
            if (err) {
                return;
            }
            snapinTable.draw(false);
            snapinTable.rows({selected: true}).deselect();
        });
    });
    snapinRemPrimaryBtn.on('click', function() {
        snapinRemPrimaryBtn.prop('disabled', true);
        var rows = snapinTable.rows({selected: true}),
            toRem = $.getSelectedIds(snapinTable),
            opts = {
                primaryrem: '1',
                primary: toRem
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            snapinRemPrimaryBtn.prop('disabled', false);
            if (err) {
                return;
            }
            snapinTable.draw(false);
            snapinTable.rows({selected: true}).deselect();
        });
    });
    snapinAddBtn.on('click', function() {
        snapinAddBtn.prop('disabled', true);
        var rows = snapinTable.rows({selected: true}),
            toAdd = $.getSelectedIds(snapinTable),
            opts = {
                updatesnapin: '1',
                snapin: toAdd
            },
            method = $(this).attr('method'),
            action = $(this).attr('action');
        $.apiCall(method, action, opts, function(err) {
            snapinAddBtn.prop('disabled', true);
            if (err) {
                return;
            }
            snapinTable.draw(false);
            snapinTable.rows({selected: true}).deselct();
        });
    });
    snapinRemoveBtn.on('click', function() {
        snapinRemoveBtn.prop('disabled', true);
        var method = snapinRemoveBtn.attr('method'),
            action = snapinRemoveBtn.attr('action'),
            rows = snapinTable.rows({selected: true}),
            toRemove = $.getSelectedIds(snapinTable),
            opts = {
                snapindel: '1',
                snapinRemove: toRemove
            };
        $.apiCall(method, action, opts, function(err) {
            snapinRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            snapinTable.draw(false);
            snapinTable.rows({selected: true}).deselect();
        });
    });
    if (Common.search && Common.search.length > 0) {
        membershipTable.search(Common.search).draw();
        imageTable.search(Common.search).draw();
        snapinTable.search(Common.search).draw();
    }
})(jQuery);
