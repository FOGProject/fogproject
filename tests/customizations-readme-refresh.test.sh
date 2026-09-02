#!/bin/bash
#
# FOG replaces a readme it wrote itself, and never one an admin edited.
#
#   tests/customizations-readme-refresh.test.sh
#
# _ensureCustomizationsTree() used to write each readme only when absent. That
# kept the admin's edits, which was the point, but it also froze FOG's own
# text: a server installed before kernel-backups/keep/ existed keeps a readme
# saying nothing under /opt/fog/customizations is written on your instruction,
# and no later run can correct it.
#
# The rule is now "replace it while it is still byte-identical to something FOG
# shipped". That is two behaviors in one branch and they fail in opposite
# directions -- a too-eager version destroys somebody's note, a too-timid one
# leaves the stale text in place forever -- so BOTH arms are executed here
# against a fixture rather than read.
#
# The function is EXECUTED, not grepped. A textual check cannot tell a working
# checksum comparison from an inverted one.
#
# No root, no network, no FOG install, no openssl.
#
# Usage: bash tests/customizations-readme-refresh.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"

pass=0
fail=0

check() {
    if [[ $2 -eq 0 ]]; then
        pass=$((pass + 1))
    else
        fail=$((fail + 1))
        printf '  FAIL  %s\n' "$1"
    fi
}

if [[ ! -r $functions ]]; then
    echo "FAIL: cannot read $functions" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# The functions under test, lifted out rather than sourcing the whole library.
# Each closes on a column-0 brace, which nothing inside a readme heredoc does.
# ---------------------------------------------------------------------------
for fn in _fogShippedReadmeSums _readmeIsFogsOwn _resolveCustomizationsDir \
          _customPkiDir _ensureCustomizationsTree; do
    body=$(sed -n "/^${fn}() {/,/^}/p" "$functions")
    if [[ -z $body ]]; then
        echo "FAIL: could not find ${fn}() in lib/common/functions.sh." >&2
        echo "  If it moved or was renamed, point this test at it -- do not" >&2
        echo "  delete the assertion." >&2
        exit 1
    fi
    eval "$body"
done

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

error_log="$work/error.log"
SVC_user="$(id -un)"
apacheuser="$(id -un)"

etcroot="$work/etc/customizations"
optroot="$work/opt/customizations"
PKI_custom_dir="$etcroot/pki"

# _resolveCustomizationsDir() is idempotent on $customizationsDir, so setting it
# here keeps every run in this test pointed at the scratch tree.
customizationsDir="$optroot"

etcreadme="$etcroot/readme.txt"
optreadme="$optroot/readme.txt"

# ---------------------------------------------------------------------------
# The first shipped revision of the /opt readme, verbatim (GH-1681).
#
# Embedded rather than fetched from git history: the point of the test is that
# a server still carrying THESE bytes gets them replaced, so the bytes are the
# fixture. Its checksum is asserted against the registered list below, so
# editing this block fails loudly instead of silently testing nothing.
# ---------------------------------------------------------------------------
cat > "$work/opt-v1.txt" <<'OPTV1'
This directory is written BY FOG. You do not need to put anything here.

Before a run rebuilds a tree that might hold something of yours, FOG copies
what it finds into here, and restores it afterward:

  ipxe-bg/          the iPXE boot menu background image
  ipxe-legacy/      iPXE binaries you replaced in the TFTP tree
  kernel-backups/   previous kernel and init generations, newest kept first.
                    bin/restorekernel.sh restores from these.

If a restore ever fails, your files are still here -- nothing is deleted on the
way through.

There is a second customizations directory, and it works the other way round:

  /etc/fog/customizations   written by YOU, only read by FOG. That is where
                            certificates and private keys you supply go, under
                            pki/.

Why two: keys and certificates are small, secret and irreplaceable, so they
belong under /etc, which is what a backup policy already captures. Kernels and
boot images are large, rebuildable binaries, and the filesystem standard does
not put binaries under /etc.
OPTV1

v1sum=$(sha256sum "$work/opt-v1.txt" | cut -d' ' -f1)
_fogShippedReadmeSums opt | grep -qxF "$v1sum"
check "the embedded first revision is one of the registered opt sums" $?

# ---------------------------------------------------------------------------
# 1. Nothing there yet: both readmes are written.
# ---------------------------------------------------------------------------
_ensureCustomizationsTree >/dev/null 2>&1

