(function($) {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#printer').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(': ' + originalName, ': ' + newName);
        e.text(text);
    };

    var generalForm = $('#printer-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err)
                return;
            updateName($('#printer').val());
            originalName = $('#printer').val();
        });
    });
    generalDeleteBtn.on('click',function() {
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
    var membershipTable = Common.registerTable($('#printer-membership-table'), onMembershipSelect, {
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
        Common.iCheck('#printer-membership-table input');
        $('#printer-membership-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            toAdd = Common.getSelectedIds(membershipTable),
            opts = {
                'updatemembership': '1',
                'membership': toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                membershipTable.draw(false);
                membershipTable.rows({selected: true}).deselect();
            } else {
                membershipAddBtn.prop('disable', false);
            }
        });
    });
    membershipRemoveBtn.on('click', function() {
        membershipRemoveBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            toRemove = Common.getSelectedIds(membershipTable),
            opts = {
                'membershipdel': '1',
                'membershipRemove' : toRemove
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
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
