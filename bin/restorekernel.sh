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
# Restores a previous kernel/init generation captured by
# backupPreservedCustomizations() (lib/common/functions.sh) under
# $fogprogramdir/customizations/kernel-backups/.
#
# Its own script rather than an installfog.sh flag because it is a rare,
# deliberate act with a blast radius of "every machine that PXE boots from
# here", and because it must be usable when an update has already replaced a
# working kernel with one that does not boot -- i.e. exactly when re-running
# the installer is the thing you do not want to do.
#
# See docs/SUPPORTED_CUSTOMIZATIONS.md.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "restorekernel.sh must be run as root user"
    exit 1
fi

usage() {
    echo -e "Usage: $0 [-h?] (--list | --generation <N>) [--yes]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --list\t\tShow each stored generation and the FOS release"
    echo -e "\t            \t\tits kernels came from"
    echo -e "\t      --generation\tRestore generation N into the live iPXE"
    echo -e "\t                 \t\tdirectory. 1 is the most recent snapshot,"
    echo -e "\t                 \t\ttaken at the START of the last install/update"
    echo -e "\t                 \t\t-- so it holds what was running BEFORE it"
    echo -e "\t-y    --yes\t\tSkip the confirmation prompt"
    exit 0
}

shortopts="h?y"
longopts="help,list,generation:,yes"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

doList=0
generation=""
autoYes=""
while :; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --list)
            doList=1
            shift
            ;;
        --generation)
            generation="$2"
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
        *)
            echo "Error: unhandled option '$1'."
            exit 10
            ;;
    esac
done

