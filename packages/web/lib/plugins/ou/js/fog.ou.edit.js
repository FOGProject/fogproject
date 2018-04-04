$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#ou').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#ou-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click',function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#ou').val());
            originalName = $('#ou').val();
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
        Common.apiCall(method, action, null, function(err) {
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
    var membershipTable = Common.registerTable($('#ou-membership-table'), onMembershipSelect, {
        columns: [
            {data: 'name'},
            {data: 'association'},
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
        Common.iCheck('#ou-membership-table input');
        $('#ou-membership-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    // Setup this tables associated checkboxes.
    var associated = $('#ou-membership-table input.associated');
    associated.on('ifClicked', onCheckboxSelect);
    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        membershipRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(membershipTable),
            opts = {
                updatemembership: 1,
                membership: toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            membershipAddBtn.prop('disabled', false);
            membershipRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('#ou-membership-table').find('.associated').each(function() {
                if ($.inArray($(this).val(), toAdd) != -1) {
                    $(this).iCheck('check');
                }
            });
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    membershipRemoveBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        membershipRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(membershipTable),
            opts = {
                membershipdel: 1,
                membershipRemove: toRemove
            };
        Common.apiCall(method,action,opts,function(err) {
            membershipAddBtn.prop('disabled', false);
            membershipRemoveBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('#ou-membership-table').find('.associated').each(function() {
                if ($.inArray($(this).val(), toRemove) != -1) {
                    $(this).iCheck('uncheck');
                }
            });
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    if (Common.search && Common.search.length > 0) {
        membershipTable.search(Common.search).draw();
    }
});
