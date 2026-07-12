$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#ou',
        formSel: '#ou-general-form'
    });

    // ---------------------------------------------------------------
    // HOST TAB
    var ouHostUpdateBtn = $('#ou-host-send'),
        ouHostRemoveBtn = $('#ou-host-remove'),
        ouHostDeleteConfirmBtn = $('#confirmhostDeleteModal');

    function disableHostButtons(disable) {
        ouHostUpdateBtn.prop('disabled', disable);
        ouHostRemoveBtn.prop('disabled', disable);
    }

    function onHostSelect(selected) {
        var disabled = selected.count() == 0;
        disableHostButtons(disabled);
    }

    ouHostUpdateBtn.on('click', function(e) {
        e.preventDefault();
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            rows = ouHostsTable.rows({selected: true}),
            toAdd = $.getSelectedIds(ouHostsTable),
            opts = {
                confirmadd: 1,
                additems: toAdd
            };
        $.apiCall(method,action,opts,function(err) {
            disableHostButtons(false);
            if (err) {
                return;
            }
            ouHostsTable.draw(false);
            ouHostsTable.rows({selected: true}).deselect();
        });
    });

    ouHostRemoveBtn.on('click', function(e) {
        e.preventDefault();
        $('#hostDelModal').modal('show');
    });

    var ouHostsTable = $('#ou-host-table').registerTable(onHostSelect, {
        order: [
            [1, 'asc'],
            [0, 'asc']
        ],
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
                    return '<div class="form-check">'
                        + '<input type="checkbox" class="associated" name="associate[]" id="locationHostAssoc_'
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
            url: '../management/index.php?node='+Common.node+'&sub=getHostsList&id='+Common.id,
            type: 'post'
        }
    });

    ouHostDeleteConfirmBtn.on('click', function(e) {
        $.deleteAssociated(ouHostsTable, ouHostUpdateBtn.attr('action'), function(err) {
            $('#hostDelModal').modal('hide');
            if (err) {
                return;
            }
            ouHostsTable.draw(false);
            ouHostsTable.rows({selected: true}).deselect();
        });
    });

    ouHostsTable.on('draw', function() {
        Common.iCheck('#ou-host-table input');
        $('#ou-host-table input.associated').on('change', onOUHostCheckboxSelect);
        onHostSelect(ouHostsTable.rows({selected: true}));
    });

    var onOUHostCheckboxSelect = function(e) {
        $.checkItemUpdate(ouHostsTable, this, e, ouHostUpdateBtn);
    };

    if (Common.search && Common.search.length > 0) {
        ouHostsTable.search(Common.search).draw();
    }
});
