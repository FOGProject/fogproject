#!/bin/bash
#
# Upgrade a REAL 1.5 database to this tree, through the real updater.
#
#   tests/upgrade-from-15-ci.test.sh
#
# WHY THIS EXISTS (GH-2000). schemaVersion.vValue is a COUNT of applied
# $this->schema elements, and 1.5 and 1.6 fill the same positions with
# different migrations. So a 1.5 server's count lands on a different 1.6
# step every time 1.5 adds one, and the updater skips every 1.6 step below
# it. 1.5.10.2518 arrived at 288: 1.6's CREATE TABLE fileDeleteQueue (287)
# was skipped and the first upgrade failed, and step 279's widening of
# users.uName was skipped and the column kept 1.5's 40 characters.
#
# Nothing else in CI could see that. tests/upgrade-rehearsal-ci.test.sh
# builds its starting database from THIS tree's steps [0, N), which is a 1.6
# database at N, never a 1.5 one. Only 1.5's own step array produces a 1.5
# database, and 1.5's steps call 1.5's classes, so they have to run inside a
# 1.5 tree.
#
# WHAT IT DOES, both halves the way bin/installfog.sh does them:
#
#   1. Takes packages/web and the installer's functions.sh from FROM_REF
#      (default origin/stable) with `git archive`.
#   2. Renders each tree's config from ITS OWN installer template -- the
#      `echo "<?php ... class Config ..."` block in lib/common/functions.sh --
#      so the config cannot drift from what a real install writes.
#   3. Serves the 1.5 tree with `php -S` and POSTs ?node=schema with the
#      install token. The database lands where a fresh 1.5 install lands.
#   4. Serves a copy of THIS tree the same way and POSTs again: the upgrade.
#   5. Fails on a failed or partial update, on a schema version short of
#      FOG_SCHEMA, or on any column or UNIQUE index whose shape differs from
#      commons/schema-expected.php (bin/upgrade-rehearsal.php shape).
#
# FROM_REF=origin/dev-branch rehearses the NEXT 1.5 release: a 1.5 schema
# change lands there first, so that leg goes red before the change reaches
# stable.
#
# SKIPS, LOUDLY, when there is no database, like the other DB-backed tests.
#
# Environment:
#   UP15_HOST   e.g. 127.0.0.1;port=3306   (required; else SKIP)
#   UP15_USER   default root
#   UP15_PASS   default empty
#   UP15_DB     default up15_ci (dropped and recreated)
#   FROM_REF    default origin/stable
#
# Exit status 0 = pass or skip, 1 = fail.

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"

HOST="${UP15_HOST:-}"
if [[ -z $HOST ]]; then
    echo "upgrade from 1.5: SKIP (UP15_HOST not set; no database)"
    exit 0
fi
DBUSER="${UP15_USER:-root}"
DBPASS="${UP15_PASS:-}"
DB="${UP15_DB:-up15_ci}"
FROM_REF="${FROM_REF:-origin/stable}"

# The database is dropped first, so it must never be a real install's.
case $DB in
    fog|fog-1.5|fog-1.6)
        echo "upgrade from 1.5: FAIL -- refusing live database name '$DB'"
        exit 1
        ;;
esac

fail() { echo "  FAIL  $*"; exit 1; }

WORK="$(mktemp -d)"
cleanup() {
    # Files the container wrote belong to its www-data; they are a+rwX here.
    rm -rf "$WORK" 2>/dev/null || true
}
trap cleanup EXIT

if ! git -C "$REPO" rev-parse --verify -q "$FROM_REF^{commit}" >/dev/null; then
    fail "$FROM_REF is not a commit here. Fetch it first (git fetch origin ${FROM_REF#origin/})."
fi
echo "upgrade from 1.5: $FROM_REF ($(git -C "$REPO" rev-parse --short "$FROM_REF")) -> this tree"

mkdir -p "$WORK/src15" "$WORK/var/log"
git -C "$REPO" archive "$FROM_REF" packages/web lib/common/functions.sh | tar -x -C "$WORK/src15" \
    || fail "could not extract $FROM_REF"
cp -r "$REPO/packages/web" "$WORK/web16" || fail "could not copy this tree"
web15="$WORK/src15/packages/web"
web16="$WORK/web16"

# Run a PHP snippet against the server with the credential in the
# environment, never on a command line.
sql() {
    UP15_HOST="$HOST" UP15_USER="$DBUSER" UP15_PASS="$DBPASS" php -r '
        $pdo = new PDO("mysql:host=" . getenv("UP15_HOST"), getenv("UP15_USER"), getenv("UP15_PASS"));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $r = $pdo->query($argv[1]);
        if ($r && $r->columnCount()) { foreach ($r->fetchAll(PDO::FETCH_NUM) as $row) { echo implode("\t", $row), "\n"; } }
    ' "$1"
}
sql "DROP DATABASE IF EXISTS \`$DB\`" || fail "cannot reach the database server at $HOST"

