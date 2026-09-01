#!/bin/bash
#
# One distro-name map, wherever it is spelled.
#
#   tests/detect-os-family.test.sh
#
# Mapping a distro name to one of FOG's four packaging families used to be
# written out three times: the /etc/os-release parse inline in
# bin/installfog.sh, the family map inline in lib/common/input.sh, and -- with
# bin/bootstrap.sh -- a third that has to exist because bootstrap runs before
# there is a clone to source anything from.
#
# The first two had already drifted. input.sh knew *mageia*; installfog.sh's
# copy did not. Nothing failed: a Mageia box simply took a different path
# through the two halves of the same question.
#
# Two of the three are now one function. The third cannot be -- see the header
# of bin/bootstrap.sh -- so this file is what holds it honest: both copies are
# run over the same list of distro names and must answer identically, every
# name, every time.
#
# It also pins the family -> osid mapping, because that renumbering is the part
# with a trap in it. GH-447 moved Arch from 3 to 4 so Alpine could take 3, so
# getting the family right and the number wrong installs the wrong distro's
# package set.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/detect-os-family.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"
bootstrap="$root/bin/bootstrap.sh"
inputsh="$root/lib/common/input.sh"

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

for f in "$functions" "$bootstrap" "$inputsh"; do
    if [[ ! -r $f ]]; then
        echo "FAIL: cannot read $f" >&2
        exit 1
    fi
done

lift() {
    awk -v fn="$2" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$1"
}

libcopy=$(lift "$functions" detectOSFamily)
bootcopy=$(lift "$bootstrap" detectOSFamily)

for pair in "lib/common/functions.sh:$libcopy" "bin/bootstrap.sh:$bootcopy"; do
    where="${pair%%:*}"
    if ! grep -q 'osfamily=' <<< "${pair#*:}"; then
        echo "FAIL: could not find detectOSFamily() in ${where}." >&2
        echo "  If it moved or was renamed, point this test at it -- do not" >&2
        echo "  delete the assertions." >&2
        exit 1
    fi
done

# Every name FOG claims to recognize, plus the shapes that must NOT match.
# Kept as one list so both copies are asked exactly the same questions.
cases="
Fedora+Linux:redhat
Red+Hat+Enterprise+Linux:redhat
CentOS+Linux:redhat
CentOS+Stream:redhat
Rocky+Linux:redhat
AlmaLinux:redhat
Mageia:redhat
Debian+GNU/Linux:debian
Ubuntu:debian
Linux+Mint:debian
Alpine+Linux:alpine
Arch+Linux:arch
Manjaro+Linux:arch
Gentoo:
openSUSE+Leap:
Slackware:
Void+Linux:
"

runcopy() {
    # A subshell per case: the function sets four globals and the whole point
    # is that each name is answered from a clean slate.
    local body="$1" name="$2"
    (
        eval "$body"
        unset linuxReleaseName OSVersion linuxReleaseName_lower osfamily
        linuxReleaseName="$name"
        OSVersion="1"
        detectOSFamily
        printf '%s|%s' "$?" "$osfamily"
    )
}

for case_ in $cases; do
    name="${case_%%:*}"
    name="${name//+/ }"
    want="${case_#*:}"

    libout=$(runcopy "$libcopy" "$name")
    bootout=$(runcopy "$bootcopy" "$name")

    libst="${libout%%|*}"; libfam="${libout#*|}"

    if [[ -n $want ]]; then
        check "functions.sh: ${name} -> ${want}" \
            "$([[ $libfam == "$want" && $libst -eq 0 ]]; echo $?)"
    else
        # An unrecognized distro must return non-zero AND leave osfamily empty.
        # Returning 1 while still setting a family would let a caller that
        # ignores the status install the wrong package manager's packages.
        check "functions.sh: ${name} is refused, with no family guessed" \
            "$([[ $libst -ne 0 && -z $libfam ]]; echo $?)"
    fi

    # The drift guard. Not "bootstrap is also right" -- "bootstrap says the
    # same thing", status included, so a change to one that is not made to the
    # other fails here rather than on somebody's server.
    check "both copies agree on ${name}" \
        "$([[ $libout == "$bootout" ]]; echo $?)"
done

# ---------------------------------------------------------------------------
# The copies are meant to be identical, not merely equivalent. Comparing them
# textually catches a divergence in a name this test does not happen to list.
# ---------------------------------------------------------------------------
strip() { sed 's/#.*//' <<< "$1" | sed 's/[[:space:]]\+/ /g; s/^ //; s/ $//' | grep -v '^$'; }
check "the two detectOSFamily bodies are textually identical" \
    "$(diff <(strip "$libcopy") <(strip "$bootcopy") >/dev/null; echo $?)"

# ---------------------------------------------------------------------------
# family -> osid, where GH-447's renumbering lives.
# ---------------------------------------------------------------------------
suggest=$(sed -n '/if \[\[ $guessdefaults == 1 \]\]; then/,/esac/p' "$inputsh")

for pair in redhat:1 debian:2 alpine:3 arch:4; do
    fam="${pair%%:*}"; want="${pair#*:}"
    got=$(sed 's/#.*//' <<< "$suggest" | grep -oE "^[[:space:]]*${fam}\)[[:space:]]*strSuggestedOS=[0-9]+" | grep -oE '[0-9]+$')
    check "input.sh maps ${fam} to osid ${want}" \
        "$([[ $got == "$want" ]]; echo $?)"
done

# Alpine is 3 and Arch is 4, not the other way round. Stated separately from
# the loop because reversing exactly this pair is the mistake GH-447 was, and a
# loop that is edited wholesale would not notice.
check "alpine is 3 and arch is 4 (GH-447 renumbering, not FOG 1.5's)" \
    "$(sed 's/#.*//' <<< "$suggest" | grep -qE 'alpine\)[[:space:]]*strSuggestedOS=3' \
        && sed 's/#.*//' <<< "$suggest" | grep -qE 'arch\)[[:space:]]*strSuggestedOS=4'; echo $?)"

# An unknown distro still SUGGESTS redhat here, even though detectOSFamily
# refuses to guess one. Different questions: this is a pre-filled answer to a
# prompt a person can correct, that one is a claim about which package-manager
# binary exists. Pinned so the asymmetry is not "tidied up" in either direction.
check "input.sh still defaults an unknown distro to osid 1" \
    "$(sed 's/#.*//' <<< "$suggest" | grep -qE '\*\)[[:space:]]*strSuggestedOS=1'; echo $?)"

# ---------------------------------------------------------------------------
# And that the callers actually call it, rather than keeping their own copy.
# ---------------------------------------------------------------------------
check "installfog.sh calls detectOSFamily instead of parsing os-release itself" \
    "$(sed 's/#.*//' "$root/bin/installfog.sh" | grep -qE '^detectOSFamily$' \
        && ! sed 's/#.*//' "$root/bin/installfog.sh" | grep -q '/etc/redhat-release'; echo $?)"

check "input.sh no longer carries its own distro-name patterns" \
    "$(! sed 's/#.*//' "$inputsh" | grep -q 'mageia'; echo $?)"

# Never as $(detectOSFamily): the globals would be set in a subshell and lost.
# Comments stripped -- functions.sh's own docstring warns against exactly this
# spelling, and that warning is worth keeping.
check "no caller invokes it in a command substitution" \
    "$(! cat "$root"/bin/*.sh "$root"/lib/common/*.sh | sed 's/#.*//' | grep -q '[$](detectOSFamily'; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
