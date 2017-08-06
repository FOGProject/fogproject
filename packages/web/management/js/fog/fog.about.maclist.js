(function($) {
    $('#macButtonDel').on('click', clearMacs);
    $('#macButtonUp').on('click', updateMacs);
})(jQuery);
/**
 * Clear macs function.
 */
function clearMacs(e) {
    e.preventDefault();
    BootstrapDialog.show({
        title: 'Delete MACs',
        message: 'Are you sure you wish to clear all mac address listings?',
        buttons: [{
            label: 'Yes',
            cssClass: 'btn-warning',
            action: function(dialogItself) {
                $('.macButtons').fadeOut('slow');
                dialogItself.close();
                location.href = '?node=about&sub=maclistPost&clear=1';
            }
        }, {
            label: 'No',
            cssClass: 'btn-info',
            action: function(dialogItself) {
                dialogItself.close();
            }
        }]
    });
}
/**
 * Update Macs function.
 */
function updateMacs(e) {
    e.preventDefault();
    BootstrapDialog.show({
        title: 'Update MACs',
        message: 'Are you sure you wish to update the mac address listings?',
        buttons: [{
            label: 'Yes',
            cssClass: 'btn-warning',
            action: function(dialogItself) {
                $('.macButtons').fadeOut('slow');
                dialogItself.close();
                location.href = '?node=about&sub=maclistPost&update=1';
            }
        }, {
            label: 'No',
            cssClass: 'btn-info',
            action: function(dialogItself) {
                dialogItself.close();
            }
        }]
    });
}
