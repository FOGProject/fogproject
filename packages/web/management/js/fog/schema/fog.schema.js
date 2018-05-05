(function($) {
    var schemaUpdateForm = $('#schema-update-form'),
        schemaUpdateBtn = $('#schema-send'),
        dbrunningShow = $('.runningdb'),
        dbstoppedShow = $('#stoppeddb'),
        completedupdt = $('#completed'),
        dbcheckintrvl,
        dbcheckajxrun;

    // Prevent the screen from refreshing when the form is submitted.
    schemaUpdateForm.on('submit', function(e) {
        e.preventDefault();
    });

    // When the install/update button is clicked, perform actions.
    schemaUpdateBtn.on('click', function(e) {
        e.preventDefault();
        schemaUpdateBtn.prop('disabled', true);
        schemaUpdateForm.processForm(function(err) {
            schemaUpdateBtn.prop('disabled', false);
            if (err && err.status > 499) {
                return;
            }
            dbrunningShow.addClass('hidden');
            dbstoppedShow.addClass('hidden');
            completedupdt.removeClass('hidden');
        });
    });

    // Simply checks the database for status.
    // If it's up, it will present the form, it will not continuously check
    // status once an up status is detected.
    // If it's down, it will recheck every two seconds.
    var rundbcheck = function() {
        Pace.ignore(function() {
            dbcheckajxrun = $.ajax({
                url: '../status/dbrunning.php',
                dataType: 'json',
                beforeSend: function() {
                    if (dbcheckajxrun) {
                        dbcheckajxrun.abort();
                    }
                    if (dbcheckintrvl) {
                        clearTimeout(dbcheckintrvl);
                    }
                    dbcheckintrvl = setTimeout(rundbcheck, 2000);
                },
                success: function(data) {
                    if (data.running) {
                        dbrunningShow.removeClass('hidden');
                        dbstoppedShow.addClass('hidden');
                        if (dbcheckajxrun) {
                            dbcheckajxrun.abort();
                        }
                        if (dbcheckintrvl) {
                            clearTimeout(dbcheckintrvl);
                        }
                    } else {
                        dbrunningShow.addClass('hidden');
                        dbstoppedShow.removeClass('hidden');
                    }
                }
            });
        });
    };
    rundbcheck();
})(jQuery);
