#!/bin/bash
#
# RETIRED. This utility no longer updates anything.
#
# It downloaded a branch tarball from GitHub, extracted it, and ran the
# installer inside it unattended as root. Three things retired it:
#
#   1. It could not do the job people most need from a 1.5 updater. Its Beta
#      arm mapped to working-1.6 and then resolved that branch's version from
#      packages/web/lib/fog/system.class.php -- a path 1.6 does not have, since
#      it moved to packages/web/src/Base/System.php. The fetch 404'd, the
#      version came back empty, and every 1.5 -> 1.6 attempt died reporting a
#      version lookup failure. verifyPayload() read the same missing path, so
#      fixing one half would only have moved the failure a few lines down.
#      GH-1587.
#
#   2. It ran `installfog.sh -y`. Unattended is defensible for a 1.5.x point
#      update, where .fogsettings already holds every answer. It is the wrong
#      default for a crossing to 1.6, which meets settings that file has never
#      held and takes a default for each of them without saying so.
#
#   3. Its trust root was verified TLS to github.com and nothing else --
#      correctly documented as such after GHSA-qp3r-8mwm-vg6h, but it means a
#      tarball is strictly weaker than a git checkout, which at least records
#      what it fetched and can be diffed and reset.
#
# The file is kept, rather than deleted, so an existing cron entry or a script
# that calls it gets this message instead of "No such file or directory" --
# and so tests/fogupdater-update-source.test.sh can keep asserting that it
# never fetches or executes anything again. That assertion is the GHSA
# guarantee in its strongest possible form.
#
# Deliberately sources nothing. Its whole job is to print and exit.

cat <<'EOF'

 * utils/FOGUpdater/fogupdater.sh has been retired and does nothing.

   It could not update a 1.5 server to 1.6 (GH-1587), and it ran the installer
   unattended, which is the wrong default for that upgrade.

   If this server is a git checkout -- the documented way to install FOG --
   use the updater beside the installer instead:

       cd <your fogproject checkout>/bin
       ./updatefog.sh --channel rc      # the current 1.6 release candidate
       ./updatefog.sh --help            # the other channels

   If it is NOT a git checkout, clone one and run its installer over this
   install. It finds the existing server through /etc/fog/fog.conf and
   upgrades it in place:

       curl -fsSL https://raw.githubusercontent.com/FOGProject/fogproject/working-1.6/bin/bootstrap.sh | bash -s -- --channel rc

   Going from 1.5 to 1.6 is a major upgrade. Take a backup first. The 1.6 tree
   carries bin/revertfog.sh, which restores the pre-upgrade database dump the
   installer takes; that dump is the only supported way back.

EOF
exit 1
