(function($) {
    var scheduleType = $('input[name="scheduleType"]'),
        groupDeployForm = $('#group-deploy-form'),
        debugCheck = $('#checkdebug'),
        specialCrons = $('#specialCrons', groupDeployForm),
        minutes = $('#cronMin', groupDeployForm),
        hours = $('#cronHour', groupDeployForm),
        dom = $('#cronDom', groupDeployForm),
        month = $('#cronMonth', groupDeployForm),
        dow = $('#cronDow', groupDeployForm);

    specialCrons.on('change focus focusout', function(e) {
        e.preventDefault();
        switch (this.value) {
            case 'hourly':
                minutes.val('0');
                hours.val('*');
                dom.val('*');
                month.val('*');
                dow.val('*');
                break;
            case 'daily':
                minutes.val('0');
                hours.val('0');
                dom.val('*');
                month.val('*');
                dow.val('*');
                break;
            case 'weekly':
                minutes.val('0');
                hours.val('0');
                dom.val('*');
                month.val('*');
                dow.val('0');
                break;
            case 'monthly':
                minutes.val('0');
                hours.val('0');
                dom.val('1');
                month.val('*');
                dow.val('*');
                break;
            case 'yearly':
                minutes.val('0');
                hours.val('0');
                dom.val('1');
                month.val('1');
                dow.val('*');
                break;
            default:
                minutes.val('');
                hours.val('');
                dom.val('');
                month.val('');
                dow.val('');
                break;
        }
    });

    debugCheck.on('ifChecked', function(e) {
        e.preventDefault();
        $('.hideFromDebug,.delayedinput,.croninput').addClass('hidden');
        $('.instant').iCheck('check');
    }).on('ifUnchecked', function(e) {
        e.preventDefault();
        $('.hideFromDebug').removeClass('hidden');
    });

    scheduleType.on('ifClicked', function(e) {
        e.preventDefault();
        switch (this.value) {
            case 'instant':
                $('.delayedinput,.croninput').addClass('hidden');
                break;
            case 'single':
                $('.delayedinput').removeClass('hidden');
                $('.croninput').addClass('hidden');
                break;
            case 'cron':
                $('.delayedinput').addClass('hidden');
                $('.croninput').removeClass('hidden');
                break;
        }
    });
})(jQuery);
