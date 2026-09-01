#!/bin/bash
#
# Guards the ONE channel vocabulary (#1279).
#
#   tests/update-channel-vocabulary.test.sh
#
# FOG had two things called a channel. They shared the word and shared nothing
# else:
#
#   fog_update_channel  which branch this server tracks   stable/staging/dev
#   FOG_CHANNEL         the label stamped into            Patches/Beta/
#                       system.class.php                  Release Candidate/Feature
#
# So working-1.6 was simultaneously channel "dev" and channel "Beta", and
# dev-branch was channel "staging" and channel "Patches". Nothing reconciled
# them and neither name said which one it was, so fog-docs described
# fog_update_channel as "stable, staging or dev" while fog-workflows described
# the same branch as being on the "Beta channel" -- both correct about different
# variables, and anyone reading one while configuring the other got it wrong
# with no error.
#
# Worse than untidy: `dev` pointed at working-1.6 while a branch literally named
# `dev-branch` existed. An admin who set fog_update_channel='dev' expecting to
# track dev-branch tracked the 1.6 beta instead, and nothing told them.
#
# There is now one vocabulary. The stored value is lowercase, the FOG_CHANNEL
# label is its title-case form:
#
#   branch        channel   FOG_CHANNEL
#   stable        stable    Stable
#   dev-branch    patches   Patches
#   working-1.6   beta      Beta
#   rc-*          --        Release Candidate
#   feature-*     --        Feature
#
# The two halves live in different files that nothing else connects --
# lib/common/functions.sh owns the update track, .githooks/lib/fog-version.sh
# owns the label -- which is exactly how they drifted in the first place. Case F
# is the reason this file exists: it EXECUTES both and compares them, so the two
# cannot silently disagree again.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
CONFIG="$REPO/lib/common/config.sh"
VERSIONSH="$REPO/.githooks/lib/fog-version.sh"

for f in "$FUNCS" "$CONFIG" "$VERSIONSH"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1 ($2)"; FAIL=$((FAIL + 1)); }

# The three mapping functions are self-contained -- no globals, no external
# tools -- so they are pulled out and run for real rather than pattern-matched.
eval "$(awk '/^channelToBranch\(\) \{/,/^\}$/' "$FUNCS")"
eval "$(awk '/^normalizeChannel\(\) \{/,/^\}$/' "$FUNCS")"
eval "$(awk '/^branchToChannel\(\) \{/,/^\}$/' "$FUNCS")"

for fn in channelToBranch normalizeChannel branchToChannel; do
    declare -F "$fn" >/dev/null || { echo "ERROR: could not extract $fn from functions.sh" >&2; exit 1; }
done

# --- A. canonical channel -> branch ---------------------------------------
while read -r ch want; do
    got=$(channelToBranch "$ch" 2>/dev/null)
    [[ $got == "$want" ]] && ok "A. channel '$ch' tracks $want" \
        || bad "A. channel '$ch' tracks $want" "got '$got'"
done <<'EOF'
stable stable
patches dev-branch
beta working-1.6
EOF

# --- B. branch -> channel is the true inverse ------------------------------
for br in stable dev-branch working-1.6; do
    ch=$(branchToChannel "$br" 2>/dev/null)
    back=$(channelToBranch "$ch" 2>/dev/null)
    [[ $back == "$br" ]] && ok "B. $br -> '$ch' -> $br round-trips" \
        || bad "B. $br round-trip" "got '$ch' -> '$back'"
done

# --- C. the retired spellings still resolve -------------------------------
# Every server installed before #1279 carries one of these in .fogsettings.
# If this goes red, an update stops working on a server that did nothing wrong.
while read -r ch want; do
    got=$(channelToBranch "$ch" 2>/dev/null)
    [[ $got == "$want" ]] && ok "C. retired name '$ch' still tracks $want" \
        || bad "C. retired name '$ch' still tracks $want" "got '$got'"
done <<'EOF'
staging dev-branch
dev working-1.6
EOF

