(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalName = $('#name').val();

    var updateName = function(newName) {
        var e = $('#pageTitle'),
            text = e.text();
        text = text.replace(": " + originalName, ": " + newName);
        e.text(text);
    };

    var generalForm = $('#user-general-form'),
        generalFormBtn = $('#general-send'),
        generalDeleteBtn = $('#general-delete');

    generalForm.submit(function(e) {
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
            updateName($('#name').val());
            originalName = $('#name').val();
        });
    });


    // ----------------------------------------------------
    // PASSWORD TAB
    var passwordForm = $('#user-changepw-form'),
        passwordFormBtn = $('#changepw-send');

    passwordForm.submit(function(e) {
        e.preventDefault();
    });
    passwordFormBtn.on('click', function(e) {
        passwordFormBtn.prop('disabled', true);
        Common.processForm(passwordForm, function(err) {
            passwordFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });

    // ----------------------------------------------------
    // API TAB
    var apiForm = $('#user-api-form'),
        apiFormBtn = $('#api-send');

    apiForm.submit(function(e) {
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