[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log="${workingdir}/error_logs/fog_restorekernel_error.log"
: > "$error_log"

# exitFail so a failing errorStat inside the sourced functions returns control
# here instead of killing the script mid-restore.
exitFail=1
. ../lib/common/functions.sh

[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    echo " * restorekernel.sh works on an EXISTING install -- run installfog.sh first."
    exit 1
fi
. "$fogprogramdir/.fogsettings"
# Before the first read of a renamed key. A .fogsettings written by a
# pre-GH-1120 installer has osid/osname, not FOG_os_id/FOG_os_name, and the
# doOSSpecificIncludes below is -n guarded -- so without this the distro config
# is silently SKIPPED, $webdirdest stays empty, and $ipxedir below becomes the
# relative string "service/ipxe". That is the exact bug the comment further
# down says ca02e0b9e already fixed once.
migrateDeprecatedKeys
# .fogsettings records docroot and webroot but NOT webdirdest -- config.sh
# derives that ("${WEB_docroot}fog/"). Sourcing .fogsettings alone therefore left
# $webdirdest empty and $ipxedir as the relative string "service/ipxe", so
# --list mislabelled everything and --generation would have copied the restore
# into a stray directory under bin/ instead of the live tree.
#
# Same ordering as bin/updatefog.sh, and for the same reason: .fogsettings
# first so the recorded values win, then config.sh to derive what it does not
# record. This is the bug commit ca02e0b9e fixed in setupacme.sh.
linuxReleaseName_lower="${FOG_os_name,,}"
. ../lib/common/config.sh
[[ -n ${FOG_os_id} ]] && doOSSpecificIncludes >/dev/null

kbdir="${fogprogramdir}/customizations/kernel-backups"
ipxedir="${webdirdest}service/ipxe"
if [[ ! -d $ipxedir ]]; then
    echo " * Could not locate the live iPXE directory (looked in '${ipxedir}')."
    echo " * Check docroot/webroot in $fogprogramdir/.fogsettings."
    exit 1
fi

if [[ ! -d $kbdir ]]; then
    echo " * No kernel backups yet at $kbdir."
    echo " * They are written at the start of each install/update, so the first"
    echo "   generation appears after the next one."
    exit 1
fi

# Reports the FOS release a file came from using the xattr downloadfiles()
# stamps at download time, so a generation is self-describing and there is no
# manifest to drift out of sync. Older files, or a filesystem mounted without
# user_xattr, simply have none.
tagof() {
    local t
    t=$(attr -q -g tag_name "$1" 2>/dev/null) || t=""
    [[ -z $t ]] && t="unknown release"
    echo "$t"
}

listGenerations() {
    local gendir found=0 f
    for gendir in "$kbdir"/gen-*; do
        [[ -d $gendir ]] || continue
        found=1
        echo "  $(basename "$gendir"):"
        for f in "$gendir"/bzImage "$gendir"/bzImage32 "$gendir"/arm_Image; do
            [[ -f $f ]] || continue
            echo "    $(basename "$f") ($(tagof "$f"))"
        done
        # A file counts as the admin's only if the live tree does NOT have one
        # of that name -- service/ipxe is full of FOG's own boot.php,
        # advanced.php, bgdark.png and kernel siblings, and calling those
        # "custom" is both misleading here and, in restorePreservedCustomizations,
        # was actively harmful.
        local shown=0
        for f in "$gendir"/*; do
            [[ -f $f ]] || continue
            [[ -e "${ipxedir}/$(basename "$f")" ]] && continue
            [[ $shown -eq 0 ]] && { echo "    not present in the live tree (restored automatically):"; shown=1; }
            echo "      $(basename "$f")"
        done
    done
    [[ $found -eq 0 ]] && echo "  (none yet)"
}

if [[ $doList -eq 1 ]]; then
    echo " * Kernel/init generations under $kbdir:"
    listGenerations
    exit 0
fi

if [[ -z $generation ]]; then
    echo " * Pass --list to see what is stored, or --generation <N> to restore."
    usage
fi
if [[ ! $generation =~ ^[0-9]+$ || $generation -lt 1 ]]; then
    echo " * --generation takes a positive integer (1 is the most recent)."
    exit 1
fi

gendir="${kbdir}/gen-${generation}"
if [[ ! -d $gendir ]]; then
    echo " * No such generation: $gendir"
    echo " * Available:"
    listGenerations
    exit 1
fi
if [[ ! -d $ipxedir ]]; then
    echo " * Live iPXE directory not found at $ipxedir."
    exit 1
fi

echo " * About to restore gen-${generation} into ${ipxedir}:"
for f in "$gendir"/*; do
    [[ -f $f ]] || continue
    echo "     $(basename "$f") ($(tagof "$f"))"
done
echo
echo " * Every machine that PXE boots from this server will use these files."
if [[ -z $autoYes ]]; then
    echo -n " * Continue? (y/N) "
    read -r reply
    case $reply in
        [Yy]|[Yy][Ee][Ss]) ;;
        *) echo " * Aborted."; exit 0 ;;
    esac
fi

# cp -a to carry the version/tag_name xattrs across, so the restored files
# still report which release they came from and a later --list stays honest.
dots "Restoring gen-${generation}"
st=0
cp -a "${gendir}/." "${ipxedir}/" >>$error_log 2>&1 || st=1
if [[ $st -ne 0 ]]; then
    echo "Failed"
    echo " * Could not copy the generation into place. See $error_log."
    exit 1
fi
chown -R ${SVC_user}:${apacheuser} "$ipxedir" >>$error_log 2>&1
errorStat 0

# The restored kernels carry whatever signature they had when they were
# snapshotted. If the Secure Boot signing key has been rotated since, that
# signature no longer verifies against the current certificate and the client
# refuses to boot -- so re-sign rather than leave a subtly broken set behind.
# _resignKernels() skips anything already carrying a valid signature, so this
# is a no-op in the common case where the key has not changed.
if [[ -n ${PKI_sb_codesign_key} && -n ${PKI_sb_codesign_cert} ]]; then
    _resignKernels
fi

echo
echo " * gen-${generation} restored."
echo " * If a client is mid-task it will still be using the previous files;"
echo "   re-deploy or reboot it to pick these up."
