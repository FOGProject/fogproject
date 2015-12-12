var sub = $_GET['sub'],
    ActiveTasksContainer = $('#active-tasks'),
    ActiveTasksLastCount = 0,
    wrapper = 'td',
    AJAXTaskUpdate,
    AJAXForceRequest,
    AJAXRunning,
    CANCELURL,
    URL,
    pauseButton,
    pauseUpdate,
    cancelButton,
    cancelTasks;
$(function() {
    $('.toggle-checkboxAction').click(function() {
        $('input.toggle-action[type="checkbox"]').
            not(':hidden').
            prop('checked',$(this).is(':checked'));
    });
    $('#action-box,#action-boxdel').submit(function() {
        taskIDArray = new Array();
        $('input.toggle-action:checked').each(function() {
            taskIDArray[taskIDArray.length] = this.value;
        });
        $('input[name="taskIDArray"]').val(taskIDArray.join(','));
    });
    if (typeof(sub) == 'undefined' || sub == 'active') {
        URL = window.location.href;
        CANCELURL = '?node=task&sub=canceltasks';
    } else if (sub.indexOf('active') != -1) {
        URL = window.location.href;
        CANCELURL = '?node=task&sub='+sub+'-post';
    }
    if (typeof(sub) == 'undefined' || sub.indexOf('active') != -1) {
        ActiveTasksContainer.before('<p class="c"><input type="button" id="taskpause" value="Pause auto update" class="active"/></p>');
        ActiveTasksContainer.after('<p class="c"><input type="button" name="Cancel" id="taskcancel" value="Cancel selected tasks?"/><div id="canceltasks"></div></p>');
        pauseButton = $('#taskpause');
        pauseUpdate = pauseButton.parent('p');
        cancelButton = $('#taskcancel');
        cancelTasks = cancelButton.parent('p');
        ActiveTasksUpdate();
        AJAXTaskUpdate = setInterval(ActiveTasksUpdate,ActiveTasksUpdateInterval);
        pauseButton.click(pauseButtonPressed);
        cancelButton.click(buttonPress);
    }
});
function pauseButtonPressed(e) {
    e.preventDefault();
    if (!$(this).hasClass('active')) {
        $(this).addClass('active').val('Pause auto update');
        ActiveTasksUpdate();
        AJAXTaskUpdate = setInterval(ActiveTasksUpdate,ActiveTasksUpdateInterval);
    } else {
        if (AJAXRunning) AJAXRunning.abort();
        clearInterval(AJAXTaskUpdate);
        $(this).removeClass().val('Continue auto update');
    }
}
function buttonPress() {
    checkedIDs = getChecked();
    if (checkedIDs.length > 0) {
        $('#canceltasks').html('Are you sure you wish to cancel these tasks?');
        $('#canceltasks').dialog({
            resizable: false,
            modal: true,
            title: 'Cancel tasks',
            buttons: {
                'Yes': function() {
                    $.post(
                        CANCELURL,
                        {task: checkedIDs},
                        function(data) {
                            ActiveTasksUpdate();
                        }
                    );
                    $(this).dialog('close');
                },
                'No': function() {
                    ActiveTasksUpdate();
                    $(this).dialog('close');
                }
            }
        });
    }
}
function ActiveTasksTableCheck() {
    if (ActiveTasksLastCount > 0) {
        if ($('.not-found').length > 0) $('.not-found').remove();
        ActiveTasksContainer.show();
        thead.show();
        pauseUpdate.show();
        cancelTasks.show();
    } else {
        if ($('.not-found').length === 0) ActiveTasksContainer.after('<p class="c not-found">'+_L['NO_ACTIVE_TASKS']+'</p>');
        ActiveTasksContainer.hide();
        thead.hide();
        pauseUpdate.hide();
        cancelTasks.hide();
    }
}
function ActiveTasksUpdate() {
    if (AJAXRunning) AJAXRunning.abort();
    AJAXRunning = $.ajax({
        type: 'POST',
        url: URL,
        cache: false,
        dataType: 'json',
        beforeSend: function() {
            Loader.addClass('loading').fogStatusUpdate(_L['ACTIVE_TASKS_LOADING']).find('i').removeClass('fa-exclamation-circle').addClass('fa-refresh fa-spin fa-fw');
        },
        success: function(response) {
            dataLength = response === null || response.data === null ? dataLength = 0 : response.data.length;
            table = $('table',ActiveTasksContainer);
            thead = $('thead',ActiveTasksContainer);
            tbody = $('tbody',ActiveTasksContainer);
            ActiveTasksLastCount = dataLength;
            Loader.removeClass('loading').fogStatusUpdate(_L['ACTIVE_TASKS_FOUND'].replace(/%1/,ActiveTasksLastCount).replace(/%2/,ActiveTasksLastCount != 1 ? 's' : '')).find('i').removeClass('fa-refresh fa-spin fa-fw').addClass('fa-exclamation-circle');
            checkedIDs = getChecked();
            ActiveTasksTableCheck();
            if (dataLength > 0) buildRow(response.data,response.templates,response.attributes);
        },
        error: function() {
            Loader.fogStatusUpdate(_L['ACTIVE_TASKS_UPDATE_FAILED']).addClass('error').find('i').css({color:'red'});
        }
    });
}
function buildRow(data,templates,attributes) {
    tbody.empty();
    var rows = '';
    for (var h in data) {
        var row = '<tr id="task-'+data[h].id+'"'+((typeof(sub) == 'undefined' || sub == 'active') && data[h].percent > 0 ? ' class="with-progress"' : '')+'>';
        for (var i in templates) {
            var attributes = [];
            for (var j in attributes) {
                attributes[attributes.length] = j+'="'+attributes[i][j]+'"';
            }
            row += '<'+wrapper+(attributes.length ? ' '+attributes.join(' ') : '')+'>'+templates[i]+'</'+wrapper+'>';
        }
        if ((typeof(sub) == 'undefined' || sub == 'active') && data[h].percent > 0 && data[h].percent < 100) {
            colspan = templates.length;
            row += '<tr id="progress-${host_id}" class="with-progress"><td colspan="'+colspan+'" class="task-progress-td min"><div class="task-progress-fill min" style="width: ${width}px"></div><div class="task-progress min"><ul><li>${elapsed}/${remains}</li><li>${percent}%</li><li>${copied} of ${total} (${bpm}/min)</li></ul></div></td></tr>';
        }
        for (var k in data[h]) {
            row = row.replace(new RegExp('\\$\\{'+k+'\\}','g'),(k == 'percent' ? parseInt(data[h][k]) : data[h][k]));
        }
        row = row.replace(new RegExp('\\${\\{\w+\\}','g'),'');
        rows += row+'</tr>';
        row = '';
    }
    tbody.append(rows);
    setChecked(checkedIDs);
    HookTooltips();
    if (typeof(sub) == 'undefined' || sub == 'active') {
        showForceButton();
        showProgressBar();
    }
    $('table').trigger('update');
}
function forceClick(e) {
    $(this).unbind('click').click(function(evt) {
        evt.preventDefault();
    });
    if (AJAXForceRequest) AJAXForceRequest.abort();
    AJAXForceRequest = $.ajax({
        type: 'POST',
        url: $(this).attr('href'),
        beforeSend: function() {
            $(this).removeClass().addClass('fa fa-refresh fa-spin fa-fw icon');
        },
        success: function(data) {
            if (typeof(data) == 'undefined' || data === null) return;
            $(this).removeClass().addClass('fa fa-angle-double-right fa-fw icon');
        },
        error: function() {
            $(this).removeClass().addClass('fa fa-bolt fa-fw icon');
        }
    });
    e.preventDefault();
}
function showForceButton() {
    $('.icon-forced').addClass('fa fa-angle-double-right fa-1x icon');
    $('.icon-force').addClass('fa fa-bolt fa-fw hand')
    $('.icon-force').unbind('click').click(forceClick);
}
function showProgressBar() {
    $('.with-progress').hover(function() {
        var id = $(this).prop('id').replace(/^progress[-_]/,'');
        var progress = $('#progress-'+id);
        progress.show();
        progress.find('.min').removeClass('min').addClass('no-min').end().find('ul').show();
    }, function() {
        var id = $(this).prop('id').replace(/^progress[-_]/,'');
        var progress = $('#progress-'+id);
        progress.find('.no-min').removeClass('no-min').addClass('min').end().find('ul').hide();
    });
}
