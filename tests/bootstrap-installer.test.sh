#!/bin/bash
#
# The one-liner bootstrap does the irreducible pre-clone work, and no more.
#
#   tests/bootstrap-installer.test.sh
#
# GH-1006. bin/bootstrap.sh is fetched over HTTP and piped into bash as root on
# a machine with nothing on it, which makes almost every interesting case here
# a REFUSAL: the wrong distro, no terminal, a directory that is not ours. A
# bootstrap that guesses in any of those is worse than one that stops.
#
# The two that matter most:
#
#   * An unsupported distribution is refused BEFORE git is installed. The
#     alternative leaves a machine carrying packages and a checkout it cannot
#     use, and says so several minutes later.
#
#   * No terminal and no --yes is a refusal, not a silent fall back to -Y.
#     Under `curl ... | bash` the script's stdin is the pipe, so the installer
#     has to be handed /dev/tty or it answers its own prompts with the
#     remaining bytes of this file. Where there is no tty to hand it, starting
#     an unattended root install of imaging software on a machine nobody is
#     watching is not a reasonable default.
#
# Nothing here runs as root, installs a package, or reaches the network. The
# root check is neutered in a COPY of the script, the package managers and git
# are stubs on PATH, and the distro is chosen by pre-setting linuxReleaseName,
# which detectOSFamily is [[ -z ]]-guarded to respect.
#
# OS=Linux is exported for the same reason: bootstrap.sh does
# `[[ -z $OS ]] && OS=$(uname -s)`, and on a developer's machine uname may not
# say Linux at all -- this suite runs under Git Bash on Windows in at least one
# place. The override uses the guard the script already provides rather than
# patching the check out, so the check itself stays exercised: the unmodified
# script is run WITHOUT the override further down to prove it still refuses.
#
# Usage: bash tests/bootstrap-installer.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
bootstrap="$root/bin/bootstrap.sh"

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

if [[ ! -r $bootstrap ]]; then
    echo "FAIL: cannot read $bootstrap" >&2
    exit 1
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# ---------------------------------------------------------------------------
# The real script, unmodified. Everything before the root check is reachable
# without being root, which is most of the argument handling.
# ---------------------------------------------------------------------------
bash "$bootstrap" --help >/dev/null 2>&1
check "--help exits 0" "$?"

check "--help works before the root check, so a user can read it" \
    "$(bash "$bootstrap" --help 2>&1 | grep -q -- '--channel'; echo $?)"

bash "$bootstrap" >/dev/null 2>&1
check "a non-root run exits 1, like installfog.sh" \
    "$([[ $? -eq 1 ]]; echo $?)"

check "and says the same thing installfog.sh says" \
    "$(bash "$bootstrap" 2>&1 | grep -q 'must be run as root user'; echo $?)"

for bad in "--nonsense" "--channel" "--branch" "--git-path relative/path"; do
    bash "$bootstrap" $bad >/dev/null 2>&1
    check "bad argument '${bad}' exits 3" "$([[ $? -eq 3 ]]; echo $?)"
done

# ---------------------------------------------------------------------------
# A copy with the root gate removed, so the rest of the flow is reachable.
# Nothing else is changed -- the point is to exercise the real logic.
# ---------------------------------------------------------------------------
sut="$work/bootstrap.sh"
sed 's/^if \[\[ ! \$EUID -eq 0 \]\]; then$/if false; then/' "$bootstrap" > "$sut"
if ! grep -q 'if false; then' "$sut"; then
    echo "FAIL: could not neuter the root check -- it changed shape." >&2
    echo "  Point this test at the new one; do not delete the assertions." >&2
    exit 1
fi

# --- stubs ----------------------------------------------------------------
# Three directories on purpose. installGit() returns early when git is already
# on PATH, so the "which package manager" cases have to run with a PATH that
# reaches NO git at all -- not the stub, and not the real one in /usr/bin
# either. Naming /usr/bin in that PATH is what made these six assertions pass
# vacuously: every machine that runs this suite, developer box and CI runner
# alike, already has git, so installGit() returned at its first line and no
# package manager was ever reached.
#
# Hence $sandbox: symlinks to the tools bootstrap.sh actually calls, git
# deliberately absent, and nothing else on PATH. A tool added to the script
# later fails here loudly rather than silently falling back to the host.
pmstubs="$work/pm"      # package managers only
stubs="$work/stubs"     # package managers + git
sandbox="$work/nogit"   # host tools, minus git
mkdir -p "$pmstubs" "$stubs" "$sandbox"
calls="$work/calls.log"

# bash included: `bash "$sut"` is resolved with the PATH set for the run.
for tool in bash awk cat cut date env grep head ls mkdir rm sed sort stat tr tty uname which; do
    src=$(command -v "$tool" 2>/dev/null) || {
        echo "FAIL: $tool is not on PATH; the sandbox cannot be built." >&2
        exit 1
    }
    ln -sf "$src" "$sandbox/$tool"
done

