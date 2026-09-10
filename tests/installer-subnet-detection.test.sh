#!/bin/bash
#
# Guards how the installer derives the DHCP subnet from the FOG interface (GH-1747).
#
#   tests/installer-subnet-detection.test.sh
#
# The report: on an interface with more than one IPv4 address, the saved mask
# was 255.255.0.0 on a /24, the generated Kea subnet was "10.0.45.2/24" (a host
# address, not a network), and the pool began at .12. Each helper looked at "the
# interface" and took a different address from it:
#
#   input.sh             every inet address, link-local 169.254.x.x included,
#                        so the primary could be one no client can reach.
#   getCidr()            the SECOND global address's prefix (head -n2 | tail
#                        -n1), through an unanchored grep, so eth1 matched eth10.
#   configureDHCP        every global address, passed unquoted to mask2network,
#                        so a second address became the MASK.
#   interface2broadcast  the first brd on the interface, whichever address.
#
# Also: cidr2mask "" printed "arithmetic syntax error ... /8", and mask2cidr
# printed its error on stdout, where the caller took it as the prefix.
#
# Everything now derives from ONE address, $ipaddress, the one FOG advertises.
#
# A fake `ip` replays each address layout. Its rows are the one-line form
# iproute2 prints for `ip -o`, copied from a real host. It builds the multi-line
# form the way iproute2 does: -o only turns each newline into "\".
#
# No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INPUT="$REPO/lib/common/input.sh"

[[ -f $FUNCS && -f $INPUT ]] || { echo "ERROR: installer sources not found under $REPO/lib/common" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

mkdir -p "$WORK/bin" "$WORK/web"
cat > "$WORK/bin/ip" <<'EOF'
#!/bin/bash
# Replays $FAKE_IP_TABLE: one `ip -o -4 addr` row per address.
oneline=0
dev=""
verb=""
while [[ $# -gt 0 ]]; do
    case $1 in
        -o) oneline=1 ;;
        -4|-f|inet|show) ;;
        addr|address|link|route) verb=$1 ;;
        dev) shift; dev=$1 ;;
        *) dev=$1 ;;
    esac
    shift
