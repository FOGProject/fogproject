#!/bin/bash
#
# utils/FOGUpdater/fogupdater.sh is retired, and stays retired.
#
#   tests/fogupdater-update-source.test.sh
#
# This file used to assert the details of how that utility fetched things --
# https only, held through redirects, --fail, the payload identified before it
# was executed. All of that came out of GHSA-qp3r-8mwm-vg6h, where the
# combination of --no-check-certificate, plain-HTTP SourceForge mirrors and an
# unverified payload meant whatever answered the request became the FOG server,
# unattended, as root.
#
# The utility is now a message and an exit. Those assertions have nothing left
# to describe, and deleting them would have quietly given up the guarantee they
# encoded. So they are replaced by the strongest form of the same statement:
# THIS FILE FETCHES NOTHING AND RUNS NOTHING. A fetcher that does not exist
# cannot be talked into fetching over cleartext.
#
# That also makes the file the tripwire for its own return. Anyone who
# reintroduces a download here -- rather than adding one to bin/updatefog.sh,
# where a checkout is fetched by git and can be diffed and reset -- fails this
# and has to come and read why it was retired.
#
# Why it was retired, in one line each:
#   * It could not reach 1.6 at all. Its Beta arm resolved working-1.6 and then
#     read that branch's version from a 1.5-only path, so every crossing died
#     on a 404. GH-1587.
#   * It ran `installfog.sh -y`, which is the wrong default for an upgrade that
#     meets settings the old .fogsettings has never held.
#
# Usage: bash tests/fogupdater-update-source.test.sh
# Exit status 0 = pass, 1 = fail.

REPO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
UPD="$REPO/utils/FOGUpdater/fogupdater.sh"

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }

if [[ ! -r $UPD ]]; then
    echo "FAIL: cannot read $UPD" >&2
    echo "  The file is kept deliberately, so an existing cron entry gets a" >&2
    echo "  message rather than 'No such file or directory'." >&2
    exit 1
fi

# What is left after removing the two things that are TEXT rather than code:
# comments, and the heredoc the retirement message is printed from. The message
# quotes the bootstrap one-liner, curl and all, because that is the instruction
# a tarball install needs -- and a check that could not tell a printed command
# from an executed one would either fail on the advice or have to stop looking
# for curl at all. Both are worse than knowing the difference.
body=$(sed 's/#.*//' "$UPD" | awk '
    /<<[[:space:]]*.?EOF.?[[:space:]]*$/ { inhere = 1; next }
    inhere && /^EOF$/                    { inhere = 0; next }
    inhere                               { next }
    { print }
')

# --- it fetches nothing ----------------------------------------------------
for tool in curl wget; do
    if grep -qE "(^|[^-[:alnum:]_])${tool}([[:space:]]|$)" <<< "$body"; then
        bad "$tool appears in a retired utility -- it must not fetch anything"
    else
        ok "no $tool"
    fi
done

for pattern in 'https\?://' 'updatemirrors' 'versionurl'; do
    if grep -qE "$pattern" <<< "$body"; then
        bad "'$pattern' appears outside a comment -- this file resolves no URLs"
    else
        ok "no live '$pattern'"
    fi
done

# --- and runs nothing ------------------------------------------------------
for pattern in 'tar[[:space:]]' 'installfog\.sh' '\bexec\b' 'eval[[:space:]]'; do
    if grep -qE "$pattern" <<< "$body"; then
        bad "'$pattern' appears outside a comment -- this file executes nothing"
    else
        ok "no live '$pattern'"
    fi
done

# It sources nothing either. lib/common/utils.sh pulls in the whole installer
# library, and a retired file has no business having it in scope.
if grep -qE '^[[:space:]]*(\.|source)[[:space:]]' <<< "$body"; then
    bad "the retired utility still sources something"
else
    ok "it sources nothing"
fi

# --- it says where to go instead -------------------------------------------
# The point of keeping the file at all. A cron entry that has been running for
# years should produce an instruction, not a silence.
if grep -q 'updatefog.sh' "$UPD"; then
    ok "it points at bin/updatefog.sh for a git checkout"
else
    bad "it does not say what to use instead for a git checkout"
fi

if grep -q 'bootstrap.sh' "$UPD"; then
    ok "it points at the bootstrap installer for a tarball install"
else
    bad "it leaves tarball installs -- the ones it used to serve -- with nowhere to go"
fi

if grep -qi 'retired' "$UPD"; then
    ok "it says it is retired"
else
    bad "it does not say it is retired"
fi

# --- and fails, rather than looking like it worked -------------------------
# A cron entry checks exit status. Printing advice and exiting 0 would report
# a successful update forever.
bash "$UPD" >/dev/null 2>&1
st=$?
if [[ $st -ne 0 ]]; then
    ok "it exits non-zero, so a cron entry reports a failure rather than a success"
else
    bad "it exits 0 -- a scheduled update would silently report success forever"
fi

echo
if [[ $FAIL -eq 0 ]]; then
    echo "PASS ($PASS assertions)"
    exit 0
fi
echo "FAIL ($FAIL of $((PASS + FAIL)) assertions)"
exit 1
