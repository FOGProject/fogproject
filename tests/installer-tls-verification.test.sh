#!/bin/bash
#
# Guards the "installer skips TLS verification" bug class.
#
#   tests/installer-tls-verification.test.sh
#
# The installer used to pass -k / --no-check-certificate on essentially every
# HTTPS call it made, and the flag had spread by copy: a new fetch was written
# by pasting an existing one, so nobody ever decided to disable verification --
# it was inherited. Three groups, and they are not the same problem:
#
#   A. Internet downloads -- the iPXE tarball, the FOS kernels and inits, the
#      fog-client binaries, the bundled plugins, the FOGUpdater tarball. All
#      from hosts with valid certificates. The sha256 alongside each does NOT
#      make -k safe: the hash travels the same unverified connection as the
#      payload, so anyone able to substitute one substitutes both. What lands
#      is a kernel FOS boots, PHP the web tier runs, and a tarball that becomes
#      the FOG server itself.
#   B. This server calling ITSELF -- backupDB fetching backup_db.php, the
#      schema probe, and the schema update. The last of those carries
#      X-Fog-Install-Token, a secret that grants a schema deploy on a server
#      that has no users yet, and -k handed it to whoever answered on that
#      address. These cannot simply drop the flag, because they can run before
#      _installCATrustAnchor() has taught the system store about FOG's CA --
#      hence _resolveSelfCacert(), which names the anchor file directly.
#   C. The node<->master bootstrap -- registerStorageNode(),
#      updateStorageNodeCredentials() and _requestNodeCert(). These are a
#      genuine chicken-and-egg: on a fresh storage node they run BEFORE
#      _installNodeWebCert() and _installCATrustAnchor(), so there is no anchor
#      for anything yet and verification cannot succeed. They keep -k, with the
#      reasoning written at the call site, and _requestNodeCert() is separately
#      protected by an HMAC keyed on $snmysqlpass.
#
# So this file does not say "never -k". It says: exactly the four Group C calls
# -- registerStorageNode() makes two of them -- and no others. The count is pinned deliberately -- a NEW unverified
# call cannot be added without also editing this number, which is a visible act
# in the diff rather than a silent inheritance.
#
# Comments are stripped before every source assertion. This file's own prose
# names -k, --insecure and --no-check-certificate repeatedly, and the block
# comments now sitting at the Group A and Group C sites name them too; a gate
# that its own documentation satisfies is not a gate. (Same failure the
# no-session-for-browserless and network-fetch-bounded gates hit.)
#
# No root, no network, no FOG install. The behavioural section builds a
# throwaway CA in a temp directory and points $fogprogramdir at it.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
FUNCS="lib/common/functions.sh"
PLUG="bin/fetch-plugins.sh"
UPD="utils/FOGUpdater/fogupdater.sh"

for f in "$FUNCS" "$PLUG" "$UPD"; do
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
# and k bundled into a cluster (-fksL, -sk, -kL) all count, and the bundle form
# is how most of these were actually written. Matching only " -k " would have
# missed six of the ten sites this change removed.
unverified() {
    joined "$1" \
        | grep -E '(^|[^-[:alnum:]_])(curl|wget)[[:space:]]' \
        | grep -vE '^[[:space:]]*echo ' \
        | grep -E -- '--insecure|--no-check-certificate|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'
}

echo "1. only the documented node-bootstrap calls skip verification"

# The allowlist is by URL, not by line number: each of the three is the only
# call in this tree that talks to that endpoint.
allowed_re='check_node_exists\.php|create_update_node\.php|nodecert\.php'

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
done < <(unverified "$FUNCS"; unverified "$PLUG"; unverified "$UPD")

if [[ $stray -eq 0 ]]; then
    ok "no unverified call outside the node-bootstrap allowlist"
else
    bad "$stray call(s) skip TLS verification and are not a documented node-bootstrap call"
fi

# Pinning the count as well as the allowlist. Without this a fourth call to
# create_update_node.php -- or a new one to nodecert.php -- would inherit the
# exemption silently, which is exactly how the flag spread in the first place.
if [[ $found -eq 4 ]]; then
    ok "exactly 4 unverified calls remain (the Group C node bootstrap)"
else
    bad "expected exactly 4 unverified calls, found $found -- if this is deliberate, say why here"
fi

