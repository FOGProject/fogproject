#!/bin/bash
#
# Guards the "installer skips TLS verification" bug class.
#
#   tests/installer-tls-verification.test.sh
#
# Port of the working-1.6 gate (GH-1169). The installer passed -k /
# --no-check-certificate on essentially every HTTPS call it made, and the flag
# had spread by copy: a new fetch was written by pasting an existing one, so
# nobody ever decided to disable verification -- it was inherited. Three
# groups, and they are not the same problem:
#
#   A. Internet downloads -- the iPXE tarball, the FOS kernels and inits, the
#      fog-client binaries, the FOGUpdater tarball. All from hosts with valid
#      certificates. The sha256 alongside each does NOT make -k safe: the hash
#      travels the same unverified connection as the payload, so anyone able
#      to substitute one substitutes both. What lands is a kernel FOS boots
#      and a tarball that becomes the FOG server itself.
#   B. This server calling ITSELF -- backupDB's fetch of backup_db.php, the
#      web-tier probe, and the schema update. The last of those carries
#      X-Fog-Install-Token, a secret that grants a schema deploy on a server
#      that has no users yet, and --no-check-certificate handed it to whoever
#      answered on that address. These cannot simply drop the flag, because
#      they run before _installCATrustAnchor() has taught the system store
#      about FOG's CA -- hence _resolveSelfCacert(), which names a resolved
#      anchor file directly. That anchor is $rootCAPem AND the root the served
#      chain terminates in: under --web-ca-cert/--web-ca-key/--web-ca-root
#      those are two DIFFERENT certificates, and naming $rootCAPem alone
#      anchored on a root that does not sign the leaf. wget's
#      --ca-certificate replaces the default bundle rather than adding to it,
#      so that did not merely fail to help, it removed the only trust that
#      would have worked.
#   C. The node/master bootstrap -- registerStorageNode() and
#      updateStorageNodeCredentials(). A genuine chicken-and-egg: on a fresh
#      storage node they run BEFORE _installCATrustAnchor(), so there is no
#      anchor for anything yet and verification cannot succeed. They keep the
#      flag, with the reasoning written at the call site.
#
# So this file does not say "never -k". It says: exactly the three Group C
# calls, and no others. The count is pinned deliberately -- a NEW unverified
# call cannot be added without also editing this number, which is a visible
# act in the diff rather than a silent inheritance.
#
# Comments are stripped before every source assertion. This file's own prose
# names -k and --no-check-certificate repeatedly, and the block comments now
# sitting at the Group A and Group C sites name them too; a gate that its own
# documentation satisfies is not a gate. (Same failure the
# no-session-for-browserless and network-fetch-bounded gates hit.)
#
# No root, no network, no FOG install. The behavioural section builds a
# throwaway CA in a temp directory.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
FUNCS="lib/common/functions.sh"
UPD="utils/FOGUpdater/fogupdater.sh"

for f in "$FUNCS" "$UPD"; do
    [[ -r $f ]] || { echo "cannot read $f -- run this from the repository"; exit 1; }
done

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# joined <file> -- backslash continuations folded onto one line, comment lines
# dropped. Both matter: several of these calls are written across two or three
# lines, so a per-line grep sees the flags on one and the URL on another.
joined() {
    sed -e :a -e '/\\$/N; s/\\\n//; ta' "$1" | grep -vE '^[[:space:]]*#'
}

# body <function-name> <file> -- the text of one shell function, comments
# stripped. Every function in these files opens "name() {" and closes on a bare
# brace in column one.
body() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { inside = 1 }
        inside { print }
        inside && /^}/ { exit }
    ' "$2" | grep -vE '^[[:space:]]*#'
}

# unverified <file> -- every line invoking curl or wget with verification off.
#
# The curl match is deliberately loose about where the k sits: -k, --insecure,
# and k bundled into a cluster (-fkOL, -sk, -kL) all count, and the bundle form
# is how most of these were actually written. Matching only " -k " would have
# missed five of the eleven sites this change removed.
unverified() {
    joined "$1" \
        | grep -E '(^|[^-[:alnum:]_])(curl|wget)[[:space:]]' \
        | grep -vE '^[[:space:]]*echo ' \
        | grep -E -- '--insecure|--no-check-certificate|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'
}

echo "1. only the documented node-bootstrap calls skip verification"

# The allowlist is by URL, not by line number: each of the three is the only
# call in this tree that talks to that endpoint.
allowed_re='check_node_exists\.php|create_update_node\.php'

found=0
stray=0
while IFS= read -r line; do
    [[ -n $line ]] || continue
    found=$((found + 1))
    if ! printf '%s\n' "$line" | grep -qE "$allowed_re"; then
        stray=$((stray + 1))
        printf '        unverified: %s\n' \
            "$(printf '%s' "$line" | sed 's/^ *//;s/  */ /g' | cut -c1-96)"
    fi
