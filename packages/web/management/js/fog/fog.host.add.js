/****************************************************
 * FOG Dashboard JS
 *	Author:		Blackout
 *	Created:	12:22 PM 9/05/2011
 *	Revision:	$Revision: 642 $
 *	Last Update:	$LastChangedDate: 2011-06-03 07:41:37 +1000 (Fri, 03 Jun 2011) $
 ***/
var MACLookupTimer;
var MACLookupTimeout = 1000;
$(function() {
    MACUpdate = function() {
        var $this = $(this);
        $this.val($this.val().replace(/-/g, ':').toUpperCase());
        if (MACLookupTimer) clearTimeout(MACLookupTimer);
        MACLookupTimer = setTimeout(function() {
            $('#primaker')
            .load('../management/index.php?sub=getmacman&prefix='+mac);
        }, MACLookupTimeout);
    };
    $('#mac').keyup(MACUpdate).blur(MACUpdate);
    $('#host-active-directory').show();
});
