var MACLookupTimer;
var MACLookupTimeout = 1000;
var length = 1;
$(function() {
    checkboxToggleSearchListPages();
    checkboxAssociations('.toggle-checkboxgroup:checkbox','.toggle-group:checkbox');
    MACUpdate();
    ProductUpdate();
});
function removeMACField() {
    $('.remove-mac').click(function(e) {
        e.preventDefault();
        remove = $(this);
        val = remove.prev().val();
        if (!val.length) return;
        url = remove.parents('form').prop('action');
        $.post(url,{additionalMACsRM: val});
        remove.parent('div').remove();
        HookTooltips();
    });
}
function MACChange(data) {
    var content = data.val();
    content1 = content.match(/^(?:[0-9A-Fa-f]{2}([-:]))(?:[0-9A-Fa-f]{2}\1){4}[0-9A-Fa-f]{2}|(?:[0-9A-Fa-f]{12})|(?:[0-9A-Fa-f]{4}([.])){2}[0-9A-Fa-f]{4}$/);
    data.blur(function() {
        if (content1 === null) $(this).addClass('error');
        else $(this).removeClass('error');
    }).parents('form').submit(function (e) {
        if (content1 === null) {
            data.addClass('error');
            return false;
        } else {
            data.removeClass('error');
            return true;
        }
    });
    if (data.val().length > 17) data.val(data.val().substring(0,17));
    if (MACLookupTimer) clearTimeout(MACLookupTimer);
    MACLookupTimer = setTimeout(function(e) {
        $('#primaker').load('?sub=getmacman&prefix='+mac);
    }, MACLookupTimeout);
}
function MACUpdate() {
    $('#mac,.additionalMAC').on('change keyup',function(e) {
        MACChange($(this));
        e.preventDefault();
    });
}
