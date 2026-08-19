#!/bin/bash
#
# Guards how the install settings resolve, and the one-shot migration.
#
#   tests/install-settings-resolution.test.sh
#
# $httpProto used to mean three unrelated things at once: "FOG uses HTTPS for
# its own URLs", "redirect HTTP to HTTPS", and "rebuild iPXE with the CA baked
# in". Splitting them into httpsRedirect / publicWebCert / rebuildIpxeWithMyCA
# is only safe if two properties hold, and neither is visible by reading the
# code:
#
#   1. An existing server does not silently acquire a redirect, or silently
#      lose netboot, when httpproto moves to https for everyone.
#   2. The migration that seeds httpsRedirect from a pre-existing
#      httpproto=https fires ONCE. If it re-fires, an admin who turns the
#      redirect off has it turned back on by the next upgrade -- and because
#      HSTS rides on the same key, "turned back on" reaches browsers that then
#      refuse plain HTTP for six months from their own cache.
#
# The resolution and preset functions are sourced from lib/common/functions.sh
# and exercised directly. The migration block lives inline in bin/installfog.sh
# rather than in a function, so it is REPLAYED here rather than called; the
# replay is kept adjacent to the original and any drift shows up as a failure
# in the round-trip cases below, which assert on the real managed-key list.
#
# No network, no root, no install.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
[[ -f $INSTALLER ]] || { echo "ERROR: $INSTALLER not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log=/dev/null
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# --- the migration, replayed exactly as bin/installfog.sh performs it --------
# persisted_httpproto / persisted_redirect stand in for .fogsettings.
migrate() {
    httpProto=""; httpsRedirect=""; publicWebCert=""; rebuildIpxeWithMyCA=""; netbootProto=""
    [[ -n $1 ]] && httpProto="$1"                       # .fogsettings
    [[ -n $2 ]] && httpsRedirect="$2"
    # Defaulted AFTER the source, not before it. installfog.sh used to seed
    # httpProto="http" ahead of the source; it cannot any more, because a value
    # already sitting in the camelCase name is indistinguishable, to
    # _migrateLegacySettingNames(), from one an admin persisted under the old
    # lower-case name -- and the persisted one would lose.
    [[ -z $httpProto ]] && httpProto="http"
    if [[ -z $httpsRedirect ]]; then
        [[ $httpProto == https ]] && httpsRedirect="yes" || httpsRedirect="no"
    fi
    httpProto="https"
    [[ -z $publicWebCert ]] && publicWebCert="no"
    [[ -z $rebuildIpxeWithMyCA ]] && rebuildIpxeWithMyCA="no"
}

echo "install settings resolution:"

# --- migration ---------------------------------------------------------------

# A fresh install must NOT get a redirect. On a new server nothing has
# fog-client yet, so no machine has inherited trust in FOG's CA, and a forced
# redirect would break exactly the ones that cannot fix themselves.
migrate "" ""
is "$httpProto|$httpsRedirect" "https|no" "fresh install: https, no redirect"

# The case that must not regress: an existing plain-HTTP server. It gains HTTPS
# availability (443 already listened) and must not gain a redirect.
migrate "http" ""
is "$httpProto|$httpsRedirect" "https|no" "upgrade from http: https, still no redirect"

# An existing httpproto=https is the only evidence its admin ever ran -S.
migrate "https" ""
is "$httpProto|$httpsRedirect" "https|yes" "upgrade from https: redirect seeded once"

# ...and having been seeded, it is persisted, so the seeding must never run
# again. An admin who turns it off keeps it off.
migrate "https" "no"
is "$httpsRedirect" "no" "third run: an admin's 'no' survives, seeding does not re-fire"

migrate "https" "yes"
is "$httpsRedirect" "yes" "third run: an admin's 'yes' survives"

# --- the four modes ----------------------------------------------------------
mode() { sinstallMode="$1"; _applyInstallMode; }

mode standard
is "$httpProto|$netbootProto|$publicWebCert|$rebuildIpxeWithMyCA" \
   "https|http|no|no" "standard: HTTPS web, HTTP netboot, no rebuild"

mode http-only
is "$httpProto|$netbootProto|$publicWebCert|$rebuildIpxeWithMyCA" \
   "http|http|no|no" "http-only: plain HTTP throughout"

mode public-cert
is "$httpProto|$netbootProto|$publicWebCert|$rebuildIpxeWithMyCA" \
   "https|https|yes|no" "public-cert: HTTPS netboot with NO rebuild"

mode embed-ca
is "$httpProto|$netbootProto|$publicWebCert|$rebuildIpxeWithMyCA" \
   "https|https|no|yes" "embed-ca: HTTPS netboot via a rebuild"

# --- netboot transport -------------------------------------------------------
# $1 publicWebCert, $2 rebuildIpxeWithMyCA, $3 the --netboot-proto FLAG,
# $4 a value already sitting in .fogsettings, $5 netbootProtoForced.
#
# $3 and $4 used to be the same argument, which is precisely the bug this
# function's caller was reported for: a value the resolver DERIVED on one run is
# persisted, and was then indistinguishable from one an admin forced. Modelling
# them as one thing is how a test can pass while the behaviour is wrong.
nb() {
    publicWebCert="$1"; rebuildIpxeWithMyCA="$2"
    snetbootProto="$3"; netbootProto="$4"; netbootProtoForced="$5"
    _resolveNetbootProto
    printf '%s' "$netbootProto"
}

is "$(nb no no '')"   "http"  "netboot defaults to http"
is "$(nb yes no '')"  "https" "publicWebCert steers netboot to https"
is "$(nb no yes '')"  "https" "rebuildIpxeWithMyCA steers netboot to https"
is "$(nb yes yes '')" "https" "both triggers together still https"
# An explicit --netboot-proto wins in BOTH directions. The second is the one
# that matters: an admin with a public certificate who wants netboot on HTTP
# anyway must be able to say so.
is "$(nb no no https)"  "https" "explicit --netboot-proto https wins"
is "$(nb yes no http)"  "http"  "explicit --netboot-proto http wins over publicWebCert"

# The reported regression. A run with neither trigger resolves http and persists
# it; the admin then declares publicWebCert="yes" and re-runs. The persisted
# value must NOT survive that -- it was derived from the very key that changed.
is "$(nb yes no '' http '')"   "https" "a persisted derived http is re-derived when publicWebCert appears"
is "$(nb no yes '' http '')"   "https" "...and when rebuildIpxeWithMyCA appears"
is "$(nb no no '' https '')"   "http"  "a persisted derived https is re-derived when both triggers go away"

# ...but a value the admin actually forced does survive, which is the whole
# reason the marker exists rather than the persisted value simply being ignored.
is "$(nb yes no '' http yes)"  "http"  "a FORCED http survives publicWebCert=yes"
is "$(nb no no '' https yes)"  "https" "a FORCED https survives with neither trigger set"

# The old resolver keyed on $caCreated, a PERSISTED key that is "yes" on every
# re-run of an existing server. With httpproto now https for everyone, that
# would have resolved netbootproto=https on every upgraded install in
# existence -- the one configuration that cannot work behind a private CA.
caCreated="yes"; externalCA="yes"
is "$(nb no no '')" "http" "a pre-existing CA does NOT drag netboot onto https"
caCreated=""; externalCA=""

# --- warnings ----------------------------------------------------------------
report() {
    netbootProto="$1"; publicWebCert="$2"; rebuildIpxeWithMyCA="$3"; httpProto=https
    _reportNetbootProto 2>&1
}

if [[ "$(report http no no)" == *"Netboot (PXE) is using HTTP"* ]]; then
    ok "reverting to HTTP netboot is reported, not silent"
else
    bad "no notice printed when netboot fell back to HTTP"
fi
if [[ "$(report http no no)" == *"Secure Boot binaries ARE staged"* ]]; then
    ok "the notice says Secure Boot is available and how to enrol"
else
    bad "the HTTP notice does not mention Secure Boot onboarding"
fi
if [[ "$(report https no no)" == *"WARNING"* ]]; then
    ok "forcing https netboot with neither trigger warns"
else
    bad "https netboot with neither publicWebCert nor rebuild is not warned about"
fi
if [[ "$(report https yes no)" == "" ]]; then
    ok "a legitimate https netboot says nothing"
else
    bad "publicWebCert https netboot printed a warning it should not"
fi

# --- persistence -------------------------------------------------------------
# Every key this change introduces has to survive a round trip through
# .fogsettings, or the migration re-fires and the admin's choice is lost.
for key in httpsRedirect publicWebCert rebuildIpxeWithMyCA netbootProto; do
    if grep -qE "^[[:space:]]*.*\b${key}\b" <(sed -n '/local -a managedKeys=(/,/^    )/p' "$FUNCS"); then
        ok "${key} is a managed key"
    else
        bad "${key} is NOT in managedKeys -- it will not survive an upgrade"
    fi
done

# --- flag surface ------------------------------------------------------------
for opt in install-mode public-web-cert rebuild-ipxe-with-my-ca https-redirect netboot-proto; do
    if grep -q -- "$opt" <(grep '^longopts=' "$INSTALLER"); then
        ok "--${opt} is accepted"
    else
        bad "--${opt} is missing from longopts"
    fi
    # Undocumented flags are how --netboot-proto sat unusable for so long.
    if grep -q -- "--${opt}" <(sed -n '/^usage()/,/^}/p' "$INSTALLER"); then
        ok "--${opt} is documented in usage()"
    else
        bad "--${opt} is not documented in usage()"
    fi
done

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
