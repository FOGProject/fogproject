var MACLookupTimer;
var MACLookupTimeout = 1000;
var length = 1;
$(function() {
    checkboxToggleSearchListPages();
    $('.toggle-checkboxgroup')
    .click(function() {
        $('input.toggle-group[type="checkbox"]')
        .not(':hidden')
        .prop('checked',$(this).is(':checked'));
    });
    $('.toggle-checkboxAction')
    .click(function() {
        $('input.toggle-host[type="checkbox"]')
        .not(':hidden')
        .prop('checked',$(this).is(':checked'));
    });
    $('#action-box,#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var hostIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) hostIDArray[hostIDArray.length] = checked.eq(i).prop('value');
        $('input[name="hostIDArray"]')
        .val(hostIDArray.join(','));
    });
    MACUpdate();
});
function removeMACField() {
    $('.remove-mac').click(function(e) {
        e.preventDefault();
        remove = $(this);
        val = remove.prev().val();
        if (val.length > 0) {
            url = remove.parents('form').prop('action');
            $.post(url,
                {additionalMACsRM: [val]},
                function(data) {
                    console.log(val);
                    console.log(data);
                }
            );
        }
        remove.parent().remove();
        HookTooltips();
    });
}
function MACChange(data) {
    var content = data;
    var content1 = content.val().replace(/\:|\-/g,'').toLowerCase();
    data.val(content1.replace(/[^0-9A-Fa-f]/g,'').replace(/(.{2})/g,'$1:'));
    if (data.val().length > 17) data.val(data.val().substring(0,17));
    if (MACLookupTimer) clearTimeout(MACLookupTimer);
    MACLookupTimer = setTimeout(function(e) {
        $('#primaker').load('?sub=getmacman&prefix='+mac);
    }, MACLookupTimeout);
}
function MACUpdate() {
    $('#mac,.additionalMAC').on('change keyup',function(e) {
        e.preventDefault();
        MACChange($(this));
    });
}
