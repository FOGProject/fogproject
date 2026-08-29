#!/usr/bin/env sh
#
# Runs every test in this directory and reports one line each.
#
# The convention these tests already follow -- standalone scripts, exit 0 for
# pass and non-zero for fail, no framework and no database (docs/adr/0008
# -secure-boot-enrollment-task-type.md:103,144) -- was documented but had no
# runner, so "the test suite passes" meant a human remembering four commands.
# That is the same reason .githooks/lib/update-language.sh exists: one copy of
# the invocation, so the hook, CI and a developer all run the identical thing.
#
# Deliberately NOT wired into .githooks/pre-commit. The hook's only blocking
# gate is schemaCheck, and adding a second one is a change to how every commit
# in this repository behaves -- a separate decision from having a runner at all.
# CI (FOGProject/fog-workflows) is where this belongs.
#
# A test may skip: exit 0 having printed a line containing "SKIP".
# secureboot-authvars.test.sh does this when efitools is absent.
#
# Usage: sh tests/run-all.sh
# Exit status 0 = every test passed or skipped, 1 = at least one failed.

testdir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

pass=0
fail=0
failed=''

run_one() {
    name=$(basename "$1")

    # Output is captured rather than streamed so a passing test contributes one
    # line instead of its own chatter; a failing one gets its output replayed in
    # full below, which is when it is actually wanted.
    out=$("$2" "$1" 2>&1)
    status=$?

    if [ $status -eq 0 ]; then
        pass=$((pass + 1))
        printf 'ok    %s\n' "$name"
    else
        fail=$((fail + 1))
        failed="$failed $name"
        printf 'FAIL  %s (exit %d)\n' "$name" "$status"
        printf '%s\n' "$out" | sed 's/^/      /'
    fi
}

for t in "$testdir"/*.test.php; do
    [ -f "$t" ] || continue
    run_one "$t" php
done

# bash, not sh. Every *.test.sh here carries a #!/bin/bash shebang and uses
# bash-only constructs -- [[ ]], arrays, ${BASH_SOURCE[0]} -- because the code
# they exercise (lib/common/functions.sh) is itself bash and cannot be sourced
# by anything else. Running them with `sh` works only where /bin/sh happens to
# BE bash; on Debian and Ubuntu it is dash, and there every one of them died on
# "Bad substitution" at the line that locates the repo, before running a single
# assertion. That reads as a failing test suite rather than a mis-invoked one.
for t in "$testdir"/*.test.sh; do
    [ -f "$t" ] || continue
    run_one "$t" bash
done

printf '\n%d passed, %d failed\n' "$pass" "$fail"

if [ $fail -gt 0 ]; then
    printf 'failed:%s\n' "$failed" >&2
    exit 1
fi

exit 0
