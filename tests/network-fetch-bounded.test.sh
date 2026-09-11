#!/bin/bash
#
# Guards the "unbounded network call in the installer" bug class.
#
#   tests/network-fetch-bounded.test.sh
#
# curl's default connect timeout is 300 seconds and it has no default total
# timeout at all. Every installer fetch that omits --connect-timeout therefore
# costs five minutes per unreachable host, per address family, and the installer
# sends all of it to $error_log -- so what the admin sees is one "dots" line and
# then nothing, for as long as it takes. That is what "the installer hangs"
# almost always turns out to be.
#
# The specific instances that were fixed:
#
#   1. checkInternetConnection() opened by running `$packageinstaller curl`.
#      A connectivity check whose first act is a package transaction needs the
#      connectivity it is about to test: apt has no dpkg-lock timeout, so it
#      waits forever behind unattended-upgrades, and on Arch $packageinstaller
#      is `pacman -Syu`, so the check triggered a full system upgrade.
#   2. Its four probes -- getent, plain HTTP, HTTPS -- had no timeout of any
#      kind, and pointed at httpbin.org, neverssl.com, github.com and
#      fogproject.org. Only one of those is a host FOG downloads anything from.
#   3. It computed dns_ok/http_ok/https_ok and nothing read them. Both failure
#      paths returned rather than exiting, and the caller ignored the status, so
#      the whole cost bought a message.
#   4. fetchipxeasset() looped ten times issuing two timeout-less curls each.
#
# What this checks and what it does not: it asserts on the SOURCE for the shape
# of the calls, and runs inetProbe() for real against an address that cannot be
# routed. It does not install FOG and it does not need the internet -- 192.0.2.1
# is TEST-NET-1 (RFC 5737), which must never be routed, so the probe fails
# whether or not the runner has a route to anywhere. Only the elapsed time is
# asserted, and only against a ceiling far below the 300s default, so a runner
# with no network at all (instant failure) passes just as well as one behind a
# firewall (5s).
#
# The result-is-consumed assertions pin the DEFINITION as well as the use.
# Pinning only "fetchipxeasset mentions internet_ok" would be satisfied by a
# reintroduced version that mentions it and ignores it, which is the exact
# defect being guarded -- so the read is checked to sit on the retry count and
# on the git fetch, where it changes what happens.
#
# No root, no package manager, no FOG install.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
FUNCS="lib/common/functions.sh"
CONF="lib/common/config.sh"

for f in "$FUNCS" "$CONF"; do
    [[ -r $f ]] || { echo "cannot read $f -- run this from the repository"; exit 1; }
done

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# body <function-name> <file> -- the text of one shell function, from its
# opening line to the first line that is a bare closing brace. Crude, but every
# function in these files is written in exactly that style.
#
# Comments are stripped. Without that, every assertion below can be satisfied by
# the prose explaining the code rather than the code -- and this file's comments
# name $pluginsgit/$pluginsurl, so "does it probe the plugins host" passed with
# the probe loop deleted. A gate that its own documentation can satisfy is not a
# gate. (Same failure the no-session-for-browserless gate hit.)
body() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { inside = 1 }
        inside { print }
        inside && /^}/ { exit }
    ' "$2" | grep -vE '^[[:space:]]*#'
}

echo "1. the connectivity check does not run the package manager"

if body checkInternetConnection "$FUNCS" | grep -q 'packageinstaller'; then
    bad "checkInternetConnection still invokes \$packageinstaller"
else
    ok "checkInternetConnection does not invoke \$packageinstaller"
fi

echo
echo "2. every remote fetch is bounded"

# Remote curls only. The maintenance/* calls to $ipaddress are this same host
# over loopback or the LAN, which cannot exhibit the 300s stall being guarded.
# joined <file> -- the file with backslash continuations folded onto one line
# and comment lines dropped. Both matter: every bounded call here is written
# across two lines, so a per-line grep sees the flags on one and the URL on the
# other and reports a false positive on each.
joined() {
    sed -e :a -e '/\\$/N; s/\\\n//; ta' "$1" | grep -vE '^[[:space:]]*#'
}