# Render the installer's own config template from $1 (a functions.sh) into $2.
# The template is one bash double-quoted string, `echo "<?php ... }" > path`.
# It is evaluated with the variables an install would have set, and with the
# redirect replaced by our target.
render_config() {
    local functions=$1 target=$2 end start body
    end=$(grep -n -E '^\}" > "\$\{webdirdest\}/(lib/fog|commons)/config\.class\.php"$' "$functions" | head -1 | cut -d: -f1)
    [[ -n $end ]] || fail "no config template end marker in $functions"
    start=$(head -n "$end" "$functions" | grep -n -E '^    echo "<\?php$' | tail -1 | cut -d: -f1)
    [[ -n $start ]] || fail "no config template start marker in $functions"
    body="$(sed -n "${start},$((end - 1))p" "$functions")"$'\n}"'
    grep -q 'class Config' <<<"$body" || fail "the block found in $functions is not the config template"
    # 1.5's installer names these one way and 1.6's another; both are set.
    local snmysqlhost="$HOST" mysqldbname="$DB" snmysqluser="$DBUSER"
    local DB_host="$HOST" DB_name="$DB" DB_user="$DBUSER"
    local phpescsnmysqlpass="${DBPASS//\\/\\\\}"
    phpescsnmysqlpass="${phpescsnmysqlpass//\'/\\\'}"
    local installToken="$TOKEN" confighostip=127.0.0.1 snapindir=/opt/fog/snapins
    local interface=lo NET_interface=lo username=fogproject SVC_user=fogproject
    local password="" SVC_password="" webdirdest="${target%/*/*}"
    local storageLocation=/images STORAGE_image_share_path=/images
    local storageLocationCapture=/images/dev storageDefaultCidr=127.0.0.0/8
    local webroot=/fog/ WEB_root=/fog/ tftpdirdst=/tftpboot
    mkdir -p "$(dirname "$target")"
    # set +u: a template variable added later must render empty, as it would
    # for an install that never set it, not abort the gate.
    set +u
    eval "$body" > "$target" || fail "could not render $target"
    set -u
    php -l "$target" >/dev/null || fail "rendered $target is not valid PHP"
}

TOKEN="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
render_config "$WORK/src15/lib/common/functions.sh" "$web15/lib/fog/config.class.php"
render_config "$REPO/lib/common/functions.sh" "$web16/commons/config.class.php"
printf "<?php\ndefine('FOG_BASE_DIR', '%s/var');\n" "$WORK" > "$web16/commons/fogpaths.php"

# The web server. Apache with mod_php, as a real install runs it, and not
# `php -S` or php-cgi: 1.5 reads REQUEST_METHOD and QUERY_STRING through
# filter_input(INPUT_SERVER), which both of those leave NULL, so 1.5 never sees
# the POST and redirects it. 7.4 is the supported floor; mysqli and gettext are
# what both trees' extension checks require.
CT="${UP15_CONTAINER:-docker}"
command -v "$CT" >/dev/null || fail "no $CT to run the web server (set UP15_CONTAINER)"
IMAGE=fog-upgrade-from-15:php74
mkdir -p "$WORK/image"
printf 'FROM docker.io/library/php:7.4-apache\nRUN docker-php-ext-install pdo_mysql mysqli gettext >/dev/null\n' \
    > "$WORK/image/Containerfile"
"$CT" build -q -t "$IMAGE" -f "$WORK/image/Containerfile" "$WORK/image" >/dev/null \
    || fail "could not build the web server image"
# Apache runs as www-data, and the 1.6 updater writes its error log into the
# tree and its own log under FOG_BASE_DIR.
chmod -R a+rwX "$WORK"

# Serve $1 at /fog/ and POST the schema deploy, the way the installer does.
# Prints the HTTP status. Host networking, so the database address in the
# config means the same thing inside the container as it does here.
deploy() {
    local root=$1 log=$2 port name code i
    port=$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); echo explode(":", stream_socket_get_name($s, false))[1];')
    name="fog-upgrade-from-15-$$-$port"
    "$CT" run -d --rm --name "$name" --network host \
        -v "$root:/var/www/html/fog:Z" -v "$WORK/var:$WORK/var:Z" "$IMAGE" \
        sh -c "sed -i 's/^Listen 80\$/Listen $port/' /etc/apache2/ports.conf && exec apache2-foreground" \
        >/dev/null || fail "could not start the web server"
    for i in $(seq 1 60); do
        curl -s -o /dev/null "http://127.0.0.1:$port/" && break
        sleep 0.5
    done
    code=$(curl -s -o "$log.body" -w '%{http_code}' -X POST \
        -H "X-Fog-Install-Token: $TOKEN" -d 'schemaupdate=1' \
        "http://127.0.0.1:$port/fog/management/index.php?node=schema")
    "$CT" logs "$name" >"$log" 2>&1
    "$CT" stop -t 2 "$name" >/dev/null 2>&1
    echo "$code"
}

version() { sql "SELECT vValue FROM \`$DB\`.schemaVersion" 2>/dev/null | head -1; }
want15=$(awk -F'[(,)]' "/define\\('FOG_SCHEMA'/{gsub(/ /,\"\",\$3); print \$3; exit}" "$web15/lib/fog/system.class.php")
want16=$(awk -F'[(,)]' "/define\\('FOG_SCHEMA'/{gsub(/ /,\"\",\$3); print \$3; exit}" "$web16/src/Base/System.php")

