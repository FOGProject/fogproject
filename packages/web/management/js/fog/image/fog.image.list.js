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
    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [2, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'protected'},
            {data: 'isEnabled'},
            {data: 'deployed'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0,
            },
            {
                responsivePriority: 0,
                render: function(data, type, row) {
                    var lock = '<span class="label label-warning"><i class="fa fa-lock fa-1x"></i></span>';
                    var unlock = '<span class="label label-danger"><i class="fa fa-unlock fa-fx"></i></span>';
                    if (row.protected > 0) {
                        return lock;
                    }
                    return unlock;
                },
                targets: 1
            },
            {
                render: function(data, type, row) {
                    var enabled = '<span class="label label-success"><i class="fa fa-check-circle"></i></span>';
                    var disabled = '<span class="label label-danger"><i class="fa fa-times-circle"></i></span>';
                    if (row.isEnabled > 0) {
                        return enabled;
                    }
                    return disabled;
                },
                targets: 2
            }
        ],
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
