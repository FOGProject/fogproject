#!/bin/bash
#
# The installer must stop on an unusable PHP before it changes anything.
#
#   tests/installer-php-preflight.test.sh
#
# Forum 18333. The only PHP gate was Initiator::_verCheck(), which runs on a
# web request. So an upgrade on a PHP below the floor reported "Setup
# complete" and left a broken web UI.
#
# checkPhpRequirements() is EXECUTED here against stand-in php and php-fpm
# binaries. The stand-in php hands each -r snippet to the real php with the
# version replaced, so the version_compare() under test is the real one. The
# call site and the option are checked textually, because a correct helper
# that nothing calls leaves the bug in place.
#
# The fake versions (8.9, 8.8, 7.3) are chosen so that no real php-fpm under
# /usr/sbin, which the function also searches, can satisfy them.
#
# No root, no network, no FOG install. Needs a php CLI on PATH.
#
# Usage: bash tests/installer-php-preflight.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"
installer="$root/bin/installfog.sh"

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

realphp=$(command -v php) || { echo "FAIL: no php CLI on PATH" >&2; exit 1; }

# Lifted rather than sourced, as in installer-php-ssh2-matches-running-php.test.sh.
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}
for fn in checkPhpRequirements checkPhpExtensions; do
    snippet=$(lift "$fn")
    if ! grep -q "^$fn() {" <<< "$snippet"; then
        echo "FAIL: could not find $fn() in lib/common/functions.sh." >&2
        echo "  If it moved or was renamed, point this test at it -- do not" >&2
        echo "  delete the assertions." >&2
        exit 1
    fi
    eval "$snippet"
done
dots() { printf '%s ' "$1"; }

