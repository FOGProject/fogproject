#!/bin/bash
#
# Guards the schema step of the installer against two failures an administrator
# cannot see.
#
#   tests/schema-prompt-answers.test.sh
#
# THE ANSWER IS WHAT THE TERMINAL SENT, not what was typed. `read` strips the
# newline and nothing else, so a client ending its lines with CRLF delivers
# $'Y\r' -- which matches no pattern updateDB() writes. A deliberate "Y" then
# fell through to the manual browser path, and that path:
#
#   - runs no migration at all
#   - prints the schema install token to stdout
#   - is followed by verifySchemaDeploy(), which reported "Done" because the
#     schema happened to already be current
#
# So the run looked successful and had deployed nothing. On a server that DID
# need migrating it would sit unmigrated under that same success line. Observed
# on a live upgrade: the operator typed Y, the transcript shows Y, and the
# manual instructions printed anyway.
#
# THE QUESTION IMPLIES PENDING WORK. Asking "install/update the schema now?" on
# a database that has every indexed step applied, and then migrating nothing,
# reads as the answer having been ignored. The question is now skipped in that
# case -- but the DEPLOY still runs, because it also seeds required rows and
# Schema::seedRequiredRows() is written for precisely the state where the
# indexed steps are done and rows are missing. A test that let the whole step
# be skipped would be pinning the wrong fix.
#
# Runs on generated fixtures with schemaVersionInDB() stubbed -- no install, no
# database, no network, no root.
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
dots() { :; }
errorStat() { :; }

echo "schema prompt answers:"

# --- 1. what the terminal adds is removed ------------------------------------
is "$(normalizeAnswer 'Y')"        "y"   "a plain Y lowercases"
is "$(normalizeAnswer $'Y\r')"     "y"   "a trailing carriage return is removed"
is "$(normalizeAnswer $'yes\r\n')" "yes" "so is a full CRLF"
is "$(normalizeAnswer 'Y ')"       "y"   "a trailing space is trimmed"
is "$(normalizeAnswer '  Y')"      "y"   "and a leading one"
is "$(normalizeAnswer $'\t N \t')" "n"   "tabs count as whitespace on both ends"
is "$(normalizeAnswer 'YES')"      "yes" "case is folded"
is "$(normalizeAnswer '')"         ""    "an empty answer stays empty"
is "$(normalizeAnswer '   ')"      ""    "and whitespace alone normalizes to empty"
is "$(normalizeAnswer 'nope')"     "nope" \
    "an unrecognized answer is passed through, not coerced"

# --- 2. the routing those answers reach --------------------------------------
# The case in updateDB() decides between a verified deploy and printing the
# token. Exercised through the same patterns the function uses, against the
# NORMALIZED answer, because that is the pair that has to agree.
route() {
    case "$(normalizeAnswer "$1")" in
        ''|[Yy]|[Yy][Ee][Ss]) printf 'automatic' ;;
        *)                    printf 'manual' ;;
    esac
}
# The regression. Every one of these was 'manual' before normalization.
for a in $'Y\r' $'y\r' 'Y ' ' y' $'yes\r' 'YES ' $'\tY'; do
    is "$(route "$a")" "automatic" \
        "$(printf '%q' "$a") deploys instead of printing the token"
done
# And the answers that must keep working exactly as they did.
is "$(route 'Y')"   "automatic" "a plain Y still deploys"
is "$(route 'yes')" "automatic" "so does yes"
is "$(route '')"    "automatic" "an empty answer still defaults to deploying"
is "$(route 'n')"   "manual"    "n still opts out"
is "$(route 'N')"   "manual"    "and N"
is "$(route $'n\r')" "manual"   "a carriage return does not turn a no into a yes"
is "$(route 'no')"  "manual"    "no opts out"
is "$(route 'nope')" "manual"   "and an unrecognized answer still opts out"

# --- 3. the wiring, which the two above cannot see ---------------------------
# normalizeAnswer() can be perfect and unreached. Both halves are pinned: the
# call exists, and it happens BEFORE the case that consumes the value.
body=$(awk '/^updateDB\(\) \{/,/^\}/' "$FUNCS")
is "$(printf '%s' "$body" | grep -c 'dbupdate=$(normalizeAnswer "\$dbupdate")')" "1" \
    "updateDB normalizes the answer"
normLine=$(printf '%s' "$body" | grep -n 'normalizeAnswer "\$dbupdate"' | cut -d: -f1)
caseLine=$(printf '%s' "$body" | grep -n '^    case \$dbupdate in' | cut -d: -f1)
if [[ -n $normLine && -n $caseLine && $normLine -lt $caseLine ]]; then
    ok "and does it before the case that reads it"
else
    bad "the normalize call is not before the case (norm=${normLine:-none} case=${caseLine:-none})"
fi
is "$(printf '%s' "$body" | grep -c 'read -r -p')" "1" \
    "the read is -r, so a backslash in the answer is not an escape"

# --- 4. is there anything to migrate ----------------------------------------
# schemaStepCount() reads the release's own step count; schemaVersionInDB() is
# stubbed, because the real one needs a database.
webdirdest="$WORK/web/"
mkdir -p "${webdirdest}commons"
mkschema() { # <n>
    : > "${webdirdest}commons/schema.php"
    local i
    for ((i = 0; i < $1; i++)); do
        echo '$this->schema[] = array(' >> "${webdirdest}commons/schema.php"
    done
}

mkschema 244
is "$(schemaStepCount)" "244" "the step count comes from schema.php"

schemaVersionInDB() { printf '244'; }
schemaIndexedStepsDone && ok "a database at the step count has nothing indexed left" \
    || bad "a database at the step count has nothing indexed left"

schemaVersionInDB() { printf '243'; }
schemaIndexedStepsDone && bad "one step behind must still be work" \
    || ok "one step behind is still work"

# A hand-set or 1.5-carried value above the count. Still nothing INDEXED to do,
# which is the question this predicate answers -- and it is also the state
# seedRequiredRows() exists to repair, which is why the deploy still runs.
schemaVersionInDB() { printf '300'; }
schemaIndexedStepsDone && ok "a value above the count has nothing indexed left" \
    || bad "a value above the count has nothing indexed left"

# Unknown on either side must behave as it always has: ask, and deploy.
schemaVersionInDB() { printf ''; }
schemaIndexedStepsDone && bad "a fresh install must not be treated as done" \
    || ok "an unreadable schema version is not 'done'"

schemaVersionInDB() { printf '244'; }
rm -f "${webdirdest}commons/schema.php"
is "$(schemaStepCount)" "" "a missing schema.php reports unknown, not zero"
schemaIndexedStepsDone && bad "an unreadable step count must not be treated as done" \
    || ok "an unreadable step count is not 'done'"

# A file whose formatting stopped matching must read as UNKNOWN, never as zero
# steps -- zero would make every database look fully migrated.
printf 'x\ny\n' > "${webdirdest}commons/schema.php"
is "$(schemaStepCount)" "" "a schema.php the pattern no longer matches reports unknown"
schemaIndexedStepsDone && bad "a zero count must not read as done" \
    || ok "a zero count is not 'done'"

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
