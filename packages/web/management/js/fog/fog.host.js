var MACLookupTimer;
var MACLookupTimeout = 1000;
var length = 1;
$(function() {
    checkboxToggleSearchListPages();
    $('.toggle-checkboxgroup').click(function(e) {
        e.preventDefault();
        allchecked = $(this).prop('checked');
        $('input.toggle-group[type="checkbox"]').not(':hidden').not(':checked').each(function(evt) {
            evt.preventDefault();
            $(this).prop('checked',allchecked);
        });
    });
    $('#action-box,#action-boxdel').submit(function() {
        var checked = $('input.toggle-action:checked');
        var hostIDArray = new Array();
        for (var i = 0,len = checked.size();i < len;i++) hostIDArray[hostIDArray.length] = checked.eq(i).prop('value');
        $('input[name="hostIDArray"]')
            .val(hostIDArray.join(','));
    });
    MACUpdate();
    ProductUpdate();
});
function removeMACField() {
    $('.remove-mac').click(function(e) {
        e.preventDefault();
        remove = $(this);
        val = remove.prev().val();
        url = remove.parents('form').prop('action');
        if (val.length > 0) {
            $.post(
                    url,
                    {additionalMACsRM: val}
                  );
        }
        remove.parent('div').remove();
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
