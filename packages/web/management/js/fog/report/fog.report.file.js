(function($) {
  // reportFileButtons is defined globally in fog.common.js -- reportButtons
  // plus the "CSV (All)" full export -- so every core report toolbar is
  // identical. reportButtons alone is what the audit and activity grids and
  // the plugin report tables (registerReportTable) wear; see fog.common.js
  // for why those do not take the export button.
  var reportString = window.atob(Common.f);

  // The endpoint for a report whose window lives in the page URL.
  //
  // The page's OWN query string carries the window (start, end, and for Run
  // History sources[]) and getList has to see it -- but it also carries
  // node, sub and f, and appending it wholesale put `sub=file` AFTER
  // `sub=getList`. PHP takes the last occurrence of a repeated key, so every
  // request re-rendered the report page and DataTables was handed HTML at
  // HTTP 200. That is what the "runhistory-table / HTTP 200 - <div class=..."
  // toast was.
  //
  // Built through URLSearchParams so there are no repeated keys to resolve
  // at all: the window params ride along untouched (set() replaces only the
  // three named, and sources[] is left as the repeated key it is), and the
  // three that address the endpoint are stated once.
  function windowedUrl() {
    var params = new URLSearchParams(window.location.search);
    params.set('node', 'report');
    params.set('sub', 'getList');
    params.set('f', Common.f);
    return '../management/index.php?' + params.toString();
  }

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
          buttons: reportFileButtons,
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
          buttons: reportFileButtons,
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
      // Hosts and users
    case 'hosts and users':
      var userloginTable = $('#userlogin-table'),
        table = userloginTable.registerTable(null, {
          order: [
            [1, 'asc']
          ],
          buttons: reportFileButtons,
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
      // Pending MAC
    case 'pending mac list':
      var pendingMacTable = $('#pendingmac-table'),
        table = pendingMacTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          buttons: reportFileButtons,
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
      // both the column and the row-group header honor it. The full key is
      // still present in the JSON payload, so this guards shoulder-surfing,
      // not a determined viewer.
      var revealKeys = false;
      var hostTable = $('#hostkeys-table'),
        table = hostTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          // reportButtons, NOT reportFileButtons: the whole content of
          // this report is the secret it masks. The DataTables CSV button
          // exports the DISPLAYED value, so it writes the mask; a full
          // server-side export would write the keys in the clear, which is
          // a disclosure change nobody asked for.
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
          buttons: reportFileButtons,
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
          buttons: reportFileButtons,
          // Every column escapes. A run's label is a task or snapin name,
          // which an operator types and a plugin can set, and DataTables
          // writes cell data as HTML unless a column supplies its own
          // render. The display-only guard inside render.text() keeps the
          // Buttons CSV/copy exports unescaped -- same shape as the history
          // and inventory reports above.
          columns: [
            {data: 'source', render: $.fn.dataTable.render.text()},
            {data: 'label', render: $.fn.dataTable.render.text()},
            {data: 'host', render: $.fn.dataTable.render.text()},
            {data: 'startedAt', render: $.fn.dataTable.render.text()},
            {data: 'endedAt', render: $.fn.dataTable.render.text()},
            {data: 'state', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
      // Imaging Report
      //
      // Not serverSide, for the same reason: the rows are the bounded fold
      // ImagingStats already ran to draw the charts above them, so paging
      // them server side would be a second query answering a slightly
      // different question. Every column is plain text out of `taskLog`,
      // including names typed by whoever created the image, so every column
      // escapes.
    case 'imaging report':
      var imagingTable = $('#imaging-table'),
        table = imagingTable.registerTable(null, {
          order: [
            [3, 'desc']
          ],
          buttons: reportFileButtons,
          columns: [
            {data: 'hostName', render: $.fn.dataTable.render.text()},
            {data: 'imageName', render: $.fn.dataTable.render.text()},
            {data: 'taskTypeName', render: $.fn.dataTable.render.text()},
            {data: 'started', render: $.fn.dataTable.render.text()},
            {data: 'ended', render: $.fn.dataTable.render.text()},
            {data: 'state', render: $.fn.dataTable.render.text()},
            {data: 'createdBy', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
      // Snapin Report
      //
      // Not serverSide, like the imaging report: the rows are the same
      // bounded window the charts above them were drawn from. Every column
      // is plain text out of snapinTasks -- the details string is whatever
      // the snapin wrote to stdout, so it escapes like the rest.
    case 'snapin report':
      var snapinReportTable = $('#snapinreport-table'),
        table = snapinReportTable.registerTable(null, {
          order: [
            [2, 'desc']
          ],
          buttons: reportFileButtons,
          columns: [
            {data: 'snapin', render: $.fn.dataTable.render.text()},
            {data: 'hostName', render: $.fn.dataTable.render.text()},
            {data: 'completed', render: $.fn.dataTable.render.text()},
            {data: 'outcome', render: $.fn.dataTable.render.text()},
            {data: 'code', render: $.fn.dataTable.render.text()},
            {data: 'details', render: $.fn.dataTable.render.text()},
            {data: 'state', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
      // Software Report
      //
      // Not serverSide, like the snapin and imaging reports above: the rows
      // are the same bounded window the "range" fields on screen were drawn
      // from, so paging them server side would be a second query answering
      // a slightly different question. Every column is plain text -- the
      // package id and install details are strings a plugin or an operator
      // set -- so every column escapes.
    case 'software report':
      var softwareReportTable = $('#softwarereport-table'),
        table = softwareReportTable.registerTable(null, {
          order: [
            [7, 'desc']
          ],
          buttons: reportFileButtons,
          columns: [
            {data: 'hostName', render: $.fn.dataTable.render.text()},
            {data: 'softwareName', render: $.fn.dataTable.render.text()},
            {data: 'package', render: $.fn.dataTable.render.text()},
            {data: 'desired', render: $.fn.dataTable.render.text()},
            {data: 'installed', render: $.fn.dataTable.render.text()},
            {data: 'status', render: $.fn.dataTable.render.text()},
            {data: 'code', render: $.fn.dataTable.render.text()},
            {data: 'checked', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
      // Installed Software
      //
      // Not serverSide, same reasoning as software report above: reportRows()
      // hands back a plain array with its own MAX_ROWS cap and no DataTables
      // paging protocol, so there is no server-side page to ask for. No
      // window either -- "currently installed" is a state, not a range -- but
      // windowedUrl() is harmless with none of its params present and every
      // other non-serverSide report here uses it, so this does too rather
      // than being the one case with a hand-built URL.
    case 'installed software':
      var installedSoftwareTable = $('#installedsoftwarereport-table'),
        table = installedSoftwareTable.registerTable(null, {
          order: [
            [2, 'desc']
          ],
          buttons: reportFileButtons,
          columns: [
            {data: 'name', render: $.fn.dataTable.render.text()},
            {data: 'version', render: $.fn.dataTable.render.text()},
            {data: 'hostCount', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
      // Fleet Report
      //
      // Ordered by the Days column DESCENDING, which is the report: the
      // machines somebody has to act on are the stalest, and "Never" is
      // the string the server sends for a host that has none. It sorts
      // above every number under DataTables' string ordering, which is
      // where it belongs -- the server sends it already ordered that way
      // and this keeps it there through a redraw.
    case 'fleet report':
      var fleetTable = $('#fleetreport-table'),
        table = fleetTable.registerTable(null, {
          order: [
            [3, 'desc']
          ],
          buttons: reportFileButtons,
          columns: [
            {data: 'hostName', render: $.fn.dataTable.render.text()},
            {data: 'imageName', render: $.fn.dataTable.render.text()},
            {data: 'lastDeploy', render: $.fn.dataTable.render.text()},
            {data: 'ageDays', render: $.fn.dataTable.render.text()},
            {data: 'lastCheckin', render: $.fn.dataTable.render.text()},
            {data: 'created', render: $.fn.dataTable.render.text()},
            {data: 'hasInventory', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
    case 'hardware report':
      var hardwareTable = $('#hardwarereport-table'),
        table = hardwareTable.registerTable(null, {
          order: [
            [0, 'asc']
          ],
          buttons: reportFileButtons,
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
    case 'storage report':
      var storageTable = $('#storagereport-table'),
        table = storageTable.registerTable(null, {
          order: [
            [7, 'desc']
          ],
          buttons: reportFileButtons,
          columns: [
            {data: 'imageName', render: $.fn.dataTable.render.text()},
            {data: 'size', render: $.fn.dataTable.render.text()},
            {data: 'groups', render: $.fn.dataTable.render.text()},
            {data: 'replicate', render: $.fn.dataTable.render.text()},
            {data: 'enabled', render: $.fn.dataTable.render.text()},
            {data: 'created', render: $.fn.dataTable.render.text()},
            {data: 'lastDeploy', render: $.fn.dataTable.render.text()},
            {data: 'bytes', render: $.fn.dataTable.render.text()}
          ],
          // "9 GiB" sorts above "10 GiB" as a string, so the size column
          // orders on the raw byte count in the hidden column beside it.
          columnDefs: [
            {
              orderData: [7],
              targets: [1]
            },
            {
              targets: [7],
              visible: false,
              searchable: false
            }
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
    case 'audit report':
      var auditTable = $('#auditreport-table'),
        table = auditTable.registerTable(null, {
          order: [
            [0, 'desc']
          ],
          buttons: reportFileButtons,
          // Every column escapes. An audit row records an ATTEMPTED
          // username and a subject label, both of which can come from an
          // unauthenticated request, and DataTables writes cell data as
          // HTML unless a column supplies its own render.
          columns: [
            {data: 'at', render: $.fn.dataTable.render.text()},
            {data: 'actor', render: $.fn.dataTable.render.text()},
            {data: 'source', render: $.fn.dataTable.render.text()},
            {data: 'ip', render: $.fn.dataTable.render.text()},
            {data: 'type', render: $.fn.dataTable.render.text()},
            {data: 'subject', render: $.fn.dataTable.render.text()},
            {data: 'permission', render: $.fn.dataTable.render.text()},
            {data: 'outcome', render: $.fn.dataTable.render.text()}
          ],
          processing: true,
          serverSide: false,
          select: false,
          ajax: {
            url: windowedUrl(),
            type: 'post'
          }
        });
      break;
  }
})(jQuery);