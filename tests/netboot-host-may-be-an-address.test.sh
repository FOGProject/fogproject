#!/bin/bash
#
# HTTPS netboot may be addressed by an IP, when the certificate carries it.
#
#   tests/netboot-host-may-be-an-address.test.sh
#
# _resolveNetbootHost() used to refuse an IP literal outright -- "This server
# has no name to use, only the address" -- and exit the installer. The belief
# behind it was that HTTPS netboot needs a NAME. That is not what iPXE does:
# x509_check_name() walks the subjectAltName list and dispatches
# X509_GENERAL_NAME_IP to x509_check_ipaddress(), which parses the requested
# host with sock_aton() and compares the binary address against the SAN. An
# address verifies exactly as a name does when the SAN is there.
#
# The refusal was not a missing feature so much as a forced rename. FOG_WEB_HOST
# is the canonical address of the server -- ClientManagement hands it to every
# FOG client, the services identify themselves by it, and every browser-facing
# absolute URL a plugin builds resolves against it -- so refusing an address for
# netboot moved an install addressed by IP onto a DNS name for everything else
# too, on every run, silently.
#
# What must NOT relax is the crossover rule: an address is matched against
# iPAddress SANs only. iPXE will not read an address out of a commonName or a
# dNSName, so neither may FOG -- a check laxer than the validator it is standing
# in for blesses an install that completes and then cannot boot.
#
# Functions are extracted from lib/common/functions.sh and eval'd, the same way
# node-cert-external-ca.test.sh does it, so the shipped file is what is tested.
# Needs openssl. No install, no database, no network, no root.
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
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# --- the code under test ----------------------------------------------------
for fn in validip _certDnsNames _certServesAddress _certServesName \
          _servedCertServes _resolveNetbootHost; do
    body=$(awk "/^${fn}\(\) \{/,/^\}/" "$FUNCS")
    [[ -n $body ]] || { echo "ERROR: could not extract $fn from $FUNCS" >&2; exit 1; }
    eval "$body" || { echo "ERROR: could not eval $fn" >&2; exit 1; }
done

# _resolveNetbootHost asks these two for the world around it. Stubbed so each
# case can put the installer in a known state without an install.
_servedCertName() { echo "$STUB_SERVED_NAME"; }
_vhostCertPath()  { echo "$STUB_CERT"; }
# Set, so the refusal path returns instead of exiting this test.
exitFail=yes
PKI_web_vhost_cert=""
sslfullchain=""
NET_fog_server_ip="10.255.20.1"

# --- fixtures ---------------------------------------------------------------
# withip:   a name AND the address, the shape FOG's own installer produces
# nameonly: the same name, no iPAddress SAN
# cnaddr:   the address as the commonName and as a dNSName, and NOT as an
#           iPAddress SAN -- the shape that must still be refused
mkleaf() {
    local name="$1" san="$2" cn="${3:-$1}"
    openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 5 \
        -subj "/CN=$cn" -keyout "$WORK/$name.key" -out "$WORK/$name.pem" \
        -addext "subjectAltName=$san" >/dev/null 2>&1
}
mkleaf withip   "IP:10.255.20.1,DNS:fogserver.test" || exit 1
mkleaf nameonly "DNS:fogserver.test"                || exit 1
mkleaf cnaddr   "DNS:10.255.20.1"                   "10.255.20.1" || exit 1

# --- A. an address the certificate carries is accepted ----------------------
STUB_CERT="$WORK/withip.pem"; STUB_SERVED_NAME="10.255.20.1"; netboothost=""
if _resolveNetbootHost >/dev/null 2>&1; then
    ok "an IP in the certificate's iPAddress SAN is accepted"
else
    bad "an IP in the certificate's iPAddress SAN was refused"
fi

# --- B. an address it does not carry is refused -----------------------------
STUB_CERT="$WORK/nameonly.pem"; STUB_SERVED_NAME="10.255.20.1"; netboothost=""
if _resolveNetbootHost >/dev/null 2>&1; then
    bad "an IP absent from the certificate was accepted; netboot would fail the handshake"
else
    ok "an IP absent from the certificate is refused"
fi

# --- C. THE crossover guard -------------------------------------------------
# The address appears as the commonName and as a dNSName. iPXE compares a
# binary address against iPAddress SANs and will find neither, so accepting
# this would produce an install that completes and cannot boot.
STUB_CERT="$WORK/cnaddr.pem"; STUB_SERVED_NAME="10.255.20.1"; netboothost=""
if _resolveNetbootHost >/dev/null 2>&1; then
    bad "an address carried only as a commonName/dNSName was accepted as an iPAddress SAN"
else
    ok "an address is not matched against commonName or dNSName"
fi

# --- D. names still behave exactly as before --------------------------------
STUB_CERT="$WORK/nameonly.pem"; STUB_SERVED_NAME="fogserver.test"; netboothost=""
if _resolveNetbootHost >/dev/null 2>&1; then
    ok "a name in the certificate is still accepted"
