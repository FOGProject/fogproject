var MACLookupTimer;
var MACLookupTimeout = 1000;
var length = 1;
$(function() {
    checkboxToggleSearchListPages();
    validatorOpts = {
        submitHandler: submithandlerfunc,
        rules: {
            host: {
                required: true,
                minlength: 1,
                maxlength: 15,
                regex: /^[\w!@#$%^()'{}\.~-]{1,15}$/
            },
            mac: {
                required: true,
                minlength: 12,
                maxlength: 17,
                regex: /^(?:[0-9A-Fa-f]{2}([-:]))(?:[0-9A-Fa-f]{2}\1){4}[0-9A-Fa-f]{2}$|^(?:[0-9A-Fa-f]{12})$|^(?:[0-9A-Fa-f]{4}([.])){2}[0-9A-Fa-f]{4}$/
            }
        }
    };
    setInterval(function() {
        $('#add, #all, #pmsubmit, #pmupdate, #pmdelete, #updategen, #levelup, #updateprinters, #defaultsel, #printdel, #updatesnapins, #snapdel, #updatestatus, #updatedisplay, #updatealo, #updateinv, #host-edit').each(function(e) {
            if ($(this).is(':visible')) {
                form = $(this).parents('form');
                validator = form.validate(validatorOpts);
            }
            $(this).on('click', function(e) {
                data = this.name;
            });
        });
        $('.hostname-input:not(:hidden), .macaddr:not(:hidden)').each(function(e) {
            if ($(this).is(':visible')) {
                if (!$(this).hasClass('isvisible')) {
                    $(this).addClass('isvisible');
                }
                $(this).on('keyup change blur', function(e) {
                    return validator.element(this);
                });
            } else {
                if ($(this).hasClass('isvisible')) {
                    $(this).removeClass('isvisible');
                }
            }
        });
    }, 1000);
    checkboxAssociations('.toggle-checkboxgroup:checkbox','.toggle-group:checkbox');
    MACUpdate();
    ProductUpdate();
    $('#process').on('click', function(e) {
        //e.preventDefault();
        checkedIDs = getChecked();
        group_new = $('#group_new').val().trim();
        group_sel = $('select[name="group"]').val();
        if (typeof(group_sel) != 'undefined') {
            group_sel = group_sel.trim();
        }
        if (checkedIDs.length < 1) {
            return;
        }
        if (group_new.length < 1 && group_sel.length < 1) {
            return;
        }
        url = $(this).parents('form').prop('action');
        postdata = {
            hostIDArray: checkedIDs.join(',')
        };
        if (group_new) {
            $.extend(postdata, {group_new : group_new});
        } else {
            $.extend(postdata, {group: group_sel});
        }
        $.post(url,postdata);
    });
    $('.mac-manufactor').each(function() {
        input = $(this).parent().find('input');
        var mac = (input.size() ? input.val() : $(this).parent().find('.mac').html());
        $(this).load('../management/index.php?sub=getmacman&prefix='+mac);
    });
    removeMACField();
    MACUpdate();
    $('.add-mac').click(function(e) {
        $('.additionalMACsRow').parents('tr').show();
        $('.additionalMACsCell').append(
            '<div class="addrow">'
            + '<div class="col-xs-10">'
            + '<div class="input-group">'
            + '<span class="mac-manufactor input-group-addon"></span>'
            + '<input type="text" class="macaddr additionalMAC form-control" '
            + 'name="additionalMACs[]" maxlength="17"/>'
            + '<span class="icon remove-mac fa fa-minus-circle hand '
            + 'input-group-addon" data-toggle="tooltip" data-placement="top" '
            + 'title="Remove MAC"></span>'
            + '</div>'
            + '</div>'
            + '<div class="col-xs-1">'
            + '<div class="row">'
            + '<span data-toggle="tooltip" data-placement="top" '
            + 'title="'
            + 'Ignore MAC on Client'
            + '" class="hand">'
            + 'I.M.C.'
            + '</span>'
            + '</div>'
            + '<div class="checkbox">'
            + '<label>'
            + '<input type="checkbox" name="igclient[]"/>'
            + '</label>'
            + '</div>'
            + '</div>'
            + '<div class="col-xs-1">'
            + '<div class="row">'
            + '<span data-toggle="tooltip" data-placement="top" '
            + 'title="'
            + 'Ignore MAC on Image'
            + '" class="hand">'
            + 'I.M.I.'
            + '</span>'
            + '</div>'
            + '<div class="checkbox">'
            + '<label>'
            + '<input type="checkbox" name="igimage[]"/>'
            + '</label>'
            + '</div>'
            + '</div>'
            + '</div>'
            + '</div>'
        );
        $('.mac-manufactor').each(function() {
            input = $(this).parent().find('input');
            var mac = (input.size() ? input.val() : $(this).parent().find('.mac').html());
            $(this).load('../management/index.php?sub=getmacman&prefix='+mac);
        });
        removeMACField();
        MACUpdate();
        e.preventDefault();
    });
    if ($('.additionalMAC').size() < 1) {
        $('.additionalMACsRow').hide().parents('tr').hide();
    } else {
        $('.additionalMACsRow').show();
    }
    if ($('.pending-mac').size() < 1) {
        $('.pendingMACsRow').hide().parents('tr').hide();
    } else {
        $('.pendingMACsRow').show();
    }
});
function removeMACField() {
    $('.remove-mac').click(function(e) {
        e.preventDefault();
        remove = $(this).parents('.addrow');
        tr = remove.parents('tr');
        val = remove.closest('input[type="text"]').val();
        if (typeof(val) == 'undefined' || !val.length) {
            remove.remove();
            if ($('.addrow').length < 1) {
                tr.hide();
            }
            return;
        }
        url = remove.parents('form').prop('action');
        $.post(url,{additionalMACsRM: val});
        remove.remove();
        if ($('.addrow').length < 1) {
            tr.hide();
        }
    });
}
function MACChange(data) {
    if (MACLookupTimer) clearTimeout(MACLookupTimer);
    MACLookupTimer = setTimeout(function(e) {
        $('#primaker').load('?sub=getmacman&prefix='+mac);
    }, MACLookupTimeout);
}
function MACUpdate() {
    $('#mac, .additionalMAC').on('change keyup blur',function(e) {
        MACChange($(this));
        e.preventDefault();
    });
}
