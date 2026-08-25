#!/bin/bash
#
# Guards the API Documentation page's try-it request snippets.
#
#   tests/apidocs-request-snippets.test.sh
#
# Swagger UI's snippet panel has one failure mode that produces no error
# anywhere: getSnippetGenerators() looks each configured generator up as
# fn.requestSnippetGenerator_<key> and *filters out* every entry whose function
# is missing. Name a language in requestSnippets.generators without registering
# a function for it and the tab simply never appears -- no console warning, no
# broken render, just a panel that looks like the config did nothing. That is
# exactly the trap the AI-written "just set requestSnippetsEnabled" recipes fall
# into, since the bundle ships only curl_bash, curl_cmd and curl_powershell.
#
# The second thing checked here is subtler and cost a round of debugging: a
# generators map passed in config is MERGED into Swagger UI's own rather than
# replacing it, so listing four generators still renders six tabs. `languages`
# is the allowlist that actually decides, which makes it -- not `generators` --
# what keeps the `curl.exe` PowerShell tab off the page.
#
# The rest asserts the generated code itself, in the places where a plausible-
# looking snippet is wrong: Content-Type through -Headers throws on Windows
# PowerShell 5.1, Python needs True/None rather than true/null, and a
# Content-Type copied onto a multipart request overrides the boundary the
# client would have generated.
#
# Needs node. Skips (exit 0) rather than failing when it is absent, the same
# way secureboot-authvars.test.sh skips without efitools.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
WEB="$REPO/packages/web"
SNIPPETS="$WEB/management/js/fog/apidocs/fog.apidocs.snippets.js"
LIST="$WEB/management/js/fog/apidocs/fog.apidocs.list.js"
PAGE="$WEB/lib/fog/page.class.php"

[[ -f $SNIPPETS ]] || { echo "ERROR: $SNIPPETS not found" >&2; exit 1; }

command -v node >/dev/null 2>&1 || {
    echo "SKIP: node is not installed"
    exit 0
}

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }

# --- the two wiring checks that are not about the JS itself ----------------

if grep -q "fog.apidocs.snippets.js" "$PAGE"; then
    ok "page.class.php serves fog.apidocs.snippets.js on the apidocs node"
else
    bad "page.class.php does not serve fog.apidocs.snippets.js; the tabs would silently stay as shipped"
fi

if grep -q "FogRequestSnippets" "$LIST"; then
    ok "fog.apidocs.list.js passes the FOG generators to SwaggerUIBundle"
else
    bad "fog.apidocs.list.js never references FogRequestSnippets"
fi

# --- the generators, exercised directly ------------------------------------

node - "$SNIPPETS" <<'NODEEOF'
'use strict';

const snippets = require(process.argv[2]);

// What the bundle already registers. Anything in `languages` that is neither
// one of these nor one of ours resolves to no function and drops out.
const BUILT_IN = ['curl_bash', 'curl_cmd', 'curl_powershell'];

let failed = 0;
function ok(name) { console.log('PASS: ' + name); }
function bad(name, detail) {
    console.log('FAIL: ' + name + (detail ? '\n      ' + detail : ''));
    failed += 1;
}
function check(name, condition, detail) {
    if (condition) { ok(name); } else { bad(name, detail); }
}

// Stand-in for the Immutable Map the bundle hands a generator. Only get() and
// forEach() are used, which is the whole reason the generators duck-type
// rather than reaching for Immutable.
function map(obj) {
    return {
        get: (key) => obj[key],
        forEach: (cb) => Object.keys(obj).forEach((key) => cb(obj[key], key)),
        size: Object.keys(obj).length
    };
}

const config = snippets.config();
const fns = snippets.plugin().fn;
const known = BUILT_IN.concat(Object.keys(snippets.generators));

// 1. Every generator we declare has a function behind it.
const orphans = Object.keys(config.generators).filter(
    (key) => BUILT_IN.indexOf(key) < 0
        && typeof fns['requestSnippetGenerator_' + key] !== 'function'
);
check(
    'every declared generator has a requestSnippetGenerator_ function',
    orphans.length === 0,
    'no function for: ' + orphans.join(', ') + ' -- these tabs would not render'
);

// 2. Every allowlisted language resolves to something that exists.
check(
    'requestSnippets.languages is set',
    Array.isArray(config.languages) && config.languages.length > 0,
    'without it the merged generator map wins and Swagger UI renders its own tabs too'
);
const unknown = (config.languages || []).filter((key) => known.indexOf(key) < 0);
check(
    'every allowlisted language is a real generator',
    unknown.length === 0,
    'unknown: ' + unknown.join(', ')
);

