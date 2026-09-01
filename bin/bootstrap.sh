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
# GH-1006. Gets FOG onto a machine that has nothing on it yet:
#
#   curl -fsSL https://raw.githubusercontent.com/FOGProject/fogproject/working-1.6/bin/bootstrap.sh | bash
#
# Installs git, clones the repository, checks out the branch for the requested
# channel, and runs installfog.sh. Nothing else -- once the clone exists,
# installfog.sh and bin/updatefog.sh own everything that happens after.
#
# WHY THIS FILE DUPLICATES detectOSFamily() AND THE CHANNEL MAP
#
# It has to be self-contained. It runs BEFORE there is a clone, so it cannot
# source lib/common/functions.sh -- the file does not exist on the machine yet,
# and fetching 300+ KB of installer over HTTP to reach twelve lines of
# /etc/os-release parsing would be worse than copying the twelve lines.
#
# The original design for this script called for extracting the detection into
# functions.sh "so bootstrap.sh and installfog.sh share one implementation".
# That is not achievable in the pre-clone half and pretending otherwise would
# have produced a script that could not run. The extraction was worth doing on
# its own merits and was done -- installfog.sh and lib/common/input.sh now both
# call detectOSFamily() instead of carrying a copy each -- but this file is not
# one of its callers. tests/detect-os-family.test.sh runs BOTH copies over the
# same distro names and fails if they ever disagree, which is the honest way to
# hold two implementations together when one of them cannot import the other.
#
# NO `set -e`, matching every other script in this repository. Failures are
# handled where they happen, so a partial failure can say what it was.
#
# Exit codes match installfog.sh's where the failure is the same one:
#   1 not root        2 not Linux       3 bad argument
#   4 unsupported distribution          5 could not install git
#   6 clone or checkout failed          7 no terminal for an interactive install

version="1.0.0"

usage() {
    echo -e "Usage: curl -fsSL <url>/bootstrap.sh | bash -s -- [options]"
    echo -e "       $0 [options]\n"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --channel\t\tWhich line to install: stable, patches, beta"
    echo -e "\t              \t\tor rc. Defaults to stable"
    echo -e "\t      --branch\t\tCheck out a literal branch or tag instead of a"
    echo -e "\t              \t\tchannel, for testing a PR or feature branch"
    echo -e "\t      --git-path\tWhere to clone to. Defaults to /root/fogproject"
    echo -e "\t-y    --yes\t\tRun the installer unattended (-Y). Without it the"
    echo -e "\t              \t\tinstaller runs interactively, which is what a"
    echo -e "\t              \t\tfirst install wants. Pass it from Ansible/cloud-init"
    echo -e "\n\tThis script only gets FOG onto the machine. Afterward,"
    echo -e "\tbin/updatefog.sh moves it between channels and bin/installfog.sh"
    echo -e "\tdoes everything else."
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

repo="https://github.com/FOGProject/fogproject.git"
gitpath="/root/fogproject"
channel="stable"
branch=""
autoYes=""

# Hand-rolled rather than getopt(1), because getopt is part of util-linux and
# this script runs before any package has been installed. On a minimal image it
# is usually there and occasionally is not, and a bootstrap that dies parsing
# its own arguments is the worst possible first impression.
while [[ $# -gt 0 ]]; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --channel)
            [[ -n $2 ]] || fail "--channel requires a value" 3
            channel="$2"
            shift 2
            ;;
        --branch)
            [[ -n $2 ]] || fail "--branch requires a value" 3
            branch="$2"
            shift 2
            ;;
        --git-path)
            [[ -n $2 && $2 == /* ]] || fail "--git-path requires an absolute path" 3
            gitpath="${2%/}"
            shift 2
            ;;
        -y | --yes)
            autoYes=1
            shift
            ;;
        *)
            fail "Unknown option: $1" "Run with --help for the list." 3
            ;;
    esac
done

if [[ ! $EUID -eq 0 ]]; then
    # Same message and same exit code as installfog.sh's own root check. No
    # auto-elevation: piping a script from the internet into a shell that then
    # reaches for sudo by itself is precisely the shape nobody should get used
    # to trusting.
    echo "FOG Installation must be run as root user"
    exit 1
fi

[[ -z $OS ]] && OS=$(uname -s)
if [[ ! $(echo "$OS" | tr [:upper:] [:lower:]) =~ "linux" ]]; then
    echo "We do not currently support Installation on non-Linux Operating Systems"
    exit 2
fi

# ---------------------------------------------------------------------------
# Which distro family, and therefore which package manager.
#
# A copy of detectOSFamily() from lib/common/functions.sh -- see the note at the
# top of this file for why it cannot be the same copy, and
# tests/detect-os-family.test.sh for what keeps the two honest.
# ---------------------------------------------------------------------------
detectOSFamily() {
    if [[ -f /etc/os-release ]]; then
        [[ -z $linuxReleaseName ]] && linuxReleaseName=$(sed -n 's/^NAME=\(.*\)/\1/p' /etc/os-release | tr -d '"')
        [[ -z $OSVersion ]] && OSVersion=$(sed -n 's/^VERSION_ID=\([^.]*\).*/\1/p' /etc/os-release | tr -d '"')
    elif [[ -f /etc/redhat-release ]]; then
        [[ -z $linuxReleaseName ]] && linuxReleaseName=$(cat /etc/redhat-release | awk '{print $1}')
        [[ -z $OSVersion ]] && OSVersion=$(cat /etc/redhat-release | sed s/.*release\ // | sed s/\ .*// | awk -F. '{print $1}')
    elif [[ -f /etc/debian_version ]]; then
        [[ -z $linuxReleaseName ]] && linuxReleaseName='Debian'
        [[ -z $OSVersion ]] && OSVersion=$(cat /etc/debian_version)
    fi
    linuxReleaseName_lower=$(echo "$linuxReleaseName" | tr [:upper:] [:lower:])
    osfamily=""
    case $linuxReleaseName_lower in
        *fedora*|*red*hat*|*centos*|*mageia*|*alma*|*rocky*)
            osfamily="redhat"
            ;;
        *ubuntu*|*bian*|*mint*)
            osfamily="debian"
            ;;
        *alpine*)
            osfamily="alpine"
            ;;
        *arch*|*manjaro*)
            osfamily="arch"
            ;;
        *)
            return 1
            ;;
    esac
    return 0
}

