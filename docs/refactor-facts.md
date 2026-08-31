# FOG Modernization: Verified Facts Ledger

**This file outranks any plan.** If a plan contradicts something here, the plan
is wrong and must be corrected before implementation.

Append-only. Never edit or delete an entry. If something here turns out to be
wrong, add a new entry that supersedes it and mark the old one `SUPERSEDED BY
F-nn`. The history of what we believed and when is part of the value.

Every entry carries the command that proved it. If you cannot write the command,
it does not belong in this file. Put it in the plan's `INFERRED` or `UNKNOWN`
section instead.

Baseline unless stated otherwise: `working-1.6`.

---

## Corrections to the approved Phase 0 plan

These were found by verification after approval. Fix the plan document before
writing any code.

### C-01 — Wrong line number on the second-place claim

The plan cites `lib/common/functions.sh:150` for
`cp -Rf $webdirsrc/* $webdirdest/`, in both the vendoring recommendation and the
`fetch-plugins.sh` interaction section. **The actual line is 6592.** Line 150 is
inside `backupPreservedCustomizations()`, in a comment about `errorStat()`
severity.

The underlying claim is correct. The citation is not, and it was tagged
`VERIFIED`.

**Action:** correct both citations. Then re-check every other `functions.sh:`
line number in the plan the same way. If more than one is off, treat all line
numbers in that document as approximate until individually confirmed.

```
grep -n 'cp -Rf \$webdirsrc' lib/common/functions.sh    # 6592
```

### C-02 — UNKNOWN #4 is closed, and the answer is favorable

Ordering at `functions.sh:6579-6593` is: restore `fog_web_*.BACKUP` first (inside
the `copybackold` block), then `cp -Rf $webdirsrc/* $webdirdest/`. **New files
win.** A stale committed `vendor/` is restored and then overwritten, not left in
place.

The committed-`vendor/` recommendation survives. Remove UNKNOWN #4 and cite this.

```
sed -n '6579,6593p' lib/common/functions.sh
```

### C-03 — The installer force-lowercases class filenames on every upgrade

Not flagged by any plan or brief so far. `functions.sh:6583-6588`:

```bash
dots "Ensuring all classes are lowercased"
for i in $(find $webdirdest -type f -name "*[A-Z]*\.class\.php" -o -name "*[A-Z]*\.event\.php" -o -name "*[A-Z]*\.hook\.php"); do
    mv "$i" "$(echo $i | tr A-Z a-z)"
done
```

Consequences:

- The lowercase filename convention is **enforced by the installer**, not merely
  observed. Any Phase 3 file named `Host.class.php` gets silently renamed on a
  production server at upgrade time.
- PSR-4 files under `src/` are safe: they are `Host.php`, which does not match
  `*.class.php`. Committed `vendor/` is safe for the same reason.
- Pre-existing bug, tangential but real: `tr A-Z a-z` is applied to the whole
  path `$i`, not the basename. A `webdirdest` containing an uppercase letter
  produces an `mv` to a directory that does not exist.

**Action:** add to Phase 3's constraint list. Any namespaced file must use a
bare `.php` suffix, never `.class.php`, or the installer will rename it.

### C-04 — F3's conclusion is overstated; Composer deletes the obstacles

The plan uses `mysqldump.class.php`'s 13 types in one file to conclude PSR-4 can
never serve the tree. But that file is `ifsnop/mysqldump-php`, on Packagist,
GPL-3.0, properly namespaced and one-class-per-file upstream. Line 15 is
`//namespace Ifsnop\Mysqldump;` — commented out so FOG's filename autoloader
could find it. `lib/router/altorouter.class.php` is the same story
(`//namespace AltoRouter;`, `@author Danny van Kooten`, MIT).

So PR 0.3 is not only groundwork for a future JWT library. It is the mechanism
that **retires existing hand-vendored code** which currently has no version, no
lockfile, no license metadata and no upgrade path. That is a materially stronger
argument for Composer than the one in the plan, and it belongs in the ADR.

**Action:** add this to 0.3's rationale. Do **not** do the swaps inside 0.3.
They are separate follow-up PRs, one per library, each of which must first diff
the vendored copy against the upstream tag to find local modifications beyond
the commented namespace.

```
grep -n 'namespace' packages/web/lib/db/mysqldump.class.php packages/web/lib/router/altorouter.class.php
```

### C-05 — Dependabot ships with 0.3, not after

Committed `vendor/` means a CVE in a dependency requires a FOG release. That is
acceptable only if the CVE is discovered automatically. Add Dependabot (or
equivalent) on `composer.lock` in the same PR that introduces `composer.json`,
not as a follow-up.

---

## Verified facts

### F-01 — The release tarball is byte-for-byte the tracked tree
`.gitattributes` is three lines, no `export-ignore`. Anything committed ships.
```
cat .gitattributes
```

### F-02 — `packages/web/lib/plugins/` is not tracked in this repo
Zero tracked files. Gitignored per ADR 0009; contents come from
`FOGProject/fog-plugins` via `bin/fetch-plugins.sh`. Any tree-wide change is two
PRs in two repositories.
```
git ls-files packages/web/lib/plugins | wc -l    # 0
```

### F-03 — Zero namespace declarations in tracked PHP
**SUPERSEDED BY F-06.** The claim holds; the command under-covers by nine files.
This is what makes the backslash-prefix pass a genuine no-op.
```
git ls-files '*.php' | xargs grep -l '^namespace ' | wc -l    # 0
```

### F-04 — `mysqldump.class.php` declares 13 types in one file
Twelve resolve only as a side effect of loading `Mysqldump`. Relevant to PSR-4
only until C-04's swap happens.
```
grep -c '^class \|^abstract class \|^interface ' packages/web/lib/db/mysqldump.class.php    # 13
```

### F-05 — The web tree is copied to the docroot at `functions.sh:6592`
Plain `cp -Rf $webdirsrc/* $webdirdest/`. No exclusions. Supersedes the plan's
`:150` citation.
```
sed -n '6592p' lib/common/functions.sh
```

### F-06 — Zero namespace declarations across all 311 tracked PHP files
Supersedes F-03, whose claim was right but whose command was not. `git ls-files
'*.php'` misses the nine daemon entry points under `packages/service/`, which are
extensionless and open with `#!/usr/bin/php -q` before `<?php`. Those nine files
are PHP, they load into the same process as everything else, and a namespace
declaration in one of them would break the backslash-prefix no-op argument just as
badly. Select on content, not on filename.

Note `^namespace` and not `namespace`: `mysqldump.class.php:16` and
`altorouter.class.php:15` both carry a commented-out `//namespace` line (see
C-04), which is not a declaration and must not be counted as one.
```
git ls-files | while IFS= read -r f; do
    head -2 "$f" 2>/dev/null | grep -q '<?php' && printf '%s\n' "$f"
done > /tmp/fogphp.txt
wc -l < /tmp/fogphp.txt                                  # 311
grep -c 'packages/service/FOG' /tmp/fogphp.txt           # 9  (the daemons are in)
xargs grep -l '^namespace ' < /tmp/fogphp.txt | wc -l    # 0
```

### F-07 — The installer's lowercase pass yields a duplicate pair, not a rename
Amends C-03, which says an uppercase-named class file "gets silently renamed".
It does not — it gets **duplicated**, which is worse.

Order inside `configureHttpd()` is: restore `fog_web_*.BACKUP` (`:6582`) →
lowercase-rename loop (`:6583-6588`) → `cp -Rf $webdirsrc/*` (`:6592`). The loop
only ever sees the *restored* tree. So a source file named `Host.class.php` is
untouched on the upgrade that installs it; on the next upgrade it arrives from the
backup, is renamed to `host.class.php`, and then `cp -Rf` lays the CamelCase
original back down beside it. The server ends up serving both.

Two files with one autoloader map key is the readdir-order collision documented at
`packages/web/commons/init.php:242-250` — identical code resolving to a different
file on different installs. That is the failure mode, not a rename.
```
sed -n '6579,6592p' lib/common/functions.sh
```