else
    bad "a name in the certificate was refused"
fi

STUB_CERT="$WORK/nameonly.pem"; STUB_SERVED_NAME="elsewhere.test"; netboothost=""
if _resolveNetbootHost >/dev/null 2>&1; then
    bad "a name absent from the certificate was accepted"
else
    ok "a name absent from the certificate is still refused"
fi

# --- E. _servedCertServes answers for both kinds ----------------------------
# This is what recordNetbootWebHost() asks before deciding whether an existing
# FOG_WEB_HOST needs correcting, so it has to be right for a name and an
# address alike.
STUB_CERT="$WORK/withip.pem"
_servedCertServes "10.255.20.1"  && ok "_servedCertServes accepts a carried address" \
                                 || bad "_servedCertServes rejected a carried address"
_servedCertServes "fogserver.test" && ok "_servedCertServes accepts a carried name" \
                                   || bad "_servedCertServes rejected a carried name"
_servedCertServes "10.9.9.9"     && bad "_servedCertServes accepted an address not in the certificate" \
                                 || ok "_servedCertServes rejects an address not in the certificate"
_servedCertServes "elsewhere.test" && bad "_servedCertServes accepted a name not in the certificate" \
                                   || ok "_servedCertServes rejects a name not in the certificate"

# --- F. recordNetbootWebHost keeps a value the certificate vouches for ------
# This is the half that stops the row moving under the admin. mysql, and the
# two output helpers, are stubbed: the question is which branch runs, not what
# the database does. WROTE records whether an UPDATE was issued at all.
body=$(awk '/^recordNetbootWebHost\(\) \{/,/^\}/' "$FUNCS")
[[ -n $body ]] || { echo "ERROR: could not extract recordNetbootWebHost" >&2; exit 1; }
eval "$body" || { echo "ERROR: could not eval recordNetbootWebHost" >&2; exit 1; }

BOOT_url_proto=https
sqloptionsuser=""; DB_password=""; DB_name="fog"; error_log="$WORK/err.log"
dots() { :; }
errorStat() { :; }
mysql() {
    # The SELECT answers with the case's stored value; anything else is the
    # write this test exists to detect.
    if [[ "$*" == *SELECT*FOG_WEB_HOST* ]]; then
        echo "$STORED"
        return 0
    fi
    WROTE=yes
    return 0
}

recorded() {
    STORED="$1"; WROTE=""; netboothost=""
    recordNetbootWebHost >/dev/null 2>&1
    echo "${WROTE:-no}"
}

STUB_CERT="$WORK/withip.pem"; STUB_SERVED_NAME="fogserver.test"

[[ $(recorded "10.255.20.1") == no ]] \
    && ok "an address the certificate carries is left alone" \
    || bad "FOG_WEB_HOST was overwritten despite the certificate carrying it"

[[ $(recorded "fogserver.test") == no ]] \
    && ok "a name the certificate carries is left alone" \
    || bad "FOG_WEB_HOST was overwritten despite the certificate carrying it"

[[ $(recorded "somewhere.else") == yes ]] \
    && ok "a value the certificate cannot prove is corrected" \
    || bad "a FOG_WEB_HOST the certificate does not carry was left in place; netboot would fail the handshake"

[[ $(recorded "NULL") == yes ]] \
    && ok "an unset value is recorded, as on a fresh install" \
    || bad "an unset FOG_WEB_HOST was not recorded"

[[ $(recorded "") == yes ]] \
    && ok "an unreadable value falls through and records the certificate name" \
    || bad "an unreadable FOG_WEB_HOST was treated as satisfactory"

# --- G. a correction says what else it just moved --------------------------
# The redirect URI an identity provider holds is computed from FOG_WEB_HOST and
# never stored, so a correction silently invalidates it and sign-in breaks at
# the next attempt. Whoever ran the installer is the only person positioned to
# fix that, and only while they are still looking at the output.
said() {
    STORED="$1"; WROTE=""; netboothost=""
    recordNetbootWebHost 2>&1
}

STUB_CERT="$WORK/withip.pem"; STUB_SERVED_NAME="fogserver.test"

case "$(said 'somewhere.else')" in
    *"It was somewhere.else"*"redirect URI"*)
        ok "a correction names the old value and what it invalidates" ;;
    *)  bad "a correction did not warn that a registered redirect URI is now stale" ;;
esac

# No warning where nothing moved: an unset value has no registration behind it,
# and crying wolf on every fresh install is how a real warning gets ignored.
case "$(said 'NULL')" in
    *"has to be updated to match"*)
        bad "a fresh install warned about a value that never existed" ;;
    *)  ok "no stale-registration warning when there was no previous value" ;;
esac

# --- report -----------------------------------------------------------------
echo
if [[ $FAIL -gt 0 ]]; then
    echo "FAIL: $FAIL of $((PASS + FAIL)) checks failed"
    exit 1
fi
echo "ok: $PASS checks passed"
exit 0
