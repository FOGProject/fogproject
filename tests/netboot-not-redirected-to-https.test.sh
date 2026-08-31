#!/bin/bash
#
# Guards GH-978 on both web servers: the forced-HTTPS redirect must not catch
# the netboot path, and must still catch everything else.
#
#   tests/netboot-not-redirected-to-https.test.sh
#
# When the netboot transport is not HTTPS, three directories have to stay
# reachable over plain HTTP, because a BOOTLOADER fetches them and cannot
# validate a certificate it has no anchor for:
#
#   service/ipxe/        boot.php, advanced.php, the menu artwork, refind, grub
#   service/secureboot/  MOK.der, which IpxeBootMenu imgfetches, and mmx64.efi /
#                        arm64-efi/mmaa64.efi, which it chains
#   service/uboot/       boot.php for ARM boards; U-Boot's `wget` is HTTP-only
#                        with no TLS at all, so it cannot even FAIL a
#                        validation -- a 308 to https simply ends the boot
#
# WHY THIS IS BEHAVIORAL, AND WHY IT EXISTS AT ALL
#
# Both arms were correct when written and both were verified by hand. Neither
# was pinned, and every part of this is one careless edit away from silently
# reverting -- which is exactly how the original defect survived eight years.
# FOG's redirect was scoped to /management/ until a 2017 commit whose subject
# was "Make sure query string is passed properly" replaced the rule with
# `(.*)`; widening it to the whole site was collateral, nobody noticed, and
# netboot has been redirected ever since.
#
# The two arms fail in completely different ways, and neither failure is
# visible in a grep:
#
#   Apache  -- a RewriteCond guards only the NEXT RewriteRule, and in VIRTUAL
#              HOST context %{REQUEST_URI} carries its leading slash, as does
#              $WEB_root. Anchor it wrong, or emit it after the rule, and the
#              condition never matches while the line is still present.
#   nginx   -- location selection happens AFTER the server rewrite phase, so a
#              server-level `return 308` fires before any location is chosen
#              and the exclusions are dead code no matter what order they are
#              emitted in. It only works as `location /`.
#
# One thing this file deliberately does NOT assert is the `^~` modifier on the
# netboot prefixes. Mutating it away leaves every check green, and correctly
# so: this block emits only prefix locations plus `location /`, and nginx picks
# the longest matching prefix, so /fog/service/ipxe/ beats / either way. `^~`
# is insurance against a future regex location here, and there is none today.
# An assertion no mutation can distinguish is decoration that reads as
# coverage, so it is left out rather than written and never exercised.
#
# So this asks the real servers what they do. Section 1 pins the EMISSION --
# the directives the installer writes, and their order, read out of
# functions.sh. Sections 2 and 3 then reconstruct the same directive shape and
# put a real nginx and a real Apache behind it. The reconstruction drops only
# the PHP handling (`index`/`include $phploc`), which needs a php-fpm socket
# and has no bearing on which location or rule is selected -- that selection is
# the entire thing under test.
#
# Skips (exit 0) per server when that server is absent. A server that is
# present and REFUSES the generated config is a failure, not a skip.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
FUNCS="lib/common/functions.sh"
[[ -r $FUNCS ]] || { echo "cannot read $FUNCS -- run this from the repository"; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

NBDIRS="ipxe secureboot uboot"

echo "1. both arms still emit an exemption for every netboot directory"

# Comments stripped: this file's prose and the call sites' own block comments
# name these directives repeatedly, and a gate its documentation satisfies is
# not a gate.
src() { grep -vE '^[[:space:]]*#' "$FUNCS"; }

if [[ $(src | grep -c 'for nbdir in ipxe secureboot uboot; do') -eq 2 ]]; then
    ok "both the nginx and Apache arms loop over all three netboot directories"
else
    bad "expected 2 netboot-directory loops (nginx + Apache), found $(src | grep -c 'for nbdir in ipxe secureboot uboot; do')"
fi

# A server-level return would make the nginx exclusions dead code, because
# nginx runs the server rewrite phase BEFORE it selects a location. Grepping
# for the return line cannot tell the two apart -- the text is identical -- so
# assert the ADJACENCY: the return must be emitted inside the `location / {`
# opened on the line before it.
locline=$(src | grep -n 'echo "    location / {"' | head -1 | cut -d: -f1)
retline=$(src | grep -n 'echo "        return 308 https' | head -1 | cut -d: -f1)
if [[ -n $locline && -n $retline && $((retline - locline)) -eq 1 ]]; then
    ok "nginx emits the redirect inside location /, not at server level"
else
    bad "nginx redirect is not immediately inside a location block (loc=$locline ret=$retline) -- exclusions become dead code"
fi

if src | grep -q 'RewriteCond %{REQUEST_URI} !\^${webrootre}service/${nbdir}/'; then
    ok "Apache exempts each netboot directory with an anchored RewriteCond"
else
    bad "Apache netboot exemption missing or no longer anchored on \${webrootre}"
fi

# ---------------------------------------------------------------- behavioral

tmp=$(mktemp -d) || exit 1
cleanup() {
    [[ -f $tmp/httpd.pid ]] && kill "$(cat "$tmp/httpd.pid")" 2>/dev/null
    [[ -f $tmp/nginx.pid ]] && kill "$(cat "$tmp/nginx.pid")" 2>/dev/null
    rm -rf "$tmp"
}
trap cleanup EXIT

docroot="$tmp/docroot"
mkdir -p "$docroot/fog/management" "$docroot/fog/service"
for d in $NBDIRS; do mkdir -p "$docroot/fog/service/$d"; echo "netboot" > "$docroot/fog/service/$d/boot.php"; done
echo menu    > "$docroot/fog/management/index.php"
echo checkin > "$docroot/fog/service/checkin.php"

# Both servers fork their workers as an unprivileged user (nobody, www-data),
# and mktemp -d is 0700 -- so a worker cannot traverse the tree and nginx's
# try_files answers 404. That reads exactly like a routing failure and is
# really a permissions one; it reproduced on Ubuntu and not on Fedora, where
# an unprivileged nginx cannot drop privileges and stays readable by accident.
chmod 755 "$tmp"
find "$docroot" -type d -exec chmod 755 {} +
find "$docroot" -type f -exec chmod 644 {} +

# The variables the installer emits with.
WEB_root="/fog/"
WEB_docroot="$docroot"
webrootre=$(printf '%s' "$WEB_root" | sed 's/[.[\*^$()+?{|]/\\&/g')
BOOT_url_proto="http"      # the mode the exemption exists for
phploc="$tmp/php.conf"; : > "$phploc"

probe() {  # probe <base-url> <path>
    curl -s -o /dev/null -w '%{http_code}' --max-redirs 0 "$1$2"
}

assert_arm() {  # assert_arm <server-label> <base-url>
    local label="$1" base="$2" d c
    for d in $NBDIRS; do
        c=$(probe "$base" "/fog/service/$d/boot.php")
        if [[ $c == 2* ]]; then
            ok "$label: service/$d/ is served over HTTP (got $c)"
        else
            case $c in
                30*) bad "$label: service/$d/ answered $c -- a bootloader path is still being redirected" ;;
                *)   bad "$label: service/$d/ answered $c -- not redirected, but not served either" ;;
            esac
        fi
    done
    c=$(probe "$base" "/fog/management/index.php")
    if [[ $c == 30* ]]; then
        ok "$label: the management UI is still redirected (got $c)"
    else
        bad "$label: the management UI answered $c -- forced HTTPS is no longer applied"
    fi
    # The exemption is three directories, not all of service/. fog-client's
    # check-in rides HTTPS like everything else.
    c=$(probe "$base" "/fog/service/checkin.php")
    if [[ $c == 30* ]]; then
        ok "$label: the rest of service/ is still redirected (got $c)"
    else
        bad "$label: service/checkin.php answered $c -- the exemption is too wide"
    fi
}

