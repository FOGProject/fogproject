(function($) {
    // One entry per column FOGPage::export() puts in the header row, which is
    // LDAPGroup's $databaseFields minus the id. serverID stays a raw id rather
    // than the server's name: the header tokens are what the CSV importer
    // matches on, and a group only means anything alongside the directory it
    // was read from.
    $('#ldapgroup-export-table').registerExportTable([
        {data: 'serverID'},
        {data: 'name'}
    ]);
})(jQuery);
