$(function() {
    // ---------------------------------------------------------------
    // GENERAL TAB
    var templateSel = $('#template'),
        userNameAttr = $('#userNameAttr'),
        groupNameAttr = $('#groupNameAttr'),
        grpMemberAttr = $('#grpMemberAttr');

    $.registerGeneralTab({
        nameInputSel: '#ldap',
        formSel: '#ldap-general-form'
    });

    // ---------------------------------------------------------------
    // GRANTS TAB
    // One grid per card, each a row-per-mapping list scoped to this
    // server. Read-only, but registered like every other grid so they
    // sort, search and page the same way. select/buttons are off because
    // there is nothing to act on -- the row links are the interaction.
    //
    // Both grids are identical apart from the sub they read, so build
    // them from one config: the pair cannot drift the way two
    // hand-copied blocks would.
    $.each({
        'ldap-grants-roles-table': 'getGrantRoleList',
        'ldap-grants-usergroups-table': 'getGrantUserGroupList'
    }, function(tableId, sub) {
        var grantTable = $('#' + tableId);
        if (!grantTable.length) {
            return;
        }
        grantTable.registerTable(null, {
            columns: [
                {data: 'groupLink'},
                {data: 'grantLink'}
            ],
            order: [[0, 'asc']],
            processing: true,
            serverSide: true,
            select: false,
            buttons: [],
            ajax: {
                url: '../management/index.php?node=ldap'
                    + '&sub=' + sub + '&id=' + Common.id,
                type: 'post'
            }
        });
    });
    templateSel.on('change blur focus focusout', function(e) {
        e.preventDefault();
        selected = this.value;
        switch (selected) {
            case '0':
                usrAttr = 'samAccountName';
                grpAttr = 'member';
                grpNam = 'name'
                break;
            case '1':
                usrAttr = 'cn';
                grpAttr = 'member';
                grpNam = 'name';
                break;
            case '2':
                usrAttr = 'uid';
                grpAttr = 'uniqueMember';
                grpNam = 'ou';
                break;
            case '3':
                usrAttr = 'uid';
                grpAttr = 'member';
                grpNam = 'cn';
                break;
            default:
                usrAttr = '';
                grpAttr = '';
                grpNam = '';
                break;
        }
        userNameAttr.val(usrAttr);
        groupNameAttr.val(grpNam);
        grpMemberAttr.val(grpAttr);
    });
});
