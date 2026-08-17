#!/bin/bash
#
# Guards the HTTP->HTTPS redirect's exclusion list in both generated vhosts.
#
#   tests/vhost-netboot-exclusion.test.sh
#
# When $httpproto is https and $netbootproto is http, the :80 vhost redirects
# everything to HTTPS -- except the paths iPXE itself fetches, because iPXE
# validates TLS strictly, has no --insecure, and cannot chain a private CA. A
# path that falls through to the redirect does not fail visibly on the server;
# it fails at a PXE-booting machine, as a boot that stops, with nothing in the
# web server's log to say why.
#
# What this checks and what it does not: it asserts on the GENERATOR in
# lib/common/functions.sh, not on a running web server. Rendering the real thing
# means calling createSSLCA(), which mints a PKI, writes to /etc and restarts
# services. The same trade-off as fos's tests/checks/*-config.sh harnesses,
# which assert on the kernel config rather than on a booted kernel. So this
# catches "someone dropped a directory from the list" -- the actual regression
# risk -- and not "nginx parses the result".
#
# Three things are pinned:
#
#   1. Both netboot-reachable directories are excluded, in BOTH web servers.
#      service/ipxe/ is the obvious one. service/secureboot/ is not, and was
#      missing: BootMenu imgfetches MOK.der and chains mmx64.efi /
#      arm64-efi/mmaa64.efi out of it, so Secure Boot enrolment was being
#      redirected onto an HTTPS iPXE could not validate.
#   2. ca.cert.der is reachable over plain HTTP in BOTH web servers. Apache has
#      had this exemption since GH-529; nginx never did, which made fetching
#      the CA require already trusting the CA.
#   3. nginx's redirect stays a `location /`, never a server-level `return`.
#      That one was measured against real nginx: a server-level return runs in
#      the server rewrite phase, BEFORE location selection, so it fires for
#      every request and every exclusion above it becomes dead code -- silently.
#
# No openssl, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# countof <fixed-string> -- occurrences of a literal line fragment.
countof() { grep -cF -- "$1" "$FUNCS" 2>/dev/null || true; }

# want <expected-count> <fixed-string> <label>
want() {
    local got
    got=$(countof "$2")
    [[ $got == "$1" ]] && ok "$3" || bad "$3 (expected $1 occurrence(s), found $got)"
}

echo "vhost netboot exclusion:"

# 1. The excluded set, once per web server. Written as a loop over the
#    directory names so the two branches cannot drift apart -- if this
#    assertion is what broke, the fix is to add the directory to the loop, not
#    to relax the test.
want 2 'for nbdir in ipxe secureboot; do' \
    "both web servers iterate the same netboot-reachable directory set"

want 1 'location ^~ ${webroot}service/${nbdir}/ {' \
    "nginx excludes each netboot directory with a prefix location"

want 1 'RewriteCond %{REQUEST_URI} !^${webrootre}service/${nbdir}/' \
    "apache excludes each netboot directory with a RewriteCond"

# `^~` specifically: a plain prefix location loses to a regex location, and
# service/ipxe/ has to beat `location /`. Without it the exclusion parses fine
# and does nothing.
if grep -qF 'location ^~ ${webroot}service/${nbdir}/ {' "$FUNCS"; then
    ok "nginx uses ^~, which is what beats location /"
else
    bad "nginx netboot location is not ^~ -- it will lose to location /"
fi

# 2. The CA download, in both. Deliberately NOT under the netbootproto guard:
#    the client this serves is one that trusts nothing yet, which has nothing
#    to do with which transport netboot uses.
want 1 'location = ${webroot}management/other/ca.cert.der {' \
    "nginx serves ca.cert.der over plain HTTP"

want 1 'RewriteRule /management/other/ca.cert.der$ - [L]' \
    "apache serves ca.cert.der over plain HTTP"

# 3. The redirect's own shape. Assert the 308 is immediately preceded by the
#    `location /` that scopes it; a server-level return would not be.
prev=$(grep -B1 -F 'return 308 https://\$host\$request_uri;' "$FUNCS" \
    | grep -F 'location / {' | wc -l | tr -d ' ')
if [[ $prev == "1" ]]; then
    ok "nginx redirect is scoped by location /, not a server-level return"
else
    bad "nginx 308 is not directly inside a 'location / {' block -- every exclusion above it is dead code"
fi

# 4. Both exclusion sets stay behind the netbootproto guard, so an install
#    whose netboot already runs on HTTPS is not given pointless carve-outs.
want 2 'if [[ $netbootproto != "$httpproto" ]]; then' \
    "both exclusion sets are guarded on netbootproto differing from httpproto"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
