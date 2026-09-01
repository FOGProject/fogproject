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
# Moves an existing FOG install's working copy to another commit, then runs the
# installer against it.
#
# THAT IS THE WHOLE JOB, and the narrowness is deliberate. This script used to
# also back up the files installfog.sh's asset sync overwrites, restore them
# afterward, and git-revert the checkout when the install failed. All of that
# is gone:
#
#   * The backup and restore moved INTO installfog.sh -- see
#     backupPreservedCustomizations/restorePreservedCustomizations in
#     lib/common/functions.sh. Living here meant a bare `./installfog.sh`
#     upgrade, which is how most people upgrade, got no protection at all.
#
#   * The automatic revert went with them. installfog.sh now NAMES the commit
#     to go back to when a run fails (offerRevert) and leaves the decision to
#     the admin -- reverting meant re-running the installer on a box that had
#     just failed one, which is the least predictable moment to do the most
#     invasive thing. bin/restorekernel.sh covers the kernels; bin/revertfog.sh
#     covers a 1.5 rollback.
#
#   * Channel persistence moved to installfog.sh's --channel, which this
#     forwards. Doing it here meant writing .fogsettings BEFORE the checkout so
#     the child installer would not re-read the old value and write it back --
#     a sequencing trick that stops being needed once the installer owns the key.
#
# What is left is the one thing installfog.sh genuinely cannot do for itself:
# change which commit it is about to install.
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
    echo -e "Usage: $0 [-h?y] [--channel stable|patches|beta] [--branch <name>] [--git-path </path>]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --channel\tUpdate channel to track: stable, patches, or beta"
    echo -e "\t               \t\tdefaults to whatever this server already tracks."
    echo -e "\t               \t\tForwarded to installfog.sh, which records it"
    echo -e "\t      --branch\tCheck out an arbitrary branch instead of a channel"
    echo -e "\t               \t\t(e.g. to test a PR/feature branch). One-off: does"
    echo -e "\t               \t\tnot change the tracked channel for future runs"
    echo -e "\t      --git-path\tOverride the git checkout path this server records"
    echo -e "\t-y    --yes\t\tSkip the confirmation prompt (for cron/GUI use)"
    echo -e "\n\tEvery other install option belongs to installfog.sh and is no"
    echo -e "\tlonger mirrored here. Run it directly if you need one:"
    echo -e "\t  cd ${bindir} && ./installfog.sh --help"
    echo -e "\n\tWhat survives an update, and where to put customizations so"
    echo -e "\tthey do: docs/SUPPORTED_CUSTOMIZATIONS.md"
    exit 0
}

shortopts="h?y"
longopts="help,channel:,branch:,git-path:,yes"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

autoYes=""
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
        -y | --yes)
            autoYes=1
            shift
            ;;
        --)
            shift
            break
            ;;
    esac
done

# Un-exported on purpose: it inverts errorStat so a failed git step returns
# control here instead of ending the process, and it must NOT leak into the
# child installfog.sh, which needs errorStat to exit on failure as usual.
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
# Before the first read of a renamed key. On a .fogsettings written by a
# pre-GH-1120 installer FOG_os_name and FOG_os_id do not exist yet, and the
# doOSSpecificIncludes below is -n guarded, so without this the distro config
# is silently SKIPPED rather than failing loudly.
migrateDeprecatedKeys
linuxReleaseName_lower="${FOG_os_name,,}"
. ../lib/common/config.sh
[[ -n ${FOG_os_id} ]] && doOSSpecificIncludes >/dev/null
. ../lib/common/update.sh

[[ -n $sgitpath ]] && FOG_git_path="$sgitpath"

# Forwarded to installfog.sh only when the admin actually asked for a channel,
# so a plain re-run never restates one and cannot overwrite what the server
# already holds.
channelArgs=()

if [[ -n $sbranch ]]; then
    # --branch is a one-off deviation for testing, not a channel switch -- it
    # deliberately leaves FOG_update_channel untouched, so a later run without
    # --branch goes right back to tracking whatever channel was configured.
    branch="$sbranch"
    echo " * FOG Update"
    echo "   Git path: ${FOG_git_path}"
    echo "   Branch:   $branch (custom -- not a tracked channel)"
    echo
else
    [[ -n $schannel ]] && FOG_update_channel="$schannel"

    if [[ -z ${FOG_update_channel} ]]; then
        echo " * No update channel configured for this server, and none given via --channel."
        echo " * Pass --channel stable|patches|beta, or --branch for a one-off checkout."
        exit 1
    fi

    branch=$(channelToBranch "${FOG_update_channel}") || {
        # Names the retired spellings too. They still WORK -- normalizeChannel
        # folds them -- so anyone who reaches this line has a genuine typo, and
        # the two vocabularies were confusable enough to be worth a sentence.
        echo " * Unknown update channel: ${FOG_update_channel} (expected stable, patches, or beta)"
        echo " * The retired names staging and dev are still accepted, and mean patches and beta."
        exit 1
    }

    [[ -n $schannel ]] && channelArgs=(--channel "${FOG_update_channel}")

    echo " * FOG Update"
    echo "   Git path: ${FOG_git_path}"
    echo "   Channel:  ${FOG_update_channel} ($branch)"
    echo
fi

# Kept even though installfog.sh has a confirmation of its own, because it is
# invoked with -Y below and so never shows it. The checkout is this script's
# own destructive act and it happens BEFORE the installer runs at all, so this
# is the confirmation that matters.
if [[ -z $autoYes ]]; then
    echo -n " * Continue with this update? (Y/N) "
    read confirmGo
    case $confirmGo in
        [Yy] | [Yy][Ee][Ss]) ;;
        *)
            echo " * Update canceled."
            exit 0
            ;;
    esac
fi

if ! gitUpdateToBranch "$branch"; then
    echo " * Git update failed -- nothing was installed. See $error_log."
    exit 1
fi

# -Y because the confirmation above already happened. Everything else the
# installer does -- the backups, the restores, recording the channel, naming a
# commit to go back to if this fails -- is its own, and is identical whether or
# not this script invoked it.
(cd "${FOG_git_path}/bin" && bash installfog.sh -Y "${channelArgs[@]}" >>$error_log 2>&1)
installStatus=$?
cd "$workingdir"

if [[ $installStatus -eq 0 ]]; then
    echo " * Update completed successfully."
    exit 0
fi

# No revert. installfog.sh has already named the commit to go back to if there
# is one worth naming (offerRevert) -- but it printed that into $error_log,
# because this script redirects the child there, so say where to look.
echo " * installfog.sh failed (exit $installStatus)."
echo " * The system has NOT been reverted. See $error_log -- if the checkout"
echo " | moved, the installer named the commit to reset to at the end of it."
exit $installStatus
