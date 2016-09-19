$(function() {
    checkboxToggleSearchListPages();
    $('#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var ldapIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) {
            ldapIDArray[ldapIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="ldapIDArray"]').val(ldapIDArray.join(','));
    });
    $('#inittemplate').change(function(e) {
        e.preventDefault();
        ldapSetFields(this.options[this.selectedIndex].value);
    });
});
function ldapSetFields(indx) {
    switch (indx) {
        case 'msad':
            usrAttr = 'samAccountName';
            grpAttr = 'memberOf';
            break;
        case 'open':
            usrAttr = 'cn';
            grpAttr = 'member';
            break;
        case 'edir':
            usrAttr = 'cn';
            grpAttr = 'uniqueMember';
            break;
        default:
            usrAttr = '';
            grpAttr = '';
            break;
    }
    console.log(usrAttr);
    $('#userNamAttr').val(usrAttr);
    $('#grpMemberAttr').val(grpAttr);
}
