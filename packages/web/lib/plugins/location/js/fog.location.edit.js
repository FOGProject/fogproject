$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#location').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#location-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        nodeSelector = $('#storagenode'),
        groupSelector = $('#storagegroup');

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
            updateName($('#location').val());
            originalName = $('#location').val();
        });
    });
    generalDeleteBtn.on('cilck', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.massDelete(null, function(err) {
            if (err) {
                generalFormBtn.prop('disabled', false);
                generalDeleteBtn.prop('disabled', false);
                return;
            }
            window.location = '../management/index.php?node='
            + Common.node
            + '&sub=list';
        });
    });
    // Sets the group selector for the selected node.
    nodeSelector.on('change focus focusout', function(e) {
        e.preventDefault();
        var nodeID = this.value;
        Pace.ignore(function() {
            $.get('../fog/storagenode/'+nodeID, function(data) {
                groupSelector.val(data.storagegroupID).select2({
                    width: '100%'
                });
            }, 'json');
        });
    });
    // Resets the node selector of the selected group is not
    // the selected nodes storage group.
    groupSelector.on('change focus focusout', function(e) {
        e.preventDefault();
        var nodeID = nodeSelector.val(),
            groupID = this.value;
        Pace.ignore(function() {
            $.get('../fog/storagegroup/'+groupID, function(data) {
                if ($.inArray(nodeID, data.allnodes) != -1) {
                    return;
                }
                nodeSelector.val('').select2({
                    width: '100%'
                });
            }, 'json');
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
    var membershipTable = Common.registerTable($('#location-membership-table'), onMembershipSelect, {
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
        Common.iCheck('#location-membership-table input');
        $('#location-membership-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    // Setup this tables associated checkboxes.
    var associated = $('#location-membership-table input.associated');
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
            $('#location-membership-table').find('.associated').each(function() {
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
            $('#location-membership-table').find('.associated').each(function() {
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