### F-08 — The lowercase pass is gated behind the opt-in `-o` / `--oldcopy` flag
Also amends C-03's "on every upgrade". The whole block sits inside
`if [[ $copybackold -gt 0 ]]` (`functions.sh:6579`). `copybackold` defaults to 0
(`functions.sh:4144`) and is only ever raised by the `-o` / `--oldcopy` installer
flag (`bin/installfog.sh:281-283`, applied at `:732`). So F-07 fires only on an
upgrade where the admin asked to copy the old web folder back.

This narrows the blast radius; it does not remove it. `lib/fog/FOGPagePost.class
.php` and `lib/fog/FOGPageRender.class.php` match the pattern today and are
therefore already exposed on any `--oldcopy` upgrade.
`lib/pages/UserGroupManagement.page.php` is not — `.page.php` is absent from the
three `-name` patterns, which is luck rather than design.
```
sed -n '6579p;4144p' lib/common/functions.sh
grep -n 'scopybackold=1' bin/installfog.sh                      # 282
git ls-files 'packages/web/**' | grep -E '[A-Z].*\.(class|event|hook)\.php$'
```

### F-09 — Composer records the repo's own commit SHA unless the root package declares a `version`
Only shows up once `vendor/` is committed, which is why it was not in the
plan. `vendor/composer/installed.php` carries a `reference` field for the root
package, and with no `version` in `composer.json` Composer fills it from git —
so every `composer install` rewrites a tracked file to whatever `HEAD` is. A
permanent false diff and a guaranteed merge conflict on a shared branch.

Declaring `"version"` makes the field `null`. It is pinned to the series
(`1.6.0`), not the build (`1.6.0-beta.NNNN`), so it is not something the
FOG_VERSION hooks should stamp and does not go stale every commit.
```
cd packages/web && composer install && cd - && git status --short packages/web/vendor
# prints nothing; before the version pin it printed " M .../installed.php"
```

### F-10 — `psrfix()` rewrites third-party source unless `vendor/` is excluded
The pre-commit hook runs `php-cs-fixer --rules=@PSR2` over every staged
`packages/web/*.php`. With `vendor/` committed and no exclusion that includes
Composer's own files — nine of them on an empty install, more with every
dependency. That makes a dependency unupdatable: `composer install` rewrites
the files, the fixer rewrites them back, and the diff never settles.

Ordering consequence, and the reason the implementation deviates from the
approved plan: the three vendor exclusions must land in a commit **before**
the one that adds `vendor/`, not after it. Hooks run from the working tree, so
an exclusion added later is added too late for the commit that needed it.
```
git add packages/web/vendor
git diff --cached --name-only -- 'packages/web/*.php'                                  # 9 files
git diff --cached --name-only -- 'packages/web/*.php' ':(exclude)packages/web/vendor/*' # none
git restore --staged packages/web/vendor
```

### F-11 — No core hook or event is ever loaded
`load()` starts a non-plugin file only when its source text matches
`$active\s?=\s?true;`. All eleven files under `packages/web/lib/hooks` and
`packages/web/lib/events` declare `public $active = false;` and none matches.
So every listener that runs on a stock server comes from a plugin; the eleven
core files are examples an admin opts into, not shipped behavior. Anything
that reasons about "core hooks" as live code is reasoning about dead code.
```
cd packages/web/lib
ls hooks/*.hook.php events/*.event.php | wc -l                       # 11
grep -lP '\$active\s?=\s?true;' hooks/*.hook.php events/*.event.php | wc -l   # 0
grep -lP 'public \$active = false;' hooks/*.hook.php events/*.event.php | wc -l  # 11
```

### F-12 — Every bundled plugin hook and event sets `$active = true`, and the regex agrees
87 files across the 16 plugins in `FOGProject/fog-plugins`; the source-text
regex and the real property value disagree in none of them. This is the
"anything currently depends on the defect" search the hook/event brief asked
for, on the half of the population we can see: on shipped code, replacing the
regex with a property read is a no-op. It says nothing about plugins we have
never read (see F-16).
```
cd /home/telliott/fog-plugins
find . \( -name '*.hook.php' -o -name '*.event.php' \) | wc -l                       # 87
find . \( -name '*.hook.php' -o -name '*.event.php' \) -exec grep -LP '\$active\s?=\s?true;' {} + | wc -l   # 0
```

### F-13 — A listener that is an object but not an array makes `register()` fatal, not logged
`eventmanager.class.php:114` interpolates `$listener[0]` inside the `catch`
that is supposed to swallow the failure. When the listener is a Closure or any
non-`ArrayAccess` object, that line raises `Error: Cannot use object of type X
as array` — an `\Error`, which `catch (\Exception)` does not catch. Registration
happens in a hook constructor during `LoadGlobals`, so the Error escapes
`base.inc.php` and the whole application answers **HTTP 500 with a zero-byte
body**, on every entry point, until the file is deleted from disk. This is the
same symptom as the autoload collision at `commons/init.php:242-250`, and it
is indistinguishable from it in a browser.

Reproduced in `/home/telliott/hookevent-lab` (see `_metrics/START.md`) by
dropping a hook with `public $active = true;` and a closure `register()` into
`lib/hooks`:
```
[PHP Fatal error: Uncaught Error: Cannot use object of type Closure as array
 in .../lib/fog/eventmanager.class.php:114
 #4 .../lib/fog/eventmanager.class.php(279): FOG\FOGBase::startClassFromFiles()]
curl -s -o /dev/null -w '%{http_code} %{size_download}\n' .../management/index.php   # 500 0
```

### F-14 — The plugin authoring guide documents the F-13 shape three times
`docs/plugin-development.md` §7 is the reference for the three Phase 2
authentication seams (ADR 0014), and all three examples register a closure.
Nothing in `fog-plugins` follows them — the real OIDC plugin uses
`Hook::registerInstalled()`, i.e. `[$this, 'method']` — so the guide describes
a shape that has never worked, in the section a third-party author is most
likely to copy, and copying it takes the server down rather than failing
quietly.
```
grep -nP "register\('[A-Z_]+',\s*function" docs/plugin-development.md   # 588, 622, 636
grep -n 'API_PLUGIN_ROUTES' /home/telliott/fog-plugins/oidc/hooks/addoidcroutes.hook.php  # 57, inside registerInstalled([...])
```

### F-15 — The activation regex disagrees with the property in both directions
`\s?` is zero-or-one and the scan does not distinguish code from comments, so
whether a hook runs is decided by its whitespace. Characterized in the lab by
varying one line and watching for the F-13 fatal (500 = the file was loaded,
200 = it never was):

| source line | real property | loaded today |
|---|---|---|
| `public $active = true;` | true | yes |
| `public $active  = true;` (two spaces) | true | **no** |
| `public $active =  true;` (two after `=`) | true | **no** |
| `public $active=true;` | true | yes |
| `public $active = TRUE;` | true | **no** |
| `public $active = false; // set $active = true; to enable` | **false** | **yes** |

The last row is the one that matters: the obvious way to document the toggle in
a comment turns the hook on. No shipped file is in either failing state (F-11,
F-12).
The `loaded today` column was confirmed end to end in the lab by varying that
one line in a hook whose `register()` triggers F-13, so a 500 means the file
was loaded and a 200 means it never was. The regex verdict on its own:
```
php /home/telliott/hookevent-lab/_metrics/active-regex-verdict.php
# public $active = true;                    regex=LOADED  property=true
# public $active  = true;                   regex=skipped property=true
# public $active =  true;                   regex=skipped property=true
# public $active=true;                      regex=LOADED  property=true
# public $active = TRUE;                    regex=skipped property=true
# public $active = false; // ... = true; .. regex=LOADED  property=false
```

### F-16 — The plugin path force-activation is load-bearing, not redundant
`hookmanager.class.php:102-104` sets `$function[0]->active = true` for any
listener whose file path contains `plugins`. `FOGBase::fileitems()` does filter
plugin files to installed-and-enabled plugins before this point, so the
force-activation is not what gates *whether a plugin's code runs* — but it is
the only thing that reads the hook's own `active` property out of the decision.
A plugin hook declaring `public $active = false;` still fires. Verified by
dropping such a hook into an installed plugin's `hooks/` directory in the lab
and confirming its callback ran on `MAIN_MENU_DATA`.

