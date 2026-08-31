#!/bin/bash
#
# Guards how the install settings resolve, and the one-shot migration.
#
#   tests/install-settings-resolution.test.sh
#
# ${WEB_url_proto} used to mean three unrelated things at once: "FOG uses HTTPS for
# its own URLs", "redirect HTTP to HTTPS", and "rebuild iPXE with the CA baked
# in". Splitting them into httpsRedirect / publicWebCert / rebuildIpxeWithMyCA
# is only safe if two properties hold, and neither is visible by reading the
# code:
#
#   1. An existing server does not silently acquire a redirect, or silently
#      lose netboot, when httpproto moves to https for everyone.
#   2. The migration that seeds httpsRedirect from a pre-existing
#      WEB_url_proto=https fires ONCE. If it re-fires, an admin who turns the
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
    WEB_url_proto=""; WEB_https_redirect=""; PKI_web_cert_publicly_trusted=""; BOOT_rebuild_ipxe_with_my_ca=""; BOOT_url_proto=""
    [[ -z ${WEB_url_proto} ]] && WEB_url_proto="http"            # pre-source default
    [[ -n $1 ]] && WEB_url_proto="$1"                       # .fogsettings
    [[ -n $2 ]] && WEB_https_redirect="$2"
    if [[ -z ${WEB_https_redirect} ]]; then
        [[ ${WEB_url_proto} == https ]] && WEB_https_redirect="yes" || WEB_https_redirect="no"
    fi
    WEB_url_proto="https"
    [[ -z ${PKI_web_cert_publicly_trusted} ]] && PKI_web_cert_publicly_trusted="no"
    [[ -z ${BOOT_rebuild_ipxe_with_my_ca} ]] && BOOT_rebuild_ipxe_with_my_ca="no"
}

echo "install settings resolution:"

# --- migration ---------------------------------------------------------------

# A fresh install must NOT get a redirect. On a new server nothing has
# fog-client yet, so no machine has inherited trust in FOG's CA, and a forced
# redirect would break exactly the ones that cannot fix themselves.
migrate "" ""
is "${WEB_url_proto}|${WEB_https_redirect}" "https|no" "fresh install: https, no redirect"

# The case that must not regress: an existing plain-HTTP server. It gains HTTPS
# availability (443 already listened) and must not gain a redirect.
migrate "http" ""
is "${WEB_url_proto}|${WEB_https_redirect}" "https|no" "upgrade from http: https, still no redirect"

# An existing WEB_url_proto=https is the only evidence its admin ever ran -S.
migrate "https" ""
is "${WEB_url_proto}|${WEB_https_redirect}" "https|yes" "upgrade from https: redirect seeded once"

# ...and having been seeded, it is persisted, so the seeding must never run
# again. An admin who turns it off keeps it off.
migrate "https" "no"
is "${WEB_https_redirect}" "no" "third run: an admin's 'no' survives, seeding does not re-fire"

migrate "https" "yes"
is "${WEB_https_redirect}" "yes" "third run: an admin's 'yes' survives"

# --- the four modes ----------------------------------------------------------
mode() { sinstallMode="$1"; _applyInstallMode; }

mode standard
is "${WEB_url_proto}|${BOOT_url_proto}|${PKI_web_cert_publicly_trusted}|${BOOT_rebuild_ipxe_with_my_ca}" \
   "https|http|no|no" "standard: HTTPS web, HTTP netboot, no rebuild"

mode http-only
is "${WEB_url_proto}|${BOOT_url_proto}|${PKI_web_cert_publicly_trusted}|${BOOT_rebuild_ipxe_with_my_ca}" \
   "http|http|no|no" "http-only: plain HTTP throughout"

mode public-cert
is "${WEB_url_proto}|${BOOT_url_proto}|${PKI_web_cert_publicly_trusted}|${BOOT_rebuild_ipxe_with_my_ca}" \
   "https|https|yes|no" "public-cert: HTTPS netboot with NO rebuild"

mode embed-ca
is "${WEB_url_proto}|${BOOT_url_proto}|${PKI_web_cert_publicly_trusted}|${BOOT_rebuild_ipxe_with_my_ca}" \
   "https|https|no|yes" "embed-ca: HTTPS netboot via a rebuild"

