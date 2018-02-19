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
        generalDeleteBtn = $('#general-delete');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function() {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        var method = generalForm.attr('method'),
            action = generalForm.attr('action'),
            opts = generalForm.find(':visible').serialize();
        Common.apiCall(method,action,opts,function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            updateName($('#printer'+type).val());
            originalName = $('#printer'+type).val();
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
    var membershipTable = Common.registerTable($('#printer-membership-table'), onMembershipSelect, {
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
        Common.iCheck('#printer-membership-table input');
        $('#printer-membership-table input.associated').on('ifClicked', onCheckboxSelect);
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
        $('.default:checked').each(function() {
            defaulton.push($(this).val());
        });
        Common.apiCall(method,action,opts,function(err) {
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
            toAdd = Common.getSelectedIds(membershipTable),
            opts = {
                'updatemembership': '1',
                'membership': toAdd
            };
        Common.apiCall(method,action,opts,function(err) {
            if (!err) {
                membershipTable.draw(false);
                membershipTable.rows({selected: true}).deselect();
                $('.default:disabled').each(function() {
                    if (toAdd.indexOf($(this).val()) != -1) {
                        $(this).prop('disabled', false);
                        Common.iCheck('.default[value='+$(this).val()+']');
                    }
                });
                $('.associated').each(function() {
                    if (toAdd.indexOf($(this).val()) != -1) {
                        $('.associated[value='+$(this).val()+']').iCheck('check');
                    }
                });
            } else {
                membershipAddBtn.prop('disabled', false);
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
                $('.default').each(function() {
                    if (toRemove.indexOf($(this).val()) != -1) {
                        $(this).prop('disabled', true);
                        Common.iCheck('.default[value='+$(this).val()+']');
                    }
                });
                $('.associated').each(function() {
                    if (toRemove.indexOf($(this).val()) != -1) {
                        $('.associated[value='+$(this).val()+']').iCheck('uncheck');
                    }
                });
            } else {
                membershipRemoveBtn.prop('disabled', false);
            }
        });
    });
    if (Common.search && Common.search.length > 0) {
        membershipTable.search(Common.search).draw();
    }

    // Hides the fields not currently selected.
    $('.network,.iprint,.cups,.local').not('.'+type).hide();
    // On change hide all the fields and show the appropriate type.
    printertype.on('change', function(e) {
        e.preventDefault();
        type = printertype.val().toLowerCase();
        $('.network,.iprint,.cups,.local').not('.'+type).hide();
        $('.'+type).show();
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