Consequence for any proposal that removes it: every bundled plugin is safe
(F-12), and every third-party plugin hook that does not set `$active = true`
goes silently inactive. We cannot enumerate that population.

### F-17 — `HookManager::notify()` is a silent no-op that reports success
`HookManager` inherits `notify()`, which iterates listeners as objects
(`$element->active`), while `HookManager` stores them as `[$object, 'method']`
arrays. Under PHP 8 that is a warning, not an error: the property read yields
null, the closure returns, nothing is invoked, and `notify()` returns **true**.
No core call site and no bundled plugin reaches it, so this is a trap for
third-party code only.
```
# lab: $HookManager->register('E', [$hook,'menuData']); $HookManager->notify('E', []);
# => returns true, invokes nothing, logs:
# PHP Warning: Attempt to read property "active" on array in .../eventmanager.class.php on line 183
```

### F-18 — `Hook extends Event` defeats the only type check separating the two
**Closed by #1203**: `Hook` extends `FOGBase` and both use the `Listener`
trait, so `instanceof Event` is false for a hook. The refusal below stays as
the specific diagnostic, and runs *before* the `instanceof Event` arm so a
plugin author is told what they actually did. Recorded as found:

`EventManager::register()` guards with `$listener instanceof Event`, which a
`Hook` satisfies. A hook object can therefore be registered as an event
listener, and `notify()` then calls `Event::onEvent()` — whose default body
`printf`s `"<EVENT> Registered"` straight into the response. On a client
protocol endpoint that is arbitrary text prepended to a `##@GO` reply.
```
# lab: $EventManager->register('LAB_MIX', $hookObject);
# => accepted; notify('LAB_MIX') returns true and prints 'LAB_MIX Registered'
```

### F-19 — Two of the six shipped event names are never fired, and one fired name has no listeners
`notify()` is called from five places in core with five names. The bundled
notification plugins (`slack`, `ntfy`, `pushbullet`) register six. They do not
match: `HOST_IMAGE_FAIL` and `HOST_IMAGEUP_COMPLETE` have listeners in all three
plugins and no `notify()` anywhere in the tree, so "image failed" and "upload
complete" notifications have never fired; `HOST_CHECKIN` is notified on every
task checkin and nothing has ever listened to it.
```
cd packages
grep -rn -e '->notify(' web/lib --include=*.php | wc -l        # 6 call sites, 5 distinct names
grep -rh -A1 '>register(' web/lib/plugins/*/events/*.event.php | grep -o "'[A-Z_a-z]*'" | sort -u
grep -rn 'HOST_IMAGE_FAIL\|HOST_IMAGEUP_COMPLETE' web/lib --include=*.php | grep -v plugins   # nothing
```

### F-20 — The whole subsystem costs about 1–2 ms of a page render, and no measurable memory
Measured in the lab across twelve authenticated management pages, on a server
with six plugins installed (`ldap location ntfy oidc ou windowskey`).

| per request | value |
|---|---|
| hook objects constructed | 42 (all from plugins; F-11 means zero from core) |
| event objects constructed | 5 |
| construction wall time | 0.8–1.6 ms |
| source-text regex scan of the 11 core files | 0.40–0.49 ms |
| `processEvent()` calls | 75–246, of which 37–158 have no listener and early-return |
| listener callbacks invoked | 24–331 |
| `ReflectionClass` constructions (one per listener per fire) | 24–331, costing **14–168 µs in total** |
| peak memory | 2–4 MiB, flat across every page |
| page wall time for comparison | 11–543 ms |

So goal 3 of the hook/event brief is answered by dropping it: the reflection
the force-activation needs is a rounding error, and the load path is under 1%
of a request. Nothing here justifies trading clarity for speed.
```
# /home/telliott/hookevent-lab/_metrics/{START.md,drive.sh,metrics.jsonl}
```

### F-21 — `notify()` repeats the GH-707 mistake `processEvent()` was fixed for
`processEvent()` caches the `hookevent` name list in a static (`$knownEvents`)
precisely because asking the database on every fire cost 2000 round trips on a
1000-host tasking. `notify()` still does an uncached `NotifyEventManager
->exists()` SELECT on **every** call. Measured at 0.155 ms of the 0.178 ms a
listener-less `notify()` costs — 87% of the call is bookkeeping for a debugging
aid. It matters less than GH-707 did only because notify() fires per task, not
per row.
```
# lab: 20 x notify('HOST_CHECKIN') => 3.56 ms total, 3.10 ms of it the exists() SELECT
```

### F-22 — `load()` tells the two managers apart by an ordering accident
`eventmanager.class.php:218-225` runs `if ($this instanceof EventManager)` then
`if ($this instanceof HookManager)`. `HookManager extends EventManager`, so a
HookManager satisfies both and reaches `.hook.php`/`hooks` only because the
second assignment overwrites the first. Swapping the two blocks makes every
HookManager load `.event.php` files, and neither PHP nor any test would say so.
```
sed -n '218,225p' packages/web/lib/fog/eventmanager.class.php
```

### F-23 — ADR 0013's promise is about the alias, not about every plugin-observable behavior
Its §2 says the reverse `class_alias` "is supported for all of 1.6" and cannot
be removed before 1.7. Nothing in it freezes the hook contract, the `$active`
semantics or `register()`'s accepted listener shapes. Combined with 1.6.0 being
unreleased — `working-1.6` stamps `1.6.0-beta.NNNN` — there is no shipped 1.6
plugin ABI, so "we cannot change that inside 1.6" is not an argument any plan
in this repository may lean on. It was leaned on four times in the first draft
of `docs/hook-event-plan.md` and was wrong every time.
```
grep -n 'supported for all of 1.6' docs/adr/0013-flat-fog-namespace-and-the-reverse-alias-abi.md
grep -n "FOG_VERSION" packages/web/src/Base/System.php   # 1.6.0-beta.NNNN, channel Beta
```

### F-24 — Why the plugin force-activation exists, from the maintainer
Recorded because the code carries no comment saying it and the reason is the
whole argument for deleting it. When `capone` was the only plugin, plugins had
no hooks of their own; hooks were adopted into the plugin system by copying
core's example hooks, and every core example declares `$active = false`. Force-
truthing any hook on a plugin path made a copied example work without the author
noticing the flag. Plugins now ship their own hooks and all 87 set
`$active = true` (F-12), so the net catches nothing.
```
# No command -- this is maintainer knowledge, not a property of the tree.
# The half that IS checkable is F-12, which is what makes the net redundant.
```

### F-25 — `ReflectionFunction::getClosureThis()` recovers the hook a closure was written inside
This is what lets `register()` accept a Closure without inventing new activation
semantics: a closure written in a hook constructor is bound to that hook, so
`$active` governs it exactly as it governs `[$this, 'method']`. A `static
function` returns null and is therefore always active.
```
php -r '
class H { public $active = false;
  public function make() { return function ($a) { return get_class($this); }; } }
$h = new H(); $c = $h->make();
var_dump((new ReflectionFunction($c))->getClosureThis() === $h);          # true
var_dump((new ReflectionFunction(static function () {}))->getClosureThis()); # NULL
'
```

### F-26 — Nothing outside the two manager classes reads their `$data`
`EventManager::$data` and `HookManager::$data` are `public`, so the stored
listener shape looks like ABI. It is not: no other file in `packages/web`,
`packages/service` or `fog-plugins` touches it. That is what makes storing a
Closure alongside the existing `[object, 'method']` arrays a non-event.
```
grep -rn 'HookManager->data\|EventManager->data' packages /home/telliott/fog-plugins   # nothing
```

### F-27 — Both catches render `$event` with `%s`, and a non-string `$event` is one of the things they exist to report
`register()` and `notify()` each throw `_('Event must be a string')` and then
interpolate that same `$event` into the log line. `%s` on an object with no
`__toString` is an `\Error`, which `catch (\Exception)` does not catch, so
the handler goes fatal on exactly the input it was written for. This is the
`_describeListener()` defect (F-13) one argument along; fixing the listener
half left this half live on both branches. An array name only warns -- an
object is the fatal case.
```
php -r 'try { echo sprintf("%s", new stdClass); }
  catch (\Exception $e) { echo "caught\n"; }
  catch (\Error $e) { echo "ESCAPED: ".$e->getMessage()."\n"; }'
# ESCAPED: Object of class stdClass could not be converted to string
```

