(function($) {
    // Each service section is an update button paired with its settings form.
    // Clicking the button posts the serialized form (plus &update) via the API
    // and re-enables itself when the call returns. Submit is already prevented
    // globally by disableFormDefaults() in fog.common.js, so no per-form bind
    // is needed here -- which also removes the old click/submit inconsistency
    // that left the user-tracker form wired differently from the rest.
    var services = [
        {btn: '#autologout-update', form: '#autologoutupdate-form'},
        {btn: '#snapinclient-update', form: '#snapinclientupdate-form'},
        {btn: '#software-update', form: '#softwareupdate-form'},
        {btn: '#hostregister-update', form: '#hostregisterupdate-form'},
        {btn: '#hostnamechanger-update', form: '#hostnamechangerupdate-form'},
        {btn: '#printermanager-update', form: '#printermanagerupdate-form'},
        {btn: '#taskreboot-update', form: '#taskrebootupdate-form'},
        {btn: '#usertracker-update', form: '#usertrackerupdate-form'},
        {btn: '#powermanagement-update', form: '#powermanagementupdate-form'}
    ];
    services.forEach(function(svc) {
        var btn = $(svc.btn),
            form = $(svc.form);
        btn.on('click', function() {
            btn.prop('disabled', true);
            var method = btn.attr('method'),
                action = btn.attr('action'),
                opts = form.serialize() + '&update';
            $.apiCall(method, action, opts, function(err) {
                btn.prop('disabled', false);
            });
        });
    });
})(jQuery);
