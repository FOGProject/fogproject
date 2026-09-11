#!/bin/bash
#
# Pins that the generated config.class.php is not world-readable.
#
# Why
# ---
# commons/config.class.php holds ${DB_password}, both FTP passwords and the
# per-install schema bootstrap token. It is written by a plain shell redirect,
# so with no chmod it inherits root's umask -- 0644 on every supported distro
# -- and every local account on the server can read all three. The FTP
# credential is fleet-wide rather than per-server, and a storage node's copy
# carries the MASTER's database password.
#
# 0640 rather than 0600 because the web tier includes this file on every
# request, as does any daemon running as the web user (on the 1.6 line that is
# FOGPluginRunner and FOGRetentionRunner; on 1.5 every daemon is root). So the
# test asserts BOTH directions: no world bit, and group read intact. A gate
# that only checked "not 0644" would go green on 0600 and take the UI down with
# it, which is a worse outage than the bug.
#
# The chown/chmod pair is EXTRACTED from lib/common/functions.sh and executed
# against a fixture file, not grepped for. A grep passes on a line that has
# been commented out, moved behind a false condition, or pointed at a path
# nothing writes. Running it is the only thing that answers the question.
#
# Usage: bash tests/generated-config-mode.test.sh
# Exit 0 = pass, 1 = fail.

set -uo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fn="$repo/lib/common/functions.sh"
[[ -r $fn ]] || { echo "FAIL: cannot read $fn"; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

failures=0
note() { echo "FAIL: $*"; failures=$((failures + 1)); }

# Where the installer actually writes the config, read out of the script
# rather than named here. The path differs by branch -- commons/ on the PSR-4
# line, lib/fog/ on 1.5 -- and a hardcoded path would make this test silently
# measure nothing on the branch it did not expect.
dest=$(grep -oE '^\}" > "\$\{webdirdest\}/?[a-z/]*config\.class\.php"' "$fn" \
    | head -1 | sed -e 's/^.*\${webdirdest}\/*//' -e 's/"$//')
[[ -n $dest ]] || { echo "FAIL: could not find the config write in $fn"; exit 1; }

# Take the lines that act on that exact path. Anchored to start-of-line
# whitespace so a commented-out line does not count as present.
block=$work/perms.sh
grep -E "^ *(chown|chmod) .*\\\$\{webdirdest\}/?${dest}\"" "$fn" \
    | sed 's/>>\$error_log 2>&1//' > "$block"

lines=$(grep -c . "$block")
if [[ $lines -lt 2 ]]; then
    note "expected a chown AND a chmod on \${webdirdest}/$dest in
      lib/common/functions.sh; found $lines line(s). Either they were removed, or
      the path they name no longer matches the path the config is written to."
    echo "$failures failure(s)"; exit 1
fi

# Run them for real against a fixture standing in for the webroot.
mkdir -p "$work/web/$(dirname "$dest")"
target=$work/web/$dest
printf "<?php class Config { const DB_PASS = 'secret'; }\n" > "$target"
chmod 0644 "$target"   # what the redirect leaves behind with no chmod at all

(
    webdirdest=$work/web
    # The installer runs as root and can chown to anyone; a test cannot, so it
    # chowns to the user already running. What is under test is the MODE.
    apacheuser=$(id -un)
    # shellcheck disable=SC1090
    source "$block"
) >/dev/null 2>&1

mode=$(stat -c '%a' "$target")

# The load-bearing half: no world bit.
if [[ $(( 8#$mode & 8#0007 )) -ne 0 ]]; then
    note "config.class.php ended at $mode -- world-readable credentials"
fi
# The other half: the web user must still be able to read it.
if [[ $(( 8#$mode & 8#0040 )) -eq 0 ]]; then
    note "config.class.php ended at $mode -- no group read, so the web tier
      (and any daemon running as the web user) cannot read its own config"
fi
# And the owner must still be able to write it, or a re-install cannot.
if [[ $(( 8#$mode & 8#0600 )) -ne 8#0600 ]]; then
    note "config.class.php ended at $mode -- owner lost read or write"
fi

if [[ $failures -gt 0 ]]; then
    echo "$failures failure(s)"
    exit 1
fi
echo "generated-config-mode: 0$mode -- owner rw, group r, world nothing"
echo "PASS"
exit 0