for tool in apt-get dnf yum pacman apk; do
    cat > "$pmstubs/$tool" <<EOF
#!/bin/bash
echo "$tool \$*" >> "\$CALLS"
exit 0
EOF
    chmod +x "$pmstubs/$tool"
    cp "$pmstubs/$tool" "$stubs/$tool"
done

# git: records what it was asked, and makes `clone` produce a checkout that
# looks real enough for the next step to act on.
cat > "$stubs/git" <<'EOF'
#!/bin/bash
echo "git $*" >> "$CALLS"
case "$1" in
    clone)
        mkdir -p "$3/.git" "$3/bin"
        cat > "$3/bin/installfog.sh" <<'INNER'
#!/bin/bash
echo "installfog $*" >> "$CALLS"
exit 0
INNER
        chmod +x "$3/bin/installfog.sh"
        ;;
    ls-remote)
        # No release candidate published, which is origin's real state.
        exit 0
        ;;
esac
exit 0
EOF
chmod +x "$stubs/git"

# Every invocation goes through here so the environment is set the same way
# each time. EXPORTED, not prefixed: a `VAR=x func` prefix sets the variable
# for the function, not for the `bash` child it starts, so the script would
# never see it.
run() {
    local distro="$1" version="$2"
    shift 2
    ( export PATH="$stubs:$PATH" CALLS="$calls" OS="Linux"
      export linuxReleaseName="$distro" OSVersion="$version"
      unset osfamily
      cd "$work" && bash "$sut" "$@" )
}

# The same, on a machine that does not have git yet -- which is the machine
# this script exists for.
runNoGit() {
    local distro="$1" version="$2"
    shift 2
    ( export PATH="$pmstubs:$sandbox" CALLS="$calls" OS="Linux"
      export linuxReleaseName="$distro" OSVersion="$version"
      unset osfamily
      cd "$work" && bash "$sut" "$@" )
}

# ---------------------------------------------------------------------------
# An unsupported distribution is refused, and refused EARLY.
# ---------------------------------------------------------------------------
: > "$calls"
out=$(run "Void Linux" 1 --git-path "$work/void" 2>&1)
st=$?
check "an unsupported distribution exits 4" "$([[ $st -eq 4 ]]; echo $?)"
check "it names the distribution it refused" \
    "$(grep -q 'Void Linux' <<< "$out"; echo $?)"
check "it lists what IS supported" \
    "$(grep -qi 'Alpine' <<< "$out" && grep -qi 'Debian' <<< "$out"; echo $?)"
check "nothing was installed before the refusal" \
    "$([[ ! -s $calls ]]; echo $?)"
check "and nothing was cloned" \
    "$([[ ! -d $work/void ]]; echo $?)"

# ---------------------------------------------------------------------------
# Each family reaches for its own package manager.
# ---------------------------------------------------------------------------
declare -A wanted=( [Ubuntu]=apt-get [Debian]=apt-get ["Rocky Linux"]=dnf ["Alpine Linux"]=apk ["Arch Linux"]=pacman )
for name in Ubuntu Debian "Rocky Linux" "Alpine Linux" "Arch Linux"; do
    : > "$calls"
    dir="$work/fam-${name// /_}"
    runNoGit "$name" 1 --git-path "$dir" --yes >/dev/null 2>&1
    # Asked to install GIT, not merely invoked: the debian arm runs
    # `apt-get -yq update` first, so a bare "^apt-get " matches even when the
    # install line is gone.
    check "${name} installs git with ${wanted[$name]}" \
        "$(grep -qE "^${wanted[$name]} .*(^| )git( |$)" "$calls"; echo $?)"
done

# dnf-before-yum is the fallback installfog.sh already uses on this family.
: > "$calls"
nodnf="$work/nodnf"
mkdir -p "$nodnf"
cp "$pmstubs/yum" "$nodnf/"
( export PATH="$nodnf:$sandbox" CALLS="$calls" OS="Linux"
  unset osfamily
  export linuxReleaseName="CentOS Linux" OSVersion=9
  cd "$work" && bash "$sut" --git-path "$work/yumbox" --yes ) >/dev/null 2>&1
check "a Red Hat box without dnf falls back to yum" \
    "$(grep -qE '^yum .*(^| )git( |$)' "$calls"; echo $?)"

# ---------------------------------------------------------------------------
# Channels resolve to branches, and rc says something true when there is none.
# ---------------------------------------------------------------------------
for pair in "stable:stable" "patches:dev-branch" "beta:working-1.6" "dev:working-1.6" "staging:dev-branch"; do
    ch="${pair%%:*}"; want="${pair#*:}"
    : > "$calls"
    run Ubuntu 22 --git-path "$work/ch-$ch" --channel "$ch" --yes >/dev/null 2>&1
    check "--channel ${ch} checks out ${want}" \
        "$(grep -q "^git -C .* checkout ${want}$" "$calls"; echo $?)"
done

: > "$calls"
out=$(run Ubuntu 22 --git-path "$work/rc" --channel rc --yes 2>&1)
check "--channel rc with none published says so, rather than 'unknown channel'" \
    "$(grep -qi 'No release candidate is currently published' <<< "$out"; echo $?)"
