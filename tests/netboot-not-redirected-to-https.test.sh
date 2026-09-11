#!/bin/bash
#
# Guards GH-978: the forced-HTTPS redirect must not catch the netboot path.
#
#   tests/netboot-not-redirected-to-https.test.sh
#
# FOG's HTTP->HTTPS redirect was written in 2017 scoped to the management UI:
#
#   RewriteRule /management/ https://%{HTTP_HOST}%{REQUEST_URI}%{QUERY_STRING} [R,L]
#
# 2b8bacfed ("Make sure query string is passed properly", 2017-04-29) replaced
# the whole rule with `(.*)` to correct how the query string was carried, and
# widening it from the management UI to the entire site came along with that.
# From then on a --force-https install also redirected every path a BOOTLOADER
# fetches.
#
# That breaks boot outright for the binaries that need HTTP most:
# downloadipxesecureboot() stages upstream's Microsoft-signed shim and iPXE's
# signed loader, which are built with no TRUST=/CERT= and so can never validate
# FOG's private CA. Redirected to HTTPS they print "Permission denied" and
# stop. So Secure Boot and --force-https were mutually exclusive on this line.
#
# WHY THIS TEST RUNS A REAL APACHE
#
# The whole fix is one `RewriteCond %{REQUEST_URI} !^${webrootre}service/ipxe/`,
# and whether it does anything at all turns on a detail no source assertion can
# see: in VIRTUAL HOST context %{REQUEST_URI} carries its leading slash, and so
# does $webroot. Get either wrong -- store the webroot bare, anchor the pattern
# differently -- and the condition silently never matches, the redirect fires
# anyway, and a grep for the line still passes. That is the same class of fake
# gate as the sibling rewrite bug this very issue reported: `(.*)` versus
# `^/?(.*)$` is the identical leading-slash question, and it went unnoticed for
# eight years.
#
# So section 2 emits the vhost from the INSTALLER'S OWN echo lines, starts
# httpd on it, and asks Apache what it does. Section 1's source checks exist
# only to catch the lines being deleted outright.
#
# Skips (exit 0) when no Apache or no mod_rewrite is present.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
FUNCS="lib/common/functions.sh"
[[ -r $FUNCS ]] || { echo "cannot read $FUNCS -- run this from the repository"; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# The installer's redirect emission: every `echo "    Rewrite..."` line in the
# https arm of the vhost writer, comments stripped. Lifted rather than
# reproduced so the test cannot drift from the shipped config.
#
# This file's own prose quotes RewriteRule and RewriteCond repeatedly, and the
# block comment at the call site does too; a gate its documentation satisfies
# is not a gate.
rewrite_echoes() {
    awk '
        /if \[\[ \$httpproto == https \]\]; then/ { inside = 1 }
        inside && /echo "<\/VirtualHost>"/        { exit }
        inside && /^[[:space:]]*echo "[[:space:]]*Rewrite/ { print }
    ' "$FUNCS" | sed 's/^[[:space:]]*//'
}

echo "1. the emission still carries both conditions and the anchored rule"

emitted=$(rewrite_echoes)

if printf '%s\n' "$emitted" | grep -q 'RewriteCond %{REQUEST_URI} !\^\${webrootre}service/ipxe/'; then
    ok "the netboot exemption is emitted"
else
    bad "no RewriteCond exempting service/ipxe/ -- GH-978 regression"
fi

# Order is load-bearing in a way that is easy to get wrong silently: a
# RewriteCond binds to the NEXT RewriteRule only. An exemption emitted after
# the redirect rule guards nothing and guards it invisibly.
exemptline=$(printf '%s\n' "$emitted" | grep -n 'REQUEST_URI} !\^\${webrootre}service/ipxe/' | cut -d: -f1)
ruleline=$(printf '%s\n' "$emitted" | grep -n 'RewriteRule \^/?(\.\*)' | cut -d: -f1)
if [[ -n $exemptline && -n $ruleline && $exemptline -lt $ruleline ]]; then
    ok "the exemption precedes the redirect rule it guards"
else
    bad "the exemption does not sit immediately before the redirect rule (cond=$exemptline rule=$ruleline)"
fi

echo
echo "2. behavioral -- Apache honors it in vhost context"

httpd_bin=""
for c in httpd apache2; do command -v "$c" >/dev/null 2>&1 && { httpd_bin=$(command -v "$c"); break; }; done
modroot=""
for d in /usr/lib64/httpd/modules /usr/lib/apache2/modules /usr/libexec/apache2; do
    [[ -f $d/mod_rewrite.so ]] && { modroot=$d; break; }
done

if [[ -z $httpd_bin || -z $modroot ]]; then
    echo "  SKIP  needs httpd/apache2 with mod_rewrite to answer this"
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    [[ $FAIL -eq 0 ]]
    exit
fi

tmp=$(mktemp -d) || exit 1
cleanup() {
    [[ -f $tmp/httpd.pid ]] && kill "$(cat "$tmp/httpd.pid")" 2>/dev/null
    rm -rf "$tmp"
}
trap cleanup EXIT

port=$(( 20000 + RANDOM % 20000 ))
docroot="$tmp/docroot"
mkdir -p "$docroot/fog/service/ipxe" "$docroot/fog/management" "$docroot/fog/service"
echo '#!ipxe' > "$docroot/fog/service/ipxe/boot.php"
echo 'menu'   > "$docroot/fog/management/index.php"
echo 'checkin'> "$docroot/fog/service/checkin.php"

# The installer's own lines, evaluated with the variables it evaluates them
# with. httpproto is not consulted here -- rewrite_echoes already selected the
# https arm -- but webroot/webrootre are exactly as the installer computes.
webroot="/fog/"
webrootre=$(printf '%s' "$webroot" | sed 's/[.[\*^$()+?{|]/\\&/g')
etcconf="$tmp/rewrite.inc"
: > "$etcconf"
while IFS= read -r line; do
    [[ -n $line ]] || continue
    eval "$line"
done < <(rewrite_echoes)

modline() { [[ -f $modroot/$2 ]] && echo "LoadModule $1 $modroot/$2"; }
{
    echo "ServerRoot $tmp"
    echo "PidFile $tmp/httpd.pid"
    echo "ErrorLog $tmp/error.log"
    echo "Listen 127.0.0.1:$port"
    modline mpm_event_module mod_mpm_event.so
    modline authz_core_module mod_authz_core.so
    modline unixd_module mod_unixd.so
    modline log_config_module mod_log_config.so
    modline rewrite_module mod_rewrite.so
    echo "ServerName 127.0.0.1"
    echo "DocumentRoot $docroot"
    echo "<Directory $docroot>"
    echo "    Require all granted"
    echo "</Directory>"
    echo "<VirtualHost 127.0.0.1:$port>"
    cat "$etcconf"
    echo "</VirtualHost>"
} > "$tmp/httpd.conf"

if ! "$httpd_bin" -f "$tmp/httpd.conf" -k start 2>"$tmp/start.err"; then
    # Deliberately a FAILURE, not a skip. The skip above is for "this box has
    # no Apache", which is a fact about the runner. Apache being present and
    # refusing this config is a fact about the config, and silently downgrading
    # that to a skip would let the one section that can see the real bug stop
    # running while the file still reported success.
    bad "$httpd_bin would not start on the generated vhost"
    sed 's/^/        /' "$tmp/start.err" "$tmp/error.log" 2>/dev/null | grep -v '^ *$' | head -5
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    exit 1
fi

for _ in $(seq 1 40); do
    curl -s -o /dev/null -m 1 "http://127.0.0.1:$port/" && break
    sleep 0.1
done

# --max-redirs 0 so a redirect is reported as a redirect rather than followed.
code() { curl -s -o /dev/null -w '%{http_code}' --max-redirs 0 "http://127.0.0.1:$port$1"; }
loc()  { curl -s -o /dev/null -w '%{redirect_url}' --max-redirs 0 "http://127.0.0.1:$port$1"; }

# The reporter's exact request.
c=$(code /fog/service/ipxe/boot.php)
if [[ $c == 2* ]]; then
    ok "service/ipxe/boot.php is served over HTTP (got $c)"
else
    bad "service/ipxe/boot.php answered $c -- the bootloader path is still being redirected"
fi

# And the redirect must still do its job everywhere else, or the exemption has
# simply disabled the feature.
c=$(code /fog/management/index.php)
if [[ $c == 30* ]]; then
    ok "the management UI is still redirected to HTTPS (got $c)"
else
    bad "the management UI answered $c -- forced HTTPS is no longer being applied"
fi

# The exemption is one directory, not all of service/. fog-client's endpoints
# ride HTTPS like everything else; a blanket !^/fog/service/ was considered and
# rejected precisely because it would exempt them too.
c=$(code /fog/service/checkin.php)
if [[ $c == 30* ]]; then
    ok "the rest of service/ is still redirected (got $c)"
else
    bad "service/checkin.php answered $c -- the exemption is wider than service/ipxe/"
fi

# The sibling half of GH-978, pinned here because this is the only place a real
# Apache evaluates the rule: vhost context matches the URL-path WITH its
# leading slash, so a bare (.*) emits a doubled slash in Location.
l=$(loc /fog/management/index.php)
if [[ $l != *"//fog/"* ]]; then
    ok "the redirect Location carries no doubled slash ($l)"
else
    bad "the redirect Location has a doubled slash: $l"
fi

echo
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
