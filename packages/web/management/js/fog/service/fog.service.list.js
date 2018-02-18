(function($) {
    // The different update buttons.
    var displayManager = $('displaymanager-update'),
        autoLogout = $('autologout-update'),
        snapinClient = $('snapinclient-update'),
        hostRegister = $('hostregister-update'),
        hostnameChanger = $('hostnamechanger-update'),
        printerManager = $('printermanager-update'),
        taskReboot = $('taskreboot-update'),
        userTracker = $('usertracker-update'),
        powerManagement = $('powermanagement-update'),
        // Forms
        displayManagerForm = $('displaymanagerupdate-form'),
        autoLogoutForm = $('autologoutupdate-form'),
        snapinClientForm = $('snapinclientupdate-form'),
        hostRegisterForm = $('hostregisterupdate-form'),
        hostnameChangerForm = $('hostnamechangerupdate-form'),
        printerManagerForm = $('printermanagerupdate-form'),
        taskRebootForm = $('taskrebootupdate-form'),
        userTrackerForm = $('usertrackerupdate-form'),
        powerManagementForm = $('powermanagementupdate-form');

    displayManager.on('click', function(e) {
        e.preventDefault();
    });
})(jQuery);