done
[[ $verb == addr || $verb == address ]] || exit 0
while IFS= read -r line; do
    [[ -z $line ]] && continue
    read -r _ name _ <<<"$line"
    [[ -n $dev && $name != "$dev" ]] && continue
    if [[ $oneline -eq 1 ]]; then
        printf '%s\n' "$line"
    else
        body=${line#*"$name"}
        body=${body#"${body%%[! ]*}"}
        printf '    %s\n' "${body//\\/$'\n'}"
    fi
done < "$FAKE_IP_TABLE"
EOF
chmod +x "$WORK/bin/ip"

# One `ip -o -4 addr` row: index, interface, address/prefix, broadcast, scope words.
row() {
    printf '%s: %s    inet %s %sscope %s %s\\       valid_lft forever preferred_lft forever\n' \
        "$1" "$2" "$3" "${4:+brd $4 }" "$5" "$2"
}
table() { local name=$1; shift; printf '%s\n' "$@" > "$WORK/$name"; }

lo=$(row 1 lo 127.0.0.1/8 "" host)
nat=$(row 3 ens37 192.168.137.130/24 192.168.137.255 "global dynamic noprefixroute")
primary=$(row 2 ens33 10.0.45.2/24 10.0.45.255 "global noprefixroute")
# A second address in the same /24. With the old code this reproduces the
# report exactly: subnet "10.0.45.2/24", pool "10.0.45.12 - 10.0.45.254".
table second "$lo" "$primary" "$(row 2 ens33 10.0.45.14/24 10.0.45.255 'global secondary dynamic noprefixroute')" "$nat"
# The report's "stray 169.254.x.x/16 line", added as a global address.
# With the old code this is the saved 255.255.0.0.
table llglobal "$lo" "$primary" "$(row 2 ens33 169.254.33.7/16 169.254.255.255 'global noprefixroute')" "$nat"
# Link-local listed first, as a link-scope address.
table llfirst "$lo" "$(row 2 ens33 169.254.33.7/16 169.254.255.255 link)" "$primary" "$nat"
# DHCP never answered on the deployment NIC: link-local only. With the old
# code this is the reported "arithmetic syntax error ... /8".
table llonly "$lo" "$(row 2 ens33 169.254.33.7/16 169.254.255.255 link)" "$nat"
# eth1 must not match eth10. eth1 is listed first, so the old "second match"
# lands on eth10.
table anchor "$lo" "$(row 2 eth1 10.1.0.5/24 10.1.0.255 global)" "$(row 3 eth10 172.16.0.5/16 172.16.255.255 global)"

error_log="$WORK/error.log"
: > "$error_log"
timestamp=0
# /usr/sbin is left out so no real kea-dhcp4 is found: the config is written
# without validation, which is all this test reads.
PATH="$WORK/bin:/usr/bin:/bin"
FAKE_IP_TABLE="$WORK/second"
export FAKE_IP_TABLE
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

dots() { :; }
diffconfig() { :; }
# Stop configureDHCP right after the Kea config is written, before it touches
# any service: a failed configureKeaDHCP with exitFail set makes it return.
eval "$(declare -f configureKeaDHCP | sed '1s/^configureKeaDHCP/_realConfigureKeaDHCP/')"
configureKeaDHCP() { _realConfigureKeaDHCP >/dev/null 2>&1; return 1; }

# The two input.sh lines under test, run as written rather than retyped here.
ipline=$(grep -m1 -E '^[[:space:]]*ipaddress=\$\(ip -4 addr show' "$INPUT")
maskline=$(grep -m1 -E '^[[:space:]]*submask=\$\(cidr2mask' "$INPUT")
[[ -n $ipline ]] && ok "input.sh ipaddress assignment found" || bad "input.sh ipaddress assignment found"
[[ -n $maskline ]] && ok "input.sh submask assignment found" || bad "input.sh submask assignment found"

jsonval() { sed -n "s/.*\"$1\": \"\\([^\"]*\\)\".*/\\1/p" "$2" | head -n1; }

# Replays input.sh's detection, then configureDHCP and writeKeaSample.
detect() {
    FAKE_IP_TABLE="$WORK/$1"
    interface=$2
    unset ipaddress ipaddresses submask network startrange endrange
    eval "$ipline"
    ipaddresses="$ipaddress"
    eval "$maskline" 2>"$WORK/mask.err"
    [[ -n $ipaddress ]] && normalizeIpAddress
}
build() {
    rm -f "$WORK/kea.conf" "$WORK/web/kea-dhcp4.conf.fog-sample"
    unset network startrange endrange
    bldhcp=1 dhcpengine=kea dhcpconfig="$WORK/kea.conf" exitFail=1 webdirdest="$WORK/web"
    configureDHCP >/dev/null 2>&1
    writeKeaSample >/dev/null 2>&1
}
expect_config() {
    is "$(jsonval subnet "$WORK/kea.conf")" 10.0.45.0/24 "$1: Kea subnet is the network"
    is "$(jsonval pool "$WORK/kea.conf")" "10.0.45.10 - 10.0.45.254" "$1: Kea pool"
    is "$(jsonval subnet "$WORK/web/kea-dhcp4.conf.fog-sample")" 10.0.45.0/24 "$1: sample subnet is the network"
    is "$(jsonval pool "$WORK/web/kea-dhcp4.conf.fog-sample")" "10.0.45.10 - 10.0.45.254" "$1: sample pool"
}

for layout in second llglobal llfirst; do
    echo "$layout:"
    detect "$layout" ens33
    is "$ipaddress" 10.0.45.2 "$layout: primary address"
    is "$submask" 255.255.255.0 "$layout: saved mask"
    build
    expect_config "$layout"
    # A re-run starts from the saved mask, and the sample writer derives its own.
    saved=$submask
    unset submask
    build
    is "$submask" 255.255.255.0 "$layout: mask derived by configureDHCP"
    submask=$saved
    expect_config "$layout (derived mask)"
done

echo "llonly:"
detect llonly ens33
is "$ipaddress" "" "llonly: a link-local address is not a server address"
grep -q 'arithmetic' "$WORK/mask.err" && bad "llonly: no arithmetic error while reading the mask" || ok "llonly: no arithmetic error while reading the mask"

echo "anchor:"
FAKE_IP_TABLE="$WORK/anchor"
is "$(getCidr eth1 10.1.0.5)" 24 "anchor: getCidr eth1 does not read eth10"
is "$(getCidr eth1)" 24 "anchor: getCidr eth1 without an address"

echo "helpers:"
FAKE_IP_TABLE="$WORK/llglobal"
is "$(getCidr ens33 10.0.45.2)" 24 "getCidr names the prefix of the address asked for"
is "$(getCidr ens33 10.9.9.9)" 24 "getCidr falls back to the first global address"
FAKE_IP_TABLE="$WORK/llfirst"
is "$(interface2broadcast ens33 10.0.45.2)" 10.0.45.255 "interface2broadcast of the address asked for"
is "$(cidr2mask 24)" 255.255.255.0 "cidr2mask 24"
is "$(cidr2mask 27)" 255.255.255.224 "cidr2mask 27"
is "$(cidr2mask '' 2>&1)" "" "cidr2mask with no prefix prints nothing"
is "$(mask2cidr 255.255.255.224 2>/dev/null)" 27 "mask2cidr 255.255.255.224"
is "$(mask2cidr 255.255.3.0 2>/dev/null)" "" "mask2cidr keeps its error off stdout"

echo
echo "installer-subnet-detection: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
