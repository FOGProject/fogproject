#!/bin/bash
#
# The install-directory guard must not match on an empty variable.
#
#   tests/installdir-guard.test.sh
#
# doOSSpecificIncludes() ends by refusing to run from a directory the install
# is going to move:
#
#     if { [[ -n $webdirdest ]] && [[ $currentdir == *"$webdirdest"* ]]; } ...
#
# It used to be a glob:
#
#     case $currentdir in *$webdirdest*|*$tftpdirdst*)
#
# and that is wrong in a way that only shows up on the unhappy path. In a
# glob, `*$webdirdest*` with an EMPTY $webdirdest is `**`, which matches every
# path there is -- so the installer refused to run from anywhere and explained
# itself with a message about the install layout that had nothing to do with
# the actual problem.
#
# Empty is reachable, and by design. The `*)` arm of the OS dispatch above
# blanks the id and RETURNS rather than exiting, so any route that reaches this
# guard without having sourced a distro config finds both variables unset --
# and the guard is the very next thing in the same function. On a 1.5 server
# the way in is a hand-edited or truncated .fogsettings.
#
# Ported from working-1.6, where a .fogsettings key rename made it easy to hit:
# an unset id took the `*)` arm and the admin got "Sorry, answer not
# recognized" followed by "Please change installation directory" about a path
# that was perfectly fine -- two messages, one cause, and the second one sent
# the reader looking in the wrong place. That rename is not on this branch, but
# the amplifier never depended on it.
#
# Nothing is installed and no root is needed: the guard is evaluated in both
# shapes against fixture values, and the shipped function is linted.
#
# Usage: bash tests/installdir-guard.test.sh
# Exit status 0 = pass, 1 = fail.

repodir=$(cd "$(dirname "$0")/.." && pwd)
functions="$repodir/lib/common/functions.sh"

failures=0
checks=0

is() {
    checks=$((checks + 1))
    if [ "$2" != "$3" ]; then
        failures=$((failures + 1))
        echo "  - $1 (got '$2', wanted '$3')"
    fi
}

check() {
    checks=$((checks + 1))
    if [ "$2" != "yes" ]; then
        failures=$((failures + 1))
        echo "  - $1"
    fi
}

if [ ! -f "$functions" ]; then
    echo "SKIP: no lib/common/functions.sh in $repodir"
    exit 0
fi

# --- both shapes, against the same inputs ----------------------------------
#
# guard_glob is the original. guard_safe is what ships now. They agree on every
# case where the variables are populated, and disagree on exactly the one that
# matters -- which is the whole argument for the change.

verdicts() {
    (
        currentdir=$1
        webdirdest=$2
        tftpdirdst=$3

        case $currentdir in
            *$webdirdest*|*$tftpdirdst*) g="REJECTED" ;;
            *)                           g="allowed"  ;;
        esac
        if { [ -n "$webdirdest" ] && case $currentdir in *"$webdirdest"*) true ;; *) false ;; esac; } \
            || { [ -n "$tftpdirdst" ] && case $currentdir in *"$tftpdirdst"*) true ;; *) false ;; esac; }; then
            s="REJECTED"
        else
            s="allowed"
        fi
        echo "$g|$s"
    )
}

# Populated variables: the guard's real job, and both shapes must still do it.
IFS='|' read -r g s <<EOF
$(verdicts "/var/www/html/fog/bin" "/var/www/html/fog/" "/tftpboot")
EOF
is 'inside the web root is still rejected (old shape)'  "$g" "REJECTED"
is 'inside the web root is still rejected (new shape)'  "$s" "REJECTED"

IFS='|' read -r g s <<EOF
$(verdicts "/tftpboot/staging" "/var/www/html/fog/" "/tftpboot")
EOF
is 'inside the tftp root is still rejected (old shape)' "$g" "REJECTED"
is 'inside the tftp root is still rejected (new shape)' "$s" "REJECTED"

IFS='|' read -r g s <<EOF
$(verdicts "/root/fogproject/bin" "/var/www/html/fog/" "/tftpboot")
EOF
is 'an ordinary path is allowed (old shape)' "$g" "allowed"
is 'an ordinary path is allowed (new shape)' "$s" "allowed"

# The one that matters. Unset variables, ordinary path.
IFS='|' read -r g s <<EOF
$(verdicts "/root/fogproject/bin" "" "")
EOF
is 'with both variables empty the old glob rejects an ordinary path' "$g" "REJECTED"
is 'with both variables empty the new form allows it'                "$s" "allowed"

# And one variable empty, which is the subtler half: the old shape rejects on
# the empty one whatever the populated one says.
IFS='|' read -r g s <<EOF
$(verdicts "/root/fogproject/bin" "" "/tftpboot")
EOF
is 'one empty variable is enough to break the old glob' "$g" "REJECTED"
is 'the new form ignores the empty one'                 "$s" "allowed"

# --- and the shipped function, not just the shapes -------------------------
#
# Without this the cases above are decorative: they prove the two shapes
# differ, which is arithmetic, not that this branch ships the safe one.
#
# Comments are stripped first -- the fix carries an explanation that QUOTES the
# broken form, and grepping the whole function finds that instead of code.
guardcode=$(sed -n '/^doOSSpecificIncludes() {/,/^}$/p' "$functions" \
    | grep -vE '^[[:space:]]*#')

check 'the shipped guard does not glob on a possibly-empty $webdirdest' \
    "$(printf '%s\n' "$guardcode" | grep -q '\*\$webdirdest\*' || echo yes)"
check 'the shipped guard does not glob on a possibly-empty $tftpdirdst' \
    "$(printf '%s\n' "$guardcode" | grep -q '\*\$tftpdirdst\*' || echo yes)"
check 'the shipped guard tests $webdirdest for non-emptiness first' \
    "$(printf '%s\n' "$guardcode" | grep -q -- '-n \$webdirdest' && echo yes)"
check 'the shipped guard tests $tftpdirdst for non-emptiness first' \
    "$(printf '%s\n' "$guardcode" | grep -q -- '-n \$tftpdirdst' && echo yes)"

# The precondition the whole bug rests on: the dispatch's `*)` arm returns
# instead of exiting, so execution DOES reach the guard with nothing sourced.
# If that ever changes to an exit, this test is still correct but no longer
# load-bearing -- and whoever makes that change should know it was deliberate.
check 'the OS dispatch *) arm still returns rather than exiting' \
    "$(sed -n '/^doOSSpecificIncludes() {/,/^}$/p' "$functions" \
        | grep -A3 '^[[:space:]]*\*)' | grep -q 'exit' || echo yes)"

if [ "$failures" -gt 0 ]; then
    echo "FAIL ($failures of $checks)"
    exit 1
fi

echo "ok  $checks checks passed"
exit 0
