var JSONParseFunction = (typeof(JSON) != 'undefined' ? JSON.parse : eval);
var startTime = new Date().getTime();
// Disk Usage Graph Stuff
var GraphDiskUsage = $('#graph-diskusage');
var GraphDiskUsageAJAX;
var GraphDiskUsageNode = $('#diskusage-selector select');
var ClientCountGroup = $('#graph-activity-selector select');
var NodeID;
var GroupID;
var GraphDiskUsageData = [{
    label: 'Free',
    data: 0,
    color: '#cb4b4b'
},
    {
        label: 'Used',
        data: 0,
        color: '#45a73c'
    }];
var diskusagetime = 120000;
var bytes, units;
var GraphDiskUsageOpts = {
    colors: ['#45a73c', '#cb4b4b'],
    series: {
        pie: {
            show: true,
            radius: 1,
            innerRadius: 0.5,
            label: {
                show: true,
                radius: 2/3,
                formatter: function(label, series) {
                    units = [' iB',' KiB',' MiB',' GiB',' TiB', 'PiB', ' EiB', ' ZiB', ' YiB'];
                    for (i = 0; series.data[0][1] >= 1024 && i < units.length - 1; i++) {
                        series.data[0][1] /= 1024;
                    }
                    return '<div>' + series.data[0][1].toFixed(2)+units[i] + ' ' + label + '<br/>' + Math.round(series.percent) + '%</div>';
                },
                threshold: 0.1
            }
        }
    },
    legend: {
        show: false,
    }
};
// Bandwidth Variable/Option settings.
var GraphData = new Array();
var GraphBandwidth = $('#graph-bandwidth');
var GraphBandwidthFilterTransmit = $('#graph-bandwidth-filters-transmit');
var GraphBandwidthFilterTransmitActive = GraphBandwidthFilterTransmit.hasClass('active');
var GraphBandwidthData = new Array();
var GraphBandwidthdata = [];
var GraphBandwidthMaxDataPoints;
var bandwidthtime = $('#bandwidthtime').val();
var UpdateTimeout;
var GraphBandwidthOpts = {
    colors: ['#cb4b4b','#7386ad','#45a73c'],
    grid: {
        borderColor: '#f3f3f3',
        borderWidth: 1,
        tickColor: '#f3f3f3'
    },
    series: {
        shadowSize: 0,
        color : '#3c8dbc'
    },
    lines: {
        fill: true,
        colors: '#3c8dbc'
    },
    xaxis: {
        mode: 'time',
        show: true
    },
    yaxis: {
        min: 0,
        tickFormatter: function(v) {
            return v +' Mbps';
        },
        show: true
    },
    legend: {
        show: true,
        position: 'nw',
        noColumns: 5,
        labelFormatter: function(label, series) {
            return label;
        }
    }
};
var GraphBandwidthFilters = $('#graph-bandwidth-filters-transmit, #graph-bandwidth-filters-receive', '#graph-bandwidth-filters-type');
var GraphBandwidthTimeFilters = $('.time-filters', '#graph-bandwidth-filters-time');
var GraphBandwidthAJAX;
// 30 Day Data
var Graph30Day = $('#graph-30day');
var Graph30DayData;
var Graph30DayOpts = {
    colors: ['#7386ad'],
    grid: {
        hoverable: true,
        borderColor: '#f3f3f3',
        borderWidth: 1,
    },
    xaxis: {
        mode: 'time',
        show: true
    },
    yaxis: {
        tickFormatter: function(v) {
            return '<div class="tick r">'+v+'</div>';
        },
        min: 0,
        minTickSize: 1,
        show: true
    },
    lines: {
        fill: false,
        color: ['#3c8dbc', '#f56954']
    },
    series: {
        shadowSize: 0,
        lines: {
            show: true,
        },
        points: {
            show: true
        }
    },
    legend: {
        position: 'nw'
    }
};
// Client Count variables
var GraphClient = $('#graph-activity');
var UpdateClientCountData = [[0,0]];
var clientcounttime = 5000;
var UpdateClientCountOpts = {
    colors: ['#cb4b4b','#7386ad','#45a73c'],
    series: {
        pie: {
            show: true,
            radius: 1,
            innerRadius: 0.5,
            label: {
                show: true,
                radius: 2/3,
                formatter: function(label, series) {
                    return '<div>' + series.data[0][1] + ' ' + label + '<br/>' + Math.round(series.percent) + '%</div>';
                },
                threshold: 0.1
            }
        }
    },
    legend: {
        show: false
    }
};
var diskinterval = false;
var bandinterval = false;
var clientinterval = false;
(function($) {
    $('.sidebar-menu').tree()
    var now = new Date().getTime();
    // 30 Day History Graph
    $('<div class="tooltip-inner" id="graph-30day-tooltip"></div>').css({
        position: 'absolute',
        display: 'none',
        opaccity: 0.8
    }).appendTo('body');
    Graph30Day.css({
        height: '150px'
    }).bind('plothover', function(event, pos, item) {
        if (item) {
            var x = item.datapoint[0],
                y = item.datapoint[1];
            date = new Date(x);
            date = date.toDateString();
            $('#graph-30day-tooltip').html(item.series.label + ': ' + y + ' on ' + date).css({
                top: item.pageY + 5,
                left: item.pageX + 5
            }).fadeIn(200);
        } else {
            $('#graph-30day-tooltip').hide();
        }
    });
    GraphBandwidth.css({
        height: '150px'
    });
    GraphDiskUsage.css({
        height: '150px'
    });
    GraphClient.css({
        height: '150px'
    });
    Update30Day();
    // Diskusage Graph - Node select - Hook select box to load new data via AJAX
    // Start counters
    GraphDiskUsageUpdate();
    GraphBandwidthMaxDataPoints = $('.time-filters.active').prop('rel');
    UpdateBandwidth();
    UpdateClientCount();
    $('#diskusage-selector select').change(function(e) {
        if (diskinterval) {
            clearTimeout(diskinterval);
        }
        GraphDiskUsageUpdate();
        e.preventDefault();
    });
    $('#graph-activity-selector select').change(function(e) {
        if (clientinterval) {
            clearTimeout(clientinterval);
        }
        UpdateClientCount();
        e.preventDefault();
    });
    // Client Count starter.
    // Only start bandwidth once the page is fully loaded.
    // Bandwidth Graph - TX/RX Filter
    GraphBandwidthFilters.on('click', function(e) {
        // Blur -> add active class -> remove active class from old active item
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        // Update title
        $('#graph-bandwidth-title > span').eq(0).html($(this).html());
        GraphBandwidthFilterTransmitActive = (GraphBandwidthFilterTransmit.hasClass('active') ? true : false);
        // On click change
        // Prevent default action
        e.preventDefault();
    });
    GraphBandwidthTimeFilters.on('click', function(e) {
        // Blur -> add active class -> remove active class from oold active item
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        // Update title
        $('#graph-bandwidth-time > span').eq(0).html($(this).html());
        // Update max points.
        GraphBandwidthMaxDataPoints = this.rel;
        // Prevent default action.
        e.preventDefault();
    });
    // Remove loading spinners
    $('.graph').not(GraphBandwidth,GraphDiskUsage).addClass('loaded');
})(jQuery);
// 30 day function
function Update30Day() {
    $.ajax({
        url: '?node=home',
        type: 'POST',
        data: {
            sub: 'get30day'
        },
        dataType: 'json',
        success: function(gdata) {
            Graph30DayData = [
                {
                    label: 'Computers Imaged',
                    data: gdata
                }
            ];
            $.plot(Graph30Day, Graph30DayData, Graph30DayOpts);
            setTimeout(Update30Day, bandwidthtime - ((new Date().getTime() - startTime) % bandwidthtime));
        }
    });
}
// Disk Usage Functions
function GraphDiskUsageUpdate() {
    if (GraphDiskUsageAJAX) GraphDiskUsageAJAX.abort();
    now = new Date().getTime();
    NodeID = GraphDiskUsageNode.val();
    URL = $('[name="nodesel"] option:selected').attr('urlcall');
    GraphDiskUsageAJAX = $.ajax({
        url: '?node=home',
        type: 'POST',
        data: {
            sub: 'diskusage',
            id:NodeID
        },
        dataType: 'json',
        beforeSend: function() {
            GraphDiskUsage
                .html('')
                .removeClass('loaded')
                .parents('a')
                .prop('href','?node=hwinfo&id='+NodeID);
        },
        success: GraphDiskUsagePlots
    });
    $('[name="nodesel"] option').each(function(e) {
        URL = $(this).attr('urlcall');
        test = document.createElement('a');
        test.href = URL;
        test2 = test.pathname+test.search;
        $.ajax({
            context: this,
            url: test2,
            data: {
                url: URL
            },
            success: function(gdata) {
                var sel = $(this);
                var text = sel.text();
                sel.text(text.replace(/\(.*\)/,'('+gdata+')'));
            }
        });
    });
}
function GraphDiskUsagePlots(gdata) {
    if (gdata === null || typeof(gdata) === 'undefined') {
        gdata = '';
    } else if (typeof(gdata.error) != 'undefined') {
        GraphDiskUsage.html((gdata.error ? gdata.error : 'No error, but no data was returned')).addClass('loaded');
    };
    GraphDiskUsageData = [
        {
            label: 'Free',
            data: parseInt(gdata.free,10),
            color: '#45a73c'
        },
        {
            label: 'Used',
            data: parseInt(gdata.used,10),
            color: '#cb4b4b'
        }
    ];
    $.plot(GraphDiskUsage,GraphDiskUsageData,GraphDiskUsageOpts);
    GraphDiskUsage.addClass('loaded');
    if (diskinterval) {
        clearTimeout(diskinterval);
    }
    diskinterval = setTimeout(GraphDiskUsageUpdate, diskusagetime - ((new Date().getTime() - startTime) % diskusagetime));
}
// Bandwidth Functions
function UpdateBandwidth() {
    urls = $('#bandwidthUrls').val().split(',');
    names= $('#nodeNames').val().split(',');
    $.ajax({
        url: '?node=home',
        data: {
            sub: 'bandwidth',
            url: urls,
            names: names
        },
        dataType: 'json',
        success: UpdateBandwidthGraph,
        error: function(jqXHR, textStatus) {
            UpdateBandwidthGraph(null);
        },
        complete: function() {
            GraphBandwidth.addClass('loaded');
        }
    });
}
function UpdateBandwidthGraph(gdata) {
    if (gdata === null || typeof(gdata) === 'undefined') {
        gdata = '';
    }
    var realtime = 'on';
    function retval(d) {
        if (parseInt(d) < 1) {
            return 0;
        } else {
            val = Math.round(d / 1024 * 8 / bandwidthtime, 2);
            if (val < 1) {
                val = 0;
            }
            return val;
        }
    }
    var d = new Date();
    var tx = new Array();
    var rx = new Array();
    var tx_old = new Array();
    var rx_old = new Array();
    Now = new Date().getTime() - (d.getTimezoneOffset() * 60000);
    var nodes_count = gdata.length;
    for (i in gdata) {
        // Setup all the values we may need.
        if (typeof(GraphBandwidthData[i]) == 'undefined') {
            GraphBandwidthData[i] = new Array();
            GraphBandwidthData[i].dev = new Array();
            GraphBandwidthData[i].tx = new Array();
            GraphBandwidthData[i].rx = new Array();
            GraphBandwidthData[i].txd = new Array();
            GraphBandwidthData[i].rxd = new Array();
        }
        while (GraphBandwidthData[i].tx.length >= GraphBandwidthMaxDataPoints) {
            GraphBandwidthData[i].tx.slice(1);
            GraphBandwidthData[i].txd.slice(1);
        }
        while (GraphBandwidthData[i].rx.length >= GraphBandwidthMaxDataPoints) {
            GraphBandwidthData[i].rx.slice(1);
            GraphBandwidthData[i].rxd.slice(1);
        }
        if (gdata[i] === null) {
            gdata[i] = {
                dev: 'Unknown',
                tx: 0,
                rx:0
            };
        }
        if (gdata[i].dev === 'Unknown'
            && GraphBandwidthData[i].dev !== 'Unknown'
        ) {
            gdata[i].dev = GraphBandwidthData[i].dev;
        }
        txlength = GraphBandwidthData[i].txd.length - 1;
        rxlength = GraphBandwidthData[i].rxd.length - 1;
        lasttx = GraphBandwidthData[i].txd[txlength];
        lastrx = GraphBandwidthData[i].rxd[rxlength];
        tx_rate = 0;
        rx_rate = 0;
        if (txlength > 0 && parseInt(lasttx) > 0) {
            tx_rate = retval(gdata[i].tx - lasttx);
        }
        if (rxlength > 0 && parseInt(lastrx) > 0) {
            rx_rate = retval(gdata[i].rx - lastrx);
        }
        GraphBandwidthData[i].txd.push(gdata[i].tx);
        GraphBandwidthData[i].rxd.push(gdata[i].rx);
        GraphBandwidthData[i].tx.push([Now,tx_rate]);
        GraphBandwidthData[i].rx.push([Now,rx_rate]);
        // Reset the old and new values for the next iteration.
        GraphBandwidthData[i].dev = gdata[i].dev;
    }
    GraphData = new Array();
    for (i in GraphBandwidthData) GraphData.push({label: i+' ('+GraphBandwidthData[i].dev+')', data: (GraphBandwidthFilterTransmitActive ? GraphBandwidthData[i].tx : GraphBandwidthData[i].rx)});
    $.plot(GraphBandwidth,GraphData,GraphBandwidthOpts);
    if (bandinterval) {
        clearTimeout(bandinterval);
    }
    if (realtime === 'on') {
        bandinterval = setTimeout(UpdateBandwidth, bandwidthtime - ((new Date().getTime() - startTime) % bandwidthtime));
    }
    $('#realtime .btn').click(function() {
        if ($(this).data('toggle') === 'on') {
            realtime = 'on';
        } else {
            realtime = 'off';
        }
        UpdateBandwidth();
    });
}
// Client Count Functions.
function UpdateClientCount() {
    GroupID = ClientCountGroup.val();
    $.ajax({
        url: '?node=home',
        type: 'POST',
        data: {
            sub: 'clientcount',
            id: GroupID
        },
        dataType: 'json',
        success: UpdateClientCountPlot
    });
}
function UpdateClientCountPlot(gdata) {
    if (gdata === null || typeof(gdata) === 'undefined') {
        gdata = '';
    }
    UpdateClientCountData = [{
        label: 'Active',
        data: parseInt(gdata.ActivityActive)
    },
        {
            label: 'Queued',
            data: parseInt(gdata.ActivityQueued)
        },
        {
            label: 'Free',
            data: parseInt(gdata.ActivitySlots)
        }];
    $.plot(GraphClient,UpdateClientCountData,UpdateClientCountOpts);
    if (clientinterval) {
        clearTimeout(clientinterval);
    }
    clientinterval = setTimeout(UpdateClientCount, clientcounttime - ((new Date().getTime() - startTime) % clientcounttime));
}
