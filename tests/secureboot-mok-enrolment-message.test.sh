#!/bin/bash
#
# Guards the "narrow check reported as a broad conclusion" bug in
# fog-enroll-mok.sh (#1266).
#
#   tests/secureboot-mok-enrolment-message.test.sh
#
# `mokutil --test-key` interrogates MokList -- shim's trust store -- and nothing
# else. The check is right for what this script does: it IS the MOK enroller,
# and re-enrolling a MOK genuinely is a no-op. The MESSAGE was wrong. It said
# "This key is already enrolled on this machine. Nothing to do." -- a
# machine-wide claim drawn from a store-specific test.
#
# The two stores are not interchangeable. A MOK only helps where shim is in the
# boot chain; firmware never reads MokList. Booting a FOG-signed binary
# DIRECTLY, with no shim, needs the certificate in **db**, which this script
# neither reads nor writes. So an admin setting up the shim-less route was told
# to stop, by a script that had not looked at the thing they were asking about.
#
# This is the dev-branch half of the #1266 fix. The other half -- the installer's
# silent skip and the web page that named the wrong cause -- does not exist on
# this branch: there is no _publishSecureBootAuthVars() and no automatic
# enrolment card. working-1.6 carries the full test as
# tests/secureboot-enrolment-diagnostics.test.sh.
#
# The script is EXECUTED, against a stubbed mokutil that reports the key as
# already enrolled. Two of the assertions below are negative and would pass
# whenever the script died early, so a guard asserts the run actually reached
# the branch under test.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
MOKSH="$REPO/packages/secureboot/fog-enroll-mok.sh"

[[ -f $MOKSH ]] || { echo "ERROR: $MOKSH not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }

mkdir -p "$WORK/kit" "$WORK/bin"
openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 1 \
    -subj "/CN=FOG enrol-mok test/" -keyout "$WORK/mok.key" \
    -out "$WORK/mok.pem" 2>/dev/null
openssl x509 -in "$WORK/mok.pem" -outform der -out "$WORK/kit/MOK.der" 2>/dev/null

# The script re-execs itself through sudo when not root. A passthrough stub
# cannot satisfy that -- EUID stays non-zero, so it re-execs forever -- so the
# re-exec is neutered in the COPY under test. Neutered, not deleted: the line
# sits inside an `if`, and removing it outright leaves a dangling `fi`.
# Asserted rather than assumed, so a change of shape fails loudly instead of
# quietly testing nothing. The re-exec is not what is under test; the message is.
if ! grep -qF 'exec sudo -- "$0" "$@"' "$MOKSH"; then
    echo "FAIL: the sudo re-exec changed shape -- this test can no longer strip it"
    exit 1
fi
sed 's|exec sudo -- "\$0" "\$@"|:|' "$MOKSH" > "$WORK/kit/fog-enroll-mok.sh"
chmod +x "$WORK/kit/fog-enroll-mok.sh"
bash -n "$WORK/kit/fog-enroll-mok.sh" || { echo "FAIL: the de-sudo'd copy does not parse"; exit 1; }

cat > "$WORK/bin/mokutil" <<'EOS'
#!/bin/bash
case "$1" in
    --sb-state) echo "SecureBoot enabled" ;;
    --test-key) echo "$2 is already enrolled" ;;
    --import)   echo "IMPORT SHOULD NOT HAPPEN" ; exit 1 ;;
esac
exit 0
EOS
chmod +x "$WORK/bin/mokutil"

# "y" answers the fingerprint prompt; the trailing newline feeds pause's read.
out=$(printf 'y\n\n' | PATH="$WORK/bin:$PATH" bash "$WORK/kit/fog-enroll-mok.sh" 2>&1)
rc=$?

if [[ $rc -eq 0 ]] && grep -q 'Certificate:' <<<"$out"; then
    ok "the run reached the already-enrolled branch"
else
    bad "the run never reached the branch (exit $rc)"
    printf '%s\n' "$out" | sed 's/^/     | /'
fi

if grep -qi 'MokList' <<<"$out" && grep -qi '\bdb\b' <<<"$out"; then
    ok "the already-enrolled path names MokList and db as different stores"
else
    bad "already-enrolled output does not distinguish MokList from db"
    printf '%s\n' "$out" | sed 's/^/     | /'
fi

if grep -qi 'enrolled on this machine' <<<"$out"; then
    bad "the machine-wide 'enrolled on this machine' claim is back"
else
    ok "no machine-wide claim drawn from a MokList-only test"
fi

if grep -qi 'IMPORT SHOULD NOT HAPPEN' <<<"$out"; then
    bad "the script re-imported a key it had just reported as enrolled"
else
    ok "an already-enrolled key is still a no-op, as intended"
fi

echo
echo "passed: $PASS   failed: $FAIL"
[[ $FAIL -eq 0 ]]
