#!/bin/bash
#
# The installer must not reinstall EPEL over an EPEL that is already there.
#
#   tests/installer-epel-already-provided.test.sh
#
# GH-1772. installPackages() decided whether to fetch Fedora's
# epel-release-latest-N.noarch.rpm by querying the package NAME:
#
#   x="epel-release"; eval $packageQuery   # -> rpm -q epel-release
#
# EPEL does not always carry that name. AlmaLinux's x86-64-v2 builds ship
# epel-release-almalinux-altarch, which "Provides: epel-release" and
# "Conflicts: epel-release". On such a host the name query answers "package
# epel-release is not installed" while EPEL is present and enabled, the
# installer downloads Fedora's package anyway, and dnf rejects the whole
# transaction:
#
#   package epel-release-almalinux-altarch-10-6.el10.alma_altarch.noarch from
#   extras conflicts with epel-release provided by epel-release-10-8.el10_2
#
# The install ends there. "skipOk" at that call site suppresses the "OK" line
# only -- it does not make the step non-fatal -- so a rejected transaction is
# the end of the run, at "Adjusting repository".
#
# epelIsConfigured() is EXECUTED here against a stand-in rpm(8), not read. A
# textual check would pass on a --whatprovides written into the wrong query,
# and the altarch case below is exactly what the old name query got wrong: put
# `rpm -q epel-release` back in the helper and the first assertion goes red.
#
# The call site is checked textually as well, because that wiring is the one
# part the behavioral half cannot reach: a correct helper that nothing calls
# leaves the bug in place.
#
# No root, no network, no rpm, no FOG install.
#
# Usage: bash tests/installer-epel-already-provided.test.sh
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

# Lifted rather than sourced: functions.sh is 7000 lines that assume an
# installer environment, and sourcing it here would run far more than the one
# function under test. Same approach as tests/errorstat-reporting.test.sh.
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}

snippet=$(lift epelIsConfigured)
if ! grep -q '^epelIsConfigured() {' <<< "$snippet"; then
    echo "FAIL: could not find epelIsConfigured() in lib/common/functions.sh." >&2
    echo "  If it moved or was renamed, point this test at it -- do not" >&2
    echo "  delete the assertions." >&2
    exit 1
fi
eval "$snippet"

# ---------------------------------------------------------------------------
# A stand-in rpm(8), ahead of the real one on PATH. It answers the two query
# shapes that matter the way a host in $mode would, so the helper's own choice
# of query is what decides the result.
# ---------------------------------------------------------------------------
stubdir=$(mktemp -d)
trap 'rm -rf "$stubdir"' EXIT

cat > "$stubdir/rpm" <<'STUB'
#!/bin/bash
args="$*"
case "$RPM_STUB_MODE" in
    altarch)
        # AlmaLinux x86-64-v2: EPEL is installed, under another name.
        if [[ $args == *--whatprovides* ]]; then
            echo "epel-release-almalinux-altarch-10-6.el10.alma_altarch.noarch"
            exit 0
        fi
        echo "package epel-release is not installed" >&2
        exit 1
        ;;
    named)
        # Rocky, CentOS Stream, AlmaLinux x86-64: Fedora's own package.
        echo "epel-release-10-8.el10_2.noarch"
        exit 0
        ;;
    none)
        # No EPEL of any kind. The installer must still fetch it.
        if [[ $args == *--whatprovides* ]]; then
            echo "no package provides epel-release" >&2
        else
            echo "package epel-release is not installed" >&2
        fi
        exit 1
        ;;
    *)
        echo "test bug: RPM_STUB_MODE unset" >&2
        exit 99
        ;;
esac
STUB
chmod +x "$stubdir/rpm"
PATH="$stubdir:$PATH"

# ---------------------------------------------------------------------------
# The regression itself.
# ---------------------------------------------------------------------------
RPM_STUB_MODE=altarch epelIsConfigured >/dev/null 2>&1
check "EPEL installed under another name counts as configured (GH-1772)" $?

# ---------------------------------------------------------------------------
# The two cases that already worked, and must keep working -- a helper that
# answers "configured" to everything would pass the assertion above and break
# every host that genuinely has no EPEL.
# ---------------------------------------------------------------------------
RPM_STUB_MODE=named epelIsConfigured >/dev/null 2>&1
check "Fedora's own epel-release counts as configured" $?

RPM_STUB_MODE=none epelIsConfigured >/dev/null 2>&1
check "a host with no EPEL at all is reported as not configured" \
    "$([[ $? -ne 0 ]]; echo $?)"

# ---------------------------------------------------------------------------
# The wiring. installPackages() must ask the helper, and must not go back to
# the name query that caused this.
# ---------------------------------------------------------------------------
body=$(awk '
    /^installPackages\(\) \{/ { grab = 1 }
    grab { print }
    grab && /^\}/ { exit }
' "$functions" | grep -vE '^[[:space:]]*#')

check "installPackages() calls epelIsConfigured" \
    "$(grep -q 'epelIsConfigured' <<< "$body"; echo $?)"

check "installPackages() no longer queries the epel-release NAME" \
    "$(! grep -q 'x="epel-release"' <<< "$body"; echo $?)"

printf '%d passed, %d failed\n' "$pass" "$fail"
[[ $fail -eq 0 ]]
