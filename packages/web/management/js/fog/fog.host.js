var MACLookupTimer;
var MACLookupTimeout = 1000;
var length = 1;
var macregex = /^(?:[0-9A-Fa-f]{2}([-:]))(?:[0-9A-Fa-f]{2}\1){4}[0-9A-Fa-f]{2}$|^(?:[0-9A-Fa-f]{12})$|^(?:[0-9A-Fa-f]{4}([.])){2}[0-9A-Fa-f]{4}$/;
$(function() {
    checkboxToggleSearchListPages();
    checkboxAssociations('.toggle-checkboxgroup:checkbox','.toggle-group:checkbox');
    MACUpdate();
    ProductUpdate();
    form = $('.hostname-input').parents('form');
    validator = form.validate({
        rules: {
            host: {
                required: true,
                minlength: 1,
                maxlength: 15
            },
            mac: {
                required: true,
                minlength: 12,
                maxlength: 17
            }
        }
    });
    $('.hostname-input').rules('add', {regex: /^[\w!@#$%^()\-'{}\.~]{1,15}$/});
    $('.macaddr').rules('add', {regex: macregex});
    $('#processgroup').click(function(e) {
        e.preventDefault();
        checkedIDs = getChecked();
        group_new = $('#group_new').val().trim();
        group_sel = $('select[name="group"]').val().trim();
        if (checkedIDs.length < 1) {
            Loader.fogStatusUpdate('No hosts selected to join to a group');
            return;
        }
        if (group_new.length < 1 && group_sel.length < 1) {
            Loader.fogStatusUpdate('No group name and no selected group to join.');
            return;
        }
        url = $(this).parents('form').attr('action');
        postdata = {
            hostIDArray: checkedIDs,
            group: group_sel,
            group_new: group_new
        };
        $.post(url,postdata,function(data) {
            Loader.fogStatusUpdate(data);
        });
        setTimeout(function() {
            Loader.fadeOut();
        },5000);
    });
    $('.mac-manufactor').each(function() {
        input = $(this).parent().find('input');
        var mac = (input.size() ? input.val() : $(this).parent().find('.mac').html());
        $(this).load('../management/index.php?sub=getmacman&prefix='+mac);
    });
    removeMACField();
    MACUpdate();
    $('.add-mac').click(function(e) {
        $('#additionalMACsRow').show();
        $('#additionalMACsCell').append('<div><input class="additionalMAC macaddr" type="text" name="additionalMACs[]"/>&nbsp;&nbsp;<i class="icon fa fa-minus-circle remove-mac hand" title="Remove MAC"></i><br/><span class="mac-manufactor"></span></div>');
        removeMACField();
        MACUpdate();
        HookTooltips();
        e.preventDefault();
    });
    if ($('.additionalMAC').size()) $('#additionalMACsRow').show();
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
    if (MACLookupTimer) clearTimeout(MACLookupTimer);
    MACLookupTimer = setTimeout(function(e) {
        $('#primaker').load('?sub=getmacman&prefix='+mac);
    }, MACLookupTimeout);
}
function MACUpdate() {
    $('#mac,.additionalMAC').on('change keyup blur',function(e) {
        MACChange($(this));
        e.preventDefault();
    });
}
