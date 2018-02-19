(function($) {
    var updatemacModal = $('#updatemacsmodal'),
        updatemacsbtn = $('#updatemacs'),
        updatemacsModalConfirmBtn = $('#updatemacsConfirm'),
        updatemacsModalCancelBtn = $('#updatemacsCancel'),
        deletemacModal = $('#deletemacsmodal'),
        deletemacsbtn = $('#deletemacs'),
        deletemacsModalConfirmBtn = $('#deletemacsConfirm'),
        deletemacsModalCancelBtn = $('#deletemacsCancel');
    // Update macs elements
    updatemacsbtn.on('click', function(e) {
        e.preventDefault();
        // Set the updates and delete buttons to disabled.
        $(this).prop('disabled', true);
        deletemacsbtn.prop('disabled', true);
        // Enable the modal buttons
        updatemacsModalConfirmBtn.prop('disabled', false);
        updatemacsModalCancelBtn.prop('disabled', false);
        // Display the modal
        updatemacModal.modal('show');
    });
    updatemacsModalCancelBtn.on('click', function(e) {
        e.preventDefault();
        // Set this button to unable to click.
        $(this).prop('disabled', true);
        // Set the main buttons back to clickable.
        deletemacsbtn.prop('disabled', false);
        updatemacsbtn.prop('disabled', false);
        // Hide the modal.
        updatemacModal.modal('hide');
    });
    updatemacsModalConfirmBtn.on('click', function(e) {
        e.preventDefault();
        // Set the udpate modal buttons to clickable
        updatemacsModalConfirmBtn.prop('disabled', false);
        updatemacsModalCancelBtn.prop('disabled', false);
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?node='+Common.node+'&sub='+Common.sub,
                type: 'post',
                data: {
                    update: '1'
                },
                dataType: 'json',
                beforeSend: function() {
                    // Set our modal buttons to disabled.
                    updatemacsModalConfirmBtn.prop('disabled', true);
                    updatemacsModalCancelBtn.prop('disabled', true);
                },
                success: function(data) {
                    $('#lookupcount').text(data.count);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                },
                complete: function() {
                    // Since we are complete, set buttons back to usable.
                    updatemacsbtn.prop('disabled', false);
                    deletemacsbtn.prop('disabled', false);
                    // Hide the modal as work is complete.
                    updatemacModal.modal('hide');
                }
            });
        });
    });
    // Delete macs elements
    deletemacsbtn.on('click', function(e) {
        e.preventDefault();
        // Set the update and delete buttons to disables.
        $(this).prop('disabled', true);
        updatemacsbtn.prop('disabled', true);
        // Enable the modal buttons
        deletemacsModalConfirmBtn.prop('disabled', false);
        deletemacsModalCancelBtn.prop('disabled', false);
        // Show the modal
        deletemacModal.modal('show');
    });
    deletemacsModalCancelBtn.on('click', function(e) {
        e.preventDefault();
        $(this).prop('disabled', true);
        // Set the main buttons back to clickable.
        deletemacsbtn.prop('disabled', false);
        updatemacsbtn.prop('disabled', false);
        // Hide the modal.
        deletemacModal.modal('hide');
    });
    deletemacsModalConfirmBtn.on('click', function(e) {
        e.preventDefault();
        // Set the delete modal buttons to clickable
        deletemacsModalConfirmBtn.prop('disabled', false);
        deletemacsModalCancelBtn.prop('disabled', false);
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?node='+Common.node+'&sub='+Common.sub,
                type: 'post',
                data: {
                    clear: '1'
                },
                dataType: 'json',
                beforeSend: function() {
                    // Set our modal buttons to disabled.
                    deletemacsModalConfirmBtn.prop('disabled', true);
                    deletemacsModalCancelBtn.prop('disabled', true);
                },
                success: function(data) {
                    $('#lookupcount').text(data.count);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                },
                complete: function() {
                    // Since we are complete, set buttons back to usable.
                    updatemacsbtn.prop('disabled', false);
                    deletemacsbtn.prop('disabled', false);
                    // Hide the modal as work is complete.
                    deletemacModal.modal('hide');
                }
            });
        });
    });
})(jQuery);