### F-28 — Every access-control mechanism in the API read path can be deleted with the test suite green
Eight single-edit mutations to `route.class.php`, full suite after each, file
restored from a copy between runs. All eight: `72 passed, 0 failed`. Deleting
`_applySiteScope()` from `listem()` -- the one line that stops one site's users
seeing another site's hosts -- is among them, as is renaming `_lang`, which
disables `stripSensitivePayload()` for every list payload. The suite pins
symbols, not behavior: `sensitive-fields-unfilterable.test.php` greps for the
strings `_assertNoSensitiveFilter(`, `HTTP_BAD_REQUEST` and `'nosearch'`, and an
inserted `return;` leaves all three in place; `site-scope-lists.test.php` tests
`Authorization::scopedObjectIDs()` and never names `Route`. Consequence: no
decomposition of this file may begin before a behavioral net exists.
```
cp packages/web/lib/router/route.class.php /tmp/route.bak
sed -i 's|self::_applySiteScope($classname);|// removed|' packages/web/lib/router/route.class.php
sh tests/run-all.sh | tail -1        # 72 passed, 0 failed
cp /tmp/route.bak packages/web/lib/router/route.class.php
```

### F-29 — `Route::ids()` will SELECT any column named in the URL, secrets included
`ids()` validates `$getField` against `$classVars['databaseFields']` and never
against `unfilterableFields()`, and its payload carries no `_lang` stamp, so
`stripSensitivePayload()` resolves an empty classname and returns it untouched.
`GET /fog/host/ids/id=1/sec_tok` therefore hands a host's plaintext fog-client
token to any caller holding `host.view`; likewise `ADPass`, `productKey`,
`user.password`, `user.token`. The single-segment form is already closed --
`/ids//sec_tok` parses as a *filter* and is refused 400. Only the two-segment
form is open. Route match proved offline; the HTTP round trip was not run.
```
php /home/telliott/scripts/background_scripts/probe_ids_getfield_route.php
# /fog/host/ids/id=1/sec_tok => ids params={"class":"host","whereItems":"id=1","getField":"sec_tok"}
php -r '...' # Route::stripSensitivePayload(['data'=>[['id'=>1,'sec_tok'=>'S']]])
#           => {"data":[{"id":1,"sec_tok":"S"}]}   -- unstamped payload, no strip
```

### F-30 — Site scope is applied to the PAGE, after the SQL LIMIT, so a scoped user's grid is empty
`_applySiteScope()` runs after `FOGManagerController::complex()` has paged the
result set, filters the rows it was handed, and rewrites `recordsTotal` and
`recordsFiltered` to the size of the filtered page -- which `paginate()` then
uses to build `nextUrl`. On the lab server a user scoped to `site1` (1 of 86
hosts, at offset 75) gets `0 rows, recordsTotal 0, nextUrl null` on page 1, so
DataTables renders "no matching records" and an API client following `nextUrl`
stops. Fail-closed, so it hides rather than leaks -- but
`SiteScope::allInScopeIDs()`'s own docblock (`sitescope.class.php:419-423`)
tells callers not to do this, and the one core caller does.
```
php /home/telliott/scripts/background_scripts/probe_sitescope_pagination.php < /dev/null
# start=0  rows=0 recordsTotal=0 nextUrl=null
# start=75 rows=1 recordsTotal=1 nextUrl=null
```

### F-31 — Four routes answer the same question as `list` with the same permission and no site scope
`names()`, `ids()`, `count()` and `unisearch()` never call `_applySiteScope()`.
`API_ROUTE_ACTIONS` maps `list`, `names`, `ids`, `count` (and `indiv`, `search`,
`active`) all to `<entity>.view`, so permission cannot tell them apart.
`count()` reaches `listem()` but sets `$countOnly`, and `complex()` then returns
`'data' => []` with `recordsFiltered` computed by SQL over the unscoped set --
`_applySiteScope` early-returns on the empty `data` and the global count is
emitted. Enumerating every host id and name on the server is one request.
```
grep -n '_applySiteScope(' packages/web/lib/router/route.class.php   # 2545 (listem), 2962 (search), 5656 (def)
sed -n '118,132p' packages/web/lib/fog/authorization.class.php       # list/names/ids/count => view
sed -n '775p' packages/web/lib/fog/fogmanagercontroller.class.php    # 'data' => $countOnly ? [] : ...
```

### F-32 — `listem()`'s plain path is flat at four queries; its `?expand` path is ~20 per row
Measured in a copy of the live tree with a statement-counting shim in
`PDODB::query()` and `FOGManagerController::sqlexec()`. GH-707's
`rel()`/`primeRel()` fix is holding for the plain path and was never applied to
the expand branch, which was written afterward. `EXPAND_MAX_ITEMS` (2500)
clamps the page size and its comment says it bounds memory; memory is ~25 KiB a
row, so the clamp permits ~50,000 statements. Queries, not memory, are the
binding constraint.

| rows | plain q | expand q | expand wall |
|---|---|---|---|
| 1 | 4 | 30 | 10 ms |
| 10 | 4 | 201 | 50 ms |
| 25 | 4 | 485 | 107 ms |
| 50 | 4 | 1008 | 325 ms |
| 86 | 4 | -- | -- |
```
php /home/telliott/scripts/background_scripts/profile_route_listem.php
```

### F-33 — Three grid classes never got the GH-707 priming and one costs 36 queries a row
Plain `listem()`, whole class, on the lab server: `storagegroup` 3 rows / 112
queries / 284 ms; `storagenode` 4 rows / 54 queries; `imaginglog` 11 rows / 16
queries. Against `snapintask` at 44 rows / 7 queries and
`macaddressassociation` at 87 rows / 6. `storagegroup` re-`load()`s a shared
`StorageGroup` per row and passes the result through a full
`getter('storagenode')` serialization per row; `imaginglog`'s `image` column
primes with `primeRel('Image', <image NAMES>)`, and `primeRel()` skips anything
with `(int)$id < 1`, so that prime is a complete no-op and the formatter runs
`->load('name')` per row.
```
php /home/telliott/scripts/background_scripts/profile_route_listem.php   # per-class section
sed -n '5032p' packages/web/lib/router/route.class.php                   # (int)$id < 1 -> skip
```

### F-34 — `Route::relColumn()` is dead code, and the drift it exists to prevent has happened three times
`relColumn()` (`route.class.php:5065`) pairs a formatter with its primer so
that, per its own docblock, "a formatter that reaches for a relation without a
primer" cannot happen. It has zero call sites in `packages/`,
`packages/service/` or `fog-plugins`. `listem()` hand-rolls the same
`prime`+`formatter` pair 22 times, and F-33 is three of those 22 having drifted.
This is the largest safe decomposition lever in the function: 834 of its 1,103
lines are the column table, and it collapses onto a helper that already exists.
```
grep -rn 'relColumn(' --include=*.php packages /home/telliott/fog-plugins | grep -v 'function relColumn'   # nothing
R=packages/web/lib/router/route.class.php
awk 'NR>=1512 && NR<=2614' $R | wc -l                    # 1103  listem
awk 'NR>=1645 && NR<=2478' $R | wc -l                    #  834  the column table
awk 'NR>=1512 && NR<=2614' $R | grep -c "'prime' =>"     #   22
```

