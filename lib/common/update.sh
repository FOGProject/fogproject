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
# Functions used only by bin/updatefog.sh: the git fetch/checkout/revert cycle
# around a channel update. Kept out of functions.sh, which installfog.sh alone
# already runs to nearly 5000 lines.
#
# This file used to also own backing up and restoring the files installfog.sh's
# asset sync overwrites (_updateAssetFiles, backupCustomizations,
# restoreCustomizations, _restorePreviousKernel). Those are gone, and the job
# moved into installfog.sh itself -- see backupPreservedCustomizations and
# restorePreservedCustomizations in lib/common/functions.sh. Living here meant
# a bare `./installfog.sh` upgrade, which is how most people upgrade, got no
# protection at all; and the old list was hardcoded to "bg.png" rather than
# reading the FOG_IPXE_BG_FILE setting whose entire purpose is renaming that
# file.

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

# Failure path: put the git checkout back where it was and re-run
# installfog.sh against the reverted commit.
#
# The restore used to happen here, after the re-install, because installfog.sh
# was what overwrote bg.png and the kernel set and could itself fail partway
# through -- restoring first and re-installing second left the fresh defaults
# in place instead of the admin's customizations.
#
# That ordering problem is gone: installfog.sh now backs up and restores
# within its own run (backupPreservedCustomizations /
# restorePreservedCustomizations), so however the re-install attempt goes, it
# is the thing that puts the customizations back. --restore-kernel-backup is
# the one addition the revert path needs, telling that run to also roll the
# default-named kernel/init set back -- an older commit wants the older
# kernels, which a normal update deliberately does not do.
revertUpdate() {
    echo " * Reverting to the previous commit ($updatePrevCommit)"
    dots "Reverting git checkout"
    git -C "$fog_git_path" reset --hard "$updatePrevCommit" >>$error_log 2>&1
    errorStat $?
    dots "Re-running installfog.sh against the reverted commit"
    (cd "$fog_git_path/bin" && bash installfog.sh -Y $updateVhostFlag --restore-kernel-backup >>$error_log 2>&1)
    errorStat $?
}
