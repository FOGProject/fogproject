(function($) {
    // The different update buttons.
    var displayManager = $('#displaymanager-update'),
        autoLogout = $('#autologout-update'),
        snapinClient = $('#snapinclient-update'),
        hostRegister = $('#hostregister-update'),
        hostnameChanger = $('#hostnamechanger-update'),
        printerManager = $('#printermanager-update'),
        taskReboot = $('#taskreboot-update'),
        userTracker = $('#usertracker-update'),
        powerManagement = $('#powermanagement-update'),
        // Forms
        displayManagerForm = $('#displaymanagerupdate-form'),
        autoLogoutForm = $('#autologoutupdate-form'),
        snapinClientForm = $('#snapinclientupdate-form'),
        hostRegisterForm = $('#hostregisterupdate-form'),
        hostnameChangerForm = $('#hostnamechangerupdate-form'),
        printerManagerForm = $('#printermanagerupdate-form'),
        taskRebootForm = $('#taskrebootupdate-form'),
        userTrackerForm = $('#usertrackerupdate-form'),
        powerManagementForm = $('#powermanagementupdate-form');

    // DISPLAY MANAGER
    displayManagerForm.on('submit', function(e) {
        e.preventDefault();
    });
    displayManager.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = displayManagerForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            displayManager.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // AUTO LOG OUT
    autoLogoutForm.on('submit', function(e) {
        e.preventDefault();
    });
    autoLogout.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = autoLogoutForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            autoLogout.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // SNAPINS
    snapinClientForm.on('submit', function(e) {
        e.preventDefault();
    });
    snapinClient.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = snapinClientForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            snapinClient.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // HOST REGISTER
    hostRegisterForm.on('submit', function(e) {
        e.preventDefault();
    });
    hostRegister.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = hostRegisterForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            hostRegister.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // HOSTNAME CHANGER
    hostnameChangerForm.on('submit', function(e) {
        e.preventDefault();
    });
    hostnameChanger.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = hostnameChangerForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            hostnameChanger.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // PRINTER MANAGER
    printerManagerForm.on('submit', function(e) {
        e.preventDefault();
    });
    printerManager.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = printerManagerForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            printerManager.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // TASK REBOOT
    taskRebootForm.on('submit', function(e) {
        e.preventDefault();
    });
    taskReboot.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = taskRebootForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            taskReboot.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // USER TRACKER
    userTrackerForm.on('click', function(e) {
        e.preventDefault();
    });
    userTracker.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = userTrackerForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            userTracker.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
    // POWER MANAGEMENT
    powerManagementForm.on('submit', function(e) {
        e.preventDefault();
    });
    powerManagement.on('click', function(e) {
        $(this).prop('disabled', true);
        var method = $(this).attr('method'),
            action = $(this).attr('action'),
            opts = powerManagementForm.serialize() + '&update';
        Common.apiCall(method, action, opts, function(err) {
            powerManagement.prop('disabled', false);
            if (err) {
                return;
            }
        });
    });
})(jQuery);