# --- D. normalizeChannel folds, and refuses the unknown -------------------
while read -r inp want; do
    got=$(normalizeChannel "$inp" 2>/dev/null)
    [[ $got == "$want" ]] && ok "D. '$inp' normalizes to '$want'" \
        || bad "D. '$inp' normalizes to '$want'" "got '$got'"
done <<'EOF'
staging patches
dev beta
patches patches
beta beta
stable stable
EOF

# --- E. an unknown channel is refused, not echoed back ---------------------
# Echoing it back would have it resolved as a branch name later, failing a long
# way from the typo that caused it.
for bad_in in devel Beta "" nightly working-1.6; do
    if out=$(normalizeChannel "$bad_in" 2>/dev/null); then
        bad "E. '$bad_in' is refused" "returned 0 with '$out'"
    elif [[ -n $out ]]; then
        bad "E. '$bad_in' is refused" "echoed '$out'"
    else
        ok "E. '$bad_in' is refused rather than passed through"
    fi
done
for bad_in in devel nightly; do
    channelToBranch "$bad_in" >/dev/null 2>&1 \
        && bad "E. channelToBranch refuses '$bad_in'" "returned 0" \
        || ok "E. channelToBranch refuses '$bad_in'"
done

# --- F. THE ALIGNMENT: both halves compared ------------------------------
# The two definitions are PARSED out of their own files and compared. Not a
# grep for a symbol: this reads the authoritative `case` arms in each file, so
# gutting either one -- renaming a value, dropping an arm, wrapping it in a
# condition that never fires -- changes what is parsed and shows up here.
#
# Why parsed rather than executed. fog-version.sh needs tags, master and
# dev-branch present locally (`git describe --tags`, `git rev-list
# master..dev-branch`), and CI checks out with actions/checkout at its default
# fetch-depth: 1 -- one commit, no tags, no other branches. Executing it would
# fail in CI for every branch, for reasons that have nothing to do with the
# vocabulary. F2 below runs it anyway WHERE IT CAN, and skips otherwise.
#
# And the `stable` arm is never executed even then. stable is the release
# branch, promoted from dev-branch by stable-releases.yml; a test has no
# business exercising it, and its arm is the one that reaches for dev-branch's
# commit count.
titlecase() { printf '%s' "$(tr '[:lower:]' '[:upper:]' <<<"${1:0:1}")${1:1}"; }

# branchon is what fog-version.sh keys on: the branch name up to the first '-'.
branchon_of() { printf '%s' "${1%%-*}"; }

# Pull `arm) ... channel="X" ...` out of fog-version.sh's compute_version case.
labelForArm() {
    awk -v want="$1" '
        /case "\$branchon" in/ { inc = 1; next }
        inc && /^        [a-z]+\)/ {
            arm = $0; sub(/\).*/, "", arm); gsub(/[ \t]/, "", arm)
        }
        inc && arm == want && /channel="/ {
            line = $0
            sub(/.*channel="/, "", line); sub(/".*/, "", line)
            print line; exit
        }
        inc && /^    esac$/ { exit }
    ' "$VERSIONSH"
}

for br in stable dev-branch working-1.6; do
    ch=$(branchToChannel "$br" 2>/dev/null)
    want=$(titlecase "$ch")
    got=$(labelForArm "$(branchon_of "$br")")
    if [[ -z $got ]]; then
        bad "F. $br: FOG_CHANNEL matches the update channel" "no channel arm parsed from fog-version.sh"
    elif [[ $got == "$want" ]]; then
        ok "F. $br: update channel '$ch' and FOG_CHANNEL '$got' are one word"
    else
        bad "F. $br: update channel '$ch' implies FOG_CHANNEL '$want'" "fog-version.sh says '$got'"
    fi
done

