#!/bin/bash
#
# fog-enroll-mok.sh - enrol FOG's Secure Boot certificate on this machine.
#
# Run this from a stock Ubuntu or Debian live USB. Those boot fine with Secure
# Boot switched on, using the distribution's own signed shim/GRUB/kernel, so
# there is no need to go into firmware setup and turn Secure Boot off just to
# get the key enrolled.
#
# Copy this script and MOK.der onto the USB stick together and double-click
# fog-enroll-mok.desktop, or run this script directly. It expects MOK.der to be
# sitting next to it.
#
set -u

scriptdir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
cert="${scriptdir}/MOK.der"

pause() {
    echo
    read -rsn1 -p "Press any key to close this window..."
    echo
}

fail() {
    echo
    echo "  ERROR: $*" >&2
    pause
    exit 1
}

echo "==============================================="
echo " FOG - enrol the Secure Boot signing key"
echo "==============================================="
echo

[[ -r $cert ]] || fail "MOK.der not found next to this script (looked in ${scriptdir})."

command -v mokutil >/dev/null 2>&1 || fail "mokutil is not installed on this live session."
command -v openssl >/dev/null 2>&1 || fail "openssl is not installed on this live session."

# Re-exec through sudo rather than making the tech remember to. Ubuntu live
# sessions allow this without a password.
if [[ $EUID -ne 0 ]]; then
    exec sudo -- "$0" "$@"
fi

sbstate=$(mokutil --sb-state 2>/dev/null)
echo " Secure Boot state: ${sbstate:-unknown}"

# The fingerprint is the SHA-256 of the DER bytes, which is exactly what the
# FOG web page displays. Checking it is the whole security of this step: it is
# what stops someone from talking you into trusting a key that is not yours.
fingerprint=$(openssl x509 -in "$cert" -inform der -noout -fingerprint -sha256 2>/dev/null \
    | sed 's/^.*=//')
subject=$(openssl x509 -in "$cert" -inform der -noout -subject 2>/dev/null | sed 's/^subject=*//')

[[ -n $fingerprint ]] || fail "Could not read $cert -- is it really a DER certificate?"

echo " Certificate:       ${subject}"
echo " SHA-256:           ${fingerprint}"
echo
echo " Compare that SHA-256 against the one shown on your FOG server's"
echo " Secure Boot page BEFORE continuing. If they differ, stop."
echo
read -rp " Does the fingerprint match? [y/N] " answer
case "$answer" in
    [Yy]*) ;;
    *) echo; echo " Aborted -- nothing was changed."; pause; exit 1 ;;
esac

if mokutil --test-key "$cert" 2>/dev/null | grep -qi "already enrolled"; then
    echo
    echo " This key is already enrolled on this machine. Nothing to do."
    pause
    exit 0
fi

echo
echo " You will now be asked for a one-time password, twice."
echo
echo " It is only used to confirm, after the reboot, that the person at the"
echo " keyboard is the person who ran this. It is not stored and does not need"
echo " to be strong -- pick something you can retype in a minute."
echo

if ! mokutil --import "$cert"; then
    fail "mokutil --import failed."
fi

cat <<'EOFNEXT'

 Done. Now reboot this machine.

 Instead of booting normally it will stop on a blue MOK Manager screen.
 Work through it in this order:

   1. Enroll MOK
   2. View key 0        <- check the name shown is your FOG server's
   3. Continue
   4. Yes
   5. Enter the password you just chose
   6. Reboot

 If MOK Manager does NOT appear, the machine did not boot through a shim and
 nothing has been enrolled.
EOFNEXT
pause
exit 0
