(function($) {
    var deleteSelected = $('#deleteSelected'),
        createnewBtn = $('#createnew'),
        createnewModal = $('#createnewModal'),
        createForm = $('#create-form'),
        createnewSendBtn = $('#send');

    function disableButtons(disable) {
        deleteSelected.prop('disabled', disable);
    }
    function onSelect(selected) {
        var disabled = selected.count() == 0;
        disableButtons(disabled);
    }
    disableButtons(true);
    // Columns and their render targets are both derived from the header
    // row's data-col attributes, never from literal indexes.
    //
    // They used to be literals, and it broke the moment a column was added:
    // Architecture went in at position 1 and every columnDefs target below it
    // still pointed at its old number, so the lock render landed on the new
    // column and the enabled render landed on Protected. Nothing errored --
    // the grid just showed the wrong icon in the wrong place. Same failure the
    // host grid documents, same fix.
    var columns = [],
        colIndex = {};
    $('#dataTable thead th').each(function() {
        var key = $(this).attr('data-col');
        if (!key) {
            // Keep the counts equal: DataTables raises "Incorrect column
            // count" and draws nothing at all if the header row and this
            // list disagree.
            if (window.console && console.warn) {
                console.warn('FOG: image list header with no data-col', this);
            }
            columns.push({data: null, defaultContent: ''});
            return;
        }
        colIndex[key] = columns.length;
        columns.push({data: key});
    });

    var columnDefs = [];
    if ('mainlink' in colIndex) {
        columnDefs.push({
            responsivePriority: -1,
            targets: colIndex.mainlink
        });
    }
    if ('arch' in colIndex) {
        columnDefs.push({
            render: function(data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                // Spelled out rather than blank: an empty cell reads as x86
                // to anyone scanning. Images captured before schema step 370
                // have no architecture recorded and never will.
                if (!data) {
                    return '<span class="text-muted">Not recorded</span>';
                }
                return '<code>' + data + '</code>';
            },
            targets: colIndex.arch
        });
    }
    if ('protected' in colIndex) {
        columnDefs.push({
            responsivePriority: 0,
            render: function(data, type, row) {
                var lock = '<span class="badge bg-warning"><i class="fas fa-lock fa-1x"></i></span>';
                var unlock = '<span class="badge bg-danger"><i class="fas fa-unlock fa-fx"></i></span>';
                if (row.protected > 0) {
                    return lock;
                }
                return unlock;
            },
            targets: colIndex.protected
        });
    }
    if ('isEnabled' in colIndex) {
        columnDefs.push({
            render: function(data, type, row) {
                var enabled = '<span class="badge bg-success"><i class="fas fa-circle-check"></i></span>';
                var disabled = '<span class="badge bg-danger"><i class="fas fa-circle-xmark"></i></span>';
                if (row.isEnabled > 0) {
                    return enabled;
                }
                return disabled;
            },
            targets: colIndex.isEnabled
        });
    }

    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: columns,
        rowId: 'id',
        columnDefs: columnDefs,
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='+Common.node+'&sub=list',
            type: 'post'
        }
    });
    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }
    $('.imagepath-input').on('keyup change blur focus focusout', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.\-]/g,'');
        this.setSelectionRange(start,end);
        e.preventDefault();
    });
    if ($('.imagepath-input').val().length <= 0) {
        $('.imagename-input').on('keyup change blur focus focusout', function(e) {
            $('.imagepath-input').val(this.value).trigger('change');
        });
    }
    $('.slider').slider();
    var image = $('#image'),
        path = $('#path');
    if (path.val().length == 0 || path.val() == null) {
        $(image).mirror(path, /[^\w+\/\.-]/g);
    }
    path.on('change', function(e) {
        var start = this.selectionStart,
            end = this.selectionEnd;
        this.value = this.value.replace(/[^\w+\/\.-]/g, '');
        this.setSelectionRange(start, end);
    });

    createnewModal.registerModal(Common.createModalShow, Common.createModalHide);
    createnewBtn.on('click', function(e) {
        e.preventDefault();
        createnewModal.modal('show');
    });
    createnewSendBtn.on('click', function(e) {
        e.preventDefault();
        createForm.processForm(function(err) {
            if (err) {
                return;
            }
            table.draw(false);
            createnewModal.modal('hide');
        });
    });
    deleteSelected.on('click', function() {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            // if we couldn't delete the items, enable the buttons
            // as the rows still exist and are selected.
            if (err) {
                disableButtons(false);
            }
        });
    });
})(jQuery);
