#!/bin/bash
#
# Guards the "narrow check reported as a broad conclusion" bug class in the
# Secure Boot enrollment path (#1266).
#
#   tests/secureboot-enrolment-diagnostics.test.sh
#
# Two faults, filed and fixed together because they share a shape. Between them
# they made db/Setup-Mode enrollment look unavailable on a server that was fully
# configured for it.
#
#   1. _publishSecureBootAuthVars()'s "no platform keys" branch RETURNED IN
#      SILENCE. That is one of three ways the PK/KEK/db .auth blobs can be
#      absent, and the only one that produced no diagnostic anywhere -- so an
#      admin re-running the installer to fix it saw nothing at all. The web page
#      then named a different cause outright ("install efitools"), which is the
#      LEAST likely explanation on a server that has efitools installed. That is
#      what was observed in the field.
#
#   2. fog-enroll-mok.sh tested MokList with `mokutil --test-key` and reported
#      "already enrolled on this machine. Nothing to do." MokList is shim's
#      trust store; firmware never reads it. Booting a FOG-signed binary
#      directly, with no shim, needs the certificate in **db** -- a store this
#      script neither reads nor writes. So an admin setting up the shim-less
#      route was told to stop, by a script that had not looked at the thing they
#      were asking about.
#
# The properties pinned here:
#
#   A  _publishSecureBootAuthVars' no-platform-keys branch emits a diagnostic
#      rather than returning silently -- executed, not grepped, once per arm.
#   B  Each of the three arms says something DIFFERENT, so the message actually
#      distinguishes the causes rather than being one generic line.
#   C  Every other "Failed" arm in that function carries a reason too.
#   D  fog-enroll-mok.sh's already-enrolled path names MokList as what it
#      checked and db as what it did not -- executed against a stubbed mokutil.
#   E  It no longer makes the machine-wide "nothing to do" claim.
#   F  The web page reports the condition and points at the installer, instead
#      of asserting efitools as the cause.
#
# A, B, C and D/E execute the code. F is a content assertion scoped to the one
# block that renders the message: the page method needs a booted FOG to render,
# and the message text IS the deliverable here, so the text is what is pinned.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
MOKSH="$REPO/packages/secureboot/fog-enroll-mok.sh"
PAGE="$REPO/packages/web/src/Pages/FOGConfigurationPage.php"

for f in "$FUNCS" "$MOKSH" "$PAGE"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }

# ---------------------------------------------------------------------------
# A/B -- the installer's no-platform-keys branch, run for real.
#
# The function is pulled out on its own with stubs for everything it touches
# before the branch. Running the installer is not an option in a test, and
# sourcing all of functions.sh drags in the whole world.
# ---------------------------------------------------------------------------
run_publish() {
    # $1 PKI_sb_enabled  $2 codesign key  $3 codesign cert
    bash -c '
        error_log=/dev/null
        webdirdest="$4"; fogprogramdir="$4"
        dots() { printf "  * %-60s" "$1"; }
        _pkiZoneDir() { echo "${fogprogramdir}/pki/$1"; }
        _ensureEfitools() { :; }
        eval "$(awk "/^_publishSecureBootAuthVars\(\) \{/,/^\}\$/" "$5")"
        secureBootPKKey="" secureBootKEKKey=""
        PKI_sb_enabled="$1" PKI_sb_codesign_key="$2" PKI_sb_codesign_cert="$3"
        _publishSecureBootAuthVars
    ' _ "$1" "$2" "$3" "$WORK" "$FUNCS" 2>&1
}

optout=$(run_publish no  /k /c)
nokey=$(run_publish  yes ""  "")
genfail=$(run_publish yes /k /c)