// 3. The whole point: PowerShell means Invoke-RestMethod, not curl.exe.
check(
    'curl_powershell is not offered',
    (config.languages || []).indexOf('curl_powershell') < 0,
    'the curl.exe tab is back; it is what the PowerShell generator replaces'
);

const jsonRequest = map({
    url: 'https://fog.example.org/fog/host/42/edit',
    method: 'PUT',
    headers: map({
        'fog-api-token': 'QUJD',
        'Content-Type': 'application/json',
        accept: 'application/json'
    }),
    body: JSON.stringify({
        name: 'lab-pc-01',
        enforce: true,
        imageID: 3,
        ip: null
    }, null, 2)
});

const ps = snippets.generators.powershell.fn(jsonRequest);
const py = snippets.generators.python.fn(jsonRequest);
const js = snippets.generators.javascript.fn(jsonRequest);

check(
    'PowerShell calls Invoke-RestMethod',
    /Invoke-RestMethod/.test(ps) && !/curl/i.test(ps),
    ps
);

// Windows PowerShell 5.1 throws "The 'Content-Type' header must be modified
// using the appropriate property or method" when it arrives via -Headers.
check(
    'PowerShell passes Content-Type as -ContentType, not in $headers',
    /-ContentType 'application\/json'/.test(ps)
        && !/'Content-Type'\s*=/.test(ps),
    ps
);

// @' ... '@ only terminates when '@ starts the line.
const terminator = ps.split('\n').filter((line) => line.indexOf("'@") >= 0);
check(
    'PowerShell here-string terminator sits at column 0',
    terminator.length > 0 && terminator.every((line) => line.indexOf("'@") === 0),
    ps
);

check(
    'Python uses requests and the verb-named helper',
    /^import requests$/m.test(py) && /requests\.put\(/.test(py),
    py
);

// JSON's literals are not Python's. A snippet carrying `true` or `null`
// raises NameError on the first line the reader runs.
check(
    'Python emits Python literals, not JSON ones',
    /"enforce": True/.test(py) && /"ip": None/.test(py)
        && !/\btrue\b/.test(py) && !/\bnull\b/.test(py),
    py
);

check(
    'JavaScript uses fetch with a JSON.stringify body',
    /await fetch\(url, \{/.test(js) && /JSON\.stringify\(\{/.test(js),
    js
);

// A multipart Content-Type has a boundary the client generates. Copying the
// one Swagger UI sent -- which has none -- overrides that, and the server gets
// a body it cannot parse.
const formRequest = map({
    url: 'https://fog.example.org/fog/image/1/upload',
    method: 'POST',
    headers: map({ 'Content-Type': 'multipart/form-data', 'fog-api-token': 'QUJD' }),
    body: map({
        name: 'thing',
        'file_**[]': { name: 'win11.img', size: 12, type: 'application/octet-stream' }
    })
});

const pyForm = snippets.generators.python.fn(formRequest);
const jsForm = snippets.generators.javascript.fn(formRequest);
const psForm = snippets.generators.powershell.fn(formRequest);

check(
    'Python drops Content-Type on a multipart request',
    !/multipart\/form-data/.test(pyForm) && /files=files/.test(pyForm),
    pyForm
);
check(
    'JavaScript drops Content-Type on a multipart request',
    !/multipart\/form-data/.test(jsForm) && /new FormData\(\)/.test(jsForm),
    jsForm
);

// Swagger UI suffixes repeated form keys; the wire name is what precedes it.
check(
    'the _**[] form-key marker is stripped',
    !/_\*\*\[\]/.test(pyForm + jsForm + psForm),
    psForm
);

// -Form uploads a FileInfo as a file and a string as a text field, so a file
// field listed in the literal as its name would be sent as the name.
check(
    'PowerShell attaches files with Get-Item rather than by name',
    /\$form\['file'\] = Get-Item 'win11\.img'/.test(psForm)
        && !/'file' = 'win11\.img'/.test(psForm),
    psForm
);

// A GET with no body must not grow a -Body or a payload out of nowhere.
const bare = map({
    url: 'https://fog.example.org/fog/host',
    method: 'GET',
    headers: map({ 'fog-api-token': 'QUJD' })
});
check(
    'a body-less request produces no body',
    !/-Body/.test(snippets.generators.powershell.fn(bare))
        && !/payload/.test(snippets.generators.python.fn(bare))
        && !/body:/.test(snippets.generators.javascript.fn(bare)),
    snippets.generators.powershell.fn(bare)
);

process.exit(failed > 0 ? 1 : 0);
NODEEOF

if [[ $? -eq 0 ]]; then
    PASS=$((PASS + 1))
else
    FAIL=$((FAIL + 1))
fi

echo
if [[ $FAIL -gt 0 ]]; then
    echo "$FAIL failed"
    exit 1
fi
echo "all checks passed"
exit 0
