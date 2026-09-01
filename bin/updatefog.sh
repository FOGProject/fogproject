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
# Moves this 1.5 server's checkout onto another FOG line and runs the installer
# it finds there. Its reason to exist is the 1.5 -> 1.6 crossing, and the
# channel most people will pass is rc.
#
# WHY THIS IS STANDALONE, AND WILL STAY THAT WAY
#
# The 1.6 line has a bin/updatefog.sh that shares a channel map, a set of
# managed .fogsettings keys and a pile of helper functions with the rest of the
# installer. None of that exists on 1.5, and back-porting it would mean surgery
# on lib/common/functions.sh on a line that is heading for end of life -- every
# edit of which risks the 1.5 installs it is supposed to help.
#
# So this file sources nothing, and duplicates the small amount it needs. It
# WILL diverge from the 1.6 implementation, and that is fine: this branch is
# terminal and does not track 1.6's future. What matters is that it can carry
# a server across, once.
#
# THE HAZARD THIS SCRIPT IS BUILT AROUND
#
# It replaces itself. Checking out 1.6 rewrites bin/updatefog.sh underneath a
# bash process that is still reading it -- bash reads a script incrementally
# and seeks by byte offset, so a file that changes length mid-run makes it
# resume in the middle of a different line. The failure is silent, arbitrary,
# and happens after the checkout has already succeeded.
#
# So the first thing this does is copy itself somewhere git will not touch and
# re-exec from there. Everything after the re-exec is running from a file
# nothing is going to rewrite. See relaunchFromCopy().
#
# Exit codes:
#   1 not root, or no install found     3 bad argument
#   6 git failed                        7 no terminal for an interactive install

bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd "$bindir"
workingdir=$(pwd)

usage() {
    echo -e "Usage: $0 [-h?y] [--channel rc|beta|stable|patches] [--branch <name>]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --channel\tWhich line to move this server to:"
    echo -e "\t               \t\t  rc      the current 1.6 release candidate"
    echo -e "\t               \t\t  beta    the 1.6 development line"
    echo -e "\t               \t\t  stable  the 1.5 stable line (where you are)"
    echo -e "\t               \t\t  patches the 1.5 patches line"
    echo -e "\t               \t\tDefaults to rc"
    echo -e "\t      --branch\tCheck out a literal branch instead of a channel"
    echo -e "\t-y    --yes\t\tSkip the confirmation AND run the installer"
    echo -e "\t               \t\tunattended. NOT recommended for the crossing to"
    echo -e "\t               \t\t1.6 -- that upgrade asks questions 1.5 never did"
    echo -e "\n\tGoing from 1.5 to 1.6 is a MAJOR upgrade. Take a backup first."
    echo -e "\tThe 1.6 tree carries bin/revertfog.sh, which puts a server back on"
    echo -e "\tits pre-upgrade 1.5 database and web tree from the dump the"
    echo -e "\tinstaller takes. That dump is the only supported way back."
    exit 0
}

