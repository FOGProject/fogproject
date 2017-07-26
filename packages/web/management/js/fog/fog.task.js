var CANCELURL,
    URL,
    pauseButton,
    pauseUpdate,
    cancelButton,
    cancelTasks,
    AJAXTaskUpdate,
    AJAXTaskRunning;
$(function() {
    if (typeof(sub) == 'undefined') {
        window.location.replace(location.href+'&sub=active');
        sub = 'active';
    }
    var cancelurl = (sub.indexOf('active') != -1 ? location.href : '');
    var Options = {
        URL: location.href,
        Container: '.table-holder',
        CancelURL:  cancelurl
    };
    Container = $(Options.Container);
    URL = Options.URL;
    CANCELURL = Options.CancelURL;
    if (!Container.length) return this;
    callme = 'hide';
    if (!$('.table').hasClass('noresults')) {
        callme = 'show';
    }
    Container.each(function(e) {
        if ($(this).hasClass('.noresults')) {
            $(this).hide();
        } else {
            $(this).show().fogTableInfo().trigger('updateAll');
        }
        if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
            $(this).before(
                '<div class="text-center">'
                + '<div class="col-xs-offset-4 col-xs-4">'
                + '<div class="form-group">'
                + '<button type="button" class="btn btn-info btn-block activebtn" id="taskpause">'
                + '<i class="fa fa-pause"></i>'
                + '</button>'
                + '</div>'
                + '</div>'
                + '</div>'
            ).after(
                '<div class="text-center">'
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
            pauseButton.click(pauseButtonPressed);
            cancelButton.click(buttonPress);
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
    });
});
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
function buttonPress() {
    checkedIDs = getChecked();
    if (checkedIDs.length < 1) return;
    $('#canceltasks').html('Are you sure you wish to cancel these tasks?');
    $('#canceltasks').dialog({
        resizable: false,
        modal: true,
        title: 'Cancel tasks',
        buttons: {
            'Yes': function() {
                $.post(CANCELURL,{task: checkedIDs},function(gdata) {ActiveTasksUpdate();});
                $(this).dialog('close');
            },
            'No': function() {
                ActiveTasksUpdate();
                $(this).dialog('close');
            }
        }
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
            if (dataLength > 0) {
                buildHeaderRow(
                    response.headerData,
                    response.attributes,
                    'th'
                );
                thead = $('thead', Container);
                buildRow(
                    response.data,
                    response.templates,
                    response.attributes,
                    'td'
                );

            }
            TableCheck();
            Container.fogTableInfo().trigger('updateAll');
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