[[ -f $etcreadme ]]
check "a fresh tree gets an /etc readme" $?
[[ -f $optreadme ]]
check "a fresh tree gets an /opt readme" $?

grep -q 'kernel-backups/keep/' "$optreadme"
check "the /opt readme documents kernel-backups/keep/" $?
grep -q 'written on YOUR instruction' "$optreadme"
check "the /opt readme names the third direction" $?
[[ -d $optroot/kernel-backups/keep ]]
check "kernel-backups/keep is created" $?

current=$(sha256sum "$optreadme" | cut -d' ' -f1)

# ---------------------------------------------------------------------------
# 2. Idempotent: a second run over FOG's current text leaves it current.
# ---------------------------------------------------------------------------
_ensureCustomizationsTree >/dev/null 2>&1
[[ "$(sha256sum "$optreadme" | cut -d' ' -f1)" == "$current" ]]
check "a second run leaves the current readme current" $?

# ---------------------------------------------------------------------------
# 3. THE STALE ARM. A server carrying the first shipped revision is upgraded.
# ---------------------------------------------------------------------------
cp -f "$work/opt-v1.txt" "$optreadme"
_ensureCustomizationsTree >/dev/null 2>&1

[[ "$(sha256sum "$optreadme" | cut -d' ' -f1)" == "$current" ]]
check "a readme FOG shipped earlier is replaced with the current text" $?
grep -q 'kernel-backups/keep/' "$optreadme"
check "the replaced readme now documents keep/" $?

# ---------------------------------------------------------------------------
# 4. THE ADMIN ARM. An edited readme is never touched, on this run or any
#    later one -- including one that only appended a line to FOG's own text.
# ---------------------------------------------------------------------------
printf '%s\n' "Note to whoever is next: ask Dave before touching ipxe-legacy." \
    > "$optreadme"
edited=$(sha256sum "$optreadme" | cut -d' ' -f1)
_ensureCustomizationsTree >/dev/null 2>&1
[[ "$(sha256sum "$optreadme" | cut -d' ' -f1)" == "$edited" ]]
check "a readme the admin wrote is left alone" $?

_ensureCustomizationsTree >/dev/null 2>&1
_ensureCustomizationsTree >/dev/null 2>&1
[[ "$(sha256sum "$optreadme" | cut -d' ' -f1)" == "$edited" ]]
check "and is still left alone on every later run" $?

cp -f "$work/opt-v1.txt" "$optreadme"
printf '%s\n' "" "One more thing: the background lives in ipxe-bg." >> "$optreadme"
appended=$(sha256sum "$optreadme" | cut -d' ' -f1)
_ensureCustomizationsTree >/dev/null 2>&1
[[ "$(sha256sum "$optreadme" | cut -d' ' -f1)" == "$appended" ]]
check "appending to FOG's own text makes it the admin's, and it is kept" $?

# The same rule has to hold on the /etc side, which is the tree whose whole
# purpose is that FOG only reads it.
printf '%s\n' "Our wildcard renews every March. -- ops" > "$etcreadme"
etcedited=$(sha256sum "$etcreadme" | cut -d' ' -f1)
_ensureCustomizationsTree >/dev/null 2>&1
[[ "$(sha256sum "$etcreadme" | cut -d' ' -f1)" == "$etcedited" ]]
check "an edited /etc readme is left alone too" $?

# ---------------------------------------------------------------------------
# 5. No sha256sum: the question cannot be answered, so nothing is overwritten.
#    That is the safe direction -- there is no run in which discarding
#    somebody's note is the better mistake.
# ---------------------------------------------------------------------------
cp -f "$work/opt-v1.txt" "$optreadme"
stale=$(sha256sum "$optreadme" | cut -d' ' -f1)
command() {
    if [[ $1 == -v && $2 == sha256sum ]]; then
        return 1
    fi
    builtin command "$@"
}
_ensureCustomizationsTree >/dev/null 2>&1
unset -f command
[[ "$(sha256sum "$optreadme" | cut -d' ' -f1)" == "$stale" ]]
check "without sha256sum a stale readme is kept rather than guessed at" $?

# ---------------------------------------------------------------------------
printf '\n%s\n' "customizations-readme-refresh: $pass passed, $fail failed"
[[ $fail -eq 0 ]]
