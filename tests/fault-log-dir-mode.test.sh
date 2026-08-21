#!/bin/bash
#
# Guards the mode of the fault log directory.
#
#   tests/fault-log-dir-mode.test.sh
#
# FOGBase::logFault() records database writes that did not land, on every
# path including the machine-facing ones that have no user. #1261 cut the
# failed statement's BOUND VALUES out of that line -- they were carrying
# password hashes, client tokens and storage node FTP passwords into a file
# on disk.
#
# What survives the cut is still not public: the class, the table and the
# shape of the statement that failed. So this directory is deliberately 0750
# while every other FOG log directory is 0755, and that difference is the
# thing most likely to be "tidied" back into line by someone matching the
# block above it. Both writers are unaffected -- the web user owns it, and
# the root-run daemons ignore the mode.
#
# Pins three properties:
#
#   1. the directory is created at all;
#   2. it is chowned to the web user, so the web tier can write it;
#   3. it is chmodded 0750, and nothing later relaxes it.
#
# Static: reads lib/common/functions.sh. No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0; FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

grep -q 'mkdir -p \$servicelogs/faults' "$FUNCS" \
    && ok "the installer creates the fault log directory" \
    || bad "no mkdir for \$servicelogs/faults -- logFault() will fall back to error_log() forever"

grep -q 'chown \${apacheuser}:\${apacheuser} \$servicelogs/faults' "$FUNCS" \
    && ok "the fault log directory is owned by the web user" \
    || bad "the fault log directory is not chowned to the web user, so the web tier cannot write it"

grep -q 'chmod 0750 \$servicelogs/faults' "$FUNCS" \
    && ok "the fault log directory is 0750" \
    || bad "the fault log directory is not chmodded 0750 -- fault lines naming tables and statements are world-readable"

# A later, looser chmod on the same path would undo the one above.
if grep -E 'chmod +0?7[0-9]?5 +\$servicelogs/faults' "$FUNCS" | grep -qv 'chmod 0750'; then
    bad "something chmods \$servicelogs/faults world-readable"
else
    ok "nothing relaxes the fault log directory afterwards"
fi

printf '%s: %d passed, %d failed\n' "$(basename "$0")" "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
