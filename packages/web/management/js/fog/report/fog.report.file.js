(function($) {
  // reportButtons is defined globally in fog.common.js and shared with the
  // plugin report tables (registerReportTable), so every report toolbar is
  // identical.
  var reportString = window.atob(Common.f);

  // This will call our respective calls
  // to report the requested data.
  switch (reportString) {
      // Files Deleted List
    case 'file deleter':
      var fileTable = $('#filedeleterlist-table'),
        table = fileTable.registerTable(null, {
          order: [
            [3, 'desc']
          ],
          rowGroup: {
            dataSrc: function(row) {
              return moment(row.createdTime, moment.ISO_8601).format('MMM DD YYYY');
            }
          },
          buttons: reportButtons,
          columns: [
            {data: 'path'},
            {data: 'pathtype'},
            {data: 'taskstatename'},
            {data: 'createdTime'},
            {data: 'completedTime'},
            {data: 'createdBy'}
          ],
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // History Report
    case 'history report':
      var historyTable = $('#history-table'),
        table = historyTable.registerTable(null, {
          order: [
            [1, 'desc']
          ],
          rowGroup: {
            dataSrc: function(row) {
              return moment(row.createdTime, moment.ISO_8601).format('MMM DD YYYY');
            }
          },
          buttons: reportButtons,
          // Every column escapes. A history row records subject labels that
          // came from a machine on the network, and DataTables writes cell
          // data as HTML unless a column supplies its own render. The
          // display-only guard is load-bearing: the Buttons CSV/copy exports
          // ask for other types and escaping those would put &amp; into the
          // exported file. Same shape as registerExportTable().
          columns: [
            $.escapedColumn('createdBy'),
            $.escapedColumn('createdTime'),
            // ADR 0020 phase 4: the server-built sentence, in the reader's
            // language, falling back to the stored prose (`info`) for rows
            // written before phase 3.
            $.escapedColumn('summary'),
            $.escapedColumn('ip')
          ],
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Host List
    case 'host list':
      var hostTable = $('#hostlist-table'),
        table = hostTable.registerTable(null, {
          order: [
            [0, 'asc'],
            [2, 'desc']
          ],
          buttons: reportButtons,
          columns: [
            {data: 'mainlink'},
            {data: 'primac'},
            {data: 'deployed'},
            {data: 'imageLink'},
            {data: 'name'}
          ],
          columnDefs: [
            {
              orderData: [4],
              targets: [0]
            },
            {
              render: function (data, type, row) {
                if (type !== 'display') {
                  return data;
                }
                return (data || '') + macVendorIcon(row.primac_vendor);
              },
              targets: [1]
            },
            {
              targets: [4],
              visible: false,
              searchable: false
            }
          ],
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Hosts and users
    case 'hosts and users':
      var userloginTable = $('#userlogin-table'),
        table = userloginTable.registerTable(null, {
          order: [
            [1, 'asc']
          ],
          buttons: reportButtons,
          columns: [
            {data: 'username', render: $.fn.dataTable.render.text()},
            {data: 'hostLink'},
            {data: 'createdTime', render: $.fn.dataTable.render.text()},
            {data: 'hostname', render: $.fn.dataTable.render.text()}
          ],
          columnDefs: [
            {
              orderData: [3],
              targets: [0]
            },
            {
              targets: [3],
              visible: false,
              searchable: false
            }
          ],
          rowGroup: {
            dataSrc: 'hostLink'
          },
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Inventory Report
    case 'inventory report':
      var inventoryTable = $('#inventory-table'),
        table = inventoryTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          buttons: reportButtons,
          // Aisle 019: every field below is fed by the UNAUTHENTICATED inventory
          // submission surface (service/ipxe/boot.php and the inventory service),
          // and DataTables writes cell data as HTML by default -- so a stored
          // payload executed here. render.text() is the right layer: the server
          // side must stay raw because Route::listem also feeds the CSV/exportAll
          // path, where HTML entities would leak into exported files.
          // 'hostLink' is deliberately excluded -- it is a server-built <a> and
          // render.text() would print the markup literally, breaking navigation.
          // Its hostName is escaped server-side in route.class.php instead.
          columns: [
            {data: 'hostLink'}, // 00
            // User set information
            {data: 'primaryUser', render: $.fn.dataTable.render.text()},
            {data: 'other1', render: $.fn.dataTable.render.text()},
            {data: 'other2', render: $.fn.dataTable.render.text()},
            // System
            {data: 'sysman', render: $.fn.dataTable.render.text()}, // 01
            {data: 'sysproduct', render: $.fn.dataTable.render.text()}, // 02 visible
            {data: 'sysversion', render: $.fn.dataTable.render.text()}, // 03
            {data: 'sysserial', render: $.fn.dataTable.render.text()}, // 04 visible
            {data: 'sysuuid', render: $.fn.dataTable.render.text()}, // 05 visible
            {data: 'systype', render: $.fn.dataTable.render.text()}, // 06
            // BIOS
            {data: 'biosversion', render: $.fn.dataTable.render.text()}, // 07
            {data: 'biosvendor', render: $.fn.dataTable.render.text()}, // 08
            {data: 'biosdate', render: $.fn.dataTable.render.text()}, // 09
            // Motherboard
            {data: 'mbman', render: $.fn.dataTable.render.text()}, // 10
            {data: 'mbproductname', render: $.fn.dataTable.render.text()}, // 11
            {data: 'mbversion', render: $.fn.dataTable.render.text()}, // 12
            {data: 'mbserial', render: $.fn.dataTable.render.text()}, // 13
            {data: 'mbasset', render: $.fn.dataTable.render.text()}, // 14
            // CPU
            {data: 'cpuman', render: $.fn.dataTable.render.text()}, // 15
            {data: 'cpuversion', render: $.fn.dataTable.render.text()}, // 16
            {data: 'cpucurrent', render: $.fn.dataTable.render.text()}, // 17
            {data: 'cpumax', render: $.fn.dataTable.render.text()}, // 18
            // Memory
            {data: 'mem', render: $.fn.dataTable.render.text()}, // 19 visible
            // Hard Disk
            {data: 'hdmodel', render: $.fn.dataTable.render.text()}, // 20
            {data: 'hdserial', render: $.fn.dataTable.render.text()}, // 21
            {data: 'hdfirmware', render: $.fn.dataTable.render.text()}, // 22
            // Case
            {data: 'caseman', render: $.fn.dataTable.render.text()}, // 23
            {data: 'casever', render: $.fn.dataTable.render.text()}, // 24
            {data: 'caseserial', render: $.fn.dataTable.render.text()}, // 25
            {data: 'caseasset', render: $.fn.dataTable.render.text()}, // 26
            // GPU
            {data: 'gpuvendors', render: $.fn.dataTable.render.text()}, // 27
            {data: 'gpuproducts', render: $.fn.dataTable.render.text()}, // 28
            // name of host
            {data: 'hostname', render: $.fn.dataTable.render.text()}, // 29 Not visible
          ],
          columnDefs: [
            {targets: [0, 5, 7, 8, 22], visible: true },
            {targets: '_all', visible: false},
          ],
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Pending MAC
    case 'pending mac list':
      var pendingMacTable = $('#pendingmac-table'),
        table = pendingMacTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          buttons: reportButtons,
          columns: [
            {data: 'hostLink'},
            {data: 'mac'}
          ],
          columnDefs: [
            {
              render: function (data, type, row) {
                if (type !== 'display') {
                  return data;
                }
                return (data || '') + macVendorIcon(row.mac_vendor);
              },
              targets: [1]
            }
          ],
          rowGroup: {
            dataSrc: 'hostLink'
          },
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Product Keys
    case 'product keys':
      // Keys are masked by default (5x5 with the middle three groups
      // bulleted). The reveal button flips this closure flag and redraws;
      // both the column and the row-group header honour it. The full key is
      // still present in the JSON payload, so this guards shoulder-surfing,
      // not a determined viewer.
      var revealKeys = false;
      var hostTable = $('#hostkeys-table'),
        table = hostTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          buttons: reportButtons.concat([
            {
              text: '<i class="far fa-eye"></i> Reveal keys',
              action: function(e, dt, node, config) {
                revealKeys = !revealKeys;
                $(node).html(
                  revealKeys
                    ? '<i class="far fa-eye-slash"></i> Hide keys'
                    : '<i class="far fa-eye"></i> Reveal keys'
                );
                dt.draw(false);
              }
            }
          ]),
          columns: [
            {data: 'mainlink'},
            {data: 'primac'},
            {
              data: 'productKey',
              render: function(data, type) {
                if (type !== 'display') {
                  return data;
                }
                return revealKeys
                  ? $.escapeHtml(data)
                  : $.escapeHtml($.productKeyMask(data));
              }
            }
          ],
          rowGroup: {
            dataSrc: 'productKey',
            startRender: function(rows, group) {
              return revealKeys
                ? $.escapeHtml(group)
                : $.escapeHtml($.productKeyMask(group));
            }
          },
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Snapin List
    case 'snapin list':
      var snapinTable = $('#snapinlist-table'),
        table = snapinTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          buttons: reportButtons,
          columns: [
            {data: 'mainlink'},
            {data: 'file'},
            {data: 'args'}
          ],
          rowId: 'id',
          processing: true,
          serverSide: true,
          select: false,
          ajax: {
            url: '../management/index.php?node=report&sub=getList&f='
            + Common.f,
            type: 'post'
          }
        });
      break;
      // Run History
      //
      // The one report here that is NOT serverSide. ActivityWindow returns
      // a plain array with its own row cap and the real filter is the date
      // range, so there is no server-side protocol to speak -- see the
      // class docblock in lib/reports/run_history.report.php.
      //
      // The range and the source ticks live in the page URL, so they are
      // forwarded to getList verbatim rather than re-read from the form:
      // whatever the server rendered the form from is what the table asks
      // for, and the two cannot drift.
    case 'run history':
      var runTable = $('#runhistory-table'),
        table = runTable.registerTable(null, {
          order: [
            [3, 'desc']
          ],
          buttons: reportButtons,
          columns: [
            {data: 'source'},
            {data: 'label'},
            {data: 'host'},
            {data: 'startedAt'},
            {data: 'endedAt'},
            {data: 'state'}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            // The page's OWN query string carries the window (start, end,
            // sources[]) and getList has to see it -- but it also carries
            // node, sub and f, and appending it wholesale put `sub=file`
            // AFTER `sub=getList`. PHP takes the last occurrence of a
            // repeated key, so every request re-rendered the report page
            // and DataTables was handed HTML at HTTP 200. That is what the
            // "runhistory-table / HTTP 200 - <div class=..." toast was.
            //
            // Built through URLSearchParams so there are no repeated keys
            // to resolve at all: the window params ride along untouched
            // (set() replaces only the three named, and sources[] is left
            // as the repeated key it is), and the three that address the
            // endpoint are stated once.
            url: (function () {
              var params = new URLSearchParams(window.location.search);
              params.set('node', 'report');
              params.set('sub', 'getList');
              params.set('f', Common.f);
              return '../management/index.php?' + params.toString();
            })(),
            type: 'post'
          }
        });
      break;
  }
})(jQuery);
