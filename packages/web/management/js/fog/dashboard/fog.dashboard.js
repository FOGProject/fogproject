var GraphBandwidthMaxDataPoints,
    Graph30Day = $('#graph-30day'),
    Graph30DayData,
    bandwidthtime = $('#bandwidthtime').val(),
    Graph30DayOpts = {
        colors: ['#3c8dbc', '#0073b7'],
        grid: {
            hoverable: true,
            borderColor: '#f3f3f3',
            borderWidth: 1
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
            fill: false
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
            show: false
        }
    },
    startTime = new Date().getTime(),
    updateclientinterval,
    clientcounttime = 5000,
    updateClientCountData = [[0, 0]],
    updateClientCountOpts = {
        colors: ['#00c0ef', '#3c8dbc', '#0073b7'],
        series: {
            pie: {
                show: true,
                radius: 1,
                innerRadius: 0.5,
                label: {
                    show: true,
                    radius: 2/3,
                    formatter: function(label, series) {
                        return '<div style="color: #f3f3f3">' + series.percent + '%</div>';
                    },
                    threshold: 0.1
                }
            }
        },
        legend: {
            show: true,
            align: 'right',
            position: 'se',
            labelColor: '#666666',
            labelFormatter: function(label, series) {
                return '<div class="graph-legend">' + label + ': ' + series.datapoints.points[1] + '</div>';
            }
        }
    },
    updatediskusageinterval,
    diskusagetime = 120000,
    GraphDiskUsageData = [
        {
            label: 'Free',
            data: 0,
            color: '#3c8dbc'
        },
        {
            label: 'Used',
            data: 0,
            color: '#00c0ef'
        }
    ],
    GraphDiskUsageOpts = {
        colors: ['#3c8dbc', '#00c0ef'],
        series: {
            pie: {
                show: true,
                radius: 1,
                innerRadius: 0.5,
                label: {
                    show: true,
                    radius: 2/3,
                    formatter: function(label, series) {
                        return '<div style="color: #f3f3f3">' + Math.round(series.percent) + '%</div>';
                    },
                    threshold: 0.1
                }
            }
        },
        legend: {
            show: true,
            align: 'right',
            position: 'se',
            labelColor: '#666666',
            labelFormatter: function(label, series) {
                units = [' iB',' KiB',' MiB',' GiB',' TiB',' PiB',' EiB',' ZiB',' YiB'];
                for (var i = 0; series.data[0][1] >= 1024 && i < units.length - 1; i++) {
                    series.data[0][1] /= 1024;
                }
                return '<div class="graph-legend">' + label + ': ' + series.data[0][1].toFixed(2) + units[i] + '</div>';
            }
        }
    },
    GraphData = [],
    GraphBandwidthData = [],
    GraphBandwidthOpts = {
        colors: ['#3c8dbc', '#0073b7'],
        grid: {
            borderColor: '#f3f3f3',
            borderWidth: 1,
            tickColor: '#f3f3f3'
        },
        series: {
            shadowSize: 0,
            color: '#3c8dbc'
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
                return v + 'Mbps';
            },
            show: true
        },
        legend: {
            show: true,
            noColumns: 5,
            labelFormatter: function(label, series) {
                return label.replace('()', '');
            },
            position: 'nw'
        }
    },
    realtime = 'on',
    updatebandwidthinterval;

