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
        Container: '#search-content,#active-tasks',
        CancelURL:  cancelurl
    };
    Container = $(Options.Container);
    if (!Container.length) alert('No Container element found: '+Options.Container);
    URL = Options.URL;
    CANCELURL = Options.CancelURL;
    if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
        Container.before('<p class="c"><input type="button" id="taskpause" value="Pause auto update" class="active"/></p>');
        Container.after('<p class="c"><input type="button" name="Cancel" id="taskcancel" value="Cancel selected tasks?"/><div id="canceltasks"></div></p>');
        pauseButton = $('#taskpause');
        pauseUpdate = pauseButton.parent('p');
        cancelButton = $('#taskcancel');
        cancelTasks = cancelButton.parent('p');
        ActiveTasksUpdate();
        pauseButton.click(pauseButtonPressed);
        cancelButton.click(buttonPress);
    }
});
function pauseButtonPressed(e) {
    if (!$(this).hasClass('active')) {
        $(this).addClass('active').val('Pause auto update');
        ActiveTasksUpdate();
    } else {
        if (AJAXTaskRunning) AJAXTaskRunning.abort();
        clearTimeout(AJAXTaskUpdate);
        $(this).removeClass().val('Continue auto update');
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
        beforeSend: function() {
            Loader.addClass('loading').fogStatusUpdate(_L['ACTIVE_TASKS_LOADING']).find('i').removeClass().addClass('fa fa-refresh fa-spin fa-fw');
        },
        success: function(response) {
            dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
            thead = $('thead',Container);
            tbody = $('tbody',Container);
            LastCount = dataLength;
            Loader.removeClass('loading').fogStatusUpdate(_L['ACTIVE_TASKS_FOUND'].replace(/%1/,LastCount).replace(/%2/,LastCount != 1 ? 's' : '')).find('i').removeClass().addClass('fa fa-exclamation-circle fa-fw');
            if (dataLength > 0) {
                buildHeaderRow(response.headerData,response.attributes,'th');
                thead = $('thead',Container);
                buildRow(response.data,response.templates,response.attributes,'td');
            }
            TableCheck();
            checkboxToggleSearchListPages();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            Loader.fogStatusUpdate(_L['ERROR_SEARCHING']+(errorThrown != '' ? errorThrown : '')).addClass('error').find('i').css({color:'red'});
        },
        complete: function() {
            AJAXTaskUpdate = setTimeout(ActiveTasksUpdate, ActiveTasksUpdateInterval - ((new Date().getTime() - startTime) % ActiveTasksUpdateInterval));
        }
    });
}
