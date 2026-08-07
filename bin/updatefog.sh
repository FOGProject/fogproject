#!/bin/bash
#
#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#    any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Updates an EXISTING FOG install in place: fetches/checks out the branch
# mapped from the configured (or given) channel, backs up the handful of
# files installfog.sh's asset sync can overwrite, re-runs installfog.sh, and
# reverts on failure. See docs/superpowers or issue #1005 for the design.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "FOG updates must be run as root user"
    exit 1
fi

for sbindir in /usr/local/sbin /usr/sbin /sbin; do
    [[ -d $sbindir ]] || continue
    case ":${PATH}:" in
        *:"${sbindir}":*) ;;
        *) PATH="${PATH}:${sbindir}" ;;
    esac
done
export PATH

usage() {
    echo -e "Usage: $0 [-h?y] [--channel stable|staging|dev] [--branch <name>] [--git-path </path>]"
    echo -e "\t                 \t\t[--no-revert] [--no-vhost]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --channel\tUpdate channel to track: stable, staging, or dev"
    echo -e "\t               \t\tdefaults to whatever this server already tracks"
    echo -e "\t      --branch\tCheck out an arbitrary branch instead of a channel"
    echo -e "\t               \t\t(e.g. to test a PR/feature branch). One-off: does"
    echo -e "\t               \t\tnot change the tracked channel for future runs"
    echo -e "\t      --git-path\tOverride the git checkout path this server records"
    echo -e "\t      --hostname\tOverride the vhost/cert hostname for this update"
    echo -e "\t               \t\t(implies --overwrite-vhost)"
    echo -e "\t      --extra-server-name\tAdd an extra vhost/cert name for this update (repeatable)"
    echo -e "\t                         \t(implies --overwrite-vhost)"
    echo -e "\t      --no-revert\tOn failure, leave the system as-is instead of"
    echo -e "\t                 \t\tautomatically reverting to the previous commit"
    echo -e "\t      --no-vhost\tDo not touch the web server vhost at all."
    echo -e "\t                 \t\tBy default FOG refreshes only the region between"
    echo -e "\t                 \t\tits MANAGED BLOCK markers and leaves anything you"
    echo -e "\t                 \t\tadded outside them alone, so skipping is rarely"
    echo -e "\t                 \t\twanted -- it also skips FOG's own security fixes"
    echo -e "\t                 \t\tto the parts it owns"
    echo -e "\t      --overwrite-vhost\tDeprecated no-op: this is now the default"
    echo -e "\t-y    --yes\t\tSkip the confirmation prompt (for cron/GUI use)"
    exit 0
}

supdateExtraServerNames=()

shortopts="h?y"
longopts="help,channel:,branch:,git-path:,no-revert,overwrite-vhost,no-vhost,yes,hostname:,extra-server-name:"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

