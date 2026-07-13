$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    $.registerGeneralTab({
        nameInputSel: '#module',
        formSel: '#module-general-form'
    });

    // ---------------------------------------------------------------
    // HOST ASSOCIATION TAB
    var moduleHostsTable = $.registerAssociationTab({
        slug: 'module-host',
        item: 'host',
        sub: 'getHostsList'
    });

    if (Common.search && Common.search.length > 0) {
        moduleHostsTable.search(Common.search).draw();
    }
});
