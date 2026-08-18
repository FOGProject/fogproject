#!/bin/bash
#
# Populate packages/web/lib/plugins from a pinned FOGProject/fog-plugins
# release.
#
# The bundled plugins live in their own repository (ADR 0009). They are not
# committed here; this fetches the release the pin names, verifies it, and
# unpacks it into the tree the installer then lays into the web root.
#
# Called by the installer (downloadplugins) and usable on its own after a
# fresh clone, which is why the real work is here rather than in
# lib/common/functions.sh: a developer wanting plugins in their working tree
# should not have to run an install to get them.
#
# Offline: pre-place the plugin directories in packages/web/lib/plugins and
# this leaves them alone, the same contract prepareiPXEsource offers.
#
set -u

pluginsgit="${pluginsgit:-https://github.com/FOGProject/fog-plugins}"
pluginsurl="${pluginsurl:-${pluginsgit}/releases/download}"

cwd="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(dirname "$cwd")"
dest="$root/packages/web/lib/plugins"
system="$root/packages/web/lib/fog/system.class.php"

# Same extraction the installer uses for FOG_IPXE_VERSION, so the pin has one
# spelling and one home.
pluginsVer="${pluginsVer:-$(awk -F\' /"define\('FOG_PLUGINS_VERSION'[,](.*)"/'{print $4}' "$system" 2>/dev/null | tr -d '[[:space:]]')}"
[[ -z $pluginsVer ]] && pluginsVer="v1.6.0"

force=0
quiet=0
for arg in "$@"; do
    case "$arg" in
        --force) force=1 ;;
        --quiet) quiet=1 ;;
        --version) echo "$pluginsVer"; exit 0 ;;
        *)
            echo "Usage: ${0##*/} [--force] [--quiet] [--version]" >&2
            exit 2
            ;;
    esac
done

say() { [[ $quiet -eq 1 ]] || echo "$@"; }

# A stamp rather than "is the directory non-empty". Non-empty is true both for
# a pre-placed offline tree, which must be left alone, and for a tree left by
# an older pin, which must be replaced -- and those need opposite answers.
# The stamp is written only by this script, so its absence means "someone else
# put this here" and its contents answer "which release".
stamp="$dest/.fog-plugins-version"
if [[ $force -eq 0 && -d $dest ]]; then
    if [[ -f $stamp ]] && [[ "$(<"$stamp")" == "$pluginsVer" ]]; then
        say "Plugins already at $pluginsVer"
        exit 0
    fi
    if [[ ! -f $stamp ]] && [[ -n "$(ls -A "$dest" 2>/dev/null)" ]]; then
        say "Plugins pre-placed by hand; leaving them alone"
        exit 0
    fi
fi

tarball="fog-plugins-${pluginsVer}.tar.gz"
url="${pluginsurl}/${pluginsVer}/${tarball}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

say "Fetching plugins ${pluginsVer}"
# Verified by re-running sha256sum rather than trusting curl's exit code: a
# truncated download and an HTTP error page both exit zero often enough to
# matter, and unpacking either is worse than failing. --fail keeps an error
# page out of the tarball in the first place.
#
# Bounded so an unreachable host costs seconds rather than libcurl's 300 second
# default connect timeout, five rounds over. --speed-time/--speed-limit catch a
# connection that opens and then stalls; --max-time is deliberately absent,
# because a slow but working link must still be allowed to finish a
# multi-megabyte tarball. This script is runnable on its own, so the bounds are
# defaulted here rather than assumed to come from lib/common/config.sh.
: "${inetConnectTimeout:=5}"
checksum=1
cnt=0
while [[ $checksum -ne 0 && $cnt -lt 5 ]]; do
    [[ -f $tmp/$tarball.sha256 ]] && (cd "$tmp" && sha256sum -c "$tarball.sha256" >/dev/null 2>&1)
    checksum=$?
    if [[ $checksum -ne 0 ]]; then
        curl --silent -fkL --connect-timeout "$inetConnectTimeout" \
            --speed-time 30 --speed-limit 1024 -o "$tmp/$tarball" "$url" >/dev/null 2>&1
        curl --silent -fkL --connect-timeout "$inetConnectTimeout" \
            --speed-time 30 --speed-limit 1024 -o "$tmp/$tarball.sha256" "${url}.sha256" >/dev/null 2>&1
    fi
    let cnt+=1
done
if [[ $checksum -ne 0 ]]; then
    echo "Could not obtain verified plugins (${pluginsVer}) from ${pluginsgit}" >&2
    echo "For an offline install, place the plugin directories in $dest" >&2
    exit 1
fi

# Unpacked beside the target and swapped in, so a failure part way through
# leaves the previous tree intact rather than half of two releases.
staging="$tmp/unpack"
mkdir -p "$staging"
if ! tar -xzf "$tmp/$tarball" -C "$staging"; then
    echo "Could not unpack $tarball" >&2
    exit 1
fi
echo "$pluginsVer" > "$staging/.fog-plugins-version"
rm -rf "$dest"
mkdir -p "$(dirname "$dest")"
mv "$staging" "$dest"
say "Plugins at $pluginsVer"