### F-35 — `OpenAPI::document()` does not read the route table, and its coverage test compares names only
Six reads from the route layer, all of them class lists or field maps:
`webrootbase()`, `$validClasses`, `$validTaskingClasses`, `$validActiveTasks`,
`serverOwnedFields()`, `sensitiveFieldMap()`, plus
`Authorization::resolveApiPermission()`. `defineRoutes()` is named in the
docblock and never called -- every path shape is a hand-written mirror. So a
path, method, parameter or response-body change desynchronises silently, and
`openapi-route-coverage.test.php` catches only route NAMES in both directions
(changing the `/names` route's path while keeping its name leaves the suite
green). Corollary that matters for `listem()`: the grid's own `dt` column names
(`mainlink`, `primac_vendor`, `taskstateicon`, ...) are not in the document at
all, so a column-table refactor cannot desync the spec -- and cannot be caught
by it either.
```
grep -nE 'Route::(\$?[A-Za-z_]+)' packages/web/lib/fog/openapi.class.php
grep -n 'Route::defineRoutes\|OpenAPI' packages/web/lib/fog/openapi.class.php | head
```

### F-36 — `listem()`'s catch relabels every failure as HTTP 406
`route.class.php:2599-2604` calls `sendResponse(HTTP_NOT_ACCEPTABLE,
$e->getMessage())`, discarding the code the inner failure chose. Invisible over
plain HTTP, because `sendResponse()` exits inside the inner function and never
returns to `listem()`. Under the ADR 0011 result-wrapper path it is not: there
`sendResponse()` throws, `listem()` catches, and the caller gets 406 for a
refusal the source raised as 400. Every service and client endpoint reading
through `asValue()`/`getX()` sees one status for all failures, and a
behavioral test written against that seam will observe 406 where the source
says 400.
```
# lab: Route::asValue(function () { Route::listem('host', 'sec_tok=x', true); });
# => RuntimeException code=406 msg={"error":"Cannot filter host on: sec_tok"}
#    _assertNoSensitiveFilter() chose HTTP_BAD_REQUEST
```

### F-37 — `Route::sensitiveFieldMap()` memoizes after firing its own event, and `processEvent()` re-enters Route
`HookManager::processEvent()` populates `$knownEvents` by calling
`Route::getIds('hookevent')`, so firing ANY event re-enters `Route`. The
sensitive-field map fires `API_SENSITIVE_FIELDS` and memoizes only after the
event returns, so any `Route` path that consults the map arrives back with
`$_sensitiveMap` still null and fires again -- an OOM in about forty frames.
Until 2026-08-19 the cycle terminated at depth 2 by accident, `ids()` being the
one function on the path that never asked for the map; adding F-29's guard
there closed it and the process died during bootstrap with no output at all.
`serverOwnedFields()` has the identical construction and is one call site from
the same fate. Both now memoize the core tiers before the event and the
augmented ones after, so a re-entrant caller gets core -- never a smaller set.
`processEvent()` already used this exact mark-before-you-recurse pattern for
`$knownEvents`, with a comment saying why.
```
# revert the pre-set in sensitiveFieldMap(), then:
php tests/route-ids-getfield-sensitive.test.php
# PHP Fatal error: Allowed memory size ... exhausted   (exit 255)
```

### F-38 — F-29 is closed on `working-1.6`
`Route::ids()` now intersects `$getField` with `unfilterableFields()` before the
existence test. Serving a request it answers 400 and names the field; off-request
it returns no rows and logs, because `sendResponse()` exits and an exiting daemon
is a restart loop (cf. 2d199fa4b). Legitimate fields are unaffected -- every
in-repo `getIds()`/`ids()` call site asks for id, name, path, snapinpath, hostID,
ip, mac, userID, usergroupID, siteID, storagegroupID, imageID, groupID, msID,
isMaster, pending, sslpath, trustedcidrs, grantroleID, clientIgnore or
imageIgnore, none of which is on either secret tier. Required F-37. Verified
under both SAPIs against the lab database; `dev-branch` is untouched and its
separate question (it has no `stripSensitive` at all) remains open.
```
# cgi-fcgi (request arm)
host sec_tok => REFUSED {"error":"Cannot select host field(s): sec_tok"}
host name    => ALLOWED ["test"]
# cli (daemon arm)
host sec_tok => []            host name => ["test"]
sh tests/run-all.sh | tail -1          # 73 passed, 0 failed
php tests/route-ids-getfield-sensitive.test.php   # ok  41 checks passed
```

### F-39 — The read path's access controls now have a behavioral net, and it turns fourteen mutations red

`tests/route-read-path-guards.test.php` (93 checks) drives the real functions
against `tests/lib/fog-test-harness.php`, a fake PDODB with no database behind
it. It closes F-28: all eight mutations that previously left the suite green
now fail it, plus six more found while writing it.

Three of the six are the interesting ones, because each is a mechanism a
reader would assume the obvious assertion already covered:

- Deleting the dedicated sensitive-filter guard still refuses the request.
  `_assertFilterKeys()` computes its valid-key list by subtracting
  `unfilterableFields()`, so a blocked field is also an unknown one and the
  fallback arm answers 400 anyway. "Was it refused?" cannot see the real guard
  go; the arms differ in that the unknown-key one returns a `valid` list.
- `search()` applies the site boundary a second time, after
  `API_MASSDATA_MAPPING` — protecting only rows a plugin **added** to the
  payload, since `listem()` already scoped. Removing it leaves every
  listem()-driven scope assertion green.
- Pinning `stripSensitivePayload()`'s behavior does not pin `printer()`'s
  call to it.

Two facts the net depends on. `DatabaseManager::getLink()` is
`self::$DB->link()`, so a fake on the static `$DB` reaches the statements
`complex()` prepares on the raw handle — that is the only reason an end-to-end
`listem()` assertion is possible without a database. And the CLI SAPI never
populates `php://input`, so the two body-side guards are driven from `php-cgi`
children instead.

If this is false, the decomposition in `docs/route-listem-plan.md` proceeds
with no net, which is the thing the plan's first commit exists to prevent.

```
sh tests/run-all.sh | tail -1                       # 74 passed, 0 failed
php tests/route-read-path-guards.test.php           # ok  93 checks passed
# and with any one of the fourteen mutations applied: exit 1
```

### F-40 — The grid column table is pinned by a golden file that runs each primer

`tests/route-column-contract.test.php` captures the column table for all 52
valid classes by hooking `CUSTOMIZE_DT_COLUMNS` — the point a plugin receives
it, before any row is formatted — and compares it against
`tests/fixtures/route-column-contract.txt`. 628 columns, deterministic.

Two of the three recorded fields cannot be obtained by reading the source:

- The formatter's `use (...)` list, via
  `ReflectionFunction::getStaticVariables()`. A formatter that stops closing
  over `$tmpcolumns` or `$classname` compiles fine and renders a different
  cell. This is the failure mode a 834-line move produces.
- Which relation classes a column's primer warms, obtained by **calling** the
  primer with a synthetic row and reading back `Route::$relCache`. Once the
  pairing is behind `relColumn()` there is nothing left in the source to read.

Closes DEAD-1: 19 of the 22 hand-rolled `prime`+`formatter` literals are now
`self::relColumn(...)`, 96 lines net removed, table byte-identical. Three stay
— two prime `MACAddress::primeVendors()` rather than `primeRel()`, and
`scheduledtask`'s `hostLink` primes two classes off one column.

If this is false, commit 3 of `docs/route-listem-plan.md` moves 834 lines of
column definitions with nothing checking that the API surface survived.

```
php tests/route-column-contract.test.php   # ok  628 columns across 52 classes
php tests/route-column-contract.test.php --update   # after an intended change
sh tests/run-all.sh | tail -1              # 75 passed, 0 failed
```

### F-41 — `listem()` is 256 lines; the column table lives in `_gridColumns()`

739 lines moved out verbatim — byte-identical line for line modulo one indent
level, and the column table byte-identical for all 52 classes per F-40. Every
guard and hook fire in `docs/route-listem-access-control-map.md` stayed where
that document puts it.

`$tableID` is the part worth remembering. It is learned while walking the
column table and handed to `complex()`, which interpolates it into both count
statements. Nothing tested it, and it now crosses a function boundary:
deleting the assignment left the column contract green — the table is
identical either way — and the whole suite green, while producing

    SELECT COUNT(``) FROM `hosts` ...

so `recordsTotal` and `recordsFiltered` stop being answers on every list in
the product. Section 9 of the net pins it now, by parsing the `COUNT(...)`
argument. Its first version searched the statement for `hostID` and passed
under the mutation, because `hostID` also appears in two JOIN clauses of the
same query. That is the second substring-over-SQL assertion in this work to
pass for the wrong reason.

