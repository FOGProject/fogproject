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
    if (!Container.length) alert('No Container element found: '+Options.Container);
    URL = Options.URL;
    CANCELURL = Options.CancelURL;
    if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
        Container.before(
            '<div class="col-xs-9 text-center">'
            + '<div class="form-group">'
            + '<button type="button" class="btn btn-default activebtn" id="taskpause">'
            + '<i class="fa fa-pause"></i>'
            + '</button>'
            + '</div>'
            + '</div>'
        ).after(
            '<div class="col-xs-9 text-center">'
            + '<div class="form-group">'
            + '<button type="button" class="btn btn-default" id="taskcancel">'
            + 'Cancel selected tasks?'
            + '</button>'
            + '<div id="canceltasks"></div>'
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
                $.post(CANCELURL,{task: checkedIDs},function(data) {ActiveTasksUpdate();});
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
    AJAXTaskRunning = $.ajax({
        url: URL,
        dataType: 'json',
        success: function(response) {
            dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
            thead = $('thead',Container);
            tbody = $('tbody',Container);
            LastCount = dataLength;
            if (dataLength > 0) {
                buildHeaderRow(response.headerData,response.attributes,'th');
                thead = $('thead',Container);
                buildRow(response.data,response.templates,response.attributes,'td');
            }
            TableCheck();
            checkboxToggleSearchListPages();
        },
        complete: function() {
            AJAXTaskUpdate = setTimeout(ActiveTasksUpdate, ActiveTasksUpdateInterval - ((new Date().getTime() - startTime) % ActiveTasksUpdateInterval));
        }
    });
}