fail() {
    echo
    echo " * $1"
    shift
    while [[ $# -gt 1 ]]; do
        echo " | $1"
        shift
    done
    echo
    exit "$1"
}

# ---------------------------------------------------------------------------
# Re-exec from a copy, before anything can rewrite this file. See the header.
#
# FOG_UPDATE_RELAUNCHED marks the copy so it does not do this again. The copy
# is removed on exit by the trap below, not here -- it is the file currently
# being read.
# ---------------------------------------------------------------------------
relaunchFromCopy() {
    local copy
    copy=$(mktemp -t fog-updatefog.XXXXXX) || return 1
    cat "$0" > "$copy" || { rm -f "$copy"; return 1; }
    chmod +x "$copy"
    FOG_UPDATE_RELAUNCHED=1 FOG_UPDATE_ORIGIN="$workingdir" \
        FOG_UPDATE_COPY="$copy" exec bash "$copy" "$@"
}


# Kept before the option loop consumes them: the relaunch below re-execs with
# the ORIGINAL arguments, and by then "$@" has been shifted empty.
origArgs=("$@")

repo="https://github.com/FOGProject/fogproject.git"
channel="rc"
branch=""
autoYes=""
sgitpath=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -h | -\? | --help) usage ;;
        --channel)
            [[ -n $2 ]] || fail "--channel requires a value" 3
            channel="$2"; shift 2 ;;
        --branch)
            [[ -n $2 ]] || fail "--branch requires a value" 3
            branch="$2"; shift 2 ;;
        --git-path)
            [[ -n $2 && $2 == /* ]] || fail "--git-path requires an absolute path" 3
            sgitpath="${2%/}"; shift 2 ;;
        -y | --yes) autoYes=1; shift ;;
        *) fail "Unknown option: $1" "Run with --help for the list." 3 ;;
    esac
done

# Parsing comes first so --help works for anyone, and a typo is answered
# before the user is told to go and find root.
if [[ ! $EUID -eq 0 ]]; then
    echo "FOG updates must be run as root user"
    exit 1
fi

if [[ -z $FOG_UPDATE_RELAUNCHED ]]; then
    relaunchFromCopy "${origArgs[@]}" || fail "Could not copy this script to a temporary location." \
        "The update has to run from a copy, because checking out another branch" \
        "rewrites this file while bash is still reading it." 1
fi
[[ -n $FOG_UPDATE_COPY ]] && trap 'rm -f "$FOG_UPDATE_COPY"' EXIT
# After the re-exec, $bindir is the temp directory. The checkout is where the
# ORIGINAL copy lived.
workingdir="${FOG_UPDATE_ORIGIN:-$workingdir}"
gitpath=$(cd "$workingdir/.." && pwd)
[[ -n $sgitpath ]] && gitpath="$sgitpath"

# ---------------------------------------------------------------------------
# The 1.6 channel vocabulary, copied. Retired spellings honoured exactly as
# lib/common/functions.sh on working-1.6 honours them -- an admin pasting a
# command from an older forum post should get a working upgrade.
#
# Note `dev` means BETA, not dev-branch, despite a branch by that name. That
# is the 1.6 vocabulary and this must not invent a different one.
# ---------------------------------------------------------------------------
normalizeChannel() {
    case "$1" in
        stable) echo "stable" ;;
        patches|staging) echo "patches" ;;
        beta|dev) echo "beta" ;;
        rc) echo "rc" ;;
        *) return 1 ;;
    esac
}

# Asked of the remote, by version order. -v:refname so rc-1.6.10 beats
# rc-1.6.2; the remote advertises no commit dates, so a "newest" answer could
# not be date-based even if that were preferable.
rcBranch() {
    local ref
    ref=$(git ls-remote --heads --sort=-v:refname "$repo" 'rc-*' 2>/dev/null | head -n1) || return 1
    [[ -n $ref ]] || return 1
    ref="${ref##*refs/heads/}"
    [[ -n $ref ]] || return 1
    echo "$ref"
}

channelToBranch() {
    case "$(normalizeChannel "$1")" in
        stable) echo "stable" ;;
        patches) echo "dev-branch" ;;
        beta) echo "working-1.6" ;;
        rc) rcBranch || return 2 ;;
        *) return 1 ;;
    esac
}

# ---------------------------------------------------------------------------
# Find the install. 1.5 has no fog_git_path, so the checkout is simply where
# this script lives -- which is what the pre-relaunch $workingdir recorded.
# ---------------------------------------------------------------------------
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    fail "No existing FOG install found at ${fogprogramdir} (.fogsettings missing)." \
         "This updates an EXISTING install. For a new one, run installfog.sh." 1
fi

if [[ ! -d "${gitpath}/.git" ]]; then
    fail "${gitpath} is not a git checkout." \
         "This server was installed from a tarball or a copied directory, so" \
         "there is no branch to move. Use the bootstrap installer instead --" \
         "it clones a checkout and runs the installer over this install, which" \
         "finds the existing server through /etc/fog/fog.conf:" \
         "" \
         "  curl -fsSL https://raw.githubusercontent.com/FOGProject/fogproject/working-1.6/bin/bootstrap.sh | bash -s -- --channel ${channel}" 1
fi

if [[ -z $branch ]]; then
    branch=$(channelToBranch "$channel")
    case $? in
        0) ;;
        2) fail "No release candidate is currently published, so the rc channel" \
                "has nothing to check out. This is normal between releases." \
                "" \
                "Use --channel beta for the 1.6 development line, or wait." 3 ;;
        *) fail "Unknown channel: ${channel}" \
                "Expected rc, beta, stable or patches. The retired names staging" \
                "and dev are also accepted, and mean patches and beta." 3 ;;
    esac
fi

# ---------------------------------------------------------------------------
# Say what this is before doing it. A 1.5 -> 1.6 move is not an update.
# ---------------------------------------------------------------------------
currentBranch=$(git -C "$gitpath" rev-parse --abbrev-ref HEAD 2>/dev/null)
currentCommit=$(git -C "$gitpath" rev-parse HEAD 2>/dev/null)

echo " * FOG Update"
echo "   Checkout: ${gitpath}"
echo "   Now on:   ${currentBranch:-unknown}"
echo "   Moving to: ${branch}"
echo

crossing=0
case $branch in
    working-1.6|rc-*) [[ $currentBranch != working-1.6 && $currentBranch != rc-* ]] && crossing=1 ;;
esac

if [[ $crossing -eq 1 ]]; then
    echo " * This is a MAJOR upgrade, 1.5 to 1.6, not a patch."
    echo " |"
    echo " | The database schema is migrated forward and the web tree is"
    echo " | replaced. The 1.6 installer takes a database dump before it starts,"
    echo " | and the 1.6 tree carries bin/revertfog.sh, which uses that dump to"
    echo " | put the server back. That dump is the ONLY supported way back --"
    echo " | there is no down-migration and there will not be one."
    echo " |"
    echo " | Take your own backup as well, and read"
    echo " | docs/SUPPORTED_CUSTOMIZATIONS.md in the 1.6 tree afterwards."
    echo
fi

if [[ -z $autoYes ]]; then
    echo -n " * Continue? (Y/N) "
    read confirmGo
    case $confirmGo in
        [Yy] | [Yy][Ee][Ss]) ;;
        *) echo " * Canceled."; exit 0 ;;
    esac
fi

# ---------------------------------------------------------------------------
# Move the checkout. Safe to do now: this process is running from the copy.
# ---------------------------------------------------------------------------
echo " * Fetching"
git -C "$gitpath" fetch --all || fail "git fetch failed." 6
echo " * Checking out ${branch}"
git -C "$gitpath" checkout "$branch" || fail "Could not check out ${branch}." 6
git -C "$gitpath" reset --hard "origin/${branch}" || fail "Could not reset to origin/${branch}." 6

if [[ ! -x "${gitpath}/bin/installfog.sh" && ! -f "${gitpath}/bin/installfog.sh" ]]; then
    fail "${branch} has no bin/installfog.sh." \
         "Nothing has been installed. To go back:" \
         "  git -C ${gitpath} reset --hard ${currentCommit}" 6
fi

# ---------------------------------------------------------------------------
# Hand over to the installer that is now on disk -- which for a crossing is
# 1.6's, not the one this script shipped beside.
#
# INTERACTIVE unless --yes. This is the case the whole file exists for, and it
# is exactly the case where -Y is wrong: the 1.6 installer asks about settings
# 1.5's .fogsettings has never held, and unattended it takes a default for each
# of them without saying so.
# ---------------------------------------------------------------------------
if [[ -n $autoYes ]]; then
    echo " * Starting the installer unattended"
    (cd "${gitpath}/bin" && bash installfog.sh -Y)
    installStatus=$?
else
    if ! (exec < /dev/tty) 2>/dev/null; then
        fail "No terminal is available, so the installer cannot run interactively." \
             "The checkout has already been moved to ${branch}; only the install" \
             "did not start. Re-run this from a terminal, or with --yes." \
             "" \
             "To put the checkout back:" \
             "  git -C ${gitpath} reset --hard ${currentCommit}" 7
    fi
    echo " * Starting the installer"
    echo
    (cd "${gitpath}/bin" && bash installfog.sh < /dev/tty)
    installStatus=$?
fi

if [[ $installStatus -eq 0 ]]; then
    echo
    echo " * Update completed successfully."
    [[ $crossing -eq 1 ]] && echo " * This server is now on FOG 1.6. From here, use its own bin/updatefog.sh."
    exit 0
fi

echo
echo " * installfog.sh failed (exit ${installStatus})."
echo " | Nothing has been reverted. To put the checkout back where it was:"
echo " |"
echo " |     git -C ${gitpath} reset --hard ${currentCommit}"
echo " |     cd ${gitpath}/bin && ./installfog.sh"
echo " |"
if [[ $crossing -eq 1 ]]; then
    echo " | If the database was already migrated, the checkout alone is not"
    echo " | enough -- use bin/revertfog.sh from the 1.6 tree, which restores the"
    echo " | pre-upgrade dump as well."
    echo " |"
fi
exit "$installStatus"
