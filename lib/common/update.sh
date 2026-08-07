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
# Functions used only by bin/updatefog.sh: backing up/restoring the handful of
# files installfog.sh's ipxe asset sync can overwrite, and the git
# fetch/checkout/revert cycle around a channel update. Kept out of
# functions.sh, which installfog.sh alone already runs to nearly 5000 lines.
#
# All paths below are derived from $webdirdest (set by lib/common/utils.sh
# from docroot/webroot, both restored from .fogsettings before this file is
# sourced), never hardcoded -- see fog_git_updater.sh's history of assuming
# /var/www/html/fog for why that matters.
[[ -z $updateBackupDir ]] && updateBackupDir="${fogprogramdir}/update-backup"

# The custom PXE background and the kernel/init set installfog.sh's ipxe
# asset sync can silently overwrite with FOG's own defaults. refind is
# optional/legacy -- only backed up if actually present.
_updateAssetFiles() {
    echo "bg.png bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi"
}

backupCustomizations() {
    dots "Backing up customizations before update"
    local ipxedir="${webdirdest}service/ipxe" f st=0
    mkdir -p "$updateBackupDir" >>$error_log 2>&1 || st=1
    for f in $(_updateAssetFiles); do
        [[ -f "$ipxedir/$f" ]] && { cp -f "$ipxedir/$f" "$updateBackupDir/$f" >>$error_log 2>&1 || st=1; }
    done
    errorStat $st
}

# Success path: put the custom background and any refind files back over
# whatever installfog.sh just installed. The kernel set is deliberately left
# alone here -- the point of an update is to pick up the latest kernel; the
# backup stays on disk purely as the manual/revert fallback below.
restoreCustomizations() {
    dots "Restoring customizations after update"
    local ipxedir="${webdirdest}service/ipxe" f st=0
    for f in bg.png refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "$updateBackupDir/$f" ]] && { cp -f "$updateBackupDir/$f" "$ipxedir/$f" >>$error_log 2>&1 || st=1; }
    done
    chown -R ${username}:${apacheuser} "$ipxedir" >>$error_log 2>&1
    errorStat $st
}

# Revert path only: puts the previous kernel/init set back, on top of
# whatever restoreCustomizations() already restored.
_restorePreviousKernel() {
    dots "Restoring previous kernel set"
    local ipxedir="${webdirdest}service/ipxe" f st=0
    for f in bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz; do
        [[ -f "$updateBackupDir/$f" ]] && { cp -f "$updateBackupDir/$f" "$ipxedir/$f" >>$error_log 2>&1 || st=1; }
    done
    chown -R ${username}:${apacheuser} "$ipxedir" >>$error_log 2>&1
    errorStat $st
}

# Fetches, checks out, and hard-resets $fog_git_path to $1 (a branch name --
# the caller has already resolved this from either $fog_update_channel via
# channelToBranch, or a one-off --branch override). Sets $updatePrevCommit
# (module-global, read by revertUpdate below) to the commit HEAD was at
# before touching anything.
gitUpdateToBranch() {
    local branch="$1" st
    updatePrevCommit=$(git -C "$fog_git_path" rev-parse HEAD 2>>$error_log)
    dots "Fetching FOG (${branch})"
    git -C "$fog_git_path" fetch --all >>$error_log 2>&1
    st=$?
    errorStat $st
    [[ $st -ne 0 ]] && return 1
    dots "Checking out ${branch}"
    git -C "$fog_git_path" checkout "$branch" >>$error_log 2>&1
    st=$?
    errorStat $st
    [[ $st -ne 0 ]] && return 1
    dots "Resetting to origin/${branch}"
    git -C "$fog_git_path" reset --hard "origin/${branch}" >>$error_log 2>&1
    st=$?
    errorStat $st
    return $st
}

# Failure path: put the git checkout back where it was, re-run installfog.sh
# against the reverted commit, and ONLY THEN restore every backed up file
# (including the kernel set, unlike the success path).
#
# The restore must come AFTER the re-install, not before: installfog.sh's own
# asset sync is what can overwrite bg.png/the kernel set in the first place,
# and that re-install attempt can itself fail partway through -- after it has
# already re-overwritten those files but before it finishes. Restoring first
# and re-installing second left exactly that case with the fresh defaults
# still in place instead of the admin's customizations, which defeats the
# entire point of a revert. Restoring last guarantees the final state on disk
# has the customizations back no matter how the re-install attempt goes.
revertUpdate() {
    echo " * Reverting to the previous commit ($updatePrevCommit)"
    dots "Reverting git checkout"
    git -C "$fog_git_path" reset --hard "$updatePrevCommit" >>$error_log 2>&1
    errorStat $?
    dots "Re-running installfog.sh against the reverted commit"
    (cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag >>$error_log 2>&1)
    errorStat $?
    _restorePreviousKernel
    restoreCustomizations
}