# Something beyond the dots line. The old code printed literally nothing, so an
# empty capture is exactly the regression this exists to catch.
for arm in optout nokey genfail; do
    body=$(printf '%s\n' "${!arm}" | grep -v 'Publishing Secure Boot variable updates')
    if [[ -n ${body//[[:space:]]/} ]]; then
        ok "A: the $arm arm explains itself instead of returning silently"
    else
        bad "A: the $arm arm printed no diagnostic -- silent skip is back"
    fi
done

# Distinct messages, or the branch is not actually distinguishing anything.
if [[ $optout != "$nokey" && $nokey != "$genfail" && $optout != "$genfail" ]]; then
    ok "B: the three causes produce three different messages"
else
    bad "B: two or more arms print the same message"
fi

# ---------------------------------------------------------------------------
# C -- no bare "Failed" anywhere in that function. A status with no reason is
# the same complaint as the silent return, one line down.
# ---------------------------------------------------------------------------
# Looks ahead for a reason line rather than at the very next line: a comment
# between the status and its explanation is normal in this file.
bare=$(awk '
    /^_publishSecureBootAuthVars\(\) \{/ { inf = 1 }
    inf && /echo "Failed"/ {
        line = NR; found = 0
        for (i = 0; i < 6; i++) {
            if ((getline) <= 0) break
            if ($0 ~ /echo " \*/) { found = 1; break }
            if ($0 ~ /return|^\}$/) break
        }
        if (!found) print line
    }
    inf && /^\}$/ { inf = 0 }
' "$FUNCS")
if [[ -z $bare ]]; then
    ok "C: every Failed arm in _publishSecureBootAuthVars states a cause"
else
    bad "C: bare Failed with no reason at line(s): $(echo "$bare" | tr '\n' ' ')"
fi

# ---------------------------------------------------------------------------
# D/E -- fog-enroll-mok.sh, executed against a stubbed mokutil that reports the
# key as already enrolled. sudo is stubbed to a passthrough because the script
# re-execs itself through it, and openssl is real -- the certificate has to
# parse or the script fails before reaching the branch under test.
# ---------------------------------------------------------------------------
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl not installed"; exit 0; }

mkdir -p "$WORK/kit" "$WORK/bin"
openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 1 \
    -subj "/CN=FOG enroll-mok test/" -keyout "$WORK/mok.key" \
    -out "$WORK/mok.pem" 2>/dev/null
openssl x509 -in "$WORK/mok.pem" -outform der -out "$WORK/kit/MOK.der" 2>/dev/null
# The script re-execs itself through sudo when not root. A passthrough stub
# cannot satisfy that -- EUID stays non-zero, so it re-execs forever -- so the
# re-exec is dropped from the COPY under test. Asserted rather than assumed: if
# that line ever changes shape this fails loudly instead of quietly testing
# nothing. The re-exec itself is not what is under test here; the message is.
if ! grep -qF 'exec sudo -- "$0" "$@"' "$MOKSH"; then
    echo "FAIL: the sudo re-exec in fog-enroll-mok.sh changed shape -- this"
    echo "      test strips it and can no longer do so reliably."
    exit 1
fi
# Neutered, not deleted: the line sits inside an `if`, so removing it outright
# leaves a dangling `fi` and the script dies before the branch under test.
sed 's|exec sudo -- "\$0" "\$@"|:|' "$MOKSH" > "$WORK/kit/fog-enroll-mok.sh"
chmod +x "$WORK/kit/fog-enroll-mok.sh"
bash -n "$WORK/kit/fog-enroll-mok.sh" || {
    echo "FAIL: the de-sudo'd copy does not parse"
    exit 1
}

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

# Proves the run reached the branch under test. Without this the two negative
# assertions below pass whenever the script dies early -- which is a check that
# can pass for the wrong reason, the thing this file exists to complain about.
if [[ $rc -eq 0 ]] && grep -q 'Certificate:' <<<"$out"; then
    ok "D: the run reached the already-enrolled branch"
else
    bad "D: the run never reached the branch (exit $rc)"
    printf '%s\n' "$out" | sed 's/^/     | /'
fi

if grep -qi 'MokList' <<<"$out" && grep -qi '\bdb\b' <<<"$out"; then
    ok "D: the already-enrolled path names MokList and db as different stores"
else
    bad "D: already-enrolled output does not distinguish MokList from db"
    printf '%s\n' "$out" | sed 's/^/     | /'
fi

if grep -qi 'enrolled on this machine' <<<"$out"; then
    bad "E: the machine-wide 'enrolled on this machine' claim is back"
else
    ok "E: no machine-wide claim drawn from a MokList-only test"
fi

if grep -qi 'IMPORT SHOULD NOT HAPPEN' <<<"$out"; then
    bad "D: the script re-imported a key it had just reported as enrolled"
else
    ok "D: an already-enrolled key is still a no-op, as intended"
fi

# ---------------------------------------------------------------------------
# F -- the web page's unavailable message. Scoped to the block that builds it,
# so an unrelated mention of efitools elsewhere on the page cannot mask this.
# ---------------------------------------------------------------------------
block=$(awk '
    /if \(!\$haveAuth\) \{/ { inb = 1 }
    inb { print }
    inb && /echo \$this->_box\(/ { exit }
' "$PAGE")

if [[ -z $block ]]; then
    bad "F: could not find the !\$haveAuth block -- the anchor moved"
elif grep -qi "and re-run the installer to enable it" <<<"$block"; then
    bad "F: the page still asserts efitools as the cause"
elif grep -qi "Publishing Secure Boot variable updates" <<<"$block"; then
    ok "F: the page reports the condition and points at the installer output"
else
    bad "F: the page names no way for an admin to find the actual cause"
fi

echo
echo "passed: $PASS   failed: $FAIL"
[[ $FAIL -eq 0 ]]