# And each of the four must actually be the one it claims to be, not four
# copies of one. A regression that duplicated the check_node_exists call and
# deleted the others would satisfy both assertions above.
for ep in check_node_exists.php create_update_node.php nodecert.php; do
    n=$( { unverified "$FUNCS"; } | grep -c -- "$ep")
    case "$ep:$n" in
        create_update_node.php:2|check_node_exists.php:1|nodecert.php:1)
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
    if body "$fn" "$FUNCS" | grep -qE -- '--insecure|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'; then
        bad "$fn still skips verification -- its sha256 arrives over the same connection"
    else
        ok "$fn verifies TLS"
    fi
done

if joined "$PLUG" | grep -E 'curl[[:space:]]' | grep -qE -- '--insecure|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'; then
    bad "$PLUG still skips verification -- what it fetches becomes PHP the web tier runs"
else
    ok "$PLUG verifies TLS"
fi

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
# set nothing, or to hand back -k, would satisfy every call-site assertion
# below while restoring the whole bug.
if printf '%s' "$helper" | grep -q -- '--cacert'; then
    ok "_resolveSelfCacert names an anchor with --cacert"
else
    bad "_resolveSelfCacert does not pass --cacert -- it is not anchoring anything"
fi
if printf '%s' "$helper" | grep -q '_resolveTrustAnchor'; then
    ok "_resolveSelfCacert resolves the anchor from the served chain"
else
    bad "_resolveSelfCacert does not call _resolveTrustAnchor"
fi
if printf '%s' "$helper" | grep -qE -- '--insecure|(^|[[:space:]])-[a-zA-Z]*k[a-zA-Z]*([[:space:]]|$)'; then
    bad "_resolveSelfCacert hands back an insecure flag"
else
    ok "_resolveSelfCacert hands back no insecure flag"
fi
if printf '%s' "$helper" | grep -q 'httpproto == https'; then
    ok "_resolveSelfCacert is a no-op on a plain-HTTP install"
else
    bad "_resolveSelfCacert does not gate on \$httpproto -- an http install would get a stray --cacert"
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
    if ! printf '%s' "$text" | grep -q "$needle" ; then
        bad "$what: the call to $needle is gone from $fn"
        return
    fi
    if ! joined "$FUNCS" | grep "$needle" | grep -q 'selfCacertOpts\[@\]'; then
        bad "$what: the call to $needle does not splice in \"\${selfCacertOpts[@]}\""
        return
    fi
    ok "$what is anchored"
}

check_site backupDB 'dbhttpcode=' "backupDB's fetch of backup_db.php"
check_site updateDB 'X-Fog-Install-Token' "the schema update (carries the install token)"

# The schema probe is not inside a function with a name worth pinning, so it is
# matched on the variable the response lands in.
if joined "$FUNCS" | grep 'probeBody' | grep -q 'selfCacertOpts\[@\]'; then
    ok "the schema probe is anchored"
else
    bad "the schema probe does not splice in \"\${selfCacertOpts[@]}\""
fi

echo
echo "4. the helper behaves (behavioural)"

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
error_log="$tmp/error.log"
fogprogramdir="$tmp/opt-fog"
mkdir -p "$fogprogramdir"

# shellcheck source=/dev/null
source "$FUNCS" >/dev/null 2>&1

# A plain-HTTP install must be left exactly as it was: no --cacert, and no
# attempt to resolve an anchor that will not exist.
httpproto=http
rootCAPem=""
sslcachain=""
selfCacertOpts=(poison)
_resolveSelfCacert
if [[ ${#selfCacertOpts[@]} -eq 0 ]]; then
    ok "http install: no --cacert added"
else
    bad "http install: got ${#selfCacertOpts[@]} option(s), expected none"
fi

# https with nothing to anchor -- curl falls back to the system store, which is
# the right answer on an install whose certificate came from a public CA.
httpproto=https
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
    selfCacertOpts=()
    _resolveSelfCacert
    if [[ ${#selfCacertOpts[@]} -eq 2 && ${selfCacertOpts[0]} == "--cacert" && -s ${selfCacertOpts[1]} ]]; then
        ok "https with a root on disk: --cacert points at a non-empty anchor"
    else
        bad "https with a root on disk: got '${selfCacertOpts[*]}'"
    fi
else
    echo "  SKIP  openssl absent, cannot build a throwaway CA"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
