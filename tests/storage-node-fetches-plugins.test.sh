#!/bin/bash
#
# Pins that a storage-node install fetches the bundled plugins before it lays
# the web root.
#
#   tests/storage-node-fetches-plugins.test.sh
#
# ADR 0009 moved the bundled plugins out of git: packages/web/lib/plugins/ is
# gitignored and bin/fetch-plugins.sh fills it. The full-server branch of
# installfog.sh calls downloadplugins before configureHttpd. The storage-node
# branch did not. configureMinHttpd calls configureHttpd, which re-lays the
# web root from packages/web, so every storage node got a web root with no
# plugins at all.
#
# A storage node still serves PXE: its default.ipxe chains to its OWN
# boot.php. That boot.php reads the master database, sees the location plugin
# active, but has no hook class to load. BootMenuBase then falls back to the
# image's primary group and SAVES that node to the task. Forum 18301: a
# deploy at site B showed the site B node in Active Tasks until the client
# booted, then switched to the master and pulled the image across the uplink.

set -u
here=$(cd "$(dirname "$0")" && pwd)
installer="$here/../bin/installfog.sh"

# The [Ss]) arm of the install-type case that runs the install steps: the one
# that calls configureMinHttpd. Print its lines up to the arm's closing ;;.
arm=$(awk '
    /^[[:space:]]*\[Ss\]\)[[:space:]]*$/ { inarm = 1; buf = ""; next }
    inarm && /^[[:space:]]*;;[[:space:]]*$/ {
        if (buf ~ /configureMinHttpd/) { print buf; exit }
        inarm = 0; next
    }
    inarm { buf = buf $0 "\n" }
' "$installer")

if [[ -z $arm ]]; then
    echo "  FAIL  could not find the storage-node [Ss]) arm that calls configureMinHttpd"
    exit 1
fi

fetch=$(grep -n '^[[:space:]]*downloadplugins[[:space:]]*$' <<<"$arm" | head -1 | cut -d: -f1)
lay=$(grep -n '^[[:space:]]*configureMinHttpd[[:space:]]*$' <<<"$arm" | head -1 | cut -d: -f1)

if [[ -z $fetch ]]; then
    echo "  FAIL  storage-node install never calls downloadplugins"
    exit 1
fi
if (( fetch > lay )); then
    echo "  FAIL  storage-node install calls downloadplugins after configureMinHttpd"
    exit 1
fi
echo "  ok    storage-node install fetches plugins before laying the web root"
