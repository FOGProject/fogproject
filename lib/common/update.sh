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
# The one function used only by bin/updatefog.sh: the git fetch/checkout that
# moves a working copy onto a channel branch. Kept out of functions.sh, which
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

# Fetches, checks out, and hard-resets ${FOG_git_path} to $1 (a branch name --
# the caller has already resolved this from either ${FOG_update_channel} via
# channelToBranch, or a one-off --branch override).
#
# Sets $updatePrevCommit to the commit HEAD was at before touching anything.
# Nothing reads it any more: revertUpdate() is gone, and installfog.sh names
# its own commit to go back to via offerRevert(), from FOG_last_good_commit in
# .fogsettings -- which is a better source, because it is the last commit that
# actually INSTALLED cleanly rather than merely the last one checked out. Kept
# because it costs one cheap call and is what a caller would reach for.
gitUpdateToBranch() {
    local branch="$1" st
    updatePrevCommit=$(git -C "${FOG_git_path}" rev-parse HEAD 2>>$error_log)
    dots "Fetching FOG (${branch})"
    git -C "${FOG_git_path}" fetch --all >>$error_log 2>&1
    st=$?
    errorStat $st
    [[ $st -ne 0 ]] && return 1
    dots "Checking out ${branch}"
    git -C "${FOG_git_path}" checkout "$branch" >>$error_log 2>&1
    st=$?
    errorStat $st
    [[ $st -ne 0 ]] && return 1
    dots "Resetting to origin/${branch}"
    git -C "${FOG_git_path}" reset --hard "origin/${branch}" >>$error_log 2>&1
    st=$?
    errorStat $st
    return $st
}