# feature is the only label left with no update channel. Still pinned so that a
# later attempt to give it one has to come here and think about it, rather than
# silently inventing a second spelling of an existing channel.
#
# rc was in this loop and is not any more -- it HAS a channel now (see
# channelToBranch in lib/common/functions.sh for why the old reasoning was
# retired). What it does not have is a title-case-derived label, so it is
# pinned as explicit pairs below instead of going through the F loop.
for pair in "feature:Feature"; do
    arm="${pair%%:*}"; want="${pair#*:}"
    got=$(labelForArm "$arm")
    [[ $got == "$want" ]] && ok "F. $arm labels '$want'" \
        || bad "F. $arm labels '$want'" "got '$got'"
    branchToChannel "${arm}-1.6" >/dev/null 2>&1 \
        && bad "F. $arm has no update channel" "branchToChannel returned one" \
        || ok "F. $arm correctly has no update channel"
done

# rc, the deliberate exception to "the label is the channel's title-case form".
# Asserted as literal pairs, in both directions, so the exception stays exactly
# one word wide and cannot quietly become a second name for something else.
got=$(labelForArm rc)
[[ $got == "Release Candidate" ]] && ok "F. rc labels 'Release Candidate'" \
    || bad "F. rc labels 'Release Candidate'" "got '$got'"

got=$(branchToChannel "rc-1.6.0")
[[ $got == rc ]] && ok "F. an rc-* branch maps to the rc channel" \
    || bad "F. an rc-* branch maps to the rc channel" "got '$got'"

got=$(normalizeChannel rc)
[[ $got == rc ]] && ok "F. rc is its own canonical spelling" \
    || bad "F. rc is its own canonical spelling" "got '$got'"

# The distinction that keeps two different failures apart: a channel FOG does
# not know is exit 1; a channel it knows but cannot resolve right now -- no
# release candidate published -- is exit 2. Callers say different things.
channelToBranch nonsense >/dev/null 2>&1; st=$?
[[ $st -eq 1 ]] && ok "F. an unknown channel exits 1" \
    || bad "F. an unknown channel exits 1" "got exit $st"

# --- F2. the same thing EXECUTED, where the checkout allows it ------------
# Belt and braces for a full local clone: proves the parse above describes what
# the script really computes. Skipped rather than failed on a shallow checkout,
# and stable is deliberately absent from the loop.
if git rev-parse --verify --quiet master >/dev/null 2>&1 \
   && [[ -n $(git tag 2>/dev/null | head -1) ]]; then
    for br in dev-branch working-1.6; do
        want=$(titlecase "$(branchToChannel "$br" 2>/dev/null)")
        got=$(sh "$VERSIONSH" "$br" head 2>/dev/null | sed -n '2p')
        if [[ -z $got ]]; then
            bad "F2. $br: fog-version.sh runs" "produced nothing"
        elif [[ $got == "$want" ]]; then
            ok "F2. $br: fog-version.sh really computes '$got'"
        else
            bad "F2. $br: fog-version.sh really computes '$want'" "got '$got'"
        fi
    done
else
    echo "SKIP: F2 needs master and tags locally (shallow checkout) -- F still ran"
fi

# --- G. config.sh migrates a stored legacy value --------------------------
# Anchored on the whole migration block, not on the function name: a grep for
# normalizeChannel passes when the assignment back to FOG_update_channel has
# been dropped, which is the failure that would leave the old value persisted.
if grep -q 'normalizeChannel "${FOG_update_channel}"' "$CONFIG" \
   && grep -q 'FOG_update_channel="${_canonicalChannel}"' "$CONFIG"; then
    ok "G. config.sh folds a stored legacy channel to its canonical spelling"
else
    bad "G. config.sh folds a stored legacy channel" "migration block missing or changed shape"
fi

# --- H. no stale vocabulary left in user-facing text ----------------------
# updatefog.sh's help and errors are where an admin learns the names.
if grep -q 'stable|staging|dev' "$REPO/bin/updatefog.sh"; then
    bad "H. updatefog.sh documents the current names" "still offers stable|staging|dev"
elif ! grep -q 'stable|patches|beta' "$REPO/bin/updatefog.sh"; then
    bad "H. updatefog.sh documents the current names" "does not offer stable|patches|beta"
else
    ok "H. updatefog.sh documents stable|patches|beta"
fi

echo
echo "passed: $PASS   failed: $FAIL"
[[ $FAIL -eq 0 ]]