# --- the mode is answered ONCE ------------------------------------------------
#
# promptInstallMode() used to key only on the run-scoped s* shadows, so every
# interactive upgrade got the four-mode menu again -- and any unrecognized reply
# INCLUDING A BARE ENTER takes the `standard` default, which _applyInstallMode
# then wrote over a public-cert or embed-ca server's keys and writeUpdateFile
# persisted. The prompt reverted the very choice it was asking about.
#
# ${FOG_install_mode} is the fix: seeded back into $sinstallMode before
# _applyInstallMode, so the first guard in promptInstallMode has already
# returned by the time the menu would print.
#
# Replayed, like the migration above, because it is inline in bin/installfog.sh
# rather than in a function. The replay covers the forced-https line as well as
# the seed, deliberately -- their ORDER is what the http-only case depends on.
upgrade() {   # $1 persisted FOG_install_mode, $2 persisted WEB_url_proto, $3 --install-mode flag
    FOG_install_mode="$1"; WEB_url_proto="$2"; WEB_https_redirect=""
    PKI_web_cert_publicly_trusted=""; BOOT_rebuild_ipxe_with_my_ca=""; BOOT_url_proto=""
    sinstallMode="$3"
    sWEB_https_redirect=""; sPKI_web_cert_publicly_trusted=""
    sBOOT_rebuild_ipxe_with_my_ca=""; sBOOT_url_proto=""
    if [[ -z ${WEB_https_redirect} ]]; then
        [[ ${WEB_url_proto} == https ]] && WEB_https_redirect="yes" || WEB_https_redirect="no"
    fi
    WEB_url_proto="https"
    [[ -z ${PKI_web_cert_publicly_trusted} ]] && PKI_web_cert_publicly_trusted="no"
    [[ -z ${BOOT_rebuild_ipxe_with_my_ca} ]] && BOOT_rebuild_ipxe_with_my_ca="no"
    [[ -z $sinstallMode ]] && sinstallMode="${FOG_install_mode}"
    _applyInstallMode
    if [[ -n ${sWEB_https_redirect} || -n ${sPKI_web_cert_publicly_trusted} \
        || -n ${sBOOT_rebuild_ipxe_with_my_ca} || -n ${sBOOT_url_proto} ]]; then
        FOG_install_mode=""
    else
        FOG_install_mode="$sinstallMode"
    fi
}

upgrade "" "http" "http-only"
is "${FOG_install_mode}|${WEB_url_proto}" "http-only|http" "a chosen mode is recorded"

# The case the docs used to carry a warning for. WEB_url_proto is forced to
# https unconditionally, so http-only left NO trace in the four keys and could
# not be recovered from them -- it had to be passed again on every upgrade or it
# silently reverted. Applying the preset after that line is what fixes it.
upgrade "http-only" "http" ""
is "${WEB_url_proto}|${FOG_install_mode}" "http|http-only" "http-only survives an upgrade with no flags"

upgrade "public-cert" "https" ""
is "${PKI_web_cert_publicly_trusted}|${BOOT_url_proto}" "yes|https" "public-cert survives an upgrade"

upgrade "embed-ca" "https" ""
is "${BOOT_rebuild_ipxe_with_my_ca}|${BOOT_url_proto}" "yes|https" "embed-ca survives an upgrade"

# A pre-1.6 server has no recorded mode, and must not acquire one: the four keys
# came through migrateDeprecatedKeys and stand on their own.
upgrade "" "https" ""
is "${FOG_install_mode}|${WEB_url_proto}|${BOOT_rebuild_ipxe_with_my_ca}" "|https|no" \
   "a pre-1.6 upgrade gets no mode and no rebuild"

# A discrete flag CLEARS the mode. This is what keeps ${FOG_install_mode} from
# becoming the trap _resolveNetbootProto documents: once a flag has moved one of
# the four keys off its preset, the shape is no longer one of the named modes,
# and a name left behind would have the NEXT run's _applyInstallMode overwrite
# the very key that moved. Empty means custom, which is exactly true.
FOG_install_mode="public-cert"; WEB_url_proto="https"; WEB_https_redirect=""
PKI_web_cert_publicly_trusted=""; BOOT_rebuild_ipxe_with_my_ca=""; BOOT_url_proto=""
sinstallMode=""; sWEB_https_redirect=""; sPKI_web_cert_publicly_trusted=""
sBOOT_url_proto=""; sBOOT_rebuild_ipxe_with_my_ca="no"
[[ -z $sinstallMode ]] && sinstallMode="${FOG_install_mode}"
_applyInstallMode
[[ -n ${sBOOT_rebuild_ipxe_with_my_ca} ]] && BOOT_rebuild_ipxe_with_my_ca=${sBOOT_rebuild_ipxe_with_my_ca}
if [[ -n ${sWEB_https_redirect} || -n ${sPKI_web_cert_publicly_trusted} \
    || -n ${sBOOT_rebuild_ipxe_with_my_ca} || -n ${sBOOT_url_proto} ]]; then
    FOG_install_mode=""
