// 30 day
var GraphBandwidthMaxDataPoints,
    Graph30Day = $('#graph-30day'),
    Graph30DayData,
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
    // Bandwidth
    bandwidthinterval,
    realtime = 'on',
    GraphBandwidthMaxDataPoints,
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
            colors: ['#3c8dbc', '#0073b7']
        },
        lines: {
            fill: true,
            colors: ['#3c8dbc', '#0073b7']
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
    // Client Count
    updateClientCountData = [[0,0]],
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
    clientinterval,
    // Disk Usage
    GraphDiskUsageOpts = {
        colors: ['#00c0ef', '#3c8dbc'],
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
                units = [' iB', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', 'YiB'];
                for (var i = 0; series.data[0][1] >= 1024 && i < units.length - 1; i++) {
                    series.data[0][1] /= 1024;
                }
                return '<div class="graph-legend">' + label + ': ' + series.data[0][1].toFixed(2) + units[i] + '</div>';
            }
        }
    },
    GraphDiskUsageData = [],
    diskusageinterval,
    startTime = new Date().getTime();
(function($) {
    // 30 day chart, updates every 5 minutes
    var update30day = function() {
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
                    $.plot(
                        Graph30Day,
                        Graph30DayData,
                        Graph30DayOpts
                    );
                    setTimeout(
                        update30day,
                        300000 - (
                            (
                                new Date().getTime() - startTime
                            ) % 300000
                        )
                    );
                }
            });
        });
    };
    $('<div class="tooltip-inner" id="graph-30day-tooltip"></div>').css({
        position: 'absolute',
        display: 'none',
        opacity: 0.8
    }).appendTo('body');
    Graph30Day.css({
        height: '150px'
    }).bind('plothover', function(event, pos, item) {
        if (item) {
            var x = item.datapoint[0],
                y = item.datapoint[1],
                date = new Date(x).toDateString();
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
    update30day();

    // Bandwidth chart
    var updateBandwidth = function() {
        var urls = $('#bandwidthUrls').val().split(','),
            names = $('#nodeNames').val().split(',');
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?node=home&sub=bandwidth',
                type: 'post',
                data: {
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
    };
    var updateBandwidthGraph = function(data) {
        var retval = function(d) {
            if (parseInt(d) < 1) {
                return 0;
            } else {
                var val = Math.round(d / 1024 * 8 / bandwidthtime, 2);
                if (val < 1) {
                    val = 0;
                }
                return val;
            }
        };
        var d = new Date(),
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
            lastrx = GraphBandwidthData[i].rxd[rxlength];
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
        var GraphData = [];
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
        if (bandwidthinterval || realtime !== 'on') {
            clearTimeout(bandwidthinterval);
        }
        if (realtime === 'on') {
            bandwidthinterval = setTimeout(
                updateBandwidth,
                1000 - ((new Date().getTime() - startTime) % 1000)
            );
        }
    };
    $('.type-filters').on('click', function(e) {
        $('#graph-bandwidth-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        e.preventDefault();
    });
    $('.time-filters').on('click', function(e) {
        $('#graph-bandwidth-time-title > span').text($(this).text());
        $(this).blur().addClass('active').siblings('a').removeClass('active');
        GraphBandwidthMaxDataPoints = $(this).prop('rel');
        e.preventDefault();
    });
    $('#realtime .btn').on('click', function(e) {
        if ($(this).data('toggle') === 'on') {
            realtime = 'on';
            updateBandwidth();
        } else {
            clearTimeout(bandwidthinterval);
            realtime = 'off';
        }
    });
    $('#graph-bandwidth').css({
        height: '150px'
    });
    updateBandwidth();

    // Client Count
    $('#graph-activity').css({
        height: '150px',
        color: '#f3f3f3',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    var updateClientCount = function() {
        sgID = $('.activity-count').val();
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?node=home&sub=clientcount',
                type: 'post',
                data: {
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
    };
    var updateClientCountGraph = function(data) {
        updateClientCountData = [
            {
                label: data._labels[0],
                data: parseInt(data.ActivitySlots)
            },
            {
                label: data._labels[1],
                data: parseInt(data.ActivityQueued)
            },
            {
                label: data._labels[2],
                data: parseInt(data.ActivityActive)
            }
        ];
        $.plot('#graph-activity', updateClientCountData, updateClientCountOpts);
        if (clientinterval) {
            clearTimeout(clientinterval);
        }
        clientinterval = setTimeout(
            updateClientCount,
            300000 - (
                (new Date().getTime() - startTime)
                % 300000
            )
        );
    };
    updateClientCount();
    $('.activity-count').on('change', function(e) {
        if (clientinterval) {
            clearTimeout(clientinterval);
        }
        updateClientCount();
        e.preventDefault();
    });

    // Disk Usage
    $('#graph-diskusage').css({
        height: '150px',
        color: '#f3f3f3',
        'text-decoration': 'none',
        'font-weight': 'bold'
    });
    var updateDiskUsage = function() {
        var now = new Date().getTime(),
            nodeid = $('.nodeid').val();
        Pace.ignore(function() {
            $.ajax({
                url: '../management/index.php?node=home&sub=diskusage',
                data: {
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
    };
    var updateDiskUsageGraph = function(data) {
        if (data.error) {
            $('#graph-diskusage').html(data.error ? data.error : 'No data returned');
        }
        GraphDiskUsageData = [
            {
                label: data._labels[0],
                data: parseInt(data.free, 10)
            },
            {
                label: data._labels[1],
                data: parseInt(data.used, 10)
            }
        ];
        $.plot($('#graph-diskusage'), GraphDiskUsageData, GraphDiskUsageOpts);
        if (diskusageinterval) {
            clearTimeout(diskusageinterval);
        }
        diskusageinterval = setTimeout(
            updateDiskUsage,
            300000 - (
                (
                    new Date().getTime() - startTime
                ) % 300000
            )
        );
    };
    nodeid = $('.nodeid').val();
    $('#hwinfolink').attr('href', '../management/index.php?node=hwinfo&id=' + nodeid);
    updateDiskUsage();
    $('.nodeid').on('change', function(e) {
        nodeid = $(this).val();
        $('#hwinfolink').attr('href', '../management/index.php?node=hwinfo&id=' + nodeid);
        if (diskusageinterval) {
            clearTimeout(diskusageinterval);
        }
        updateDiskUsage();
        e.preventDefault();
    });
})(jQuery);
