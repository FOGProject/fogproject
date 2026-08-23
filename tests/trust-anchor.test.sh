#!/bin/bash
#
# Guards what the installer puts in this server's OWN system trust store.
#
#   tests/trust-anchor.test.sh
#
# _installCATrustAnchor() exists so that HTTPS calls made ON the FOG server TO
# the FOG server verify. It writes whatever _resolveTrustAnchor() picked into
# the distro's anchor directory. Picking the wrong certificate there does not
# fail loudly -- the install completes, the web UI works in a browser, and only
# the server's own curl/PHP calls to itself quietly go on failing verification,
# which is the symptom the mechanism was added to remove in the first place.
#
# Two ways it got that wrong, both fixed and both pinned here:
#
#   1. On a master it anchored ${PKI_root_ca_cert} unconditionally. With --external-ca /
#      --web-ca-root the vhost serves a chain terminating in the ADMIN's root,
#      while ${PKI_root_ca_cert} is still FOG's own -- validateExternalCA deliberately
#      never reassigns it. So the store learned a root nothing was served under.
#   2. The node path took the LAST certificate in ${PKI_web_trust_chain}. The writers of
#      that file disagree on order: createWebIntermediateCA and
#      fog-sign-node-cert write issuer-first with the root appended, while
#      validateExternalCA writes the root FIRST. "Last certificate" therefore
#      means the root in one case and the INTERMEDIATE in the other.
#
# Needs openssl. Runs entirely on generated fixtures -- no install, no network,
# no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

command -v openssl >/dev/null 2>&1 || {
    echo "SKIP: openssl is not installed"
    exit 0
}

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

