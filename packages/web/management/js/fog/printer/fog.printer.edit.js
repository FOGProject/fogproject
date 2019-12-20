(function($) {
    var printertype = $('#printertype'),
        printercopy = $('#printercopy'),
        type = printertype.val().toLowerCase();

    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#printer'+type+':visible').val(),
        updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            $('#printercopy option').each(function() {
                opttext = $(this).text().split(' - ');
                if (opttext[0] == originalName) {
                    opttext[0] = newName;
                    opttext = opttext.join(' - ');
                    $(this).text(opttext);
                }
            });
            $('#printercopy').select2();
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#printer-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        generalForm.processForm(function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#printer'+type).val());
            originalName = $('#printer'+type).val();
        }, ':input:visible');
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
    // MEMBERSHIP TAB
    var membershipDefaultBtn = $('#membership-default'),
        membershipAddBtn = $('#membership-add'),
        membershipRemoveBtn = $('#membership-remove');
    membershipAddBtn.prop('disabled', true);
    membershipRemoveBtn.prop('disabled', true);
    function onMembershipSelect(selected) {
        var disabled = selected.count() == 0;
        membershipAddBtn.prop('disabled', disabled);
        membershipRemoveBtn.prop('disabled', disabled);
    }
    function onCheckboxSelect(selected) {
    }
    var membershipTable = $('#printer-hosts-table').registerTable(onMembershipSelect, {
        order: [
            [2, 'asc'],
            [0, 'asc']
        ],
        columns: [
            {data: 'name'},
            {data: 'isDefault'},
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
                    if (row.association !== 'associated') {
                        checkval = ' disabled';
                    } else if (row.isDefault > 0) {
                        checkval = ' checked';
                    } else {
                        checkval = '';
                    }
                    return '<div class="checkbox">'
                        + '<input type="checkbox" class="default" name="default[]" id="memberDefault_'
                        + row.id
                        + '" value="'
                        + row.id
                        + '"'
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
                        + '<input type="checkbox" class="associated" name="associate[]" id="memberAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getHostsList&id='+Common.id,
            type: 'post'
        }
    });
    membershipTable.on('draw', function() {
        Common.iCheck('#printer-hosts-table input');
        $('#printer-hosts-table input.associated').on('ifClicked', onCheckboxSelect);
    });
    membershipDefaultBtn.on('click', function() {
        membershipDefaultBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            defaulton = [],
            opts = {
                updatedefault: '1',
                defaulton: defaulton
            };
        // Get all the checked default options.
        $('#printer-hosts-table').find('.default:checked').each(function() {
            defaulton.push($(this).val());
        });
        $.apiCall(method,action,opts,function(err) {
            membershipDefaultBtn.prop('disabled', true);
            if (!err) {
                membershipTable.draw(false);
                membershipTable.rows({selected: true}).deselect();
            } else {
                membershipDefaultBtn.prop('disabled', false);
            }
        });
    });
    membershipAddBtn.on('click', function() {
        membershipAddBtn.prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = membershipTable.rows({selected: true}),
            toAdd = $.getSelectedIds(membershipTable),
            opts = {
                'updatemembership': '1',
                'membership': toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            if (!err) {
                membershipTable.draw(false);
                membershipTable.rows({selected: true}).deselect();
                $('#printer-hosts-table').find('.default:disabled').each(function() {
                    if ($.inArray($(this).val(), toAdd) != -1) {
                        $(this).prop('disabled', false);
                        Common.iCheck(this);
                    }
                });
                $('#printer-hosts-table').find('.associated').each(function() {
                    if ($.inArray($(this).val(), toAdd) != -1) {
                        $(this).iCheck('check');
                    }
                });
            } else {
                membershipAddBtn.prop('disabled', false);
            }
        });
    });
    membershipRemoveBtn.on('click', function() {
        $('#hostDelModal').modal('show');
    });
    $('#confirmhostDeleteModal').on('click', function(e) {
        $.deleteAssociated(membershipTable, membershipRemoveBtn.attr('action'), function(err) {
            if (err) {
                return;
            }
            $('#hostDelModal').modal('hide');
            membershipTable.draw(false);
            membershipTable.rows({selected: true}).deselect();
        });
    });
    if (Common.search && Common.search.length > 0) {
        membershipTable.search(Common.search).draw();
    }

    // Hides the fields not currently selected.
    $('.network,.iprint,.cups,.local').addClass('hidden');
    $('.'+type).removeClass('hidden');
    // On change hide all the fields and show the appropriate type.
    printertype.on('change', function(e) {
        e.preventDefault();
        type = printertype.val().toLowerCase();
        $('.network,.iprint,.cups,.local').addClass('hidden');
        $('.'+type).removeClass('hidden');
    });
    // Setup all fields to match when/where appropriate
    $('[name="printer"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="printer"]').val(val);
        });
    });
    $('[name="description"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="description"]').val(val);
        });
    });
    $('[name="inf"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="inf"]').val(val);
        });
    });
    $('[name="port"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="port"]').val(val);
        });
    });
    $('[name="ip"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="ip"]').val(val);
        });
    });
    $('[name="model"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="model"]').val(val);
        });
    });
    $('[name="configFile"]').on('change', function() {
        var val = $(this).val();
        $(this).each(function() {
            $('[name="configFile"]').val(val);
        });
    });
})(jQuery);
