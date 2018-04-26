(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#user').val(),
        originalDisplayName = $('.fog-user').text();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        document.title = text;
        e.text(text);
    };

    var updateDisplayName = function(newName) {
        var e = $('.fog-user'),
            text = e.text();
        text = text.replace(originalDisplayName, newName)
        e.text(text);
    };

    var generalForm = $('#user-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete'),
        generalDeleteModal = $('#deleteModal'),
        generalDeleteModalConfirm = $('#confirmDeleteModal'),
        generalDeleteModalCancel = $('#closeDeleteModal');

    generalForm.on('submit',function(e) {
        e.preventDefault();
    });
    generalFormBtn.on('click', function(e) {
        generalFormBtn.prop('disabled', true);
        generalDeleteBtn.prop('disabled', true);
        Common.processForm(generalForm, function(err) {
            generalFormBtn.prop('disabled', false);
            generalDeleteBtn.prop('disabled', false);
            if (err) {
                return;
            }
            newName = $('#user').val().trim();
            anchorFields = getQueryParams($('.fog-user').attr('href'));
            console.log(anchorFields);
            foguser = {
                node: anchorFields['node'],
                sub: anchorFields['sub'],
                id: anchorFields['id']
            };
            if (Common.id == foguser.id) {
                newDisplay = $('#display').val().trim();
                if (!newDisplay) {
                    newDisplay = newName;
                }
                updateDisplayName(newDisplay);
                originalDisplayName = newDisplay;
            }
            updateName(newName);
            originalName = newName;
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
    $("#user").inputmask({"mask": Common.masks.username, "placeholder": ""});


    // ----------------------------------------------------
    // PASSWORD TAB
    var passwordForm = $('#user-changepw-form'),
        passwordFormBtn = $('#changepw-send');

    passwordForm.on('submit',function(e) {
        e.preventDefault();
    });
    passwordFormBtn.on('click', function(e) {
        passwordFormBtn.prop('disabled', true);
        Common.processForm(passwordForm, function(err) {
            passwordFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
            $('.password1-input, .password2-input').val('');
        });
    });

    // ----------------------------------------------------
    // API TAB
    var apiForm = $('#user-api-form'),
        apiFormBtn = $('#api-send');

    apiForm.on('submit',function(e) {
        e.preventDefault();
    });
    apiFormBtn.on('click', function(e) {
        apiFormBtn.prop('disabled', true);
        Common.processForm(apiForm, function(err) {
            apiFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    $('.resettoken').on('click', function(e) {
        e.preventDefault();
        Pace.ignore(function() {
            $.ajax({
                url: '../status/newtoken.php',
                dataType: 'json',
                success: function(data) {
                    $('.token').val(data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                }
            });
        });
    });
})(jQuery);
