$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#type').val()
        + '-'
        + $('#value').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#rule-general-form'),
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
            name = $('#type').val()
                + '-'
                + $('#value').val();
            updateName(name);
            originalName = name;
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
    // ROLE ASSOCIATION TAB
    var rolesAddBtn = $('#roles-add'),
        rolesRemoveBtn = $('#roles-remove');

    rolesAddBtn.prop('disabled', true);
    rolesRemoveBtn.prop('disabled', true);

    function onRolesSelect (selected) {
        var disabled = selected.count() == 0;
        rolesAddBtn.prop('disabled', disabled);
        rolesRemoveBtn.prop('disabled', disabled);
    }

    var rolesTable = $('#rule-roles-table').registerTable(onRolesSelect, {
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
                    return '<a href="../management/index.php?node=accesscontrol&sub=edit&id='
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
                    + '<input type="checkbox" class="associated" name="associate[]" id="roleAssoc_'
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
            + '&sub=getRolesList&id='
            + Common.id,
            type: 'post'
        }
    });

    rolesTable.on('draw', function() {
        Common.iCheck('#rule-roles-table input');
    });

    rolesAddBtn.on('click', function() {
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = rolesTable.rows({selected: true}),
            toAdd = $.getSelectedIds(rolesTable),
            opts = {
                updateroles: 1,
                role: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            if (err) {
                return;
            }
            rolesTable.draw(false);
            rolesTable.rows({selected: true}).deselect();
        });
    });

    rolesRemoveBtn.on('click', function() {
        $('#roleDelModal').modal('show');
    });

    $('#confirmroleDeleteModal').on('click', function(e) {
        $.deleteAssociated(rolesTable, rolesRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#roleDelModal').modal('hide');
            rolesTable.draw(false);
            rolesTable.rows({selected: true}).deselect();
        });

    });

    if (Common.search && Common.search.length > 0) {
        rolesTable.search(Common.search).draw();
    }
});
