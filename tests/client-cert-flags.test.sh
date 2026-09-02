#!/bin/bash
#
# Guards --client-cert / --client-key.
#
#   tests/client-cert-flags.test.sh
#
# The client-communication keypair is the one certificate every registered
# fog-client pins, and until now it was the only zone an admin could not point
# at their own files -- the exception a model whose premise is "say where the
# cert is" cannot have.
#
# Three behaviors, and the difference between them is the whole point:
#
#   half a pair          REFUSED. A certificate without its key locks out every
#                        registered host, and the failure surfaces per host as a
#                        failed authorize with nothing naming the file.
#   a pair that does     REFUSED. Same outcome, and since the admin just named
#   not pair             both files it is a typo rather than history.
#   a valid DIFFERENT    WARNED, and PROCEEDED. Re-pinning a fleet is a
#   pair                 legitimate thing to ask for; refusing it would make the
#                        flags useless for the case they exist for.
#
# The refusals are gated on the flag SHADOWS, not on the resolved values, and
# that is load-bearing: both are managed keys that writeUpdateFile persists every
# run, so on any upgrade they are non-empty from .fogsettings alone and a check
# against the resolved values would refuse every ordinary upgrade. Pinned below.
#
# Needs openssl. No network, no root, no install -- the refusal block is
# extracted from bin/installfog.sh and evaluated, so it cannot drift from the
# installer.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
[[ -f $INSTALLER ]] || { echo "ERROR: $INSTALLER not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log="$WORK/error.log"
: > "$error_log"

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

echo "client cert flags:"

# --- the flag is actually parsed --------------------------------------------
# Both spellings must reach the shadow variables, and via the same readable-file
# guard the other file-taking flags use.
for f in --client-cert --client-key; do
    grep -qF -- "$f" "$INSTALLER" \
        && ok "$f appears in bin/installfog.sh" \
        || bad "$f is not in bin/installfog.sh"
    grep -qF -- "$f" <(sed -n '/^usage()/,/^}/p' "$INSTALLER") \
        && ok "$f is documented in usage()" \
        || bad "$f is missing from usage()"
done
sed -n '/--web-ca-cert | --web-ca-key/,/^            ;;/p' "$INSTALLER" \
    | grep -q 'sPKI_client_encrypt_cert="\$2"' \
    && ok "--client-cert stages sPKI_client_encrypt_cert" \
    || bad "--client-cert does not stage its shadow"
sed -n '/--web-ca-cert | --web-ca-key/,/^            ;;/p' "$INSTALLER" \
    | grep -q 'sPKI_client_encrypt_key="\$2"' \
    && ok "--client-key stages sPKI_client_encrypt_key" \
    || bad "--client-key does not stage its shadow"
grep -q '^\[\[ -n \${sPKI_client_encrypt_cert} \]\] && PKI_client_encrypt_cert=' "$INSTALLER" \
    && ok "the cert shadow is applied to the managed key" \
    || bad "the cert shadow is never applied"
grep -q '^\[\[ -n \${sPKI_client_encrypt_key} \]\] && PKI_client_encrypt_key=' "$INSTALLER" \
    && ok "the key shadow is applied to the managed key" \
    || bad "the key shadow is never applied"

# --- fixtures: two genuine pairs, plus a mismatched one ---------------------
mkpair() {
    openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 3 \
        -subj "/CN=comm-$1" -keyout "$WORK/$1.key" -out "$WORK/$1.crt" \
        >>$error_log 2>&1
}
mkpair a || { echo "ERROR: fixture pair a failed" >&2; exit 1; }
mkpair b || { echo "ERROR: fixture pair b failed" >&2; exit 1; }

# --- the refusal block, extracted from the installer ------------------------
# Evaluated rather than restated, so a change to the real guard is reflected
# here instead of being shadowed by a copy.
block=$(sed -n '/^# --client-cert \/ --client-key: only meaningful as a pair/,/^    unset ccmod ckmod$/p' "$INSTALLER")
block="$block"$'\nfi'
if [[ -z $block || $block != *"exit 9"* ]]; then
    bad "could not extract the --client-cert/--client-key refusal block"
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    exit 1
fi
ok "the refusal block is present in bin/installfog.sh"

# Runs the block in a subshell with `exit` captured, and reports status+output.
tryflags() {
    local out st
    out=$(
        sPKI_client_encrypt_cert="$1"
        sPKI_client_encrypt_key="$2"
        PKI_client_encrypt_cert="$3"
        PKI_client_encrypt_key="$4"
        eval "$block"
        echo "__REACHED_END__"
    )
    st=$?
    printf '%s\n' "$out"
    return $st
}

# 1. cert without key
out=$(tryflags "$WORK/a.crt" "" "" ""); st=$?
is "$st" "9" "--client-cert alone exits 9"
[[ $out == *"must be set together"* ]] \
    && ok "...and says both are required" || bad "...but did not say why (got: $out)"

# 2. key without cert
out=$(tryflags "" "$WORK/a.key" "" ""); st=$?
is "$st" "9" "--client-key alone exits 9"

# 3. a mismatched pair
out=$(tryflags "$WORK/a.crt" "$WORK/b.key" "" ""); st=$?
is "$st" "9" "a cert and key that do not pair exits 9"
[[ $out == *"do not pair"* ]] \
    && ok "...and says they do not pair" || bad "...but did not say why (got: $out)"

# 4. unreadable as a certificate/key
echo "not a certificate" > "$WORK/junk.pem"
echo "not a key" > "$WORK/junk2.pem"
out=$(tryflags "$WORK/junk.pem" "$WORK/a.key" "" ""); st=$?
is "$st" "9" "a file that is not a certificate exits 9"

# 4b. BOTH unreadable. This is the case that makes the raw-modulus comparison
#     necessary: `openssl x509 -modulus` on a bad file prints nothing, and
#     nothing piped through `openssl md5` is the perfectly non-empty MD5 of the
#     empty string -- so with the md5 idiom two garbage files produce identical
#     non-empty values, satisfy the emptiness guard, and "pair".
out=$(tryflags "$WORK/junk.pem" "$WORK/junk2.pem" "" ""); st=$?
is "$st" "9" "TWO unreadable files exit 9 rather than 'pairing'"
[[ $out == *"Could not read"* ]] \
    && ok "...and are reported as unreadable, not as a mismatch" \
    || bad "...but the message did not say they were unreadable (got: $out)"

# 5. THE POINT: a valid pair is accepted and does NOT exit
out=$(tryflags "$WORK/a.crt" "$WORK/a.key" "" ""); st=$?
is "$st" "0" "a valid pair is accepted"
[[ $out == *"__REACHED_END__"* ]] \
    && ok "...and the install proceeds rather than refusing" \
    || bad "a valid pair did not reach the end of the block"

# 6. And a valid but DIFFERENT pair is equally accepted -- that is a re-pin,
#    which is warned about later by _warnClientRepin, never refused here.
out=$(tryflags "$WORK/b.crt" "$WORK/b.key" "$WORK/a.crt" "$WORK/a.key"); st=$?
is "$st" "0" "swapping to a different valid pair is accepted, not refused"

# 7. The shadow gating. An ordinary upgrade has both managed keys populated from
#    .fogsettings and no flags on the command line. Checking the resolved values
#    instead of the shadows would refuse this -- every upgrade, for everyone.
out=$(tryflags "" "" "$WORK/a.crt" "$WORK/a.key"); st=$?
is "$st" "0" "an upgrade with both keys persisted and no flags is not refused"
#    Including the pathological version of it: a persisted pair that no longer
#    pairs is history to be reported by _createCommLeaf, not grounds to refuse
#    an install that might be the thing that fixes it.
out=$(tryflags "" "" "$WORK/a.crt" "$WORK/b.key"); st=$?
is "$st" "0" "a persisted pair that no longer pairs does not block an upgrade"

# --- _clientLeafTarget: where a supplied pair actually points ---------------
# The flags are only useful if the resolver keeps the admin's path instead of
# overwriting it with the zone path.
fogprogramdir="$WORK/opt/fog"
snapindir="$fogprogramdir/snapins"
PKI_client_cert_dir="$snapindir/ssl"
mkdir -p "${PKI_client_cert_dir}"
zonekey="$(_pkiZoneDir client)/leaf/.srvprivate.key"
canonkey="${PKI_client_cert_dir}/.srvprivate.key"

is "$(_clientLeafTarget "" "$zonekey" "$canonkey")" "$zonekey" \
   "unset resolves to the zone path"
is "$(_clientLeafTarget "$zonekey" "$zonekey" "$canonkey")" "$zonekey" \
   "the zone path resolves to itself"
# The upgrade case, and the one most easily got wrong: an upgraded server's
# record holds the old snapin-dir path, which IS outside the zone. Treating that
# as "the admin's own file" would mean no server ever migrates.
is "$(_clientLeafTarget "$canonkey" "$zonekey" "$canonkey")" "$zonekey" \
   "an upgraded server's recorded snapin-dir path still resolves to the zone"
is "$(_clientLeafTarget "$WORK/a.key" "$zonekey" "$canonkey")" "$WORK/a.key" \
   "a file the admin named is kept, not overwritten by the zone path"
# A record naming something that is gone falls back rather than being trusted.
is "$(_clientLeafTarget "$WORK/removed.key" "$zonekey" "$canonkey")" "$zonekey" \
   "a recorded path that no longer exists falls back to the zone"

# --- the same flaw in _discardOrphanedCommLeaf, which DELETES ---------------
# Its own comment says "deleting on a failed read would turn a bad openssl
# invocation into a destroyed certificate", and the md5 idiom defeated the guard
# meant to prevent exactly that: an unreadable CERT beside a readable key gave
# md5("") != real-modulus, i.e. a "mismatch", and the certificate was removed.
# The certificate is the admin's way back from a lost key, so destroying it
# converts a recoverable accident into a mandatory re-pin of every client.
rootCAKeyOffline=0
PKI_client_encrypt_cert="$WORK/orphan.crt"
PKI_client_encrypt_key="$WORK/orphan.key"

# a. a genuinely mismatched, READABLE pair: the certificate should go.
cp "$WORK/a.crt" "${PKI_client_encrypt_cert}"
cp "$WORK/b.key" "${PKI_client_encrypt_key}"
_discardOrphanedCommLeaf
[[ ! -f "${PKI_client_encrypt_cert}" ]] \
    && ok "_discardOrphanedCommLeaf removes a genuinely orphaned certificate" \
    || bad "_discardOrphanedCommLeaf kept a certificate that does not pair"

# b. an UNREADABLE certificate beside a readable key: it must survive.
echo "truncated or unreadable" > "${PKI_client_encrypt_cert}"
cp "$WORK/a.key" "${PKI_client_encrypt_key}"
_discardOrphanedCommLeaf
[[ -f "${PKI_client_encrypt_cert}" ]] \
    && ok "an unreadable certificate is NOT destroyed on a failed read" \
    || bad "an unreadable certificate was deleted -- a bad read destroyed it"

# c. a matching readable pair is obviously left alone.
cp "$WORK/a.crt" "${PKI_client_encrypt_cert}"
cp "$WORK/a.key" "${PKI_client_encrypt_key}"
_discardOrphanedCommLeaf
[[ -f "${PKI_client_encrypt_cert}" ]] \
    && ok "a matching pair is left alone" \
    || bad "a matching pair was deleted"

# --- the record has to SURVIVE writeUpdateFile, and the next run ------------
# The resolver above was already correct; the bug was that writeUpdateFile then
# assigned the zone path over its answer, so `--client-cert/--client-key` were
# honored for exactly one run and silently reverted. Nothing tested
# writeUpdateFile against these keys at all, which is how that survived.
#
# Two passes, because one pass cannot see the bug: pass one held the admin's
# path in the live variable regardless, and only the FILE it wrote was wrong.
run2work="$WORK/tworun"
fogprogramdir="$run2work/opt/fog"
snapindir="$fogprogramdir/snapins"
PKI_client_cert_dir="$snapindir/ssl"
# Into the scratch tree, or _pkiRootDir() reaches for the host's own /etc/fog
# and _pkiZoneDir client resolves outside anything this test created.
PKI_root_dir="$fogprogramdir/pki"
mkdir -p "${PKI_client_cert_dir}" "$fogprogramdir/pki/client/leaf"
apacheuser="${apacheuser:-www-data}"
version="1.6.0"
PKI_client_encrypt_cert="$WORK/a.crt"
PKI_client_encrypt_key="$WORK/a.key"

# writeUpdateFile reads a great deal of the install's state. Give it only what
# it needs to emit a file; every unset managed key emits an empty line, which is
# fine here -- the two keys under test are what this asserts.
writeUpdateFile >/dev/null 2>&1

settings="$fogprogramdir/.fogsettings"
[[ -f $settings ]] \
    && ok "writeUpdateFile emitted a settings file" \
    || bad "writeUpdateFile wrote no settings file"

is "${PKI_client_encrypt_key}" "$WORK/a.key" \
   "pass 1: the live key still names the admin's file after writeUpdateFile"
is "${PKI_client_encrypt_cert}" "$WORK/a.crt" \
   "pass 1: the live cert still names the admin's file after writeUpdateFile"
is "$(sed -n "s/^PKI_client_encrypt_key='\(.*\)'$/\1/p" "$settings")" "$WORK/a.key" \
   "pass 1: the RECORDED key is the admin's file, not the zone path"
is "$(sed -n "s/^PKI_client_encrypt_cert='\(.*\)'$/\1/p" "$settings")" "$WORK/a.crt" \
   "pass 1: the RECORDED cert is the admin's file, not the zone path"

# Pass two is the run that used to lose it: read the file back the way the
# installer does, then resolve and record again with no flags passed.
unset PKI_client_encrypt_key PKI_client_encrypt_cert
. "$settings" >/dev/null 2>&1
is "${PKI_client_encrypt_key}" "$WORK/a.key" \
   "pass 2: sourcing .fogsettings hands back the admin's key"

_resolveClientLeafPaths >/dev/null 2>&1
is "${PKI_client_encrypt_key}" "$WORK/a.key" \
   "pass 2: _resolveClientLeafPaths keeps it"
writeUpdateFile >/dev/null 2>&1
is "$(sed -n "s/^PKI_client_encrypt_key='\(.*\)'$/\1/p" "$settings")" "$WORK/a.key" \
   "pass 2: the record survives a second run with no flags"

# And the compat link must still POINT at the admin's file, because that is what
# FOGBase::certDecrypt() opens -- it hardcodes the .srvprivate.key filename and
# never reads PKI_client_encrypt_key. A link into the zone here means every
# fog-client authenticates against the wrong key, or none.
_linkClientLeafCompat >/dev/null 2>&1
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "the canonical name is a symlink, not a copy of the key" \
    || bad "the canonical name is not a symlink -- the key was duplicated"
is "$(readlink -f "${PKI_client_cert_dir}/.srvprivate.key")" "$(readlink -f "$WORK/a.key")" \
   "the canonical name resolves to the admin's key"

# _separateCommKey runs FIRST in createSSLCA, before the resolver, and used to
# know only about the client zone. FOG's own compat link at the admin's file
# resolves outside that zone, so it dereferenced the link and copied the key
# back into $snapindir/ssl as a real file -- which the resolver's migrate loop
# then moved into the zone. That unwound the relocation on every run, and left a
# private key lying in the directory the snapin replicator walks.
_separateCommKey >/dev/null 2>&1
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "_separateCommKey leaves FOG's own link at the admin's file alone" \
    || bad "_separateCommKey dereferenced the link and copied the admin's key"
is "$(readlink -f "${PKI_client_cert_dir}/.srvprivate.key")" "$(readlink -f "$WORK/a.key")" \
   "and it still resolves to the admin's key"
[[ ! -f "$fogprogramdir/pki/client/leaf/.srvprivate.key" ]] \
    && ok "the admin's key was not migrated into FOG's zone" \
    || bad "the admin's key material was moved into FOG's own zone"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
