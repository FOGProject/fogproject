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

    // ----------------------------------------------------
    // BEARER API TOKEN CARD
    //
    // Every tab form in this file is wired the same way: suppress the native
    // submit, and drive the post from a button's click handler. There is no
    // generic binding that picks a form up automatically -- disableFormDefaults()
    // only stops the native submit -- so a card with no wiring here renders
    // fine and silently does nothing when clicked.
    var apiTokenForm = $('#user-apitoken-form'),
        apiTokenFormBtn = $('#apitoken-send'),
        issueTokenBtn = $('#issuetoken');

    apiTokenForm.on('submit', function(e) {
        e.preventDefault();
    });

    // Save: enable/disable and delete. Rides the tab form.
    apiTokenFormBtn.on('click', function(e) {
        apiTokenFormBtn.prop('disabled', true);
        apiTokenForm.processForm(function(err) {
            apiTokenFormBtn.prop('disabled', false);
            if (err) {
                return;
            }
            // The rows the save acted on are stale now -- a deleted token
            // still has a row and a toggled one still shows its old state.
            location.reload();
        });
    });

    // Issue: its own endpoint, because the plaintext comes back in this
    // response and is shown once. See the PHP side for why it cannot ride
    // the form.
    issueTokenBtn.on('click', function(e) {
        var name = $.trim($('#newtokenname').val() || '');

        // Checked here as well as on the server, which is the refusal that
        // counts. A token name is required and unique per account: it is
        // the only thing that tells one row from another when somebody is
        // deciding which credential to revoke. Bouncing an empty one off
        // the server just to render the same sentence is a round trip for
        // nothing.
        if ('' === name) {
            $.notifyFromAPI(
                {
                    error: 'Give the token a name saying what it is for.',
                    title: 'API Token Failed'
                },
                false
            );
            return;
        }

        issueTokenBtn.prop('disabled', true);
        $.apiCall(
            'post',
            '../management/index.php?node=user&sub=issueAPIToken&id=' + Common.id,
            { newtokenname: name },
            function(err, data) {
                issueTokenBtn.prop('disabled', false);
                if (err || !data || !data.token) {
                    return;
                }
                // Shown, not stored. Nothing here writes the token anywhere
                // that survives the page: no localStorage, no data attribute
                // that a later render reuses.
                $('#apitoken-fresh-value').val(data.token);
                $('#apitoken-fresh-header').text(data.token);
                $('#apitoken-fresh').removeClass('d-none');
                $('#newtokenname').val('');
                $('#apitoken-fresh-value').trigger('focus').trigger('select');
            }
        );
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

    // ---------------------------------------------------------------
    // SITE TAB
    // Single dropdown, so registerSelectTab rather than the grid wiring.
    // node:'site' adds the create-and-select button when the user holds
    // site.create; without it the tab is just the select and Update.
    $.registerSelectTab({
        slug: 'user-site',
        send: 'site-send',
        node: 'site'
    });
})(jQuery);
