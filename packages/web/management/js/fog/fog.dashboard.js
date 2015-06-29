var JSONParseFunction = (typeof(JSON) != 'undefined' ? JSON.parse : eval)
// Disk Usage Graph Stuff
var GraphDiskUsage = $('#graph-diskusage','#content-inner');
var GraphDiskUsageAJAX;
var GraphDiskUsageNode = $('#diskusage-selector select','#content-inner');
var NodeID;
var GraphDiskUsageData = [
{label: 'Free',data:0},
{label: 'Used',data:0}
];
var bytes, units;
var GraphDiskUsageOpts = {
colors: ['#45a73c','#cb4b4b'],
        series: {
pie: {
show: true,
      radius: 1
     }
        },
legend: {
show: true,
      align: 'right',
      position: 'se',
      labelColor: '#666',
      labelFormatter: function(label, series) {
          units = [' iB',' KiB',' MiB',' GiB',' TiB',' PiB',' EiB',' ZiB',' YiB'];
          for (i =0; series.data[0][1] >= 1024 && i < units.length -1; i++) {
              series.data[0][1] /= 1024;
          }
          return '<div style="font-size:8pt;padding2px;margin-top:16px;">'+label+': '+Math.round(series.percent)+'% <br />'+series.data[0][1].toFixed(2)+units[i]+'</div>';
      }
        }
};
// Bandwidth Variable/Option settings.
var GraphData = new Array();
var GraphBandwidth = $('#graph-bandwidth', '#content-inner');
var GraphBandwidthFilterTransmit = $('#graph-bandwidth-filters-transmit', '#graph-bandwidth-filters');
var GraphBandwidthFilterTransmitActive = GraphBandwidthFilterTransmit.hasClass('active');
var GraphBandwidthData = new Array();
var GraphBandwidthdata = [];
var GraphBandwidthMaxDataPoints = 120;
var UpdateTimeout;
var GraphBandwidthOpts = {
colors: ['#7386AD','#91a73c'],
        xaxis: {
mode: 'time'
        },
yaxis: {
min: 0,
     tickFormatter: function (v) { return v+' Mbps';}
       },
series: {
lines: {show: true},
       shadowSize: 0,
        },
legend: {
show: true,
      position: 'nw',
      noColumns: 5,
      labelFormatter: function(label, series) { return label; }
        },
};
var GraphBandwidthFilters = $('#graph-bandwidth-filters-transmit, #graph-bandwidth-filters-receive', '#graph-bandwidth-filters');
var GraphBandwidthAJAX;
// 30 Day Data
var Graph30Day = $('#graph-30day', '#content-inner');
var Graph30DayData;
var Graph30DayOpts = {
colors: ['#7386ad'],
        xaxis: {
mode: 'time',
        },
yaxis: {
tickFormatter: function (v) {
                   return '<div style="width: 35px; text-align: right; padding-right: 7px;">'+v+'</div>';
               },
min: 0,
     minTickSize: 1,
       },
series: {
lines: {
show: true,
      fill: true,
       },
points: {
show: true,
        }
        },
legend: {
position: 'nw'
        }
};
// Client Count variables
var GraphClient = $('#graph-activity','#content-inner');
var UpdateClientCountData = [[0,0]];
var UpdateClientCountOpts = {
colors: ['#cb4b4b','#7386ad','#45a73c'],
        series: {
pie: {
show: true,
      radius: 1,
      label: {
radius: .75,
        formatter: function(label, series) {
            return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
        },
background: {opacity: 0.5}
      }
     },
        },
legend: {
show: true,
      align: 'left',
      labelColor: '#666',
      labelFormatter: function(label, series) {
          return '<div style="font-size:8pt;padding:2px">'+label+': '+series.datapoints.points[1]+'</div>';
      }
        }
};
$(function() {
        // Diskusage Graph - Node select - Hook select box to load new data via AJAX
        GraphDiskUsageUpdate();
        UpdateClientCount();
        $('#diskusage-selector select').change(function() {
            GraphDiskUsageUpdate();
            UpdateClientCount();
            return false;
            });
        // Client Count starter.
        // Only start bandwidth once the page is fully loaded.
        // 30 Day History Graph
        if (typeof(Graph30dayData) != 'undefined') {
        Graph30DayData = [
        {label: 'Computers Imaged',data: JSONParseFunction(Graph30dayData)}
        ];
        }
        $.plot(Graph30Day,Graph30DayData,Graph30DayOpts);
        // Start counters
        UpdateBandwidth();
        // Bandwidth Graph - TX/RX Filter
        GraphBandwidthFilters.click(function() {
                // Blur -> add active class -> remove active class from old active item
                $(this).blur().addClass('active').siblings('a').removeClass('active');
                // Update title
                $('#graph-bandwidth-title > span').eq(0).html($(this).html());
                GraphBandwidthFilterTransmitActive = (GraphBandwidthFilterTransmit.hasClass('active') ? true : false);
                // On click change
                clearTimeout(UpdateTimeout);
                UpdateBandwidth();
                // Prevent default action
                return false;
                });
        // Bandwidth Graph - Time Filter
        $('#graph-bandwidth-filters div:eq(2) a').click(function() {
                // Blur -> add active class -> remove active class from old active item
                $(this).blur().addClass('active').siblings('a').removeClass('active');
                // Update title
                $('#graph-bandwidth-title > span').eq(1).html($(this).html());
                // Update max data points variable
                GraphBandwidthMaxDataPoints = $(this).attr('rel');
                // On click change
                clearTimeout(UpdateTimeout);
                UpdateBandwidth();
                // Prevent default action
                return false;
                });
        // Remove loading spinners
        $('.graph').not(GraphBandwidth,GraphDiskUsage).addClass('loaded');
});
// Disk Usage Functions
function GraphDiskUsageUpdate() {
    if (GraphDiskUsageAJAX) GraphDiskUsageAJAX.abort();
    NodeID = GraphDiskUsageNode.val();
    GraphDiskUsageAJAX = $.ajax({
url: '?node=home',
cache: false,
type: 'POST',
data: {
sub: 'diskusage',
id:NodeID
},
dataType: 'json',
beforeSend: function() {
GraphDiskUsage.html('').removeClass('loaded').parents('a').attr('href','?node=hwinfo&id='+NodeID);
},
success: GraphDiskUsagePlots,
complete: function() {
setTimeout(GraphDiskUsageUpdate,120000);
}
});
}
function GraphDiskUsagePlots(data) {
    if (typeof(data['error']) != 'undefined') {
        GraphDiskUsage.html((data['error'] ? data['error'] : 'No error, but no data was returned')).addClass('loaded');
        return;
    };
    GraphDiskUsageData = [
    {label: 'Free',data: parseInt(data['free'])},
    {label: 'Used',data: parseInt(data['used'])}
    ];
    $.plot(GraphDiskUsage,GraphDiskUsageData,GraphDiskUsageOpts);
    GraphDiskUsage.addClass('loaded');
}
// Bandwidth Functions
function UpdateBandwidth() {
    NodeID = GraphDiskUsageNode.val();
    $.ajax({
url: '?node=home',
cache: false,
type: 'POST',
data: {
sub: 'bandwidth',
},
dataType: 'json',
success: UpdateBandwidthGraph,
complete: function() {
UpdateTimeout = setTimeout(UpdateBandwidth,1000);
GraphBandwidth.addClass('loaded');
}
});
}
var new_rx = new Array();
var new_tx = new Array();
var rate_rx = new Array();
var rate_tx = new Array();
var old_rate_rx = new Array();
var old_rate_tx = new Array();
var old_rx = new Array();
var old_tx = new Array();
var real_rx = new Array();
var real_tx = new Array();
var byte_rx = new Array();
var byte_tx = new Array();
function UpdateBandwidthGraph(data) {
    var d = new Date();
    Now = new Date().getTime() - (d.getTimezoneOffset() * 60000);
    var seconds = 1;
    var range_rx = 512;
    var range_tx = 512;
    for (i in data) {
        if (typeof(rate_rx[i]) == "undefined"){
            rate_rx[i] = new Array();
            rate_tx[i] = new Array();
            new_rx[i] = new Array();
            new_tx[i] = new Array();
            old_rate_rx[i] = new Array();
            old_rate_tx[i] = new Array();
            real_rx[i] = new Array();
            real_tx[i] = new Array();
            rate_rx[i].push([Now,0]);
            rate_tx[i].push([Now,0]);
            byte_rx[i] = new Array();
            byte_tx[i] = new Array();
        }
        new_rx[i] = data[i]['rx'];
        new_tx[i] = data[i]['tx'];
        if (typeof(old_rx[i]) != "undefined" && typeof(old_rx[i]) != "undefined") {
            byte_rx[i] = new_rx[i] - old_rx[i];
            byte_tx[i] = new_tx[i] - old_tx[i];
            real_rx[i] = Math.round(byte_rx[i] / seconds / 1024 * 8 / 1000,2);
            real_tx[i] = Math.round(byte_rx[i] / seconds / 1024 * 8 / 1000,2);
            console.log(real_tx[i]);
            console.log(real_rx[i]);
            if (real_rx[i] > 0) rate_rx[i].push([Now,old_rate_rx[i]]);
            else old_rate_rx[i] = real_rx[i];
            if (real_tx[i] > 0) rate_tx[i].push([Now,old_rate_tx[i]]);
            else old_rate_tx[i] = real_tx[i];
        }
        while (rate_tx[i].length >= GraphBandwidthMaxDataPoints) {
            rate_rx[i].shift();
            rate_tx[i].shift();
        }
        old_rx[i] = new_rx[i];
        old_tx[i] = new_tx[i];
    }
    GraphData = new Array();
    for (i in rate_tx) {
        GraphData.push({label: i, data: (GraphBandwidthFilterTransmitActive ? rate_tx[i] : rate_rx[i])});
    }
    $.plot(GraphBandwidth,GraphData,GraphBandwidthOpts);
}
// Client Count Functions.
function UpdateClientCount() {
    NodeID = GraphDiskUsageNode.val();
    $.ajax({
url: '?node=home',
cache: false,
type: 'POST',
data: {
sub: 'clientcount',
id: NodeID
},
dataType: 'json',
success: function(data) {
if (data.length == 0) return;
UpdateClientCountPlot(data);
},
complete: function() {
setTimeout(UpdateClientCount,1000);
}
});
}
function UpdateClientCountPlot(data) {
    UpdateClientCountData = [
    {label:'Active',data:parseInt(data['ActivityActive'])},
    {label:'Queued',data:parseInt(data['ActivityQueued'])},
    {label:'Free',data:parseInt(data['ActivitySlots'])}
    ];
    $.plot(GraphClient,UpdateClientCountData,UpdateClientCountOpts);
    $('#ActivityActive').html(data['ActivityActive']);
    $('#ActivityQueued').html(data['ActivityQueued']);
    $('#ActivitySlots').html(data['ActivitySlots']);
}