ok()   { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()  { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# --- fixture -----------------------------------------------------------------
# Two independent CAs, each root + intermediate, so "chains to MY ca" and
# "chains to the admin's ca" are genuinely different answers.
mkca() {
    # Two statements, not one `local name=... dir="$WORK/$name"`: bash expands
    # every word of a command before running it, so $name in the same `local`
    # would still be the caller's (unset) one.
    local name="$1"
    local dir="$WORK/$name"
    mkdir -p "$dir"
    openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 30 \
        -subj "/CN=${name} Root" -keyout "$dir/root.key" -out "$dir/root.pem" \
        >/dev/null 2>&1 || return 1
    openssl req -new -nodes -newkey rsa:2048 -sha256 \
        -subj "/CN=${name} Intermediate" \
        -keyout "$dir/int.key" -out "$dir/int.csr" >/dev/null 2>&1 || return 1
    printf 'basicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n' \
        > "$dir/int.ext"
    openssl x509 -req -in "$dir/int.csr" -CA "$dir/root.pem" -CAkey "$dir/root.key" \
        -CAcreateserial -sha256 -days 30 -extfile "$dir/int.ext" \
        -out "$dir/int.pem" >/dev/null 2>&1 || return 1
    return 0
}

mkca fog || { echo "ERROR: could not build the fog CA fixture" >&2; exit 1; }
mkca ext || { echo "ERROR: could not build the ext CA fixture" >&2; exit 1; }

fpof() { openssl x509 -in "$1" -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//'; }

FOGROOT_FP=$(fpof "$WORK/fog/root.pem")
FOGINT_FP=$(fpof "$WORK/fog/int.pem")
EXTROOT_FP=$(fpof "$WORK/ext/root.pem")
EXTINT_FP=$(fpof "$WORK/ext/int.pem")

# Fingerprints of every certificate in a bundle, one per line.
bundlefps() {
    local d f
    d=$(mktemp -d)
    awk -v d="$d" '/-----BEGIN CERTIFICATE-----/{n++} n{print > (d "/c" n ".pem")}' "$1"
    for f in "$d"/c*.pem; do
        [[ -f $f ]] && fpof "$f"
    done
    rm -rf "$d"
}

has_fp() { bundlefps "$1" | grep -qxF "$2"; }
count_certs() { grep -c 'BEGIN CERTIFICATE' "$1" 2>/dev/null || echo 0; }

# --- load the functions under test -------------------------------------------
error_log=/dev/null
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# Each case gets its own $fogprogramdir, because _resolveTrustAnchor writes into
# $(_pkiZoneDir web)/ca and a leftover file from a previous case would be
# indistinguishable from a pass.
newcase() {
    fogprogramdir="$WORK/case$1"
    mkdir -p "$fogprogramdir"
    PKI_root_ca_cert=""
    PKI_web_trust_chain=""
    trustAnchorPem=""
    FOG_install_type=""
}

echo "trust anchor:"

# Case A -- FOG issues everything. ${PKI_root_ca_cert} and the chain's root are the same
# certificate reached two ways; the bundle must collapse to one, not list it
# twice.
newcase A
PKI_root_ca_cert="$WORK/fog/root.pem"
PKI_web_trust_chain="$WORK/a-chain.pem"
cat "$WORK/fog/int.pem" "$WORK/fog/root.pem" > "${PKI_web_trust_chain}"
if _resolveTrustAnchor; then
    check "$(count_certs "$trustAnchorPem")" "1" "A: FOG-issued master anchors exactly one certificate"
    has_fp "$trustAnchorPem" "$FOGROOT_FP" \
        && ok "A: it is FOG's root" || bad "A: FOG's root is missing"
else
    bad "A: _resolveTrustAnchor returned non-zero"
fi

# Case B -- the regression this was written for. External CA: the vhost chain
# terminates in the admin's root, ${PKI_root_ca_cert} is still FOG's. Both are needed and
# both must be present. Before the fix this anchored FOG's root alone.
newcase B
PKI_root_ca_cert="$WORK/fog/root.pem"
PKI_web_trust_chain="$WORK/b-chain.pem"
# validateExternalCA's order: root FIRST, then the intermediate.
cat "$WORK/ext/root.pem" "$WORK/ext/int.pem" > "${PKI_web_trust_chain}"
if _resolveTrustAnchor; then
    check "$(count_certs "$trustAnchorPem")" "2" "B: external-CA master anchors two certificates"
    has_fp "$trustAnchorPem" "$EXTROOT_FP" \
        && ok "B: the admin's root is anchored" \
        || bad "B: the admin's root is MISSING -- server-side HTTPS to itself will not verify"
    has_fp "$trustAnchorPem" "$FOGROOT_FP" \
        && ok "B: FOG's own root is still anchored" || bad "B: FOG's own root was dropped"
    has_fp "$trustAnchorPem" "$EXTINT_FP" \
        && bad "B: the INTERMEDIATE was anchored -- selection fell back to position" \
        || ok "B: the intermediate is correctly not anchored"
else
    bad "B: _resolveTrustAnchor returned non-zero"
fi

# Case C -- storage node. No root of its own; everything comes from the chain
# the master sent, written issuer-first with the root appended. This one already
# worked before the fix and is here to stay working: fog-sign-node-cert always
# appends the root last, so the old "last certificate" rule happened to be right
# for nodes. It is the master path above that was wrong.
newcase C
FOG_install_type=S
PKI_web_trust_chain="$WORK/c-chain.pem"
cat "$WORK/fog/int.pem" "$WORK/fog/root.pem" > "${PKI_web_trust_chain}"
if _resolveTrustAnchor; then
    check "$(count_certs "$trustAnchorPem")" "1" "C: node anchors exactly one certificate"
    has_fp "$trustAnchorPem" "$FOGROOT_FP" \
        && ok "C: it is the root, not the intermediate" \
        || bad "C: the root is missing"
else
    bad "C: _resolveTrustAnchor returned non-zero"
fi

# Case D -- order independence, stated directly. A root-first chain is exactly
# what validateExternalCA writes, and selecting by position picks the
# intermediate out of it.
newcase D
FOG_install_type=S
PKI_web_trust_chain="$WORK/d-chain.pem"
cat "$WORK/ext/root.pem" "$WORK/ext/int.pem" > "${PKI_web_trust_chain}"
if _resolveTrustAnchor; then
    has_fp "$trustAnchorPem" "$EXTROOT_FP" \
        && ok "D: root-first chain still yields the root" \
        || bad "D: root-first chain yielded the wrong certificate"
else
    bad "D: _resolveTrustAnchor returned non-zero"
fi

# Case E -- a chain with no root at all. The master can legitimately send one,
# and it must be a clean "nothing extra to add", not a failure and not garbage.
newcase E
PKI_root_ca_cert="$WORK/fog/root.pem"
PKI_web_trust_chain="$WORK/e-chain.pem"
cp "$WORK/fog/int.pem" "${PKI_web_trust_chain}"
if _resolveTrustAnchor; then
    check "$(count_certs "$trustAnchorPem")" "1" "E: rootless chain contributes nothing extra"
    has_fp "$trustAnchorPem" "$FOGINT_FP" \
        && bad "E: an intermediate leaked into the anchor" \
        || ok "E: no intermediate leaked in"
else
    bad "E: _resolveTrustAnchor returned non-zero"
fi

# Case F -- nothing to anchor yet. Must return 1 so _installCATrustAnchor
# declines quietly rather than writing an empty .crt into the trust store.
newcase F
if _resolveTrustAnchor; then
    bad "F: returned success with no root and no chain"
else
    ok "F: returns non-zero when there is nothing to anchor"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
