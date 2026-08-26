#!/bin/bash
#
# Pins which CA key algorithms validateExternalCA() accepts.
#
#   tests/external-ca-key-algorithms.test.sh
#
# The function has to answer one question about the admin's --ca-cert/--ca-key
# pair: are they each other's halves? That was `openssl x509 -modulus` against
# `openssl rsa -modulus`, which only understands RSA -- so an elliptic curve CA
# could never pair with its own key and the install aborted claiming a mismatch
# that was not there (GH-1393).
#
# The obvious repair -- detect the algorithm, branch to a per-algorithm
# comparison, reject anything not on the list -- traded that bug for a worse
# one: RSA-PSS worked BEFORE the check existed (`openssl rsa -modulus` reads an
# RSA-PSS key fine) and a two-entry allow-list turned it into a hard install
# failure. So did Ed25519 and Ed448, which are elliptic curve CAs and the whole
# point of the change.
#
# What is here instead compares the SUBJECT PUBLIC KEY: `openssl x509 -pubkey`
# and `openssl pkey -pubout` emit byte-identical SPKI PEM for every algorithm
# openssl can load, so one comparison covers all of them and there is no list to
# fall behind openssl again. This test is that claim, per algorithm.
#
# Two things the comparison must NOT wave through, also pinned here:
#
#   DSA      openssl pairs it happily and nothing downstream accepts it -- TLS
#            1.3 removed DSA signatures, browsers reject the chain, iPXE has no
#            DSA. Already refused before this change, as a bogus "key does not
#            match"; still refused, now for the stated reason.
#
#   a real   the pairing check has to still FAIL on a genuine mismatch. A
#   mismatch comparison that is accidentally always-true would pass every case
#            above and be worse than the bug it replaced.
#
# And the iPXE advisory: the imported CA is compiled into the iPXE binary as
# CERT=/TRUST=, and the pinned tag verifies RSA and ECDSA P-256/P-384 only. An
# Ed25519 or P-521 CA installs fine and then fails HTTPS netboot, so the
# installer says so at import time.
#
# Needs openssl. Runs on generated fixtures -- no install, no network, no root.
# An algorithm the local openssl cannot generate is skipped, not failed.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
SKIPPED=0
ok()   { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()  { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
skip() { SKIPPED=$((SKIPPED + 1)); printf '  skip  %s\n' "$1"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
# dots/errorStat draw the installer's progress line; the assertions here are
# about exit status and the text of a failure, not about the cosmetics.
dots() { :; }
errorStat() { :; }

error_log="$WORK/error.log"
: > "$error_log"
fogprogramdir="$WORK/opt/fog"
PKI_client_cert_dir="$WORK/opt/fog/snapins/ssl"
# Unset so the function's closing "you are switching CA" warning -- which is
# about an already-issued vhost cert, not about algorithms -- stays quiet.
PKI_web_vhost_cert=""

# Generate a private key for $1 into $2. Returns non-zero when this openssl
# cannot produce that algorithm, which is a skip and not a failure: Ed448 and
# RSA-PSS need 1.1.1+, and a FIPS build may refuse others.
genkey() {
    case "$1" in
        rsa)      openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$2" 2>>"$error_log" ;;
        ec-p256)  openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:P-256 -out "$2" 2>>"$error_log" ;;
        ec-p384)  openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:P-384 -out "$2" 2>>"$error_log" ;;
        ec-p521)  openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:P-521 -out "$2" 2>>"$error_log" ;;
        # `ecparam -genkey` without -noout prefixes the file with an EC
        # PARAMETERS block. That is what most EC how-tos produce, and it must
        # not change the SPKI openssl derives from it.
        ec-params) openssl ecparam -name prime256v1 -genkey -out "$2" 2>>"$error_log" ;;
        rsa-pss)  openssl genpkey -algorithm RSA-PSS -pkeyopt rsa_keygen_bits:2048 -out "$2" 2>>"$error_log" ;;
        ed25519)  openssl genpkey -algorithm ED25519 -out "$2" 2>>"$error_log" ;;
        ed448)    openssl genpkey -algorithm ED448 -out "$2" 2>>"$error_log" ;;
        dsa)      openssl genpkey -genparam -algorithm DSA -pkeyopt dsa_paramgen_bits:2048 \
                      -out "$2.params" 2>>"$error_log" \
                      && openssl genpkey -paramfile "$2.params" -out "$2" 2>>"$error_log" ;;
        *)        return 1 ;;
    esac
}

