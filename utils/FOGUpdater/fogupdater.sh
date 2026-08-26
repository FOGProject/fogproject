#!/bin/bash
# GH-314: resolve against this script's own location, not the caller's cwd.
. "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/../../lib/common/utils.sh"
#
# GHSA-qp3r-8mwm-vg6h -- this utility downloads a tarball and runs its
# installer unattended as root, so whatever answers these requests becomes the
# FOG server. Three things follow from that and none of them are optional:
#
#   1. Every fetch verifies TLS. No -k, no --insecure, no
#      --no-check-certificate. tests/installer-tls-verification.test.sh pins
#      this file specifically.
#   2. Every URL is https and stays https through redirects. github.com
#      redirects the archive endpoint to codeload.github.com, so "the URL I
#      typed is https" is not the same statement as "the bytes arrived over
#      TLS" -- curl's --proto/--proto-redir make it one, and they refuse a
#      cleartext $updatemirrors override set in .fogsettings rather than
#      merely not defaulting to one.
#   3. Nothing is executed until the payload has been identified. There is no
#      release signature to check -- FOG does not sign releases today, and
#      GitHub publishes no checksum for branch archives -- so the trust root
#      here is verified TLS to github.com and nothing else. The version
#      re-check below is therefore NOT a security control; it refuses a
#      payload whose FOG_VERSION is not the one we resolved, which catches a
#      stale tarball left in $downloaddir, a truncated download, and the
#      branch moving between the version check and the fetch.
#
# The plain-HTTP SourceForge mirror list this used to default to is gone for a
# second reason as well: SourceForge has carried no release since fog_1.4.4, so
# the stable path had been downloading a 404 page for years.
#
[[ -z $downloaddir ]] && downloaddir="/opt"
downloaddir="${downloaddir%/}"
echo " ***************************************************************"
echo " *                         ** Notice **                        *"
echo " ***************************************************************"
echo " *                                                             *"
echo " * Your FOG server may go offline during this upgrade process! *"
echo " *                                                             *"
echo " ***************************************************************"
# The installer this ends up running writes to /opt, the web root, the database
# and the service manager. Refuse up front rather than half way through.
[[ $EUID -ne 0 ]] && handleError " * This utility must be run as root" 1
# requireHttps <url> -- abort unless the URL is https.
#
# curl's --proto '=https' already refuses anything else, so this is not what
# makes the guarantee; it is what makes the failure legible. Without it an
# http:// mirror in .fogsettings surfaces as "curl exit 1" with no clue which
# of the fetches objected or why.
#
# Called as a plain statement, never inside $( ), because handleError exits and
# an exit inside a command substitution kills only the subshell.
requireHttps() {
    [[ $1 == https://* ]] && return 0
    handleError " * Refusing to update over an unverified transport: $1" 8
}
# fetch <url> <destination|-> -- one verified download.
#
# curl rather than wget because --proto/--proto-redir have no wget equivalent:
# they pin the scheme for the whole redirect chain, which is what actually
# holds guarantee 2 above. curl is a FOG_packages entry on every supported
# distro, so it is as available here as wget was.
#
# --fail matters as much as verification does. Without it an HTTP error page is
# written to the destination and reported as a successful download, which is
# how the dead SourceForge mirrors used to hand `tar` an HTML 404.
fetch() {
    curl --silent --show-error --fail --location \
        --proto '=https' --proto-redir '=https' \
        --connect-timeout 15 --speed-time 30 --speed-limit 1024 \
        --output "$2" "$1"
}
# Which branch this server tracks.
#
# $updatebranch (.fogsettings or the environment) wins, then the installed
# FOG_CHANNEL, then the legacy $trunk switch. FOG_CHANNEL cannot tell stable
# from dev-branch -- .githooks/lib/fog-version.sh stamps both "Patches" -- so
# $trunk still selects between those two exactly as it did before. What
# FOG_CHANNEL is needed for is the case that used to be actively destructive:
# on a 1.6 server the stable path resolved a 1.5.x version, called it "not up
# to date", and installed 1.5 over the top. "Release Candidate" and "Feature"
# have no branch this can derive, so they stop rather than guess -- same
# reason, one step further out.
channel=$(awk -F\' /"define\('FOG_CHANNEL'[,](.*)"/'{print $4}' "$configpath" | tr -d '[[:space:]]')
if [[ -n $updatebranch ]]; then
    branch="$updatebranch"
elif [[ $channel == Beta ]]; then
    branch="working-1.6"
elif [[ -n $trunk ]]; then
    branch="dev-branch"
elif [[ -z $channel || $channel == Patches ]]; then
    branch="stable"
else
    handleError " * Unrecognized release channel '$channel' -- set updatebranch in .fogsettings to choose what to track" 9
fi
echo " * Update channel: ${channel:-unknown} (tracking branch $branch)"
# The version of a branch is the FOG_VERSION its own system.class.php defines,
# read with the same awk utils.sh uses on the installed copy -- so "latest" and
# "running" are the same number produced the same way. This replaces the POST
# to fogproject.org/version/index.php, which returned JSON for stable and dev
# only: it had no answer for the beta channel, and it was a second service that
# had to be kept in step with the branches by hand.
versionurl="https://raw.githubusercontent.com/FOGProject/fogproject/${branch}/packages/web/lib/fog/system.class.php"
requireHttps "$versionurl"
dots "Checking latest version"
latest=$(fetch "$versionurl" - 2>/dev/null | awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' | tr -d '[[:space:]]')
if [[ -z $latest ]]; then
    echo "Failed"
    handleError " * Could not determine the latest FOG version for branch $branch" 1
fi
echo "Done"
echo " * Latest FOG Version: $latest"
[[ $version == $latest ]] && handleError " * You are already up to date!" 0
echo "   You are not running the latest $branch version"
echo " * Preparing to upgrade"
echo " * Attempting to download $branch to $downloaddir"
# $updatemirrors keeps working for anyone mirroring internally: each entry is a
# base URL that "$branch.tar.gz" is appended to, and each is held to the same
# https requirement as the default. The default is GitHub's branch archive,
# which is where FOG releases live now.
[[ -z $updatemirrors ]] && updatemirrors="https://github.com/FOGProject/fogproject/archive/refs/heads"
fileplace="$downloaddir/fog_${latest}.tar.gz"
downloaded=""
for url in $updatemirrors; do
    echo " * Trying mirror $url"
    requireHttps "${url%/}/${branch}.tar.gz"
    dots "Attempting Download"
    if fetch "${url%/}/${branch}.tar.gz" "$fileplace" >/dev/null 2>&1; then
        echo "Done"
        downloaded=1
        break
    fi
    echo "Failed"
done
[[ -z $downloaded ]] && handleError "   Failed to download current file" 5
echo
echo
echo " * Extracting package $fileplace"
echo
echo
dots "Extracting"
# A fresh directory every time. The old code untarred over whatever was already
# there, so a previous run's tree survived and files deleted upstream were
# still present in the thing that then got installed.
extractdir="$downloaddir/fog_${latest}"
rm -rf "$extractdir" >/dev/null 2>&1
mkdir -p "$extractdir" >/dev/null 2>&1
# --strip-components=1 drops the archive's own top-level directory, whose name
# differs per source (fog_1.5.10/ from SourceForge, fogproject-<branch>/ from
# GitHub). Stripping it fixes the path to the installer here instead of
# guessing it from the archive, which is what the old
# `cd $downloaddir/fog_$latest/*/bin` glob was doing -- and it retires the
# unset $extract that made the stable path untar straight into $downloaddir.
# Not errorStat: it reports through $error_log, which only installfog.sh ever
# sets. Reached from a utility, its failure arm runs `tail -n 5` with no file
# argument, so tail reads stdin and a failed extraction hangs the update
# instead of reporting it.
if ! tar -xzf "$fileplace" -C "$extractdir" --strip-components=1 >/dev/null 2>&1; then
    echo "Failed"
    handleError " * Could not extract $fileplace" 4
fi
echo "Done"
echo
# Identify what was actually unpacked before running it. See note 3 at the top:
# this is a correctness check, not a signature check.
#
# A function rather than four inline lines so a test can run the decision
# itself. Asserting on the ORDER of the lines cannot tell a check that fires
# from one that has been made unreachable -- and a guard that falls through
# returns normally, which reads as success, is a defect this tree has met
# repeatedly (#1215/#1216).
verifyPayload() {
    local dir="$1" expect="$2" unpacked=""
    unpacked=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' "$dir/packages/web/lib/fog/system.class.php" 2>/dev/null | tr -d '[[:space:]]')
    [[ $unpacked == $expect ]] && return 0
    echo " * Downloaded package reports version '${unpacked:-none}', expected '$expect'" >&2
    return 1
}
dots "Verifying package"
if ! verifyPayload "$extractdir" "$latest"; then
    echo "Failed"
    handleError " * Refusing to install a package that is not the version resolved for $branch" 6
fi
echo "Done"
[[ ! -x $extractdir/bin/installfog.sh ]] && handleError " * No installer found in $extractdir" 7
cd "$extractdir/bin" || handleError " * Could not enter $extractdir/bin" 7
./installfog.sh -y