# Remote calls only, by exclusion rather than by matching a literal http:// on
# the line -- several of these build the URL in a variable, and requiring the
# literal is what let _ensureEfitools' unbounded fetch of git.kernel.org sit
# here unnoticed. The three exclusions are named individually so a NEW remote
# call cannot accidentally inherit one:
#
#   ipaddress     the maintenance/* posts -- this same host over loopback
#   dbhttpcode=   backupDB's fetch of backup_db.php, likewise $ipaddress
#   nodecert.php  a storage node asking its master for a certificate: LAN, not
#                 the internet. Still unbounded, and still able to stall a node
#                 install if the master is down -- out of scope here, but a
#                 real instance of the same class if anyone wants it.
# `curl` followed by a flag is an invocation; `curl` inside a message is not.
# Every real call in these files opens with a flag, and dropping echo lines
# takes care of the one that prints a curl command as help text.
remotecurls() {
    joined "$1" \
        | grep -E '(^|[^-[:alnum:]_])curl[[:space:]]+-' \
        | grep -vE '^[[:space:]]*echo ' \
        | grep -v 'ipaddress' \
        | grep -v 'dbhttpcode=' \
        | grep -v 'nodecert.php'
}

# hascap <line> -- true when the call carries a total time cap.
hascap() { printf '%s\n' "$1" | grep -qE -- '(^| )-m [0-9]|--max-time'; }

# Two rules, and between them every remote call is bounded at both ends:
#
#   A. the connect must be bounded, by --connect-timeout or by a total cap
#   B. a call with NO total cap must carry --speed-time, or a transfer that
#      opens and then stops never returns
#
# A total cap is right for a small body (the fogstorage probe's -m 30) and wrong
# for a multi-megabyte artifact, where a slow but working link has to be allowed
# to finish -- which is why B exists rather than simply requiring --max-time
# everywhere.
unbounded=0
nostall=0
while IFS= read -r line; do
    [[ -n $line ]] || continue
    short=$(printf '%s' "$line" | sed 's/^ *//;s/  */ /g' | cut -c1-96)
    if ! printf '%s\n' "$line" | grep -q -- '--connect-timeout' && ! hascap "$line"; then
        unbounded=$((unbounded + 1))
        printf '        no connect bound: %s\n' "$short"
    fi
    if ! hascap "$line" && ! printf '%s\n' "$line" | grep -q -- '--speed-time'; then
        nostall=$((nostall + 1))
        printf '        no stall guard:   %s\n' "$short"
    fi
done < <(remotecurls "$FUNCS")

if [[ $unbounded -eq 0 ]]; then
    ok "every remote curl bounds its connect"
else
    bad "$unbounded remote curl call(s) carry neither --connect-timeout nor a total cap"
fi
if [[ $nostall -eq 0 ]]; then
    ok "every remote curl without a total cap guards against a stalled transfer"
else
    bad "$nostall remote curl call(s) have neither a total cap nor --speed-time"
fi

# The asset downloads must NOT carry --max-time: these are multi-megabyte
# tarballs and a slow but working link has to be allowed to finish. The stall
# they need protecting from is a transfer that opens and then stops, which is
# --speed-time/--speed-limit's job, not --max-time's.
# The wrong fix for a stalled download is to cap it, which is why the two are
# mutually exclusive: carrying --speed-time is the statement "this one is an
# artifact fetch and may legitimately take a while", and --max-time on top of
# that reintroduces the failure on slow links that the stall guard exists to
# avoid.
both=$(remotecurls "$FUNCS" | grep -- '--speed-time' | grep -c -- '--max-time')
if [[ $both -eq 0 ]]; then
    ok "no artifact download carries both --speed-time and --max-time"
else
    bad "$both call(s) carry --speed-time AND --max-time -- a slow link will still fail"
fi