autoRevert=1
autoYes=""
# Was -F by default, because regenerating the vhost meant destroying any hand
# customization -- createSSLCA() rewrote the whole file and could not tell
# "default" from "admin edited this". That is no longer true: it now writes
# only between the FOG MANAGED BLOCK markers (see spliceManagedBlock in
# lib/common/functions.sh) and leaves everything outside them alone.
#
# So the default flips. Skipping the vhost now costs an admin every future
# security fix FOG makes to the parts it owns -- ciphers, headers, the
# LocationMatch rules -- to protect content that is no longer at risk. -F
# remains available for "do not touch this file at all", which is a real
# preference, just no longer the one that should be automatic.
updateVhostFlag=""
while :; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --channel)
            schannel="$2"
            shift 2
            ;;
        --branch)
            sbranch="$2"
            shift 2
            ;;
        --git-path)
            if [[ -n "${2}" && "${2}" == /* ]]; then
                sgitpath="${2%/}"
            else
                echo "Error: --git-path requires an absolute path"
                usage
            fi
            shift 2
            ;;
        --hostname)
            if [[ -n "${2}" ]]; then
                supdatehostname="${2}"
            else
                echo "Error: --hostname requires a value"
                exit 9
            fi
            shift 2
            ;;
        --extra-server-name)
            if [[ -n "${2}" ]]; then
                supdateExtraServerNames+=("${2}")
            else
                echo "Error: --extra-server-name requires a value"
                exit 9
            fi
            shift 2
            ;;
        --no-revert)
            autoRevert=0
            shift
            ;;
        --overwrite-vhost)
            # Now the default. Kept so an existing cron job or script that
            # passes it keeps working rather than dying in getopt.
            updateVhostFlag=""
            shift
            ;;
        --no-vhost)
            updateVhostFlag="-F"
            shift
            ;;
        -y | --yes)
            autoYes="1"
            shift
            ;;
        --)
            shift
            break
            ;;
        *)
            echo "Error: unhandled option '$1'. This is an updater bug --"
            echo "please report it at https://github.com/FOGProject/fogproject/issues"
            exit 10
            ;;
    esac
done

# --hostname/--extra-server-name are requests for a vhost-VISIBLE change, so
# they override an explicit --no-vhost. Without this, createSSLCA() prints
# "Skipped" instead of writing the vhost: .fogsettings and the cert SAN would
# change (cert generation happens before the novhost check) while
# server_name/ServerAlias silently kept the old names -- a cert and a vhost
# that disagree about what this server is called.
#
# No longer needed for the common case now that regenerating is the default,
# but still required for the explicit --no-vhost + --hostname combination.
if [[ -n $supdatehostname || ${#supdateExtraServerNames[@]} -gt 0 ]]; then
    updateVhostFlag=""
fi

[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log="${workingdir}/error_logs/fog_update_error.log"
: > "$error_log"

# errorStat (lib/common/functions.sh) exits the process on any non-zero
# status unless $exitFail is set -- installfog.sh's default, since a failed
# install step should stop it. updatefog.sh needs the opposite: a failed git
# fetch/checkout/reset must return control to gitUpdateToBranch() so
# revertUpdate() can run, not kill the script out from under it. Deliberately
# NOT exported: the nested `bash installfog.sh` call below is a separate
# process and should keep errorStat's normal exit-on-failure behavior there.
exitFail=1

. ../lib/common/functions.sh

# Resolve which install this is updating -- same fog.conf pointer
# installfog.sh itself reads (GH-850), so this always finds the same install
# a fresh installfog.sh run on this box would.
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    echo " * updatefog.sh updates an EXISTING install -- run installfog.sh first."
    exit 1
fi

# .fogsettings before config.sh (the opposite order installfog.sh uses on a
# fresh install): there is always a prior install here, so its recorded
# osid/osname/docroot/etc. should win over config.sh's first-run defaults,
# not the other way around. linuxReleaseName_lower is derived from osname
# rather than re-detected, since only doOSSpecificIncludes' per-distro
# config.sh actually needs it here.
. "$fogprogramdir/.fogsettings"
linuxReleaseName_lower="${osname,,}"
. ../lib/common/config.sh
[[ -n $osid ]] && doOSSpecificIncludes >/dev/null
. ../lib/common/update.sh

# writeUpdateFile() (functions.sh) refreshes the "## Version:" comment line in
# .fogsettings as a side effect; installfog.sh derives this the same way at
# its own top, but updatefog.sh never sources that far into it.
[[ -z $version ]] && version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"

[[ -n $sgitpath ]] && fog_git_path="$sgitpath"

if [[ -n $sbranch ]]; then
    # --branch is a one-off deviation for testing, not a channel switch -- it
    # deliberately leaves fog_update_channel untouched, so a later run without
    # --branch goes right back to tracking whatever channel was configured.
    branch="$sbranch"
    echo " * FOG Update"
    echo "   Git path: $fog_git_path"
    echo "   Branch:   $branch (custom -- not a tracked channel)"
    echo
else
    [[ -n $schannel ]] && fog_update_channel="$schannel"

    if [[ -z $fog_update_channel ]]; then
        echo " * No update channel configured for this server, and none given via --channel."
        echo " * Pass --channel stable|staging|dev, or --branch for a one-off checkout."
        exit 1
    fi

    branch=$(channelToBranch "$fog_update_channel") || {
        echo " * Unknown update channel: $fog_update_channel (expected stable, staging, or dev)"
        exit 1
    }

    # Persist the resolved channel now, before touching git -- writeUpdateFile
    # merges just the managed keys (fog_git_path/fog_update_channel among them)
    # into the existing .fogsettings, leaving every other line as-is. Without
    # this, --channel only ever changed the channel for THIS run: the child
    # `installfog.sh` below re-sources the OLD value from .fogsettings and
    # writes that back, so the override never stuck for future unattended runs.
    writeUpdateFile

    echo " * FOG Update"
    echo "   Git path: $fog_git_path"
    echo "   Channel:  $fog_update_channel ($branch)"
    echo
fi

if [[ -z $autoYes ]]; then
    echo -n " * Continue with this update? (Y/N) "
    read confirmGo
    case $confirmGo in
        [Yy] | [Yy][Ee][Ss]) ;;
        *)
            echo " * Update cancelled."
            exit 0
            ;;
    esac
fi

backupCustomizations
if ! gitUpdateToBranch "$branch"; then
    echo " * Git update failed -- nothing was installed. See $error_log."
    exit 1
fi

extraServerNameArgs=()
for extraname in "${supdateExtraServerNames[@]}"; do
    extraServerNameArgs+=(--extra-server-name "$extraname")
done
(cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag ${supdatehostname:+--hostname "$supdatehostname"} "${extraServerNameArgs[@]}" >>$error_log 2>&1)
installStatus=$?
cd "$workingdir"

if [[ $installStatus -eq 0 ]]; then
    restoreCustomizations
    echo " * Update completed successfully."
    exit 0
fi

echo " * installfog.sh failed (exit $installStatus)."
if [[ $autoRevert -eq 1 ]]; then
    revertUpdate
    echo " * Reverted to the previous commit -- see $error_log for what failed."
    exit $installStatus
fi
echo " * --no-revert given; leaving the system as-is. See $error_log."
exit $installStatus
