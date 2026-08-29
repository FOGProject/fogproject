#!/bin/bash
#
# Guards the pre-DHCP delay stanza that --boot-delay writes into autoexec.ipxe.
#
#   tests/boot-delay-stanza.test.sh
#
# The delay exists because some switches take several seconds to bring a port
# out of STP listening or out of powersave, and iPXE's first DHCP attempt goes
# out before that. It cannot be a UI setting -- it has to run BEFORE DHCP, and
# the web UI is only reachable after DHCP has succeeded -- so the installer is
# the only place it can come from, and the file an admin edits at 2am is the
# only escape hatch between installs. Both of those make this stanza worth
# pinning:
#
#   1. The commented arm has to be THERE. It was the whole reason the delay
#      could stop being a second set of compiled binaries, and the ESP archives
#      shipped it while the server's own TFTP copy silently did not -- an admin
#      diagnosing a link-up problem read a netboot script that said nothing
#      about the sleep it needed. Both surfaces come from _bootDelayBlock() now
#      and this asserts they still do.
#
#   2. The rewrite has to stay idempotent in BOTH directions. installfog.sh runs
#      many times over a server's life; blocks that stack, or a cleared delay
#      that leaves a live sleep behind, are both silent and both change how
#      every EFI client on the network boots.
#
# Also pinned: an admin's own sleep written OUTSIDE the sentinels survives. That
# is why the block is bracketed rather than matched on the sleep line, and a
# future edit to a bare /^sleep /d would eat it with nothing to show for it.
#
# No install, no network, no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# _applyBootDelay() reads $tftpdirdst and rewrites the file in place.
tftpdirdst="$WORK/tftpboot"
mkdir -p "$tftpdirdst"
SCRIPT="$tftpdirdst/autoexec.ipxe"

# A stand-in for the real boot script: only the shebang and one recognizable
# line matter, because everything this function does is anchored on the
# sentinels rather than on the surrounding content.
fresh() {
    cat > "$SCRIPT" <<'EOS'
#!ipxe
echo Checking net0 for DHCP...
isset ${net0/mac} && ifopen net0 && dhcp net0 || goto dhcpnet1
EOS
}

count() { grep -c -- "$1" "$SCRIPT"; }

echo "== no delay configured =="
BOOT_dhcp_delay_seconds=0
fresh
_applyBootDelay >/dev/null
is "$(count '^# FOG-BOOT-DELAY-BEGIN')" "1" \
   "the stanza is written even with no delay -- one block"
is "$(count '^# FOG-BOOT-DELAY-END')" "1" \
   "and it is closed exactly once"
is "$(count '^#sleep 10')" "1" \
   "the sleep ships commented out, ready to uncomment"
is "$(count '^sleep ')" "0" \
   "no delay is active when --boot-delay was not given"
# The text has to name the flag: uncommenting fixes this copy until the next
# install, and an admin who is not told that loses the fix to a reinstall and
# has no idea why.
if grep -q -- '--boot-delay' "$SCRIPT"; then
    ok "the commented arm names --boot-delay as the way to make it permanent"
else
    bad "the commented arm does not say how to make the delay survive a reinstall"
fi
# The shebang must stay first or iPXE will not run the script at all.
is "$(head -n1 "$SCRIPT")" "#!ipxe" \
   "the block is inserted after the shebang, not before it"

echo "== a delay configured =="
BOOT_dhcp_delay_seconds=15
fresh
_applyBootDelay >/dev/null
is "$(count '^sleep 15')" "1" "--boot-delay 15 writes a live sleep"
is "$(count '^#sleep ')" "0" "and drops the commented arm"
is "$(count '^# FOG-BOOT-DELAY-BEGIN')" "1" "still exactly one block"
is "$(head -n1 "$SCRIPT")" "#!ipxe" "the shebang is still first"

echo "== idempotency, in both directions =="
_applyBootDelay >/dev/null
_applyBootDelay >/dev/null
is "$(count '^# FOG-BOOT-DELAY-BEGIN')" "1" \
   "re-running does not stack a second block"
is "$(count '^sleep 15')" "1" "and does not stack a second sleep"
BOOT_dhcp_delay_seconds=30
_applyBootDelay >/dev/null
is "$(count '^sleep 30')" "1" "raising the delay replaces the block"
is "$(count '^sleep 15')" "0" "and leaves no trace of the old value"
BOOT_dhcp_delay_seconds=0
_applyBootDelay >/dev/null
is "$(count '^sleep ')" "0" \
   "clearing the delay removes the live sleep"
is "$(count '^#sleep 10')" "1" \
   "and puts the commented arm back"

echo "== an admin's own sleep is not eaten =="
BOOT_dhcp_delay_seconds=0
fresh
printf 'sleep 3\n' >> "$SCRIPT"
_applyBootDelay >/dev/null
is "$(count '^sleep 3')" "1" \
   "a sleep written outside the sentinels survives the rewrite"
BOOT_dhcp_delay_seconds=20
_applyBootDelay >/dev/null
is "$(count '^sleep 3')" "1" \
   "and survives a delay being set as well"
is "$(count '^sleep 20')" "1" "alongside the installer's own"

echo "== a missing tftp root is a no-op =="
# _applyBootDelay() runs from configureTFTPandPXE() and also has to be safe on a
# server where that tree was never staged. Returning early is what keeps it from
# creating an autoexec.ipxe with a delay block and no boot logic in it.
BOOT_dhcp_delay_seconds=0
fresh
before="$(cat "$SCRIPT")"
tftpdirdst="$WORK/nosuchdir" _applyBootDelay >/dev/null
is "$(cat "$SCRIPT")" "$before" \
   "no tftp root means the script is left exactly as it was"
if [[ -e $WORK/nosuchdir/autoexec.ipxe ]]; then
    bad "a script was created under a tftp root that does not exist"
else
    ok "and nothing is created where the tree was never staged"
fi

echo "== both surfaces come from one generator =="
# The TFTP copy and the ESP archives used to be two bodies of text kept in step
# by a comment. If they drift again, a site that reads the USB stick and then
# the netboot script finds two different scripts.
for d in 0 10 15; do
    BOOT_dhcp_delay_seconds=$d
    fresh
    _applyBootDelay >/dev/null
    tftp_block="$(awk '/^# FOG-BOOT-DELAY-BEGIN/,/^# FOG-BOOT-DELAY-END/' "$SCRIPT")"
    esp_block="$(_bootDelayBlock)"
    is "$tftp_block" "$esp_block" \
       "delay=$d writes the same stanza to the TFTP copy and the ESP archives"
done

unset BOOT_dhcp_delay_seconds

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