If this is false, a decomposition of `listem()` can break both record counts
with every test green.

```
php tests/route-read-path-guards.test.php   # ok  95 checks passed
php tests/route-column-contract.test.php    # ok  628 columns across 52 classes
sh tests/run-all.sh | tail -1               # 75 passed, 0 failed
```

### F-42 — The site boundary is in the query; `count()` was fixed by that alone

`listem()` passes `Authorization::scopedObjectWhere()` to `complex()` as
`$whereAll`, so the boundary is ANDed into the row query and both counts
instead of filtering rows the database has already paged. Verified on the lab
against the live database: a site1-scoped user's page 1 now returns their host
and `recordsTotal` 1, where before it returned nothing and their one host sat
at offset 75.

Three properties worth keeping:

- The membership rule exists once. `SiteScope::_inScopeSelect()` builds the
  SELECT; `allInScopeIDs()` runs it, `inScopeWhere()` embeds it. Two copies in
  two dialects drift silently.
- A subquery, not an id list — one expression whatever the fleet size, one
  round trip fewer, and the `max_allowed_packet` question never arises.
- The tri-state is safe by construction: `null` (no boundary) is the ONLY
  falsy return, and deny-all is the truthy `'1=0'`. `if (!$where) { skip }`
  therefore skips only when skipping is right.

`count()` needed no code of its own — `recordsFiltered` is SQL. On the lab, a
user entitled to 1 of 86 hosts: `count` 86 → **1**. `names`, `ids` and
`unisearch` still answer 86 and are the rest of SCOPE-1.

```
php /home/telliott/scripts/background_scripts/probe_sitescope_pagination.php < /dev/null
php /home/telliott/scripts/background_scripts/probe_scope1_routes.php < /dev/null
php tests/route-read-path-guards.test.php   # ok  106 checks passed
```

### F-43 — All four SCOPE-1 routes are scoped, per request, not per process

`names()`, `ids()` and `unisearch()` carry the site boundary in their WHERE;
`count()` was already fixed by F-42. Gated on `'cli' === PHP_SAPI` — the same
predicate `ids()` already uses — because `getIds()`/`getNames()` are called
from ~90 places in core and the services and a daemon has no `FOGUser`, so a
process-wide boundary would answer `'1=0'` and stop every replicator and
scheduler on a site-configured server from finding its work.

Two things worth not re-deriving:

- **`ids()` was believed unfixable** for a non-`id` `getField` (DEC-2 parked
  it). That is true of filtering the returned rows and false of a WHERE: the
  boundary constrains ROWS, so it does not care which COLUMN was asked for.
- **`unisearch()`'s fragment must be parenthesised.** Its match clause is a
  chain of ORs and `AND` binds tighter, so appending the boundary scopes the
  last arm only and leaves the rest matching server-wide. Valid SQL either
  way, and the statement mentions the membership table either way — only the
  parenthesisation distinguishes them, so that is what the net reads.

Verified on the lab against the live database, a user entitled to 1 of 86
hosts: all four answer 1 under `php-cgi`, and `names`/`ids`/`unisearch` still
answer 86 under CLI, which is the daemons and is the intended half of the gate.

```
php scripts/background_scripts/probe_scope1_routes.php < /dev/null
SCRIPT_FILENAME=.../probe_scope1_routes.php REDIRECT_STATUS=200 \
  REQUEST_METHOD=GET php-cgi -q < /dev/null
php tests/route-read-path-guards.test.php   # ok  108 checks passed
```

### F-44 — `?expand` is primed; ~20 statements/row became ~7, payload identical

The expand branch resolved a full object per row and then every member of
every expanded collection one at a time. Both now go through
`primeRel()`/`rel()`, the pair GH-707 introduced for the grid columns and that
this branch never used. Measured on the lab, `?expand=all` on `host`: 1024
statements for a 50-row page becomes 389, and the payload is byte-identical at
every page size (compared as sorted JSON).

Not flat, and the remainder is known: `$class->get($rel['field'])` still loads
lazily through `FOGController` on each row. The per-row OBJECT loads are gone,
the per-row RELATION accessor is not.

Pinned in the net as a MARGINAL cost -- the slope between two page sizes.
The intercept is a property of the fake; the slope is the defect.

Two measurement traps worth not re-discovering:

- `listem()`'s third argument is `$inputoverride`, a BOOL meaning "no
  php://input body". Passing a pass_vars array is merely truthy, skips the
  branch that folds `?length`/`?start` in, and silently returns the WHOLE
  TABLE -- so a probe reports the same query count for every page size asked
  for.
- `parseExpand()` runs at the top of `listem()`, so `QUERY_STRING` must be set
  before the call.

```
php /home/telliott/scripts/background_scripts/compare_expand_payload.php Host 50 < /dev/null
php tests/route-read-path-guards.test.php   # ok  110 checks passed
```

### F-45 — Listing a storage group or node runs NESTED listem() calls

`Route::listem('storagenode')` fires `CUSTOMIZE_DT_COLUMNS` three times: once
for `storagenode`, then twice for `task`, because the storage machinery
reaches tasks while rendering. Same for `storagegroup`.

This corrects F-40. The column-contract hook kept the LAST table it saw, so it
filed `task`'s 34 columns under `storagenode`'s name and under
`storagegroup`'s -- 68 of the fixture's 628 lines described the wrong class,
and looked entirely plausible doing it. The hook now takes the FIRST fire
whose `classname` matches the class being listed: first because the outermost
call builds its table before anything nested runs, and matched because a
nested call for another class must not answer for this one.

The corrected fixture is 598 columns. The 50 unaffected classes are unchanged,
so commits 2 and 3 were gated on a correct table for everything they touched;
what was wrong was the record for two classes neither commit changed.

A class whose table is never captured is now recorded as `__NO_TABLE__` rather
than contributing no lines, so "stopped building a table" cannot show up as
merely a shorter fixture.

If this is false, any assertion about the storage grids' columns is an
assertion about the task grid's.

```
php tests/route-column-contract.test.php   # ok  598 columns across 52 classes
```

### F-46 — The storage group grid showed every group the FIRST group's nodes

Two formatters shared one `new StorageGroup()` by reference: `enablednodes`
called `->set('id', $row['ngID'])->load()`, `masternode` called
`getMasterStorageNode()` on what that left behind. `set()/load()` on an object
that has already loaded a different group does not clear the previous group's
resolved relations, so from row two onwards both columns answered about the
first group.

On the lab, three groups whose real members are `[1]`, `[3,2]` and `[]` all
reported `enablednodes [1]` and `DefaultMember` as their master node. Verified
against `nfsGroupMembers` directly. The wrong answer is a real node name, which
is why it never looked wrong.

This corrects DEC-3, which recorded it as an ordering accident that "changes no
output".

Priming with `primeRel()` was tried and is WRONG here: `loadMany()` leaves a
group in a state `getMasterStorageNode()` answers differently on. The fix is a
per-id memo holding a fresh object, keeping the exact `load()` path.

PERF-2's premise does not survive measurement either. None of its three classes
is rel-shaped; the cost is inside model methods that query on their own --
`getMasterStorageNode()` probing nodes, `getClientLoad()` counting tasks per
node, and `imaginglog` resolving its image BY NAME, which `primeRel()` cannot
serve because it keys on id. Batching those means changing StorageNode and
StorageGroup, not the column table.

```
php /home/telliott/scripts/background_scripts/probe_perf2_classes.php < /dev/null
# storagegroup 36 q/row, storagenode 12.5, imaginglog 1.09 -- all model-side
```

### F-47 — `new $string` resolves from the GLOBAL namespace, so the ORM's name derivations work through the alias, not through the flat namespace

`FOGController::getManager()` builds `self::shortName($this).'Manager'` and calls
`new $man`. PHP does **not** apply the current namespace to a class name held in
a variable — a string is always resolved as fully qualified. So inside
`namespace FOG;` that lookup asks for a *global* `HostManager`, which exists only
because `hostmanager.class.php` ends with `class_alias(__NAMESPACE__ . '\HostManager',
'HostManager')`. The same is true of `FOGManagerController::__construct()`'s
inverse derivation, of `FOGBase::getClass()`'s `new \ReflectionClass($class)`, and
of every one of `Route::$validClasses`' 52 lowercase strings.

