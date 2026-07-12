(function($) {
    // ----------------------------------------------------
    // GENERAL TAB
    var originalDisplayName = $('.fog-user').text();

    var updateDisplayName = function(newName) {
        var e = $('.fog-user'),
            text = e.text();
        text = text.replace(originalDisplayName, newName)
        e.text(text);
    };

    $.registerGeneralTab({
        nameInputSel: '#user',
        formSel: '#user-general-form',
        trimName: true,
        onRenameSuccess: function(newName) {
            var anchorFields = getQueryParams($('.fog-user').attr('href')),
                foguser = {
                    node: anchorFields['node'],
                    sub: anchorFields['sub'],
                    id: anchorFields['id']
                };
            if (Common.id == foguser.id) {
                var newDisplay = $('#display').val().trim();
                if (!newDisplay) {
                    newDisplay = newName;
                }
                updateDisplayName(newDisplay);
                originalDisplayName = newDisplay;
            }
        }
    });

    // ----------------------------------------------------
    // PASSWORD TAB
    var passwordForm = $('#user-changepw-form'),
        passwordFormBtn = $('#changepw-send');

    passwordForm.on('submit',function(e) {
        e.preventDefault();
    });
    passwordFormBtn.on('click', function(e) {
        passwordFormBtn.prop('disabled', true);
        passwordForm.processForm(function(err) {
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
        apiForm.processForm(function(err) {
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

    // ----------------------------------------------------
    // ROLE ASSOCIATION TAB
    var userRolesTable = $.registerAssociationTab({
        slug: 'user-role',
        item: 'role',
        sub: 'getRolesList'
    });

    // ----------------------------------------------------
    // GROUP ASSOCIATION TAB
    var userGroupsTable = $.registerAssociationTab({
        slug: 'user-group',
        item: 'usergroup',
        sub: 'getGroupsList'
    });
})(jQuery);
