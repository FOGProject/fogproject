#!/usr/bin/env sh
#
# require_tools - announce missing hook tooling instead of silently skipping.
#
# The pre-commit hook generates artifacts that are committed to the repository:
# PSR2 formatting, the gettext .pot/.po files, and FOG_VERSION/FOG_CHANNEL.
# Every one of those is produced by a tool installed on the committer's machine,
# and a hook cannot install anything on a machine it does not control. So the
# only real choice is what to do when a tool is absent, and there are three:
#
#   skip silently   -- what this used to do. Commits then differ by machine with
#                      nothing to say so, and the next person who does have the
#                      tools produces a large unrelated diff that gets attributed
#                      to whatever they were actually working on.
#   refuse          -- consistent, but it stops a contributor without gettext
#                      from committing at all. Too hostile for a community
#                      project, and the usual result is that people disable the
#                      hook entirely.
#   skip out loud   -- what this does. The commit still lands, the omission is
#                      visible, and CI regenerates the artifact centrally.
#
# The exception is schemaCheck in the hook itself, which fails closed: it is a
# correctness gate rather than a formatting pass, and a stale schema manifest is
# not visible until someone's upgrade silently does nothing.
#
# Note that availability is only half the problem. Two machines that both have
# the tools still disagree when the VERSIONS differ -- php-cs-fixer 3.x and 4.x
# format differently, and gettext releases change .pot wrapping and escaping.
# This helper cannot solve that; only pinning the tool version or generating in
# one central place can, which is why CI is the backstop rather than the hook.
#
# Usage:
#   . "$project_dir/.githooks/lib/require-tools.sh"
#   require_tools "PSR2 formatting" php-cs-fixer || return
#
# Returns 0 when every named tool is on PATH; otherwise prints one warning
# naming what is missing and how to get it, and returns 1.

# Per-tool install hint. Kept deliberately short and distro-neutral -- the point
# is to name the package, not to guess the reader's package manager.
_tool_hint() {
    case "$1" in
        xgettext|msgcat|msgmerge)
            echo "provided by the 'gettext' package"
            ;;
        php)
            echo "provided by the PHP CLI package ('php-cli' on Debian/Ubuntu and Fedora/RHEL)"
            ;;
        php-cs-fixer)
            echo "install from https://cs.symfony.com/doc/installation.html"
            ;;
        *)
            echo "not found in PATH"
            ;;
    esac
}

require_tools() {
    _rt_purpose="$1"
    shift

    _rt_missing=""
    for _rt_tool in "$@"; do
        command -v "$_rt_tool" >/dev/null 2>&1 || _rt_missing="$_rt_missing $_rt_tool"
    done

    [ -z "$_rt_missing" ] && return 0

    printf 'pre-commit: SKIPPING %s -- not installed:%s\n' \
        "$_rt_purpose" "$_rt_missing" >&2
    # Unquoted on purpose: the list is space separated and the elements are
    # command names, which cannot contain whitespace.
    for _rt_tool in $_rt_missing; do
        printf 'pre-commit:   %s: %s\n' "$_rt_tool" "$(_tool_hint "$_rt_tool")" >&2
    done
    printf 'pre-commit: the commit will proceed WITHOUT those changes; CI regenerates them.\n' >&2

    return 1
}
