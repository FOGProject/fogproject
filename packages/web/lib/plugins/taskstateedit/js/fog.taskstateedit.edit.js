$(function() {
    // Any special functions that can be commonized for this element.
    var onCheckboxSelect = function(event) {
    };
    // ---------------------------------------------------------------
    // GENERAL TAB
    var originalName = $('#taskstate').val(),
        updateName = function(newName) {
            var e = $('#pageTitle'),
                text = e.text();
            text = text.replace(': ' + originalName, ': ' + newName);
            document.title = text;
            e.text(text);
        };

    var generalForm = $('#taskstate-general-form'),
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
            updateName($('#taskstate').val());
            originalName = $('#taskstate').val();
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
});