done < <(unverified "$FUNCS"; unverified "$UPD")

if [[ $stray -eq 0 ]]; then
    ok "no unverified call outside the node-bootstrap allowlist"
else
    bad "$stray call(s) skip TLS verification and are not a documented node-bootstrap call"
fi

# Pinning the count as well as the allowlist. Without this a third call to
# create_update_node.php would inherit the exemption silently, which is exactly
# how the flag spread in the first place.
if [[ $found -eq 3 ]]; then
    ok "exactly 3 unverified calls remain (the Group C node bootstrap)"
else
    bad "expected exactly 3 unverified calls, found $found -- if this is deliberate, say why here"
fi

# And each of the three must actually be the one it claims to be, not three
# copies of one. A regression that duplicated the check_node_exists call and
# deleted the others would satisfy both assertions above.
for ep in check_node_exists.php create_update_node.php; do
    n=$(unverified "$FUNCS" | grep -c -- "$ep")
    case "$ep:$n" in
        create_update_node.php:2|check_node_exists.php:1)
            ok "$ep: $n unverified call(s), as expected" ;;
        *)
            bad "$ep: $n unverified call(s), expected 1 (2 for create_update_node.php)" ;;
    esac
done

echo
echo "2. the internet downloads verify (Group A)"

# Named individually rather than swept, so deleting a fetch cannot pass this
# section by making its assertion vacuous.
for fn in fetchipxeasset downloadfiles; do
    if body "$fn" "$FUNCS" | grep -qE -- '--insecure|--no-check-certificate|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'; then
        bad "$fn still skips verification -- its sha256 arrives over the same connection"
    else
        ok "$fn verifies TLS"
    fi
done

if joined "$UPD" | grep -q -- '--no-check-certificate'; then
    bad "$UPD still passes --no-check-certificate"
else
    ok "$UPD verifies TLS"
fi

echo
echo "3. the server's calls to itself are anchored (Group B)"

if grep -q '^_resolveSelfCacert() {' "$FUNCS"; then
    ok "_resolveSelfCacert() is defined"
else
    bad "_resolveSelfCacert() is gone -- the Group B calls have nothing to anchor against"
fi

helper=$(body _resolveSelfCacert "$FUNCS")

# Pin the DEFINITION, not just that callers mention it. A helper rewritten to
# set nothing, or to hand back --no-check-certificate, would satisfy every
# call-site assertion below while restoring the whole bug.
if printf '%s' "$helper" | grep -q -- '--ca-certificate'; then
    ok "_resolveSelfCacert names an anchor with --ca-certificate"
else
    bad "_resolveSelfCacert does not pass --ca-certificate -- it is not anchoring anything"
fi
if printf '%s' "$helper" | grep -q '_resolveTrustAnchor'; then
    ok "_resolveSelfCacert anchors on the resolved trust anchor"
else
    bad "_resolveSelfCacert does not call _resolveTrustAnchor -- nothing resolves the chain's root"
fi
# Pin the RESOLVER's definition too, not just that the helper calls it. A
# _resolveTrustAnchor rewritten to emit $rootCAPem alone would satisfy the
# assertion above while restoring the whole bug, silently, on every
# external-CA install.
resolver=$(body _resolveTrustAnchor "$FUNCS")
if printf '%s' "$resolver" | grep -q 'rootCAPem'; then
    ok "_resolveTrustAnchor includes \$rootCAPem, the file _installCATrustAnchor uses"
else
    bad "_resolveTrustAnchor does not read \$rootCAPem"
fi
if printf '%s' "$resolver" | grep -q 'sslcachain'; then
    ok "_resolveTrustAnchor also includes the root the served chain ends in"
else
    bad "_resolveTrustAnchor does not read \$sslcachain -- an imported Web CA would not verify"
fi
if printf '%s' "$helper" | grep -qE -- '--insecure|--no-check-certificate|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'; then
    bad "_resolveSelfCacert hands back an insecure flag"
else
    ok "_resolveSelfCacert hands back no insecure flag"
fi
if printf '%s' "$helper" | grep -q 'httpproto == https'; then
    ok "_resolveSelfCacert is a no-op on a plain-HTTP install"
else
    bad "_resolveSelfCacert does not gate on \$httpproto -- an http install would get a stray flag"
fi

# The three call sites, each pinned to the function it lives in and to the
# endpoint it talks to, so a spliced-in "${selfCacertOpts[@]}" somewhere else
# cannot stand in for the one that was removed.
check_site() {
    local fn="$1" needle="$2" what="$3" text
    text=$(body "$fn" "$FUNCS")
    if ! printf '%s' "$text" | grep -q '_resolveSelfCacert'; then
        bad "$what: $fn never calls _resolveSelfCacert"
        return
    fi
    if ! printf '%s' "$text" | grep -q "$needle"; then
        bad "$what: the call to $needle is gone from $fn"
        return
    fi
    if ! joined "$FUNCS" | grep "$needle" | grep -q 'selfCacertOpts\[@\]'; then
        bad "$what: the call to $needle does not splice in \"\${selfCacertOpts[@]}\""
        return
    fi
    ok "$what is anchored"
}

