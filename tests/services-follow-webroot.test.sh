#!/bin/bash
#
# Pins where the full-server install copies the daemons: straight after the web
# root rebuild, and before any step that can exit the installer.
#
#   tests/services-follow-webroot.test.sh
#
# Every daemon requires the web root's commons/base.inc.php, so
# /opt/fog/service and the web root are one program. The copy used to run
# beside configureFOGService, far after updateDB. When updateDB exited (a TLS
# failure talking to the server's own web tier is enough), the web root was new
# and the daemons were old. The next daemon restart then failed on a bare
# FOGCore, which the new tree no longer aliases (ADR 0013), and every service
# crash-looped. Nothing errored during the install itself.
#
# Static: reads bin/installfog.sh. No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALLER="$(cd "$HERE/.." && pwd)/bin/installfog.sh"

[[ -f $INSTALLER ]] || { echo "ERROR: $INSTALLER not found" >&2; exit 1; }

# Line number of the first bare call to $1 at or after line $2.
call_line() {
    awk -v fn="$1" -v from="$2" 'NR >= from && $1 == fn && NF == 1 { print NR; exit }' "$INSTALLER"
}

FAIL=0
bad() { FAIL=1; printf '  FAIL  %s\n' "$1"; }

# configureHttpd is called only by the full-server arm; the storage-node arm
# calls configureMinHttpd and has no updateDB.
httpd=$(call_line configureHttpd 1)
[[ -n $httpd ]] || { echo "ERROR: no configureHttpd call found" >&2; exit 1; }
services=$(call_line installFOGServices "$httpd")
updatedb=$(call_line updateDB "$httpd")

if [[ -z $services ]]; then
    bad "full-server arm never calls installFOGServices after configureHttpd"
elif [[ -z $updatedb ]]; then
    bad "full-server arm has no updateDB after configureHttpd"
elif (( services > updatedb )); then
    bad "installFOGServices (line $services) runs after updateDB (line $updatedb)"
fi

# Called twice would copy twice and hide a later reorder from the check above.
count=$(awk -v from="$httpd" 'NR >= from && $1 == "installFOGServices" && NF == 1' "$INSTALLER" | wc -l)
(( count == 1 )) || bad "full-server arm calls installFOGServices $count times, expected 1"

if (( FAIL )); then
    exit 1
fi
echo "  ok    installFOGServices follows configureHttpd and precedes updateDB"