Two consequences, in opposite directions.

**ADR 0013's mechanical argument for a flat namespace does not hold as stated.**
It says `Host` + `'Manager'` gives `HostManager` under a flat namespace and
`Model\HostManager` under a split one. `shortName()` strips the namespace
(`fogbase.class.php:543-548`), so a split namespace yields the bare
`HostManager` too — and *neither* shape resolves without the alias. The
derivation is indifferent to the namespace layout. ADR 0013's other reasons for
flat (the Phase 0.2 bridge refusing nested names, `docs/plugin-development.md`
having told authors to write `FOG\Host`, and 226 chances to hand-file a class
into an invented taxonomy) are untouched by this and are why the decision still
stands. This narrows the rationale; it does not reverse the decision.

**Removing the `class_alias` lines is much larger than a reference sweep.** Every
string-driven instantiation in the tree breaks the moment the global name goes,
whatever the namespace shape — not only the 168 bare-name `extends` in
`fog-plugins` and `Route::$validClasses`, but `getManager()`, the
`FOGManagerController` inverse, and all ~350 `getClass()` literals. Any plan for
that work has to make the strings fully qualified first, as its own step, and
that step is the work.

```
php -r '
namespace FOG;
class HostManager {}
$s = "HostManager";
try { new $s; echo "resolved\n"; } catch (\Throwable $e) { echo $e->getMessage(), "\n"; }
\class_alias(__NAMESPACE__ . "\HostManager", "HostManager");
echo \get_class(new $s), "\n";
'
# Class "HostManager" not found
# FOG\HostManager
sed -n '543,548p;1778,1785p' packages/web/lib/fog/fogbase.class.php   # shortName
sed -n '1778,1785p' packages/web/lib/fog/fogcontroller.class.php      # getManager
```

### F-48 — `FOG\Auth\OidcProvider` does not exist, and ADR 0013's use of it as an example is contradicted by ADR 0014

ADR 0013's closing rule — *"flat for the migrated legacy tree, nested for new
subsystems in `src/`"* — illustrates itself with `FOG\Auth\OidcProvider`. No
such class has ever been written, in core or in `fog-plugins`; the name appears
only in three planning documents. And the subsystem it names is not core code:
ADR 0014 §2 is titled *"OIDC ships as a plugin, in `FOGProject/fog-plugins`"*,
and the real implementation is ten `*.class.php` files under
`fog-plugins/oidc/class/`.

Consequence: `packages/web/src/` has **no** nested subsystem today, and the
worked example anyone reads ADR 0013 for points at the one place a class like
that must not go. Taken at face value it puts plugin code inside core's
namespace, which is the shadowing ADR 0009 refuses by construction. The rule
itself stands; it needs a real example, and there is not one yet.

```
grep -rn 'OidcProvider' --include='*.php' packages /home/telliott/fog-plugins | grep -v vendor   # nothing
grep -rln 'OidcProvider' docs/                 # refactor-phase3-plan.md, adr/0013-*, composer-psr4-plan.md
find /home/telliott/fog-plugins/oidc -name '*.class.php' | wc -l    # 10
sed -n '72p' docs/adr/0014-authentication-seams-in-core-identity-providers-as-plugins.md
# ### 2. OIDC ships as a plugin, in `FOGProject/fog-plugins`
```

### F-49 — Moving `System` breaks `fogupdater` on every server already installed

`utils/FOGUpdater/fogupdater.sh:102` resolves "what is the latest version of
this branch" by fetching
`raw.githubusercontent.com/FOGProject/fogproject/<branch>/packages/web/lib/fog/system.class.php`
and awking `FOG_VERSION` out of it. On the Beta channel `<branch>` is
`working-1.6`. That file is installed to `$fogprogramdir/utils/` -- verified on
this box at `/opt/fog/utils/FOGUpdater/fogupdater.sh` -- so **every server
already running a 1.6 beta carries the old path baked in.**

Consequence, and it is the reason this is a fact rather than a task: the break
is not repairable by shipping a fix. A server whose updater cannot resolve the
version cannot use the updater to fetch the updater that could. It errors with
"Could not determine the latest FOG version for branch working-1.6" and the
only way forward is a tarball and `installfog.sh` by hand. Fixing
`fogupdater.sh` to probe both paths is necessary and is not sufficient; it only
helps servers that have already taken the fix.

This is the whole of the cost of moving `System`, and nothing else in the
migration has this shape: every other consumer of a moved file ships in the
same tarball as the file.

```
sed -n '102p' utils/FOGUpdater/fogupdater.sh
grep -n 'raw.githubusercontent' /opt/fog/utils/FOGUpdater/fogupdater.sh   # 102, old path
curl -so /dev/null -w '%{http_code}\n' https://raw.githubusercontent.com/FOGProject/fogproject/working-1.6/packages/web/lib/fog/system.class.php   # 200 today
curl -so /dev/null -w '%{http_code}\n' https://raw.githubusercontent.com/FOGProject/fogproject/working-1.6/packages/web/src/Base/System.php        # 404 today
```

**Resolved 2026-08-27: moved anyway, no shim.** The break was traced through
the whole install surface first, and it is narrower than the paragraph above
suggests. `utils/reporting/report.sh` and everything under `bin/`, `lib/` and
`.githooks/` self-heal: `installUtilities()` (`lib/common/functions.sh:6044`)
`rm -rf`s and re-copies both `$fogprogramdir/utils` and `$fogprogramdir/lib` on
every install, and the rest run from the tarball being installed. The only site that runs from the
old copy *before* the upgrade is the version lookup above, plus the
`verifyPayload` that reads the same path out of the extracted tarball.

So the realised cost is: a Beta-channel server upgrading through the bundled
updater gets one loud, non-destructive failure -- nothing is downloaded and
nothing is changed -- and recovers with a clone and `installfog.sh`, after
which the new updater is installed and it cannot recur. `stable` and
`dev-branch` are unaffected until they are ported.

The alternative was a compatibility file left at the old path carrying only
`define('FOG_VERSION', ...)`. Rejected: it is a `*.class.php` declaring no
class, so `Initiator::classFileList()` and the filename/class gate in
`tests/autoload.test.php` would each need a permanent exemption,
`apply-fog-version.sh` would become a two-file writer needing a drift gate, and
it could never be removed -- all to avoid a failure that is loud, harmless and
self-healing, in the one directory this migration exists to empty.

Both `fogupdater.sh` sites now probe `packages/web/src/Base/System.php` first
and fall back to the old path, because this one script tracks `working-1.6`,
`dev-branch` and `stable` and only the first has moved. `lib/common/utils.sh`
and `utils/reporting/report.sh` probe both for the same reason against the
INSTALLED tree, which on an upgrade is whatever the previous release laid down.
The fallbacks go when the last tracked branch has moved.

---

### F-50 — A source grep on a guard's call site cannot see the guard stop firing

`api-server-owned-fields.test.php` asserts that `Route::serverOwnedFields()`
returns the right list, then greps the field loops for the string
`_refuseServerOwned(`. That is worth keeping and it is not a net. Wrapping the
call in `if (false)` leaves the string exactly where it was, and the whole
suite stays green. The same shape covers `lockout-guard-is-unscoped.test.php`,
which drives `Authorization::adminExistsGiven()` thoroughly and never mentions
`Route` -- so nothing asserted that `deletemass()` consults it at all.

Three guards were disabled one at a time on `working-1.6` at `2c1db9a3e` and
each left 243 of 243 tests passing:

```
# in Route.php, one at a time, then: sh tests/run-all.sh
#   edit():       if (false && in_array($key, $serverOwned, true))   # 243 passed
#   deletemass(): if (false && self::$_deleteDepth < 1)              # 243 passed
#   joining():    if (false && 'POST' == self::$reqmethod)           # 243 passed
```