(function($) {

    // Bandwidth Start
    // =======================================================================
    // Transmit/Receive Filters
    $('.type-filters').on('click', function(e) {
        $('#graph-bandwidth-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        e.preventDefault();
    });

    // Timing Filters
    $('.time-filters').on('click', function(e) {
        $('#graph-bandwidth-time-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        GraphBandwidthMaxDataPoints = $(this).prop('rel');
        e.preventDefault();
    });

    // Realtime on/off
    $('#realtime .btn').on('click', function(e) {
        if ($(this).data('toggle') === 'on') {
            updateBandwidth();
            realtime = 'on';
        } else {
            clearTimeout(updatebandwidthinterval);
            realtime = 'off';
        }
    });

    $('#graph-bandwidth').css({
        height: '150px'
    });

    // Bandwidth Chart
    updateBandwidth();

    // Bandwidth End
    // =======================================================================

    // Disk Usage Start
    // =======================================================================
    $('#graph-diskusage').css({
        height: '150px',
        color: '#3f3f3f',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    $('.nodeid').on('change', function(e) {
        if (updatediskusageinterval) {
            clearTimeout(updatediskusageinterval);
        }
        updateDiskUsage();
        e.preventDefault();
    });
    updateDiskUsage();

    // Disk Usage End
    // =======================================================================

    // Client Count Start
    // =======================================================================
    $('#graph-activity').css({
        height: '150px',
        color: '#3f3f3f',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    updateClientCount();
    $('.activity-count').on('change', function(e) {
        if (updateclientinterval) {
            clearTimeout(updateclientinterval);
        }
        updateClientCount();
        e.preventDefault();
    });
    // Client Count End
    // =======================================================================

    // 30 day chart start
    // =======================================================================
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
            $('#graph-30day-tooltip').html(
                item.series.label + ': ' + y + ' on ' + date
            ).css({
                top: item.pageY + 5,
                left: item.pageX + 5
            }).fadeIn(200);
        } else {
            $('#graph-30day-tooltip').hide();
        }
    });
    update30Day();
    // 30 day chart end
    // =======================================================================
})(jQuery);

// Gets the 30 day graph
function update30Day() {
    Pace.ignore(function() {
        $.ajax({
            url: '../management/index.php?node=home',
            type: 'post',
            data: {
                sub: 'get30day'
            },
            dataType: 'json',
            success: function(data) {
                Graph30DayData = [
                    {
                        label: 'Computers Imaged',
                        data: data
                    }
                ];
                $.plot(Graph30Day, Graph30DayData, Graph30DayOpts);
                setTimeout(update30Day, bandwidthtime - ((new Date().getTime() - startTime) % bandwidthtime));
            }
        });
    });
}
// Gets the activity count information
function updateClientCount() {
    sgID = $('.activity-count').val();
    Pace.ignore(function() {
        $.ajax({
            url: '../management/index.php?node=home',
            type: 'post',
            data: {
                sub: 'clientcount',
                id: sgID
            },
            dataType: 'json',
            success: updateClientCountGraph,
            error: function(jqXHR, textStatus, errorThrown) {
            },
            complete: function() {
                $('#graph-activity').addClass('loaded');
            }
        });
    });
}
// Updates the actual graph data for client count.
function updateClientCountGraph(data) {
    updateClientCountData = [{
        label: 'Active',
        data: parseInt(data.ActivityActive)
    },
    {
        label: 'Queued',
        data: parseInt(data.ActivityQueued)
    },
    {
        label: 'Free',
        data: parseInt(data.ActivitySlots)
    }];
    $.plot('#graph-activity', updateClientCountData, updateClientCountOpts);
    if (updateclientinterval) {
        clearTimeout(updateclientinterval);
    }
    updateclientinterval = setTimeout(updateClientCount, clientcounttime - ((new Date().getTime() - startTime) % clientcounttime));
}
// Gets the Bandwidth information
function updateBandwidth() {
    urls = $('#bandwidthUrls').val().split(',');
    names = $('#nodeNames').val().split(',');
    Pace.ignore(function() {
        $.ajax({
            url: '../management/index.php?node=home',
            type: 'post',
            data: {
                sub: 'bandwidth',
                url: urls,
                names: names
            },
            dataType: 'json',
            success: updateBandwidthGraph,
            error: function(jqXHR, textStatus, errorThrown) {
                updateBandwidthGraph('');
            },
            complete: function() {
                $('#graph-bandwidth').addClass('loaded');
            }
        });
    });
}
// Displays the Bandwidth information and starts the loop over.
function updateBandwidthGraph(data) {
    function retval(d) {
        if (parseInt(d) < 1) {
            return 0;
        } else {
            var val = Math.round(d / 1024 * 8 / bandwidthtime, 2);
            if (val < 1) {
                val = 0;
            }
            return val;
        }
    }
    var d = new Date,
        tx = [],
        rx = [],
        tx_old = [],
        rx_old = [],
        nodes_count = data.length,
        Now = new Date().getTime() - (d.getTimezoneOffset() * 60000);
    for (i in data) {
        if (typeof GraphBandwidthData[i] == 'undefined') {
            GraphBandwidthData[i] = [];
            GraphBandwidthData[i].dev = [];
            GraphBandwidthData[i].tx = [];
            GraphBandwidthData[i].rx = [];
            GraphBandwidthData[i].txd = [];
            GraphBandwidthData[i].rxd = [];
        }
        while (GraphBandwidthData[i].tx.length >= GraphBandwidthMaxDataPoints) {
            GraphBandwidthData[i].tx.slice(1);
            GraphBandwidthData[i].txd.slice(1);
        }
        while (GraphBandwidthData[i].rx.length >= GraphBandwidthMaxDataPoints) {
            GraphBandwidthData[i].rx.slice(1);
            GraphBandwidthData[i].rxd.slice(1);
        }
        if (data[i] === null) {
            data[i] = {
                dev: 'Unknown',
                tx: 0,
                rx: 0
            };
        }
        if (data[i].dev === 'Unknown' && GraphBandwidthData[i].dev !== 'Unknown') {
            data[i].dev = GraphBandwidthData[i].dev;
        }
        txlength = GraphBandwidthData[i].txd.length - 1;
        rxlength = GraphBandwidthData[i].rxd.length - 1;
        lasttx = GraphBandwidthData[i].txd[txlength];
        lastrx = GraphBandwidthData[i].rxd[txlength];
        tx_rate = 0;
        rx_rate = 0;
        if (txlength > 0 && parseInt(lasttx) > 0) {
            tx_rate = retval(data[i].tx - lasttx);
        }
        if (rxlength > 0 && parseInt(lastrx) > 0) {
            rx_rate = retval(data[i].rx - lastrx);
        }
        GraphBandwidthData[i].txd.push(data[i].tx);
        GraphBandwidthData[i].rxd.push(data[i].rx);
        GraphBandwidthData[i].tx.push([Now, tx_rate]);
        GraphBandwidthData[i].rx.push([Now, rx_rate]);
        // Reset for next iteration
        GraphBandwidthData[i].dev = data[i].dev;
    }
    GraphData = [];
    for (i in GraphBandwidthData) {
        GraphData.push({
            label: i + ' (' + GraphBandwidthData[i].dev + ')',
            data: (
                $('#graph-bandwidth-filters-transmit').hasClass('active') ?
                GraphBandwidthData[i].tx :
                GraphBandwidthData[i].rx
            )
        });
    }
    $.plot($('#graph-bandwidth'), GraphData, GraphBandwidthOpts);
    if (updatebandwidthinterval || realtime !== 'on') {
        clearTimeout(updatebandwidthinterval);
    }
    if (realtime === 'on') {
        updatebandwidthinterval = setTimeout(updateBandwidth, bandwidthtime - ((new Date().getTime() - startTime) % bandwidthtime));
    }
}
// Updates the disk usage data.
function updateDiskUsage() {
    now = new Date().getTime();
    nodeid = $('.nodeid').val();
    Pace.ignore(function() {
        $.ajax({
            url: '../management/index.php?node=home',
            type: 'post',
            data: {
                sub: 'diskusage',
                id: nodeid
            },
            dataType: 'json',
            success: updateDiskUsageGraph,
            error: function(jqXHR, textStatus, errorThrown) {
            },
            complete: function() {
            }
        });
    });
    $('.nodeid option').each(function(e) {
        item = this;
        url = $(item).attr('urlcall');
        link = document.createElement('a');
        link.href = url;
        linkurl = link.pathname + link.search;
        Pace.ignore(function() {
            $.ajax({
                url: linkurl,
                data: {
                    url: url
                },
                success: function(data) {
                    var sel = $(item),
                        text = sel.text();
                    sel.text(text.replace(/\(.*\)/,'(' + data + ')'));
                },
                error: function(jqXHR, textStatus, errorThrown) {
                },
                complete: function() {
                }
            });
        });
    });
}
// Update the diskusage graph.
function updateDiskUsageGraph(data) {
    if (data.error) {
        $('#graph-diskusage').html(data.error ? data.error : 'No error, but no data was returned');
    }
    GraphDiskUsageData = [
        {
            label: 'Free',
            data: parseInt(data.free, 10)
        },
        {
            label: 'Used',
            data: parseInt(data.used, 10)
        }
    ];
    $.plot($('#graph-diskusage'), GraphDiskUsageData, GraphDiskUsageOpts);
    if (updatediskusageinterval) {
        clearTimeout(updatediskusageinterval);
    }
    updatediskusageinterval = setTimeout(updateDiskUsage, diskusagetime - ((new Date().getTime() - startTime) % diskusagetime));
}