echo
echo "2. nginx"

if ! command -v nginx >/dev/null 2>&1; then
    echo "  SKIP  nginx not installed"
else
    # The installer's own emission for the :80 arm.
    etcconf="$tmp/nginx-inc.conf"; : > "$etcconf"
    for nbdir in $NBDIRS; do
        echo "    location ^~ ${WEB_root}service/${nbdir}/ {" >> "$etcconf"
        echo "        root ${WEB_docroot};" >> "$etcconf"
        echo "        try_files \$uri \$uri/ =404;" >> "$etcconf"
        echo "    }" >> "$etcconf"
    done
    echo "    location = ${WEB_root}management/other/ca.cert.der {" >> "$etcconf"
    echo "        root ${WEB_docroot};" >> "$etcconf"
    echo "    }" >> "$etcconf"
    echo "    location / {" >> "$etcconf"
    echo "        return 308 https://\$host\$request_uri;" >> "$etcconf"
    echo "    }" >> "$etcconf"

    nport=$(( 20000 + RANDOM % 20000 ))
    mkdir -p "$tmp/nglogs" "$tmp/ngtmp"
    {
        echo "pid $tmp/nginx.pid;"
        echo "error_log $tmp/nglogs/error.log;"
        echo "daemon on;"
        echo "events { worker_connections 64; }"
        echo "http {"
        echo "  access_log off;"
        echo "  client_body_temp_path $tmp/ngtmp;"
        echo "  proxy_temp_path $tmp/ngtmp/p;"
        echo "  fastcgi_temp_path $tmp/ngtmp/f;"
        echo "  uwsgi_temp_path $tmp/ngtmp/u;"
        echo "  scgi_temp_path $tmp/ngtmp/s;"
        echo "  server {"
        echo "    listen 127.0.0.1:$nport;"
        echo "    server_name 127.0.0.1;"
        echo "    root $docroot;"
        cat "$etcconf"
        echo "  }"
        echo "}"
    } > "$tmp/nginx.conf"

    if ! nginx -c "$tmp/nginx.conf" -p "$tmp" 2>"$tmp/ng.err"; then
        bad "nginx would not start on the generated config"
        sed 's/^/        /' "$tmp/ng.err" | head -3
    else
        for _ in $(seq 1 40); do curl -s -o /dev/null -m 1 "http://127.0.0.1:$nport/" && break; sleep 0.1; done
        assert_arm "nginx" "http://127.0.0.1:$nport"
    fi
