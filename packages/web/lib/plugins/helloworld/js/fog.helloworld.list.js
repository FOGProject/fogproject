/**
 * Hello World list page (sub=list).
 *
 * Wires the DataTable (server-side, hits ?node=helloworld&sub=list), the
 * "create new" modal (submits to addPost via processForm), and bulk delete.
 * The `columns[].data` keys match the column data emitted by the list
 * endpoint: 'mainlink' (the linked name) and any model field by its friendly
 * name (here 'description'). Column order matches $headerData on the page.
 */
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
        disableButtons(selected.count() == 0);
    }

    disableButtons(true);
    var table = $('#dataTable').registerTable(onSelect, {
        order: [
            [0, 'asc']
        ],
        columns: [
            {data: 'mainlink'},
            {data: 'description'}
        ],
        rowId: 'id',
        columnDefs: [
            {
                responsivePriority: -1,
                targets: 0
            }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: '../management/index.php?node='
            + Common.node
            + '&sub=list',
            type: 'post'
        }
    });

    if (Common.search && Common.search.length > 0) {
        table.search(Common.search).draw();
    }

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
            table.rows({selected: true}).deselect();
            createnewModal.modal('hide');
        });
    });

    deleteSelected.on('click', function() {
        disableButtons(true);
        $.deleteSelected(table, function(err) {
            disableButtons(false);
            if (err) {
                return;
            }
            table.draw(false);
            table.rows({selected: true}).deselect();
        });
    });
})(jQuery);
