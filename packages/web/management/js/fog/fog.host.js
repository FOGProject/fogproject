var MACLookupTimer;
var MACLookupTimeout = 1000;
var length = 1;
$(function() {
    checkboxToggleSearchListPages();
    checkboxAssociations('.toggle-checkboxgroup:checkbox','.toggle-group:checkbox');
    MACUpdate();
    ProductUpdate();
    validateInputs('.hostname-input',/^[\w!@#$%^()\-'{}\.~]{1,15}$/);
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
    validateInputs(data,/^(?:[0-9A-Fa-f]{2}([-:]))(?:[0-9A-Fa-f]{2}\1){4}[0-9A-Fa-f]{2}|(?:[0-9A-Fa-f]{12})|(?:[0-9A-Fa-f]{4}([.])){2}[0-9A-Fa-f]{4}$/);
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