check "and does not clone anything" \
    "$([[ ! -d $work/rc ]]; echo $?)"

: > "$calls"
out=$(run Ubuntu 22 --git-path "$work/bogus" --channel nonsense --yes 2>&1)
check "an unknown channel is a different message" \
    "$(grep -qi 'Unknown channel' <<< "$out"; echo $?)"
check "which still names the retired spellings" \
    "$(grep -qi 'staging and dev' <<< "$out"; echo $?)"

# --branch is a one-off and must NOT record a channel.
: > "$calls"
run Ubuntu 22 --git-path "$work/br" --branch some-pr-branch --yes >/dev/null 2>&1
check "--branch checks out the literal branch" \
    "$(grep -q 'checkout some-pr-branch$' "$calls"; echo $?)"
check "--branch does not forward --channel to the installer" \
    "$(! grep -q 'installfog.*--channel' "$calls"; echo $?)"

# The other half of the same rule, on its own run so it reads its own log.
: > "$calls"
run Ubuntu 22 --git-path "$work/fwd" --channel beta --yes >/dev/null 2>&1
check "a channel run DOES forward --channel to the installer" \
    "$(grep -q 'installfog.*--channel beta' "$calls"; echo $?)"

# ---------------------------------------------------------------------------
# Idempotence: an existing checkout is never cloned over or reset.
# ---------------------------------------------------------------------------
existing="$work/existing"
mkdir -p "$existing/.git" "$existing/bin"
echo "mine" > "$existing/precious"
cat > "$existing/bin/installfog.sh" <<'EOF'
#!/bin/bash
echo "installfog $*" >> "$CALLS"
EOF
: > "$calls"
out=$(run Ubuntu 22 --git-path "$existing" --channel beta --yes 2>&1)
check "an existing checkout is not cloned over" \
    "$(! grep -q '^git clone' "$calls"; echo $?)"
check "a file in it survives" \
    "$([[ $(cat "$existing/precious") == mine ]]; echo $?)"
check "nothing is reset --hard" \
    "$(! grep -q 'reset --hard' "$calls"; echo $?)"
check "it points the admin at updatefog.sh instead" \
    "$(grep -q 'updatefog.sh' <<< "$out"; echo $?)"

# A non-empty directory that is NOT a checkout is refused rather than merged.
notours="$work/notours"
mkdir -p "$notours"
echo x > "$notours/somefile"
run Ubuntu 22 --git-path "$notours" --yes >/dev/null 2>&1
check "a non-empty directory that is not a checkout exits 6" \
    "$([[ $? -eq 6 ]]; echo $?)"
check "and is left alone" \
    "$([[ -f $notours/somefile && ! -d $notours/.git ]]; echo $?)"

# ---------------------------------------------------------------------------
# The tty rule. This test process has no controlling terminal, which is
# exactly the cloud-init/CI case.
# ---------------------------------------------------------------------------
: > "$calls"
out=$(run Ubuntu 22 --git-path "$work/notty" --channel beta < /dev/null 2>&1)
st=$?
if [[ $st -eq 7 ]]; then
    ok=0
else
    # A tty IS available in some local shells; the refusal cannot be observed
    # there, and reporting a pass would be a lie either way.
    ok=$( (exec < /dev/tty) 2>/dev/null && echo 0 || echo 1 )
    [[ $ok -eq 0 ]] && echo "  SKIP  no-tty refusal (this shell has a usable /dev/tty)"
fi
check "no tty and no --yes exits 7 rather than installing unattended" "$ok"

if [[ $st -eq 7 ]]; then
    check "it tells the user to pass --yes" \
        "$(grep -q -- '--yes' <<< "$out"; echo $?)"
    check "it does not start the installer" \
        "$(! grep -q '^installfog' "$calls"; echo $?)"
    # The clone is fine to have happened -- the machine is further along and
    # nothing is lost. What must not happen is an install nobody asked for.
    check "the refusal comes after the clone, not instead of it" \
        "$(grep -q '^git clone' "$calls"; echo $?)"
fi

# --yes installs unattended, which is the whole point of the flag.
: > "$calls"
run Ubuntu 22 --git-path "$work/yes" --channel beta --yes >/dev/null 2>&1
check "--yes runs the installer" \
    "$(grep -q '^installfog' "$calls"; echo $?)"
check "--yes passes -Y" \
    "$(grep -q '^installfog.*-Y' "$calls"; echo $?)"

# ---------------------------------------------------------------------------
# It must not grow into an installer.
# ---------------------------------------------------------------------------
body=$(sed 's/#.*//' "$bootstrap")
check "bootstrap does not source anything from the repo" \
    "$(! grep -qE '^\s*\.\s+\.\./lib|^\s*source\s' <<< "$body"; echo $?)"
check "bootstrap does not write .fogsettings" \
    "$(! grep -q 'fogsettings' <<< "$body"; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
