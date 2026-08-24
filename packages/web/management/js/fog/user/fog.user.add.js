(function($) {
    $('#user-create-form').wireCreateForm({mode: 'clear'});

    // "API Only Account" and the password fields are mutually exclusive: an
    // account that can never sign in has no use for one, and being asked for
    // it anyway reads as though the flag does not really mean what it says.
    //
    // Three things happen together, and all three are needed:
    //
    //   prop('required', false) -- validateForm() reads prop('required'), so
    //     without this the form refuses to submit against fields nobody can
    //     see, with the error rendered off-screen.
    //   prop('disabled', true)  -- a disabled input is left out of FormData
    //     entirely, so nothing is posted and addPost() generates an unusable
    //     random password instead. Merely clearing the value would post '',
    //     which User::set() bcrypts into a valid hash OF the empty string.
    //   hiding the row          -- the answer to "why am I being asked for
    //     this", which is the whole point.
    //
    // Delegated off document rather than bound to #apionly directly so the
    // same wiring covers the create-and-associate modal, whose markup is
    // fetched after this file has run.
    $(document).on('change', '#apionly', function() {
        var apiOnly = $(this).is(':checked'),
            fields = $('#password, #password_name');

        fields.each(function() {
            var field = $(this),
                // '.row.mb-3' is what renderAddForm() actually emits around
                // a label/field pair -- NOT a form-group. validateForm()
                // itself looks for 'div[class^="form-group"]' here and gets
                // an empty set on every modern page, which it survives only
                // because it has a fallback. Copying that selector hid
                // nothing at all, which is what deploying this found.
                row = field.closest('.row.mb-3');
            field
                .prop('required', !apiOnly)
                .prop('disabled', apiOnly);
            if (apiOnly) {
                // Cleared as well as disabled: re-ticking the box must not
                // resurrect a half-typed password the user cannot see.
                field.val('');
                // The field may already be marked invalid from an earlier
                // failed submit. Leaving that behind would show an error
                // against a row nobody can see. validateForm() inserts the
                // message directly AFTER the input, not inside the row.
                field.removeClass('is-invalid');
                field.next('span.invalid-feedback').remove();
            }
            row.toggleClass('d-none', apiOnly);
        });

        $('#apionly-password-note').toggleClass('d-none', !apiOnly);
    }).trigger('change');
})(jQuery);