# root CA -> intermediate CA, both on algorithm $1, into directory $2. The
# intermediate is what an admin passes as --ca-cert/--ca-key; the root is
# --ca-root. Deliberately the same algorithm on both, which is the realistic
# case and the one the function has to verify a chain across.
makeca() {
    local alg="$1" d="$2"
    mkdir -p "$d" || return 1
    genkey "$alg" "$d/root.key" || return 1
    genkey "$alg" "$d/int.key" || return 1
    openssl req -x509 -new -nodes -key "$d/root.key" -days 2 \
        -subj "/CN=${alg} root" -addext "basicConstraints=critical,CA:TRUE" \
        -out "$d/root.pem" 2>>"$error_log" || return 1
    openssl req -new -nodes -key "$d/int.key" -subj "/CN=${alg} intermediate" \
        -out "$d/int.csr" 2>>"$error_log" || return 1
    printf 'basicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n' \
        > "$d/int.ext"
    openssl x509 -req -in "$d/int.csr" -CA "$d/root.pem" -CAkey "$d/root.key" \
        -CAcreateserial -days 2 -extfile "$d/int.ext" -out "$d/int.pem" \
        2>>"$error_log" || return 1
    [[ -s $d/int.pem ]]
}

# Run the function over a prepared fixture directory and capture BOTH its exit
# status and its output. A subshell is not optional: every failure path in
# validateExternalCA is `exit 1`, which would take this test process with it.
runvalidate() {
    local d="$1"
    OUT=$(
        importWebCACert="$d/int.pem"
        importWebCAKey="$d/int.key"
        importWebCARoot="$d/root.pem"
        validateExternalCA web 2>&1
    )
    STATUS=$?
}

echo "external CA key algorithms:"

# --- every algorithm openssl can load pairs with its own certificate ---------
#
# ec-p521, rsa-pss, ed25519 and ed448 are the cases a two-entry allow-list
# rejected; rsa and ec-p256 are the two it allowed. All of them have to pass,
# and for the same reason rather than through one branch each.
for alg in rsa ec-p256 ec-p384 ec-p521 ec-params rsa-pss ed25519 ed448; do
    d="$WORK/$alg"
    if ! makeca "$alg" "$d"; then
        skip "$alg (this openssl cannot generate it)"
        continue
    fi
    runvalidate "$d"
    if [[ $STATUS -ne 0 ]]; then
        bad "$alg is accepted (exit $STATUS: $(printf '%s' "$OUT" | tr '\n' ' '))"
    else
        ok "$alg is accepted"
    fi
done

# --- DSA is refused, and says why -------------------------------------------
d="$WORK/dsa"
if ! makeca dsa "$d"; then
    skip "dsa (this openssl cannot generate it)"
else
    runvalidate "$d"
    if [[ $STATUS -eq 0 ]]; then
        bad "dsa is refused"
    else
        ok "dsa is refused"
    fi
    if printf '%s' "$OUT" | grep -qi 'DSA'; then
        ok "the DSA refusal names DSA rather than claiming a key mismatch"
    else
        bad "the DSA refusal names DSA (got: $(printf '%s' "$OUT" | tr '\n' ' '))"
    fi
fi

# --- a genuine mismatch still fails -----------------------------------------
#
# The guard against an accidentally always-true comparison. Same algorithm, same
# curve, different key -- so nothing but the public key itself distinguishes
# them.
d="$WORK/mismatch"
if ! makeca ec-p256 "$d"; then
    skip "mismatch (this openssl cannot generate EC P-256)"
else
    genkey ec-p256 "$d/other.key"
    cp "$d/other.key" "$d/int.key"
    runvalidate "$d"
    if [[ $STATUS -eq 0 ]]; then
        bad "an EC key that is not the certificate's key is refused"
    else
        ok "an EC key that is not the certificate's key is refused"
    fi
fi

# --- the iPXE advisory fires on exactly what iPXE cannot verify -------------
for alg in rsa ec-p256 ec-p384; do
    d="$WORK/$alg"
    [[ -s $d/int.pem ]] || continue
    runvalidate "$d"
    if printf '%s' "$OUT" | grep -qi 'netboot will fail'; then
        bad "$alg draws no iPXE advisory"
    else
        ok "$alg draws no iPXE advisory"
    fi
done
for alg in ec-p521 ed25519 ed448 rsa-pss; do
    d="$WORK/$alg"
    [[ -s $d/int.pem ]] || continue
    runvalidate "$d"
    if printf '%s' "$OUT" | grep -qi 'netboot will fail'; then
        ok "$alg is advised about HTTPS netboot"
    else
        bad "$alg is advised about HTTPS netboot"
    fi
done

echo
echo "  passed: $PASS  failed: $FAIL  skipped: $SKIPPED"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
