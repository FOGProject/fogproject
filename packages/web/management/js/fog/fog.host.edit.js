var LoginHistory = $('#login-history');
var LoginHistoryDate = $('#loghist-date');
var LoginHistoryData = new Array();
var Labels = new Array();
var LabelData = new Array();
var LoginData = new Array();
var LoginDateMin = new Array();
var LoginDateMax = new Array();
function UpdateLoginGraph() {
    $.ajax({
        url: location.href.replace('edit','hostlogins'),
        cache: false,
        type: 'POST',
        data: {dte: LoginHistoryDate.val()},
        dataType: 'json',
        success: UpdateLoginGraphPlot
    });
}
function UpdateLoginGraphPlot(data) {
    if (data == null) return;
    j =0;
    for (i in data) {
        LoginDateMin = new Date(new Date(data[i]['min'] * 1000).getTime() - new Date(data[i]['min'] * 1000).getTimezoneOffset() * 60000);
        LoginDateMax = new Date(new Date(data[i]['max'] * 1000).getTime() - new Date(data[i]['max'] * 1000).getTimezoneOffset() * 60000);
        LoginTime = new Date(new Date(data[i]['login'] * 1000).getTime() - new Date(data[i]['login'] * 1000).getTimezoneOffset() * 60000);
        LogoutTime = new Date(new Date(data[i]['logout'] * 1000).getTime() - new Date(data[i]['logout'] * 1000).getTimezoneOffset() * 60000);
        if (typeof(Labels) == 'undefined') {
            Labels = new Array();
            LabelData[i] = new Array();
            LoginData[i] = new Array();
        }
        if ($.inArray(data[i]['user'],Labels) > -1) {
            LoginData[i] = [LoginTime,$.inArray(data[i]['user'],Labels)+1,LogoutTime,data[i]['user']];
        } else {
            Labels.push(data[i]['user']);
            LabelData[i] = [j+1,data[i]['user']];
            LoginData[i] = [LoginTime,++j,LogoutTime,data[i]['user']];
        }
    }
    LoginHistoryData = [{label: 'Logged In Time',data:LoginData}];
    var LoginHistoryOpts = {
        colors: ['rgb(0,120,0)'],
        series: {
            gantt: {
                active:true,
                show:true,
                barHeight:.2
            }
        },
        xaxis: {
            min: LoginDateMin,
            max: LoginDateMax,
            tickSize: [2,'hour'],
            mode: 'time'
        },
        yaxis: {
            min: 0,
            max: LabelData.length + 1,
            ticks: LabelData,
        },
        grid: {
            hoverable: true,
            clickable: true,
        },
        legend: {
            position: "nw"
        }
    };
    $.plot(LoginHistory, LoginHistoryData, LoginHistoryOpts);
}
$(function() {
    $('#resetSecData').val('Reset Encryption Data');
    $('#resetSecData').click(function() {
        $('#resetSecDataBox').html('Are you sure you wish to reset this hosts encryption data?');
        $('#resetSecDataBox').dialog({
            resizable: false,
            modal: true,
            title: 'Clear Encryption',
            buttons: {
                'Yes': function() {
                    $.ajax({
                        url: '../management/index.php',
                        type: 'POST',
                        timeout: 1000,
                        data: {
                            sub: 'clearAES',
                            id: $_GET['id'],
                        },
                    });
                    $(this).dialog('close');
                },
                'No': function() {
                    $(this).dialog('close');
                }
            }
        });
    });
    UpdateLoginGraph();
    $('input:not(:hidden):checkbox[name="default"]').click(function() {
        $(this).each(function(e) {
            if (this.checked) this.checked = false;
            e.preventDefault();
        });
        $('input:checkbox').prop('checked',false);
    });
    $('.mac-manufactor').each(function() {
        input = $(this).parent().find('input');
        var mac = (input.size() ? input.val() : $(this).parent().find('.mac').html());
        $(this).load('../management/index.php?sub=getmacman&prefix='+mac);
    });
    removeMACField();
    MACUpdate();
    $('.add-mac').click(function(e) {
        e.preventDefault();
        $('#additionalMACsRow').show();
        $('#additionalMACsCell').append('<div><input class="additionalMAC" type="text" name="additionalMACs[]" />&nbsp;&nbsp;<i class="icon fa fa-minus-circle remove-mac hand" title="Remove MAC"></i><br/><span class="mac-manufactor"></span></div>');
        removeMACField();
        MACUpdate();
        HookTooltips();
    });
    if ($('.additionalMAC').size()) $('#additionalMACsRow').show();
    $('#groupMeShow').is(':checked') ? $('#groupNotInMe').show() : $('#groupNotInMe').hide();
    $('#printerNotInHost').is(':checked') ? $('#printerNotInHost').show() : $('#printerNotInHost').hide();
    $('#SnapinNotInHost').is(':checked') ? $('#snapinNotInHost').show() : $('#snapinNotInHost').hide();
    $('#groupMeShow').click(function() {
        $('#groupNotInMe').toggle();
    });
    $('.toggle-checkbox1').click(function() {
        $('input.toggle-group1:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkbox2').click(function() {
        $('input.toggle-group2:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('#hostPrinterShow').click(function() {
        $('#printerNotInHost').toggle();
    });
    $('#hostSnapinShow').click(function() {
        $('#snapinNotInHost').toggle();
    });
    $('.toggle-checkboxprint').click(function() {
        $('input.toggle-print:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkboxsnapin').click(function() {
        $('input.toggle-snapin:checkbox')
            .not(':hidden')
            .prop('checked',$(this).is(':checked'));
    });
});
