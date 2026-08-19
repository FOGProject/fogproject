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

### C-02 — UNKNOWN #4 is closed, and the answer is favourable

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
lockfile, no licence metadata and no upgrade path. That is a materially stronger
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
core files are examples an admin opts into, not shipped behaviour. Anything
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

### F-23 — ADR 0013's promise is about the alias, not about every plugin-observable behaviour
Its §2 says the reverse `class_alias` "is supported for all of 1.6" and cannot
be removed before 1.7. Nothing in it freezes the hook contract, the `$active`
semantics or `register()`'s accepted listener shapes. Combined with 1.6.0 being
unreleased — `working-1.6` stamps `1.6.0-beta.NNNN` — there is no shipped 1.6
plugin ABI, so "we cannot change that inside 1.6" is not an argument any plan
in this repository may lean on. It was leaned on four times in the first draft
of `docs/hook-event-plan.md` and was wrong every time.
```
grep -n 'supported for all of 1.6' docs/adr/0013-flat-fog-namespace-and-the-reverse-alias-abi.md
grep -n "FOG_VERSION" packages/web/lib/fog/system.class.php   # 1.6.0-beta.NNNN, channel Beta
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