fi

echo
echo "3. Apache"

httpd_bin=""
for c in httpd apache2; do command -v "$c" >/dev/null 2>&1 && { httpd_bin=$(command -v "$c"); break; }; done
modroot=""
for d in /usr/lib64/httpd/modules /usr/lib/apache2/modules /usr/libexec/apache2; do
    [[ -f $d/mod_rewrite.so ]] && { modroot=$d; break; }
done

if [[ -z $httpd_bin || -z $modroot ]]; then
    echo "  SKIP  no Apache with mod_rewrite installed"
else
    etcconf="$tmp/httpd-inc.conf"; : > "$etcconf"
    echo "    RewriteEngine On" >> "$etcconf"
    for nbdir in $NBDIRS; do
        echo "    RewriteCond %{REQUEST_URI} !^${webrootre}service/${nbdir}/" >> "$etcconf"
    done
    echo "    RewriteCond %{HTTPS} off" >> "$etcconf"
    echo "    RewriteRule ^/?(.*)\$ https://%{HTTP_HOST}/\$1 [R,L]" >> "$etcconf"

    aport=$(( 20000 + RANDOM % 20000 ))
    modline() { [[ -f $modroot/$2 ]] && echo "LoadModule $1 $modroot/$2"; }
    {
        echo "ServerRoot $tmp"
        echo "PidFile $tmp/httpd.pid"
        echo "ErrorLog $tmp/httpd-error.log"
        echo "Listen 127.0.0.1:$aport"
        modline mpm_event_module mod_mpm_event.so
        modline authz_core_module mod_authz_core.so
        modline unixd_module mod_unixd.so
        modline log_config_module mod_log_config.so
        modline rewrite_module mod_rewrite.so
        echo "ServerName 127.0.0.1"
        echo "DocumentRoot $docroot"
        echo "<Directory $docroot>"
        echo "    Require all granted"
        echo "</Directory>"
        echo "<VirtualHost 127.0.0.1:$aport>"
        cat "$etcconf"
        echo "</VirtualHost>"
    } > "$tmp/httpd.conf"

    if ! "$httpd_bin" -f "$tmp/httpd.conf" -k start 2>"$tmp/ap.err"; then
        bad "$httpd_bin would not start on the generated vhost"
        sed 's/^/        /' "$tmp/ap.err" "$tmp/httpd-error.log" 2>/dev/null | grep -v '^ *$' | head -5
    else
        for _ in $(seq 1 40); do curl -s -o /dev/null -m 1 "http://127.0.0.1:$aport/" && break; sleep 0.1; done
        assert_arm "apache" "http://127.0.0.1:$aport"
    fi
fi

echo
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