fi
is "${FOG_install_mode}|${BOOT_rebuild_ipxe_with_my_ca}" "|no" \
   "a discrete flag clears the mode and keeps its own value"

# The backstop for the one upgrade the seed cannot cover -- a pre-1.6 server,
# which has no ${FOG_install_mode} to seed from. Asserted on the source because
# the behavior cannot be reached from a test: promptInstallMode also returns
# early when stdin is not a tty, which it never is here, so a behavioral check
# would pass whether the guard existed or not.
guard=$(sed -n '/^promptInstallMode() {/,/^$/p' "$FUNCS" | grep -c 'priorInstall')
is "$guard" "1" "promptInstallMode is guarded on priorInstall"

# --- netboot transport -------------------------------------------------------
# $1 publicWebCert, $2 rebuildIpxeWithMyCA, $3 the --netboot-proto FLAG,
# $4 a value already sitting in .fogsettings, $5 netbootProtoForced.
#
# $3 and $4 used to be the same argument, which is precisely the bug this
# function's caller was reported for: a value the resolver DERIVED on one run is
# persisted, and was then indistinguishable from one an admin forced. Modelling
# them as one thing is how a test can pass while the behavior is wrong.
nb() {
    PKI_web_cert_publicly_trusted="$1"; BOOT_rebuild_ipxe_with_my_ca="$2"
    sBOOT_url_proto="$3"; BOOT_url_proto="$4"; BOOT_url_proto_forced="$5"
    _resolveNetbootProto
    printf '%s' "${BOOT_url_proto}"
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
# it; the admin then declares PKI_web_cert_publicly_trusted="yes" and re-runs. The persisted
# value must NOT survive that -- it was derived from the very key that changed.
is "$(nb yes no '' http '')"   "https" "a persisted derived http is re-derived when publicWebCert appears"
is "$(nb no yes '' http '')"   "https" "...and when rebuildIpxeWithMyCA appears"
is "$(nb no no '' https '')"   "http"  "a persisted derived https is re-derived when both triggers go away"

# ...but a value the admin actually forced does survive, which is the whole
# reason the marker exists rather than the persisted value simply being ignored.
is "$(nb yes no '' http yes)"  "http"  "a FORCED http survives PKI_web_cert_publicly_trusted=yes"
is "$(nb no no '' https yes)"  "https" "a FORCED https survives with neither trigger set"

# The old resolver keyed on $caCreated, a PERSISTED key that was "yes" on every
# re-run of an existing server. With WEB_url_proto now https for everyone, that
# would have resolved BOOT_url_proto=https on every upgraded install in
# existence -- the one configuration that cannot work behind a private CA.
#
# GH-1120 retired $caCreated outright and made $externalca run-scoped. Both are
# still set here on purpose: the case is that NEITHER can reach the resolver, and
# it keeps failing if one is ever wired back in.
caCreated="yes"; externalca="yes"
is "$(nb no no '')" "http" "a pre-existing CA does NOT drag netboot onto https"
caCreated=""; externalca=""

# --- warnings ----------------------------------------------------------------
report() {
    BOOT_url_proto="$1"; PKI_web_cert_publicly_trusted="$2"; BOOT_rebuild_ipxe_with_my_ca="$3"; WEB_url_proto=https
    _reportNetbootProto 2>&1
}

if [[ "$(report http no no)" == *"Netboot (PXE) is using HTTP"* ]]; then
    ok "reverting to HTTP netboot is reported, not silent"
else
    bad "no notice printed when netboot fell back to HTTP"
fi
if [[ "$(report http no no)" == *"Secure Boot binaries ARE staged"* ]]; then
    ok "the notice says Secure Boot is available and how to enroll"
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

# --- persistence and the GH-1120 key model -----------------------------------
# Every key has to survive a round trip through .fogsettings, or the migration
# re-fires and the admin's choice is lost.
#
# Both arrays are read out of the real source rather than restated here, and
# COMMENTS ARE STRIPPED FIRST: the arrays carry a lot of explanatory prose that
# names keys, so a grep over the raw block passes for a key that is only
# MENTIONED in a comment -- exactly the failure this is meant to catch.
arrayKeys() {
    sed -n "/local -a $1=(/,/^    )/p" "$FUNCS" \
        | sed -e 's/#.*$//' -e "s/local -a $1=(//" -e 's/)//' \
        | tr -s ' \n' '\n\n' | grep -vE '^$'
}
managed="$(arrayKeys managedKeys)"
deprecated="$(arrayKeys deprecatedKeys)"
inlist() { printf '%s\n' "$2" | grep -qxF "$1"; }

# The four ADR 0015 keys, under their GH-1120 names.
for key in WEB_https_redirect PKI_web_cert_publicly_trusted \
           BOOT_rebuild_ipxe_with_my_ca BOOT_url_proto; do
    if inlist "$key" "$managed"; then
        ok "${key} is a managed key"
    else
        bad "${key} is NOT in managedKeys -- it will not survive an upgrade"
    fi
done

# All 69 keys of the model are managed, and NOTHING ELSE is: adding a key to
# this array turns a hand-set key into a managed one, and the admin's value
# starts being overwritten. That is a behavior change even though it looks
# like documentation, so the count is asserted as well as the membership.
modelKeys="
    BOOT_dhcp_delay_seconds BOOT_external_tftp_server BOOT_kernel_backups_kept BOOT_rebuild_ipxe_with_my_ca
    BOOT_tftp_options BOOT_url_proto BOOT_url_proto_forced DB_backup_path
    DB_external DB_host DB_name DB_password
    DB_user DHCP_dns_server_ip DHCP_enabled DHCP_engine
    DHCP_range_end DHCP_range_start DHCP_router DHCP_service_name
    FOG_copy_back_old FOG_git_path FOG_install_lang FOG_install_mode
    FOG_install_type FOG_installed FOG_os_id FOG_os_name
    FOG_packages FOG_program_dir FOG_send_reports FOG_update_channel
    NET_fog_server_ip NET_hostname NET_interface NET_subnet_mask
    PKI_allowed_domain_names PKI_client_cert_dir PKI_client_encrypt_cert PKI_client_encrypt_key
    PKI_internal_subnets PKI_root_ca_cert PKI_root_ca_key PKI_root_dir
    PKI_san_dns_names
    PKI_san_ip_addresses PKI_sb_ca_cert PKI_sb_codesign_cert PKI_sb_codesign_key
    PKI_sb_enabled PKI_web_ca_cert PKI_web_ca_key PKI_web_cert_publicly_trusted
    PKI_web_external_root_cert PKI_web_trust_chain PKI_web_vhost_cert PKI_web_vhost_key
    STORAGE_image_share_path STORAGE_rebuild_nfs_exports SVC_firewall_control SVC_password
    SVC_user WEB_docroot WEB_https_redirect WEB_php_version
    WEB_root WEB_server_engine WEB_url_primary WEB_url_proto"
missing=""
for key in $modelKeys; do
    inlist "$key" "$managed" || missing="$missing $key"
done
is "$missing" "" "every key of the GH-1120 model is in managedKeys"
is "$(printf '%s\n' $modelKeys | wc -l)" "$(printf '%s\n' $managed | wc -l)" \
   "managedKeys holds exactly the model keys and nothing else"

# All 79 pre-GH-1120 spellings are stripped on upgrade. Without this an upgraded
# server keeps a line describing a layout the installer no longer implements --
# and because .fogsettings is SOURCED, a stale line is a live shell variable
# that later code may still read.
legacyKeys="
    acmeLeaf backupPath bldhcp blexports
    bootdelay caCreated catrust copybackold
    dhcpd dhcpengine dnsaddress docroot
    dodhcp endrange extcacert extcakey
    extcaroot externalca extraServerNames fog_git_path
    fog_update_channel fogprogramdir fogupdateloaded fwconfigure
    hostname httpproto httpsRedirect installlang
    installtype interface internalDomains internalSubnets
    ipaddress ipaddresses kernelBackupGenerations mysqldbname
    netbootProtoForced netbootproto noTftpBuild osid
    osname packages password php_ver
    plainrouter publicWebCert rebuildIpxeWithMyCA rootCAKey
    rootCAPem routeraddress sbNameConstraints secureBootCert
    secureBootKey secureBootMokCert secureboot sendreports
    snmysqlexternal snmysqlhost snmysqlpass snmysqluser
    sslcachain sslcakey sslcapem sslcsr
    sslpath sslprivkey sslpubcert startrange
    storageLocation submask tftpAdvOpts username
    webCertFile webExtCACert webExtCAKey webExtCARoot
    webKeyFile webroot webserver"
unstripped=""
for key in $legacyKeys; do
    inlist "$key" "$deprecated" || unstripped="$unstripped $key"
done
is "$unstripped" "" "every pre-GH-1120 key is in deprecatedKeys"

# No key may be in both. The awk merge tests DEP before MAP, so a key in both
# would be stripped from the position it occupies and then re-appended at the
# end -- silently reordering the file on every run.
both=""
for key in $managed; do
    inlist "$key" "$deprecated" && both="$both $key"
done
is "$both" "" "no key is both managed and deprecated"

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