All three are covered by `tests/route-write-path-guards.test.php` now. The
general rule the three share is what belongs here: a grep pins a symbol's
USE, and the failure mode of every guard is that it stops FIRING. The two are
different questions and only the second one matters.

---

### F-51 — `FOGBase::$reqmethod` is NULL under `FogTestHarness::boot()`

`FOGBase` populates it from `filter_input(INPUT_SERVER, 'REQUEST_METHOD')`
during a request init the harness boot does not run. So any route that
branches on the verb takes its `default:` arm under the harness, whatever
`REQUEST_METHOD` the CGI child was given.

This is the reason the first attempt at netting `joining()`'s POST class gate
was fake: every case fell to the switch's `default:` and answered 400 having
run nothing, so a disabled gate and a working one were indistinguishable. Any
future test of a verb-dispatched route has to name it:

```
FogTestHarness::setStatic('FOGBase', 'reqmethod', $_SERVER['REQUEST_METHOD']);
```

---

### F-52 — On a DB-free fixture the statement count is the only signal a gate fired

`joining:host` and `joining:group` both end in a refusal under the write-path
harness: the gate refuses the first at the top, and the second fails further
down for want of real rows. Status code, message and shape are identical, so a
check comparing them passes with the gate disabled.

What the gate actually does is stop anything from running, and that is
observable -- with the log cleared immediately before the call, a refused class
issues zero statements and an allowed one reaches the database. Disabling the
class test does not just change a code; it gets far enough to start creating
hosts out of the names in the body:

```
php tests/route-write-path-guards.test.php     # ok  10 checks passed
# with `if (false && $classname != 'group')`:
#   REFUSED {"error":"Invalid hostname; must be 1-15 of these characters: ...}
#   STATEMENTS 170
```

---

### F-53 — The lockout guard's DEPTH condition is still unnetted

**SUPERSEDED BY F-54.**

`deletemass()` calls `Authorization::assertAdminRemainsAfterDelete()` only at
`self::$_deleteDepth < 1`, because the cascade re-enters `deletemass()` per
dependent table and those intermediate states are part of one operation
already judged as a whole. The CALL is netted; the CONDITION is not.

```
# in Route.php: if (true || self::$_deleteDepth < 1)
php tests/route-write-path-guards.test.php     # ok  10 checks passed
```

It survives because the cascade on that fixture finds no rows to re-enter
with. Netting it needs a fixture whose intermediate state reads as a lockout
while the whole operation does not -- for example a user whose deletion
cascades through `roleuserassociation` rows that momentarily leave no
administrator. Until that exists, a change to the depth condition is
unguarded.

---

### F-54 — A depth condition is netted by counting calls, not by provoking a refusal

Supersedes F-53, which asked for the wrong fixture. `deletemass()` runs the
lockout guard only at `self::$_deleteDepth < 1`, and F-53 proposed netting it
with a cascade whose intermediate state reads as a lockout while the whole
operation does not. That is hard to build and it is not what the condition
says. The condition's contract is that the guard does not RUN at depth, and
running is directly observable in the query log with no lockout involved.

Two signals, both from one call that deletes one of two administrators -- a
delete the guard must allow, so the outcome is identical either way and only
the queries differ:

- `SELECT `uID` FROM `users`` bare, with no WHERE. Issued by
  `adminExistsGiven()`, `localAdminExists()` and `interactiveAdminExists()`,
  and by nothing else in the tree. Its presence proves the guard ran at the
  top of this call.
- `roleUserAssoc` read with a WHERE, projecting `ruaUserID` or `ruaRoleID`.
  Only `Authorization::_remaining()` produces that shape, and `_remaining()`
  is reachable only from the guard's ASSOCIATION arms -- which only a call at
  depth can reach. The cascade's own lookup of the rows it is about to delete
  projects `ruaID`, so it is distinguishable and is not counted.

```
php tests/route-write-path-guards.test.php   # ok  12 checks passed
#   baseline                                    ALLOWED USERREADS yes RUAREADS 0
#   if (true || self::$_deleteDepth < 1)        ... RUAREADS 1   -> RED
#   if (self::$_deleteDepth < 2)                ... RUAREADS 1   -> RED
#   guard call deleted outright                 ... USERREADS no -> RED (2 checks)
```

The general form, which is the part worth carrying to the next one of these:
**a condition about WHEN something runs is netted by observing that it ran,
not by engineering an input on which running it would give a different
answer.** The second is a test of the guard; only the first is a test of the
condition.

---

### F-55 — A guard can be vouched for by an identical guard in a sibling arm

`Route::cancel()` carries the same statement twice --
`if (!in_array($class->get('stateID'), $states))` in the `filedeletequeue`
arm and again in `default:`. cancel-route-reports-truthfully.test.php pins it
with one regex that is not anchored to either arm, so the regex matches the
FIRST occurrence and the filedeletequeue guard stands in for the default one.
Disabling the default arm's test left that gate green -- and that test is the
founding bug of this route, a Failed task answering 200 "canceled" with its
state untouched.

Two guards that read identically are indistinguishable to a source grep, and
a gate cannot report which one it matched. So a text gate over a method with
repeated guards vouches for the count of them, not for any particular one.

```
php tests/cancel-route-reports-truthfully.test.php   # ok
#   default arm's test -> `if (false && !in_array(...))`   still ok  <- blind
php tests/route-cancel-guards.test.php               # ok  13 checks passed
#   same mutation                                          FAIL      <- red
```

---

### F-56 — `in_array()` in the default cancel arm is loose, and must stay loose

`get('stateID')` returns the database's STRING `'3'`; `$states` holds
INTEGERS `[0,1,2,3]`. A strict `in_array(..., true)` therefore matches
nothing and would refuse every cancel in the product. The cost of the loose
form is that a class with no stateID column reaches the test with `null`,
`null == 0` is true, and it is canceled whatever state it is in.

Nothing hits that today: every class in `$validTaskingClasses` that reaches
`default:` -- multicastsession, snapinjob, snapintask, task -- declares a
stateID. That is a fact about the list as it stands, not a property of the
code, and drift is silent. It is now asserted rather than assumed.

This is also why the M3 mutation was invisible at first: with the
`scheduledtask` arm removed the class falls to `default:`, passes the state
test on `null == 0`, and issues the same DELETE the dedicated arm would.
What separates the arms is `_requireFound()` -- a missing id is a 404 from
the arm and a searched-and-canceled-nothing 200 from `default:`.

```
php tests/route-cancel-guards.test.php   # ok  13 checks passed
#   add a state-less class to $validTaskingClasses
#     -> FAIL: every class reaching the default arm declares a stateID
#              -- missing: image
```

---

### F-57 — `phpstan clear-result-cache` does not clear the result cache

Run with `-c phpstan-tests.neon`, it reports "Result cache cleared from
directory: /tmp/phpstan" and the next run still serves stale findings. Four
`include.fileNotFound` errors in `tests/lib/rehearsal-runner.php` survived
it, reproduced against a pristine `git archive` export of the tracked tree,
and did not exist in CI on the same SHA -- which reads exactly like a
local/CI divergence and cost a round of chasing one. `rm -rf /tmp/phpstan`
cleared them.

Treat a phpstan result that disagrees with CI on the same commit as a cache
artifact until `rm -rf /tmp/phpstan` says otherwise.

```
vendor/bin/phpstan analyse -c phpstan-tests.neon --memory-limit=2G --no-progress
#   [ERROR] Found 4 errors
vendor/bin/phpstan clear-result-cache -c phpstan-tests.neon
#   Result cache cleared from directory: /tmp/phpstan
vendor/bin/phpstan analyse -c phpstan-tests.neon --memory-limit=2G --no-progress
#   [ERROR] Found 4 errors        <- unchanged
rm -rf /tmp/phpstan
vendor/bin/phpstan analyse -c phpstan-tests.neon --memory-limit=2G --no-progress
#   [OK] No errors
```

---

## How to add an entry

```
### F-nn — One-line claim in the present tense
Two or three sentences of consequence. What breaks if this is false.
```
the exact command, with the expected output as a comment
```
```

Number sequentially. Do not reuse numbers, including for superseded entries.
