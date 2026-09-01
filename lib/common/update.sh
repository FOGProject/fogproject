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
    # WHY THERE IS A LADDER HERE AND NOT JUST A RESET
    #
    # `git reset --hard` stays, and stays as the last rung, because the tree
    # genuinely can be unclean in ways an ordinary checkout cannot survive --
    # the reference case being a `chmod -R` over a parent directory, which
    # leaves every file looking modified to git and makes a plain pull fail.
    # Refusing there would strand exactly the server that most needs updating.
    #
    # What was wrong was doing it unconditionally and silently. A clean tree
    # needs no reset at all, and a dirty one deserves to have its contents
    # named before they are discarded -- an admin who edited something inside
    # the checkout should be able to read what went, not discover it later.
    #
    # So: clean tree, no reset. Dirty tree, say what is dirty, then reset.
    local dirty modecount total
    dirty=$(git -C "${FOG_git_path}" status --porcelain 2>>$error_log)

    dots "Checking out ${branch}"
    git -C "${FOG_git_path}" checkout "$branch" >>$error_log 2>&1
    st=$?
    errorStat $st
    if [[ $st -ne 0 && -z $dirty ]]; then
        # A clean tree that will not check out is not the case reset --hard
        # was put here for, and hiding a real git failure behind it would be
        # the worst of both.
        echo " * Could not check out ${branch}, and the working tree is clean --"
        echo " | so this is not a local-modification problem. See $error_log."
        return 1
    fi

    if [[ -z $dirty ]]; then
        # Nothing to discard. The reset would be a no-op against a tree that
        # already matches, so skip it and say nothing.
        dots "Fast-forwarding to origin/${branch}"
        git -C "${FOG_git_path}" merge --ff-only "origin/${branch}" >>$error_log 2>&1
        st=$?
        errorStat $st
        [[ $st -eq 0 ]] && return 0
        # A clean tree that will not fast-forward means the branch has been
        # rewritten upstream (a force-push, or a rebased rc-*). Falling through
        # to the reset is right, and now it is the only thing left to try.
        echo " * origin/${branch} is not a fast-forward from here, so the"
        echo " | checkout is being reset onto it."
    else
        total=$(printf '%s
' "$dirty" | grep -c .)
        # Permission-only noise is its own case and has its own fix. A
        # `chmod -R` over a parent leaves every tracked file modified with no
        # content change at all, and `core.fileMode false` addresses that
        # surgically -- worth saying, because the reset below will "fix" it
        # once and it will come back the next time someone runs chmod.
        modecount=$(git -C "${FOG_git_path}" diff --summary 2>/dev/null | grep -c 'mode change')
        echo " * The checkout has ${total} local change(s), which are about to be discarded:"
        printf '%s
' "$dirty" | sed 's/^/ |   /' | head -n 20
        [[ $total -gt 20 ]] && echo " |   ... and $((total - 20)) more"
        if [[ $modecount -gt 0 ]]; then
            echo " |"
            echo " | ${modecount} of these are permission changes with no content change,"
            echo " | which usually means a chmod ran over a parent directory. If that"
            echo " | keeps happening, git config --global core.fileMode false stops git"
            echo " | reporting it, and is a better fix than resetting every update."
        fi
        echo " |"
        echo " | The full list is in $error_log."
        printf '%s
' "$dirty" >> "$error_log"
    fi

    dots "Resetting to origin/${branch}"
    git -C "${FOG_git_path}" reset --hard "origin/${branch}" >>$error_log 2>&1
    st=$?
    errorStat $st
    return $st
}