# ---------------------------------------------------------------------------
# The channel map, likewise a copy -- of channelToBranch()/normalizeChannel()
# in lib/common/functions.sh. The retired stable/staging/dev spellings are
# honored here exactly as they are there, silently and forever: an admin who
# pastes a command from an older forum post should get a working install, not a
# lecture about vocabulary.
#
# Note what `dev` means. It is the retired spelling of BETA, not of dev-branch,
# despite a branch by that name existing -- see normalizeChannel's comment.
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

# Asked of the remote with ls-remote, which needs no clone -- the reason the
# rc channel is resolved by version order rather than commit date at all, since
# the remote ref advertisement carries no dates. -v:refname so rc-1.6.10 beats
# rc-1.6.2.
# refs/heads/rc-*, NOT a bare rc-*. ls-remote matches a pattern against the
# TAIL of each ref at slash boundaries, so `rc-*` also matches
# refs/heads/feat/rc-update-channel -- a feature branch, offered to an admin as
# the current release candidate. Anchoring at refs/heads/ makes "rc-" mean the
# start of the branch name rather than the start of its last path segment, and
# the sed re-checks that on the way out: matching rules are not the place to
# rely on one layer alone when the answer decides what gets checked out.
rcBranch() {
    local ref
    ref=$(git ls-remote --heads --sort=-v:refname "$repo" 'refs/heads/rc-*' 2>/dev/null \
        | sed -n 's#^[0-9a-f]\{7,\}[[:space:]]\{1,\}refs/heads/\(rc-[^/]\{1,\}\)$#\1#p' \
        | head -n1) || return 1
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
# Refuse an unsupported distribution UP FRONT.
#
# The alternative -- install git, clone, and let installfog.sh discover it --
# leaves a machine with packages and a checkout it cannot use, and tells the
# person about it several minutes later.
# ---------------------------------------------------------------------------
if ! detectOSFamily; then
    fail "FOG does not support this distribution: ${linuxReleaseName:-unknown}" \
         "Supported: Red Hat family (RHEL, CentOS, Rocky, Alma, Fedora, Mageia)," \
         "Debian family (Debian, Ubuntu, Mint), Alpine, and Arch (incl. Manjaro)." \
         4
fi

installGit() {
    command -v git >/dev/null 2>&1 && return 0
    echo " * Installing git (${osfamily})"
    case $osfamily in
        debian)
            apt-get -yq update
            apt-get -yq install git
            ;;
        redhat)
            # dnf where it exists, yum where it does not -- the same fallback
            # installfog.sh uses for its own package work on this family.
            if command -v dnf >/dev/null 2>&1; then
                dnf -y install git
            else
                yum -y install git
            fi
            ;;
        alpine)
            apk add --no-cache git
            ;;
        arch)
            pacman -Sy --noconfirm git
            ;;
    esac
    command -v git >/dev/null 2>&1
}

