#!/bin/bash
#
# Guards _unshadowApacheSite(): a regular file at sites-enabled/001-fog.conf
# must not keep hiding the vhost the installer writes to sites-available/.
#
#   tests/apache-shadowed-site.test.sh
#
# Forum 18306: FOGUefi edited the 1.5 vhost in place through the sites-enabled
# link, which made the link a regular file. a2ensite and a2dissite refuse to
# touch a real file, so after the 1.6 upgrade Apache still served the 1.5
# vhost, and its HTTP-to-HTTPS redirect broke netboot.
#
# Runs on a scratch directory tree -- no install, no Apache, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

fresh() {
    rm -rf "$WORK/apache2"
    mkdir -p "$WORK/apache2/sites-available" "$WORK/apache2/sites-enabled"
    AVAIL="$WORK/apache2/sites-available/001-fog.conf"
    ENABLED="$WORK/apache2/sites-enabled/001-fog.conf"
    echo "# FOG 1.6 managed vhost" > "$AVAIL"
}

echo "== a regular file in sites-enabled is moved aside =="
fresh
echo "RewriteRule (.*) https://%{HTTP_HOST}/\$1 [R,L]" > "$ENABLED"
out="$(_unshadowApacheSite "$AVAIL" 1234 2>&1)"
[[ ! -e $ENABLED ]] && ok "A: the stale file is gone from sites-enabled" \
    || bad "A: the stale file is still in sites-enabled"
grep -q "HTTP_HOST" "$AVAIL.shadowed.1234" 2>/dev/null && ok "B: its content is kept beside the managed vhost" \
    || bad "B: the content was not kept at $AVAIL.shadowed.1234"
[[ $out == *"$ENABLED"* ]] && ok "C: the installer says what it moved" \
    || bad "C: no message names the moved file (got '$out')"
grep -q "managed vhost" "$AVAIL" && ok "D: the managed vhost is untouched" \
    || bad "D: the managed vhost changed"

echo "== a link is left alone =="
fresh
ln -s ../sites-available/001-fog.conf "$ENABLED"
out="$(_unshadowApacheSite "$AVAIL" 1234 2>&1)"
[[ -L $ENABLED ]] && ok "E: the link is still there" || bad "E: the link was removed"
[[ -z $out && ! -e $AVAIL.shadowed.1234 ]] && ok "F: nothing is moved or printed" \
    || bad "F: a link was treated as stale (got '$out')"

echo "== nothing enabled =="
fresh
_unshadowApacheSite "$AVAIL" 1234 >/dev/null 2>&1; rc=$?
[[ $rc -eq 0 && ! -e $ENABLED ]] && ok "G: no site enabled is not an error" \
    || bad "G: rc=$rc"

echo "== a vhost outside sites-available is not a Debian layout =="
mkdir -p "$WORK/httpd/conf.d"
echo "# rhel" > "$WORK/httpd/conf.d/fog.conf"
_unshadowApacheSite "$WORK/httpd/conf.d/fog.conf" 1234 >/dev/null 2>&1
[[ -f $WORK/httpd/conf.d/fog.conf && ! -e $WORK/httpd/conf.d/fog.conf.shadowed.1234 ]] \
    && ok "H: a conf.d vhost is left alone" || bad "H: a conf.d vhost was moved"

echo "== the installer calls it before a2dissite =="
# Anchored on the call line, so a comment naming the function cannot satisfy it.
callLine="$(grep -n '^[[:space:]]*_unshadowApacheSite "\$etcconf" "\$timestamp"$' "$FUNCS" | head -1 | cut -d: -f1)"
disLine="$(grep -n '^[[:space:]]*a2dissite 001-fog ' "$FUNCS" | head -1 | cut -d: -f1)"
[[ -n $callLine && -n $disLine && $callLine -lt $disLine ]] \
    && ok "I: the call precedes the first a2dissite 001-fog" \
    || bad "I: call line '$callLine', a2dissite line '$disLine'"

echo
echo "passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]]
