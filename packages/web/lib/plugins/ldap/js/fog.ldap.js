$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            name: {
                required: true
            },
            address: {
                required: true
            },
            searchDN: {
                required: true
            },
            port: {
                required: true
            },
            userNamAttr: {
                required: true
            },
            grpMemberAttr: {
                required: true
            }
        }
    };
    setupTimeoutElement('#add, #update', '[name="name"], [name="address"], [name="searchDN"], [name="port"], [name="userNamAttr"], [name="grpMemberAttr"]', 1000);
    $('.action-boxes').on('submit',function() {
        var checked = $('input.toggle-action:checked');
        var ldapIDArray = [];
        for (var i = 0,len = checked.size();i < len;i++) {
            ldapIDArray[ldapIDArray.length] = checked.eq(i).attr('value');
        }
        $('input[name="ldapIDArray"]').val(ldapIDArray.join(','));
    });
    $('#inittemplate').on('change',function(e) {
        e.preventDefault();
        ldapSetFields(this.options[this.selectedIndex].value);
    });
    $('#useGroupMatch').on('change',function(e) {
        e.preventDefault();
        ldapUseGroupToggle(this.options[this.selectedIndex].value);
    }).trigger('change');
});
function ldapSetFields(indx) {
    switch (indx) {
        case 'edir':
            usrAttr = 'cn';
            grpAttr = 'uniqueMember';
            break;
        case 'msad':
            usrAttr = 'samAccountName';
            grpAttr = 'member';
            break;
        case 'open':
            usrAttr = 'cn';
            grpAttr = 'member';
            break;
        default:
            usrAttr = '';
            grpAttr = '';
            break;
    }
    $('#userNamAttr').val(usrAttr);
    $('#grpMemberAttr').val(grpAttr);
}
function ldapUseGroupToggle(indx) {
    if (indx == 0) {
        $('#adminGroup,#userGroup,#userNamAttr,#grpMemberAttr,#bindDN,#bindPwd')
            .prop('readonly', true)
            .css({'background-color': 'lightgrey'});
    } else {
        $('#adminGroup,#userGroup,#userNamAttr,#grpMemberAttr,#bindDN,#bindPwd')
            .prop('readonly', false)
            .css({'background-color': 'white'});
    }
}