# --- 1. a fresh 1.5 install ----------------------------------------------
code=$(deploy "$web15" "$WORK/deploy15.log")
got=$(version)
if [[ $code != 2* || -z $got ]]; then
    sed 's/^/        /' "$WORK/deploy15.log" "$WORK/deploy15.log.body" 2>/dev/null | tail -20
    fail "the 1.5 schema deploy answered HTTP $code and left schema '${got:-none}'"
fi
echo "  ok    1.5 install: schema $got (FOG_SCHEMA $want15)"

# --- 2. the upgrade --------------------------------------------------------
code=$(deploy "$web16" "$WORK/deploy16.log")
got=$(version)
# The updater writes this log as the web server's user with mode 0200, so it
# is read back through the container.
errlog() {
    "$CT" run --rm -v "$web16:/fog:Z" "$IMAGE" \
        sh -c 'cat /fog/fog_schema_update_error.log 2>/dev/null' | sed 's/^/        /' | tail -40
}
if [[ $code != 2* ]]; then
    sed 's/^/        /' "$WORK/deploy16.log.body" 2>/dev/null | tail -20
    echo
    errlog
    fail "the upgrade answered HTTP $code and left schema ${got:-none}"
fi
if [[ -e $web16/fog_schema_update_error.log ]]; then
    errlog
    fail "the upgrade answered HTTP $code but wrote fog_schema_update_error.log"
fi
[[ $got == "$want16" ]] || fail "the upgrade landed on schema $got, not FOG_SCHEMA $want16"
echo "  ok    upgrade: HTTP $code, schema $got, no update error"

# --- 3. the structure matches a fresh 1.6 install ---------------------------
cp -r "$REPO/packages/web" "$WORK/webshape"
printf "<?php\ndefine('FOG_BASE_DIR', '%s/var');\n" "$WORK" > "$WORK/webshape/commons/fogpaths.php"
R() { REHEARSAL_DB="$DB" php "$REPO/bin/upgrade-rehearsal.php" "$WORK/webshape" "$@" 2>&1 | grep -v '^PHP Warning'; }
R makeconfig --host="$HOST" --user="$DBUSER" --pass="$DBPASS" >/dev/null || fail "makeconfig did not write a lab config"
shape="$(R shape --db="$DB")"
n=$(sed -n 's/.*differences from the manifest: \([0-9]*\).*/\1/p' <<<"$shape")
[[ -n $n ]] || { echo "$shape" | sed 's/^/        /'; fail "the shape check did not run"; }
if [[ $n -ne 0 ]]; then
    echo "$shape" | sed 's/^/        /'
    fail "$n column(s) or UNIQUE index(es) differ from a fresh install"
fi
echo "  ok    structure matches commons/schema-expected.php"

# --- 4. every AUTO_INCREMENT a fresh install has ------------------------------
# shapeDrift() compares a column's type and nullability, not AUTO_INCREMENT,
# and a MODIFY that leaves it out drops it silently. The next INSERT that omits
# the id then fails with 1364. The manifest's CREATE statements declare it.
lost=$(UP15_HOST="$HOST" UP15_USER="$DBUSER" UP15_PASS="$DBPASS" UP15_DB="$DB" \
    php -r '
    $m = include $argv[1];
    $pdo = new PDO("mysql:host=" . getenv("UP15_HOST"), getenv("UP15_USER"), getenv("UP15_PASS"));
    $live = [];
    $q = $pdo->prepare("SELECT LOWER(TABLE_NAME), LOWER(COLUMN_NAME) FROM information_schema.COLUMNS"
        . " WHERE TABLE_SCHEMA = ? AND EXTRA LIKE \"%auto_increment%\"");
    $q->execute([getenv("UP15_DB")]);
    foreach ($q->fetchAll(PDO::FETCH_NUM) as $r) { $live[$r[0] . "." . $r[1]] = true; }
    $tables = $pdo->prepare("SELECT LOWER(TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
    $tables->execute([getenv("UP15_DB")]);
    $have = array_flip($tables->fetchAll(PDO::FETCH_COLUMN));
    foreach ((array)$m["tables"] as $t => $spec) {
        if (!isset($have[strtolower($t)])) { continue; }
        if (preg_match("/[(,]\\s*`([^`]+)` [^,]*AUTO_INCREMENT/i", (string)($spec["create"] ?? ""), $c)
            && !isset($live[strtolower($t) . "." . strtolower($c[1])])) {
            echo "$t.$c[1]\n";
        }
    }' "$REPO/packages/web/commons/schema-expected.php")
if [[ -n $lost ]]; then
    sed 's/^/        /' <<<"$lost"
    fail "column(s) that are AUTO_INCREMENT on a fresh install are not after the upgrade"
fi
echo "  ok    every AUTO_INCREMENT column a fresh install has"
echo
echo "  upgrade from $FROM_REF: pass"
exit 0