check_site backupDB 'backup_db.php' "backupDB's fetch of backup_db.php"
check_site updateDB 'X-Fog-Install-Token' "the schema update (carries the install token)"
check_site checkWebTier 'probeBody' "the web-tier probe"

echo
echo "4. the helper behaves (behavioural)"

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
error_log="$tmp/error.log"

# shellcheck source=/dev/null
source "$FUNCS" >/dev/null 2>&1

# A plain-HTTP install -- the default here -- must be left exactly as it was.
httpproto=http
rootCAPem="$tmp/ca.pem"
printf 'not a real certificate\n' > "$rootCAPem"
selfCacertOpts=(poison)
_resolveSelfCacert
if [[ ${#selfCacertOpts[@]} -eq 0 ]]; then
    ok "http install: no --ca-certificate added"
else
    bad "http install: got ${#selfCacertOpts[@]} option(s), expected none"
fi

# https with nothing to anchor -- wget falls back to the system store, which is
# the right answer on an install whose certificate came from a public CA.
httpproto=https
rootCAPem=""
selfCacertOpts=(poison)
_resolveSelfCacert
if [[ ${#selfCacertOpts[@]} -eq 0 ]]; then
    ok "https with no anchor: falls back to the system store"
else
    bad "https with no anchor: got ${selfCacertOpts[*]}, expected nothing"
fi

# https with a real root on disk -- the anchor must be named explicitly, which
# is the whole point: the system store does not know it yet at the moment
# backupDB and updateDB run.
if command -v openssl >/dev/null 2>&1; then
    openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
        -keyout "$tmp/ca.key" -out "$tmp/ca.pem" \
        -subj "/CN=fog-tls-gate-test" >/dev/null 2>&1
    rootCAPem="$tmp/ca.pem"
    # _resolveTrustAnchor writes under $(_pkiZoneDir web), so the zone has to
    # live somewhere writable for the duration of the test.
    fogprogramdir="$tmp/fog"
    anchor="$tmp/fog/pki/web/ca/.trustAnchor.pem"
    sslcachain=""
    selfCacertOpts=()
    _resolveSelfCacert
    if [[ ${#selfCacertOpts[@]} -eq 1 && ${selfCacertOpts[0]} == "--ca-certificate=$anchor" ]]; then
        ok "https with a root on disk: --ca-certificate points at the anchor"
    else
        bad "https with a root on disk: got '${selfCacertOpts[*]}'"
    fi
    if [[ $(grep -c 'BEGIN CERTIFICATE' "$anchor" 2>/dev/null) -eq 1 ]]; then
        ok "no separate chain root: the anchor holds exactly one certificate"
    else
        bad "no separate chain root: anchor holds $(grep -c 'BEGIN CERTIFICATE' "$anchor" 2>/dev/null), expected 1"
    fi

    # The regression this whole change exists for: an imported Web CA, whose
    # chain terminates in ANOTHER server's root. Both roots must reach the
    # anchor, or the server cannot verify its own certificate and backupDB and
    # the schema deploy fail with "unable to get local issuer certificate".
    openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
        -keyout "$tmp/hub.key" -out "$tmp/hub.pem" \
        -subj "/CN=fog-tls-gate-hub" >/dev/null 2>&1
    cat "$tmp/hub.pem" > "$tmp/chain.pem"
    sslcachain="$tmp/chain.pem"
    selfCacertOpts=()
    _resolveSelfCacert
    if [[ $(grep -c 'BEGIN CERTIFICATE' "$anchor" 2>/dev/null) -eq 2 ]]; then
        ok "imported Web CA: the anchor holds the local root AND the chain's"
    else
        bad "imported Web CA: anchor holds $(grep -c 'BEGIN CERTIFICATE' "$anchor" 2>/dev/null), expected 2"
    fi
    if openssl verify -CAfile "$anchor" "$tmp/hub.pem" >/dev/null 2>&1; then
        ok "imported Web CA: the foreign root verifies against the anchor"
    else
        bad "imported Web CA: the foreign root does not verify against the anchor"
    fi

    # Same certificate reached two ways must not be listed twice.
    cat "$tmp/ca.pem" > "$tmp/chain.pem"
    selfCacertOpts=()
    _resolveSelfCacert
    if [[ $(grep -c 'BEGIN CERTIFICATE' "$anchor" 2>/dev/null) -eq 1 ]]; then
        ok "chain root == \$rootCAPem: deduplicated to one certificate"
    else
        bad "chain root == \$rootCAPem: anchor holds $(grep -c 'BEGIN CERTIFICATE' "$anchor" 2>/dev/null), expected 1"
    fi
else
    echo "  SKIP  openssl absent, cannot build a throwaway CA"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
