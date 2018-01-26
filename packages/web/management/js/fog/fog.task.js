var CANCELURL,
    URL,
    pauseButton,
    pauseUpdate,
    cancelButton,
    cancelTasks,
    AJAXTaskUpdate,
    AJAXTaskRunning,
    ActiveTasksUpdateInterval = 5000;
(function($) {
    if (typeof sub == 'undefined') {
        var newurl = location.protocol
        + '//'
        + location.host
        + location.pathname
        + '?node='
        + node
        + '&sub=active';
        if (history.pushState) {
            history.pushState(
                {
                    path: newurl
                },
                '',
                newurl
            );
        }
        location.replace(location.href+'&sub=active');
        sub = 'active';
    }
    $('.search-input').on('keyup change focus', function(e) {
        clearTimeout(AJAXTaskRunning);
        $('#taskpause,#taskcancel').remove();
    });
    var cancelurl = (sub.indexOf('active') != -1 ? location.href : ''),
        Options = {
            URL: location.href,
            CancelURL:  cancelurl
        };
    URL = Options.URL;
    CANCELURL = Options.CancelURL;
    if (!Container.length) {
        return this;
    }
    callme = 'hide';
    if (!Container.hasClass('noresults')) {
        callme = 'show';
    }
    Container[callme]();
    if (typeof sub == 'undefined' || sub.indexOf('active') != -1) {
        Container.before(
            '<div class="taskbuttons text-center">'
            + '<div class="col-xs-offset-4 col-xs-4">'
            + '<div class="form-group">'
            + '<button type="button" class="btn btn-info btn-block activebtn" id="taskpause">'
            + '<i class="fa fa-pause"></i>'
            + '</button>'
            + '</div>'
            + '</div>'
            + '</div>'
        ).after(
            '<div class="taskbuttons text-center">'
            + '<div class="col-xs-offset-4 col-xs-4">'
            + '<div class="form-group">'
            + '<button type="button" class="btn btn-warning btn-block" id="taskcancel">'
            + 'Cancel selected tasks?'
            + '</button>'
            + '<div id="canceltasks"></div>'
            + '</div>'
            + '</div>'
            + '</div>'
        );
        pauseButton = $('#taskpause');
        pauseUpdate = pauseButton.parent('p');
        cancelButton = $('#taskcancel');
        cancelTasks = cancelButton.parent('p');
        ActiveTasksUpdate();
        pauseButton.on('click', pauseButtonPressed);
        cancelButton.on('click', buttonPress);
        $('.search-input').on('focus', function() {
            if (AJAXTaskRunning) AJAXTaskRunning.abort();
            clearTimeout(AJAXTaskUpdate);
            pauseButton.removeClass('activebtn').find('i.fa-pause').removeClass('fa-pause').addClass('fa-play');
        }).on('focusout', function() {
            if (this.value.length < 1) {
                pauseButton.addClass('activebtn').find('i.fa-play').removeClass('fa-play').addClass('fa-pause');
                ActiveTasksUpdate();
            }
        });
    }
})(jQuery);
function pauseButtonPressed(e) {
    if (!$(this).hasClass('activebtn')) {
        $(this).addClass('activebtn').find('i.fa-play').removeClass('fa-play').addClass('fa-pause');
        ActiveTasksUpdate();
    } else {
        if (AJAXTaskRunning) AJAXTaskRunning.abort();
        clearTimeout(AJAXTaskUpdate);
        $(this).removeClass('activebtn').find('i.fa-pause').removeClass('fa-pause').addClass('fa-play');
    }
    e.preventDefault();
}
function buttonPress(e) {
    e.preventDefault();
    checkedIDs = getChecked();
    if (checkedIDs.length < 1) {
        return;
    }
    BootstrapDialog.show({
        title: 'Cancel Tasks',
        message: 'Are you sure you wish to cancel the selected tasks?',
        buttons: [{
            label: 'Yes',
            cssClass: 'btn-warning',
            action: function(dialogItself) {
                $.post(
                    CANCELURL,
                    {
                        task: checkedIDs
                    },
                    function(gdata) {
                        ActiveTasksUpdate();
                    }
                );
                Container.find('input[value="'+checkedIDs.join('"], input[value="')+'"]').parents('tr').remove();
                Container.trigger('updateAll');
                dialogItself.close();
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
function ActiveTasksUpdate() {
    if (AJAXTaskRunning) AJAXTaskRunning.abort();
    AJAXTaskRunning = $.ajax({
        type: 'GET',
        cache: false,
        url: URL,
        dataType: 'json',
        success: function(response) {
            dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
            thead = $('thead', Container);
            tbody = $('tbody', Container);
            LastCount = dataLength;
            buildHeaderRow(
                response.headerData,
                response.attributes
            );
            buildRow(
                response.data,
                response.templates,
                response.attributes
            );
            TableCheck();
            AJAXTaskRunning = null;
            checkboxToggleSearchListPages();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            AJAXTaskRunning = null;
        },
        complete: function() {
            AJAXTaskUpdate = setTimeout(
                ActiveTasksUpdate,
                ActiveTasksUpdateInterval - (
                    (
                        new Date().getTime() - startTime
                    ) % ActiveTasksUpdateInterval
                )
            );
        }
    });
}