stubs=$(mktemp -d)
trap 'rm -rf "$stubs"' EXIT
# php: -r runs the snippet on the real php with the fake version; -m lists
# $FAKE_MODS one per line.
cat > "$stubs/php" <<STUB
#!/bin/bash
if [[ \$1 == -m ]]; then printf '%s\n' "[PHP Modules]" \$FAKE_MODS; exit 0; fi
code=\$2
code=\${code//PHP_MAJOR_VERSION . \".\" . PHP_MINOR_VERSION/\"\$FAKE_CLI\"}
code=\${code//PHP_VERSION/\"\$FAKE_CLI.0\"}
exec "$realphp" -r "\$code"
STUB
cat > "$stubs/php-fpm" <<'STUB'
#!/bin/bash
if [[ $1 == -m ]]; then printf '%s\n' "[PHP Modules]" $FAKE_FPM_MODS; exit 0; fi
echo "PHP $FAKE_FPM.0 (fpm-fcgi) (built: Jan  1 2026 00:00:00)"
STUB
chmod +x "$stubs/php" "$stubs/php-fpm"

allmods="curl ftp gettext json mbstring openssl pdo_mysql pcntl posix"

# Runs the check in a subshell from bin/, as the installer does. Prints the
# output, then "rc=<status>".
run() {
    (
        cd "$root/bin" || exit 99
        PATH="$stubs:$PATH"
        php_apk=""
        FOG_os_id=${FOG_os_id:-2}
        checkPhpRequirements
    ) 2>&1
    echo "rc=$?"
}

# ---------------------------------------------------------------------------
out=$(FAKE_CLI=8.9 FAKE_FPM=8.9 FAKE_MODS="$allmods" FAKE_FPM_MODS="$allmods" skipPhpCheck="" run)
check "matching CLI and FPM above the floor passes (got: $out)" \
    "$([[ $out == *"rc=0" && $out == *"OK (CLI and FPM 8.9"* ]]; echo $?)"
check "no extension warning when all are loaded" \
    "$([[ $out != *Missing* ]]; echo $?)"

out=$(FAKE_CLI=7.3 FAKE_FPM=7.3 FAKE_MODS="$allmods" FAKE_FPM_MODS="$allmods" skipPhpCheck="" run)
check "a CLI below the floor stops the installer (got: $out)" \
    "$([[ $out == *"rc=1" && $out == *"PHP CLI is 7.3. FOG needs PHP"* ]]; echo $?)"

out=$(FAKE_CLI=8.9 FAKE_FPM=8.8 FAKE_MODS="$allmods" FAKE_FPM_MODS="$allmods" skipPhpCheck="" run)
check "an FPM of another version stops the installer (got: $out)" \
    "$([[ $out == *"rc=1" && $out == *"no PHP-FPM 8.9 is installed"* ]]; echo $?)"

out=$(FAKE_CLI=7.3 FAKE_FPM=7.3 FAKE_MODS="$allmods" FAKE_FPM_MODS="$allmods" skipPhpCheck=1 run)
check "--skip-php-check continues past a failed check (got: $out)" \
    "$([[ $out == *"rc=0" && $out == *"--skip-php-check was given"* ]]; echo $?)"

out=$(FAKE_CLI=8.9 FAKE_FPM=8.9 FAKE_MODS="$allmods" FAKE_FPM_MODS="curl json" skipPhpCheck="" run)
check "a missing FPM extension warns but does not stop (got: $out)" \
    "$([[ $out == *"rc=0" && $out == *"Missing: ftp gettext mbstring openssl pdo_mysql"* ]]; echo $?)"

out=$(FAKE_CLI=8.9 FAKE_FPM=8.9 FAKE_MODS="curl ftp gettext json mbstring openssl pdo_mysql" FAKE_FPM_MODS="$allmods" skipPhpCheck="" run)
check "the CLI alone is checked for pcntl and posix (got: $out)" \
    "$([[ $out == *"rc=0" && $out == *"PHP CLI extensions Missing: pcntl posix"* && $out == *"PHP-FPM extensions OK"* ]]; echo $?)"

out=$(FAKE_CLI=8.9 FAKE_FPM=8.9 FAKE_MODS="" FAKE_FPM_MODS="" skipPhpCheck="" FOG_os_id=4 run)
check "Arch skips the extension check (got: $out)" \
    "$([[ $out == *"rc=0" && $out != *extensions* ]]; echo $?)"

out=$(FAKE_CLI=7.3 FAKE_FPM=7.3 FAKE_MODS="$allmods" FAKE_FPM_MODS="$allmods" skipPhpCheck="" exitFail=1 run)
check "-X (exitFail) returns 1 instead of exiting (got: $out)" \
    "$([[ $out == *"rc=1" && $out == *"PHP CLI is 7.3"* ]]; echo $?)"

# ---------------------------------------------------------------------------
# The floor is read from _verCheck(). If that line changes shape, the read
# returns nothing and every install would stop, so pin it to the real file.
floor=$(sed -n "s/.*version_compare(phpversion(), '\([0-9.]*\)', '<').*/\1/p" \
    "$root/packages/web/commons/init.php" | head -1)
check "the floor reads from commons/init.php (got '$floor')" \
    "$([[ $floor =~ ^[0-9]+\.[0-9]+$ ]]; echo $?)"
check "the function reads the floor with that same expression" \
    "$(grep -qF "version_compare(phpversion(), '\\([0-9.]*\\)', '<')" <<< "$(lift checkPhpRequirements)"; echo $?)"

# ---------------------------------------------------------------------------
# Call site: after the packages are confirmed, before anything is configured.
order=$(grep -n -E '^\s*(confirmPackageInstallation|checkPhpRequirements|configureUsers)\s*$' "$installer" | awk -F: '{print $2}' | tr -d ' ' | tr '\n' ' ')
check "installfog.sh calls it between confirmPackageInstallation and configureUsers (got: $order)" \
    "$([[ $order == "confirmPackageInstallation checkPhpRequirements configureUsers " ]]; echo $?)"
check "--skip-php-check is in longopts" \
    "$(grep '^longopts=' "$installer" | grep -q 'skip-php-check'; echo $?)"
check "--skip-php-check is handled in the option case" \
    "$(grep -qE '^\s*--skip-php-check\)' "$installer"; echo $?)"

echo "installer-php-preflight: $pass passed, $fail failed"
[[ $fail -eq 0 ]]
