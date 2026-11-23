#!/bin/bash
#
# The installer's OS-detection step must not need lsb_release.
#
#   tests/installer-no-lsb-release.test.sh
#
# bin/installfog.sh used to try to install lsb_release after detectOSFamily(),
# then read it for OSVersion and OSMinorVersion. That step was dead weight:
#
#   - detectOSFamily() already sets OSVersion from /etc/os-release, so the
#     lsb_release read for it was guarded off on every supported box;
#   - nothing reads OSMinorVersion, so that read ran on every install and fed
#     nothing;
#   - the install matched only Debian/Ubuntu/Mint, CentOS/RHEL/Fedora/Alma/
#     Rocky and Arch, and on EL9+ its redhat-lsb-core package no longer exists.
#
# So on a box without lsb_release (Oracle Linux 10 reported it, forum topic
# 18326) the run printed
#
#     ./installfog.sh: line 801: lsb_release: command not found
#
# and carried on. This runs the step between detectOSFamily() and the GH-850
# block with no lsb_release on PATH, and fails on any "command not found" or
# any attempt to install a package.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/installer-no-lsb-release.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
installer="$root/bin/installfog.sh"

[[ -r $installer ]] || { echo "FAIL: cannot read $installer" >&2; exit 1; }

# The step under test: from the detectOSFamily call up to the GH-850 comment
# that opens the next block.
segment=$(awk '
    /^detectOSFamily$/ { grab = 1 }
    /^# GH-850:/       { exit }
    grab               { print }
' "$installer")

if ! grep -q '^listPackages$' <<< "$segment"; then
    echo "FAIL: could not find the detectOSFamily/listPackages step in" >&2
    echo "  bin/installfog.sh. If it moved, point this test at it -- do not" >&2
    echo "  delete the assertion." >&2
    exit 1
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# A PATH holding only the tools the step may legitimately use, plus stubs for
# every package manager the old code called, which record that they ran.
mkdir -p "$work/bin"
for tool in bash awk; do
    real=$(command -v "$tool") && ln -s "$real" "$work/bin/$tool"
done
for pm in apt-get dnf yum pacman; do
    printf '#!%s\necho "%s $*" >> "%s/installs"\n' \
        "$(command -v bash)" "$pm" "$work" > "$work/bin/$pm"
    chmod +x "$work/bin/$pm"
done

# The reporter's box, as detectOSFamily() leaves it: OSVersion set, no family.
# The stubs keep the host's /etc/os-release out of it. dots is stubbed so the
# old step fails on lsb_release, not on a helper this harness did not define.
stderr=$(
    PATH="$work/bin" bash -c '
        error_log=/dev/null
        detectOSFamily() { linuxReleaseName_lower="oracle linux server"; OSVersion=10; osfamily=""; return 1; }
        listPackages() { :; }
        dots() { :; }
        '"$segment"'
    ' 2>&1 >/dev/null
)

fail=0
if grep -q 'command not found' <<< "$stderr"; then
    echo "  FAIL  the step ran a missing command:"
    sed 's/^/          /' <<< "$stderr"
    fail=1
fi
if [[ -s $work/installs ]]; then
    echo "  FAIL  the step tried to install a package:"
    sed 's/^/          /' "$work/installs"
    fail=1
fi

if [[ $fail -eq 0 ]]; then
    echo "PASS: OS detection runs without lsb_release"
    exit 0
fi
exit 1