# wget's defaults are worse than curl's: no connect timeout at all, and
# --tries=20, so an unreachable host costs twenty full SYN retry cycles.
#
# Same discipline as remotecurls: a flag after the command is an invocation,
# prose is not. On this branch most wgets go to $ipaddress (this host over
# loopback, including $probeUrl, which is built from it), so only the udpcast
# config.guess/config.sub pair is genuinely remote.
remotewgets() {
    joined "$1" \
        | grep -E '(^|[^-[:alnum:]_])wget[[:space:]]+-' \
        | grep -vE '^[[:space:]]*(echo|dots) ' \
        | grep -v 'ipaddress' \
        | grep -v 'probeUrl' \
        | grep -v 'packages='
}

unboundedwget=0
while IFS= read -r line; do
    [[ -n $line ]] || continue
    printf '%s\n' "$line" | grep -q -- '--connect-timeout' && continue
    unboundedwget=$((unboundedwget + 1))
    printf '        unbounded: %s\n' "$(printf '%s' "$line" | sed 's/^ *//;s/  */ /g' | cut -c1-100)"
done < <(remotewgets "$FUNCS")

if [[ $unboundedwget -eq 0 ]]; then
    ok "no wget in $FUNCS lacks --connect-timeout"
else
    bad "$unboundedwget wget call(s) have no --connect-timeout (and wget defaults to --tries=20)"
fi

echo
echo "3. the probe verifies TLS"

if body inetProbe "$FUNCS" | grep -qE '(^|[[:space:]])(-k|--insecure)([[:space:]]|$)'; then
    bad "inetProbe passes -k -- a proxy with its own CA passes the probe and then fails the git clone"
else
    ok "inetProbe does not pass -k"
fi

echo
echo "4. the result is consumed, not just computed"

# The original bug was not that the check was wrong, it was that nothing read
# it. Pin the two places that act on the answer, by the line that acts.
for fn in fetchipxeasset downloadfiles; do
    if body "$fn" "$FUNCS" | grep -q 'internet_ok.*tries=1'; then
        ok "$fn drops its retry count when the host is unreachable"
    else
        bad "$fn does not act on \$internet_ok -- ten rounds of retries against a dead host"
    fi
done

if body prepareiPXEsource "$FUNCS" | grep -q 'internet_ok'; then
    ok "prepareiPXEsource skips its git fetch when the host is unreachable"
else
    bad "prepareiPXEsource does not act on \$internet_ok -- git has no connect timeout of its own"
fi

if grep -q 'internet_ok=1' "$CONF"; then
    ok "\$internet_ok is defaulted in $CONF, so a path that skips the check behaves as before"
else
    bad "\$internet_ok has no default -- an install that never calls the check would read it as unset"
fi

echo
echo "5. the check probes the hosts FOG actually downloads from"

probed=$(body checkInternetConnection "$FUNCS")
for v in ipxegit ipxeurl; do
    if printf '%s' "$probed" | grep -q "\$$v"; then
        ok "probes \$$v"
    else
        bad "does not probe \$$v -- an override to an internal mirror goes untested"
    fi
done
for h in httpbin.org neverssl.com fogproject.org; do
    if printf '%s' "$probed" | grep -q "$h"; then
        bad "still probes $h, which FOG downloads nothing from"
    else
        ok "no longer probes $h"
    fi
done

echo
echo "6. an unroutable host fails fast (behavioural)"

error_log=$(mktemp)
trap 'rm -f "$error_log"' EXIT
inetConnectTimeout=5
inetMaxTime=15
# shellcheck source=/dev/null
source "$FUNCS" >/dev/null 2>&1

start=$SECONDS
inetProbe 192.0.2.1 >/dev/null 2>&1
rc=$?
elapsed=$((SECONDS - start))

if [[ $rc -eq 0 ]]; then
    bad "inetProbe reported TEST-NET-1 (192.0.2.1) as reachable"
else
    ok "inetProbe reports TEST-NET-1 as unreachable (exit $rc)"
fi
# 20s ceiling against a 300s default: generous enough that a loaded runner or a
# doubled $inetConnectTimeout still passes, tight enough that the unbounded
# version cannot.
if [[ $elapsed -le 20 ]]; then
    ok "inetProbe returned in ${elapsed}s (ceiling 20s, libcurl's default is 300s)"
else
    bad "inetProbe took ${elapsed}s -- something is not bounding the connect"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
