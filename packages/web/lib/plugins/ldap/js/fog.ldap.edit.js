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
    // GROUP MAPPINGS TAB
    // Read-only, but registered like every other grid so it sorts,
    // searches and pages the same way. select/buttons are off because
    // there is nothing to act on -- the row links are the interaction.
    var groupMapTable = $('#ldap-groupmap-table');
    if (groupMapTable.length) {
        groupMapTable.registerTable(null, {
            columns: [
                {data: 'mainLink'},
                {data: 'grants'}
            ],
            order: [[0, 'asc']],
            processing: true,
            serverSide: true,
            select: false,
            buttons: [],
            ajax: {
                url: '../management/index.php?node=ldap'
                    + '&sub=getGroupMapList&id=' + Common.id,
                type: 'post'
            }
        });
    }
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
