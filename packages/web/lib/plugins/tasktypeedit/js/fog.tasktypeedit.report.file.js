(function($) {
  var reportString = window.atob(Common.f);

  // Every plugin's report JS loads on any Reports page, so guard on the
  // report name and only act on this plugin's own report.
  switch (reportString) {
    case 'tasktypeedit report':
      $('#tasktypeedit-report-table').registerReportTable([
        {data: 'name'},
        {data: 'description', visible: false},
        {data: 'icon', visible: false},
        {data: 'kernel', visible: false},
        {data: 'kernelArgs', visible: false},
        {data: 'type', visible: false},
        {data: 'isAdvanced', visible: false},
        {data: 'access'},
        {data: 'initrd', visible: false}
      ]);
      break;
  }
})(jQuery);