if ! installGit; then
    fail "Could not install git with this system's package manager." \
         "Install it by hand and run this again." 5
fi

# ---------------------------------------------------------------------------
# Resolve the branch before touching the disk, so a bad channel costs nothing.
# ---------------------------------------------------------------------------
# Whether the branch was resolved FROM a channel, which is what decides if
# --channel gets forwarded. $branch is non-empty either way by the time the
# installer runs, so it cannot answer this itself.
fromChannel=0
if [[ -z $branch ]]; then
    fromChannel=1
    branch=$(channelToBranch "$channel")
    case $? in
        0) ;;
        2)
            fail "No release candidate is currently published, so the rc channel" \
                 "has nothing to install. This is normal between releases." \
                 "" \
                 "Install the beta line instead with --channel beta." 3
            ;;
        *)
            fail "Unknown channel: ${channel}" \
                 "Expected stable, patches, beta or rc." \
                 "The retired names staging and dev are also accepted, and mean" \
                 "patches and beta respectively." 3
            ;;
    esac
fi

# ---------------------------------------------------------------------------
# Clone, or leave an existing checkout alone.
#
# Idempotent by refusing to be clever: if there is already a .git here, this
# script's job is done and it has no business resetting, pulling, or deleting
# somebody's working tree. bin/updatefog.sh is the thing that moves an existing
# checkout between channels, and it knows how to do it safely.
# ---------------------------------------------------------------------------
if [[ -d ${gitpath}/.git ]]; then
    echo " * ${gitpath} is already a git checkout -- not cloning over it."
    echo " | To move an existing install to another channel, use:"
    echo " |   cd ${gitpath}/bin && ./updatefog.sh --channel ${channel}"
    echo
else
    if [[ -e $gitpath && -n $(ls -A "$gitpath" 2>/dev/null) ]]; then
        fail "${gitpath} exists and is not empty, but is not a git checkout." \
             "Move it aside, or choose another path with --git-path." 6
    fi
    echo " * Cloning FOG into ${gitpath}"
    git clone "$repo" "$gitpath" || fail "git clone failed." 6
fi

echo " * Checking out ${branch}"
git -C "$gitpath" checkout "$branch" || fail "Could not check out ${branch}." \
    "If this is a channel, the branch may have been renamed; if you passed" \
    "--branch, check the name." 6

# ---------------------------------------------------------------------------
# Hand over to the installer.
#
# < /dev/tty, and this is the whole reason it is here: under
# `curl ... | bash`, this script's stdin IS THE PIPE. Every read in
# installfog.sh would take the remaining bytes of this file, or EOF, and answer
# its own prompts with them. Reattaching the terminal is what makes an
# interactive install possible at all from a one-liner.
#
# With no terminal and no --yes, this REFUSES rather than quietly falling back
# to -Y. An unattended install of imaging software, as root, on a machine
# nobody is watching, is not something to start because a tty happened to be
# missing -- it is something to ask for.
# ---------------------------------------------------------------------------
# Forwarded only when a channel was actually chosen. --branch is a one-off for
# testing a PR, exactly as it is in updatefog.sh: it must not leave the server
# recording a channel it is not on.
channelArgs=()
[[ $fromChannel -eq 1 ]] && channelArgs=(--channel "$channel")

if [[ -n $autoYes ]]; then
    echo " * Starting the installer unattended"
    (cd "${gitpath}/bin" && bash installfog.sh -Y "${channelArgs[@]}")
    exit $?
fi

# Tested by OPENING it, not by stat'ing it. /dev/tty exists in plenty of
# contexts where opening it fails with ENXIO -- a container started without a
# tty is the common one -- and `[[ -r ]]` cannot tell those apart.
if ! (exec < /dev/tty) 2>/dev/null; then
    fail "No terminal is available, so the installer cannot run interactively." \
         "This happens when the one-liner runs from cloud-init, CI, or a" \
         "container with no tty attached." \
         "" \
         "Re-run with --yes for an unattended install:" \
         "  curl -fsSL <url>/bootstrap.sh | bash -s -- --channel ${channel} --yes" 7
fi

echo " * Starting the installer"
echo
(cd "${gitpath}/bin" && bash installfog.sh "${channelArgs[@]}" < /dev/tty)
exit $?
