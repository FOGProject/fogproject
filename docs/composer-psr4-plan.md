# Plan: FOG core becomes Composer-native

**Baseline:** `working-1.6` at `4765278a7`, tree green
(`sh tests/run-all.sh` → `169 passed, 0 failed`).

**Reads with:** ADR 0009 (why the plugin roots keep a runtime loader), ADR 0013
(why the namespace is flat and why the reverse alias is the 1.6 plugin ABI),
`docs/refactor-facts.md` (the ledger this plan may not contradict).

Every claim is tagged `VERIFIED` (a command in this document proves it),
`INFERRED` (follows from verified facts but is not itself run) or `UNKNOWN`.

The tree is green after every commit. `sh tests/run-all.sh` is the gate and is
stated once rather than repeated per commit.

---

## The shape of the answer, up front

The 202 movable class files **need no content edits at all**. They already
declare `namespace FOG;` and already `class_alias` their bare name back
(Phase 3, ADR 0013). PSR-4 asks only that the filename match the class name,
and it already does, modulo case. So the move itself is `git mv` plus case
correction, and nothing that references a class is touched — here, in
`fog-plugins`, or in a third-party plugin.

`VERIFIED` — 250 autoloadable files under `packages/web/lib` outside
`plugins/`: 204 `.class.php` and the 46 discovery-named. Every one declares
exactly **one** type; every basename matches its declared class name
case-insensitively; there are **zero** duplicate type names; only the two
AltoRouter files are outside `namespace FOG`, and they are ADR 0013's
deliberate exclusions.

```
php bin/psr4-scan.php            # the scan behind this table (see Commit 0)
git ls-files 'packages/web/lib' | grep -c '\.class\.php$'    # 204
for e in page hook event report; do
    printf '%s: ' "$e"; git ls-files 'packages/web/lib' | grep -c "\.$e\.php$"
done                                                          # 26 10 1 9
```

What is **not** free is the thing the sketch left out. `Initiator` cannot
simply stand down for core; it has to gain a **second, opposite** bridge.

---

## The one hard problem: bare names stop resolving

Composer claims the `FOG\` prefix and nothing else. `Initiator::_bridgeNamespaced()`
translates **forward** only — `FOG\Host` → the classMap. Once core leaves the
classMap, a request for the bare name `Host` is answered by nobody.

That is not an edge case. It is the majority of all class references FOG serves:

`VERIFIED` — `fog-plugins` inherits from core by bare name **168 times**, and
never by the namespaced spelling.

```
grep -rhoE 'extends (FOG\\)?[A-Za-z_]+' /home/telliott/fog-plugins --include='*.php' \
  | sort | uniq -c | sort -rn | head
#  72 extends Hook          26 extends FOGManagerController
#  26 extends FOGController 17 extends FOGPage
#   8 extends ReportManagement  7 extends Event
```

`VERIFIED` — `Route::$validClasses` (`packages/web/lib/router/route.class.php:474`)
is **52 lowercase strings**, and the whole REST API instantiates classes from
them. So the bridge must be case-insensitive, not merely present.

```
sed -n '474,526p' packages/web/lib/router/route.class.php
```

### The fix, and the proof it works

A reverse arm in `Initiator::autoload()`: on a bare name, consult a
lowercase → canonical map built from a recursive scan of `src/`, then
`class_exists('FOG\' . $canonical)`. The file's own `class_alias` declares the
bare name as a side effect of loading, so one bridge hit satisfies every casing
— PHP's class lookup is case-insensitive once the alias exists.

`VERIFIED` — proved against Composer's real `ClassLoader`, configured exactly
as the multi-root map below, with the reverse bridge attached:

```
FOG\Host               -> src/fog/Host.php
Host                   -> src/fog/Host.php
host                   -> src/fog/Host.php
FOG\PDODB              -> src/db/PDODB.php
pdodb                  -> src/db/PDODB.php
TaskQueue              -> src/reg-task/TaskQueue.php
FOGClient              -> src/client/FOGClient.php
FOG\Sub\Nested         -> src/Sub/Nested.php      (a real sub-namespace)
```

**The map is built from a filesystem scan, never from Composer's classmap.**
`fogproject-tests.yml:250-253` deliberately excludes `packages/web/vendor/composer`
from enforcement — the generated-artifact skew reason — so a stale
`autoload_classmap.php` is invisible to CI. A scan folds into
`Initiator::classFileList()`'s existing TTL cache and its existing
`forgetClassFileList()` invalidation, which is the same self-healing the plugin
uploader already depends on (ADR 0009).

---

## The second hard problem: core-vs-plugin shadowing flips

`VERIFIED` — `autoload()` checks the classMap at `commons/init.php:730` and only
falls through to the bridge at `:744`.

```
grep -n 'if (isset(self::$classMap\[$key\]))\|self::_bridgeNamespaced($class);' \
    packages/web/commons/init.php    # 730, 744
```

`INFERRED` — once core classes leave the classMap, a plugin shipping
`class/host.class.php` becomes the **only** claimant for the key `host` and wins
outright. That is precisely what autoload()'s own docblock says must never
happen: *"a plugin must never be able to shadow a core class by naming a file
after it."*

`tests/autoload-core-wins.test.php` would still pass, because it constructs its
collision against a core file **in `lib/`** — a file that no longer exists.
A test that cannot fail is worse than no test.

**So the reverse bridge is consulted BEFORE the classMap, not after**, and the
test is rewritten to assert the new mechanism. This is the single item in this
plan whose omission is a security regression rather than a bug.

---

## Layout: why not `FOG\Db\PDODB`?

That is the conventional PSR-4 shape and it is genuinely cleaner than what
follows: one root, no tie-breaking, name-to-path fully mechanical. It is the
right end state. It is not what this migration can deliver, for one concrete
reason and one decided one.

**The concrete reason: 167 of the 202 files are in `lib/fog`.** A real
sub-namespace layout maps `FOG\ => src/` and puts `FOG\Db\PDODB` in
`src/Db/PDODB.php` — which means `FOG\Host` goes in `src/Host.php`, in the
root. The big directory is back in the root, sprawled, and 167 of 202 files are
exactly as unstructured as the flat option. Sub-namespacing only the five small
directories (`db`, `client`, `service`, `reg-task`, `router` — 35 movable files
between them) buys a tidy 35 and leaves the other 167 loose.

`VERIFIED` — the split:

```
cd packages/web/lib
for d in fog client db reg-task router service; do
    printf '%s: ' "$d"; ls "$d"/*.class.php 2>/dev/null | wc -l
done      # fog 167, client 11, db 3, reg-task 5, router 4 (2 movable), service 14
```

**The decided reason: grouping all 202 needs a taxonomy, and ADR 0013 rejected
inventing one.** Mirroring the directories literally gives `FOG\Fog\Host`.
Anything better — `FOG\Model\Host`, `FOG\Manager\HostManager` — is a mapping
no directory expresses, maintained by hand, with 202 chances to file a class in
the wrong bucket and a class that does not resolve as the penalty for guessing
wrong. That argument is untouched by anything in this plan and it is why the
namespace stays flat.

Note that ADR 0013's *other* argument — the `getManager()` derivation — turns
out not to bear on this either way. See **F-47**: `new $string` resolves from
the global namespace, so `shortName($this).'Manager'` works through the
`class_alias` and is indifferent to the namespace shape. The ADR's conclusion
stands on its remaining reasons; this narrows the rationale rather than
reversing it.

### So: two moves — directories first, namespaces second

The multi-root map is what makes this possible, and it is better than either
"flat now" or "taxonomy in one pass":

**Move 1 (this plan).** Files go straight to their **final** taxonomy
directories — `src/Items/Host.php`, `src/Managers/HostManager.php`,
`src/Base/FOGBase.php`, `src/Router/Route.php` — while keeping the flat
`namespace FOG;`. **Still zero content edits.**

```json
"autoload": { "psr-4": { "FOG\\": [
    "src/", "src/Items/", "src/Managers/", "src/Base/", "src/Service/",
    "src/Client/", "src/Net/", "src/Db/", "src/Auth/", "src/Audit/",
    "src/TaskHandling/", "src/Boot/", "src/Router/", "src/Util/",
    "src/Exception/"
] } }
```

PSR-4 permits one prefix to map to a list of roots; Composer appends the
relative class name to each in turn and takes the first hit. **The directory is
not part of the class name**, so `FOG\Host` stays `FOG\Host`, the file stays
`Host.php`, and nothing that produces or consumes a class name changes.

**Move 2 (done, 2026-08-27).** Flip each file's namespace to match the directory
it is already in, collapse the map to a single `"FOG\\": "src/"`, add the
`use` lines. **Zero file moves** — `src/Items/Host.php` declaring
`namespace FOG\Items;` under a single `src/` root resolves to the same path it
is already at.

The estimate of 291 `use` lines came out at **325 across 178 of the 202 files**;
the other 24 reference nothing outside their own bucket. 245 same-bucket
references needed nothing at all. **The aliases did not need updating**:
`class_alias(__NAMESPACE__ . '\X', 'X')` re-exports the bare name from wherever
the class now lives, so decision 2 of ADR 0013 carried through untouched — see
that ADR's 2026-08-27 amendment, which also corrects the reason this move was
thought to be expensive.

Each commit is then exactly one *kind* of change, which is the whole point. If a
page stops rendering after move 1 it is the autoloader; after move 2 it is a
missing `use`. Combined, it is either, and a 202-file rename cannot be bisected
against a 177-file rewrite.

`VERIFIED` — move 1's arrangement, against Composer's real `ClassLoader` with
the reverse bridge attached (probed with the old directory names; the mechanism
is indifferent to which they are):

```
FOG\Host               -> src/fog/Host.php
Host                   -> src/fog/Host.php
host                   -> src/fog/Host.php
FOG\PDODB              -> src/db/PDODB.php
pdodb                  -> src/db/PDODB.php
TaskQueue              -> src/reg-task/TaskQueue.php
FOGClient              -> src/client/FOGClient.php
FOG\Sub\Nested         -> src/Sub/Nested.php      (a real sub-namespace)
```

The last line is the composition property: a real sub-namespace under the bare
`src/` root resolves alongside the flat-namespaced taxonomy directories, so
ADR 0013's closing rule — *"flat for the migrated legacy tree, nested for new
subsystems in `src/`"* — keeps working throughout.

**There is no such subsystem today, and ADR 0013's example of one is wrong.**
It offers `FOG\Auth\OidcProvider`; see **F-48**. OIDC is a *plugin*
(ADR 0014 §2, "OIDC ships as a plugin, in `FOGProject/fog-plugins`"), so its
classes belong under `FOGPlugin\Oidc\` and would never appear in
`packages/web/src/` at all. `src/Auth/` therefore holds exactly the four core
auth classes — `Authorization`, `CSRF`, `SiteScope`, `Redaction` — uniformly
`namespace FOG;` after move 1 and uniformly `namespace FOG\Auth;` after move 2.
No mixed state.

What the multi-root map costs, honestly:

- **Root order decides a tie, silently.** Two files sharing a basename under
  different roots both claim one class name and the first-listed root wins —
  the readdir-order failure class `autoload-core-wins.test.php` exists for.
  `VERIFIED` there are **zero** such collisions across all 202 today, so a test
  asserting basename uniqueness across `src/` closes it permanently. That same
  test pins the reverse bridge's lowercase map to a single answer, so it earns
  its keep twice.
- **Probe cost is nil.** `optimize-autoloader: true` is already set, so the
  committed classmap resolves in one hash lookup; a file added without
  re-dumping falls back to at most seven `file_exists` calls.

---

## The taxonomy

Derived where it can be, hand-filed where it cannot. **151 of 202 fall out of
the parent chain with no judgement at all** — which is the fact that reopens
ADR 0013's "202 chances to file a class in the wrong bucket".

`VERIFIED` — bucket sizes and the derivation:

```
php bin/psr4-scan.php --buckets
# Items    = extends FOGController         64
# Managers = extends FOGManagerController  64
# Service  = extends FOGService            13
# Client   = extends FOGClient             10
# remainder                                51
```

| Namespace | Count | Filled by |
|---|---|---|
| `FOG\Items` | 65 | `extends FOGController`, + `MACAddress` |
| `FOG\Managers` | 64 | `extends FOGManagerController` |
| `FOG\Base` | 17 | the hierarchy roots + `System` |
| `FOG\Service` | 14 | `extends FOGService`, + `FOGService` |
| `FOG\Client` | 11 | `extends FOGClient`, + `FOGClient` |
| `FOG\Net` | 5 | `FOGFTP`, `FOGSSH`, `FOGURLRequests`, `Ping`, `FOGRollingURL` |
| `FOG\Db` | 4 | `PDODB`, `DatabaseManager`, `Mysqldump`, `SchemaReconciler` |
| `FOG\Auth` | 4 | `Authorization`, `CSRF`, `SiteScope`, `Redaction` |
| `FOG\Audit` | 4 | `Audit`, `ActivityWindow`, `Retention`, `Blame` |
| `FOG\TaskHandling` | 3 | `TaskingElement`, `TaskQueue`, `TaskError` |
| `FOG\Boot` | 3 | `IpxeBootMenu`, `Registration`, `WakeOnLan` |
| `FOG\Router` | 3 | `Route`, `OpenAPI`, `HTTPResponseCodes` |
| `FOG\Util` | 3 | `Timer`, `FOGCron`, `FOGLogPaths` |
| `FOG\Exception` | 2 | `SnapinSaveException`, `UploadException` |
| **total** | **202** | |

Two boundaries worth stating, because they are the ones a later reader will
re-litigate:

- **`Items` holds the record, `TaskHandling` holds the machinery.** `Task`,
  `TaskLog`, `TaskState`, `TaskType`, `SnapinTask`, `SnapinJob`,
  `ScheduledTask` and `MulticastSession` are rows and stay in `Items`;
  `TaskQueue`, `TaskError` and `TaskingElement` are the things that *drive* a
  task and are not rows.
- **`WakeOnLan` is `Boot`, not `Util`.** It exists to get a host to the point
  where the boot menu can answer it — same subsystem, same lifecycle. `Util`
  is for things with no subsystem at all.

### The 46 discovery-named files join by namespace only

`FOG\Pages` (26), `FOG\Hooks` (10), `FOG\Reports` (9), `FOG\Events` (1).

`VERIFIED` — this is inside the HARD constraint, because **the discovery
contract is the filename, not the namespace.** `FOGPageManager::loadPageClasses()`
derives `HostManagement` from `basename($file)` and then calls `new $className`,
which per **F-47** resolves from the *global* namespace through the
`class_alias`. So `lib/pages/hostmanagement.page.php` can declare
`namespace FOG\Pages;` and stay exactly where it is, under exactly that name,
and the directory walk never notices.

They get a namespace line and an updated alias. They do not move and they are
not renamed.

### `BootMenu` became `IpxeBootMenu`

The class builds the iPXE menu and nothing else; the generic name had been
telling readers otherwise for years. Spelling is `IpxeBootMenu`, not
`iPXEBootMenu`: PSR-1 wants StudlyCaps, and `Ipxe` / `IpxeManager` already set
the house spelling.

Done in its own commit after the directory move, with **no back-alias**. Two
things the earlier estimate here got wrong, both found by re-running its own
verification command against the moved tree:

- **"39 references across 13 core files"** counted substrings. `addBootMenuItem`,
  `AddBootMenuItem`, the `BOOT_MENU_*` event names and `$foglang['PXEBootMenu']`
  all match `BootMenu` and none of them is the class. The real declared-name
  references were **three**: the declaration, its `class_alias`, and
  `new BootMenu()` in `service/ipxe/boot.php`. Everything else was comments and
  test-harness prose.
- **"and one plugin (`capone/hooks/addbootmenuitem.hook.php`)"** was the same
  error. That file names `AddBootMenuItem` (its own hook class) and the
  `BOOT_MENU_ITEM` event; it never references this class. **No `fog-plugins`
  release was needed**, so ADR 0009's cross-repo ordering rule did not apply.

`VERIFIED` — the `get_class()` producer sweep #1099 established the pattern for
is a **no-op** here: `IpxeBootMenu` contains no `get_class`, `__CLASS__`,
`::class` or `shortName()`, and nothing in core asks for it by string
(`getClass('BootMenu')`, a `bootmenu` literal, a DB row). So the rename could
not leak into data, and the only risk was a missed call site — which is why the
alias was renamed rather than kept as a back-compat shim.

```
grep -rn 'get_class\|__CLASS__\|::class\|shortName' packages/web/src/Boot/IpxeBootMenu.php   # nothing
grep -rn "getClass('Boot\|'bootmenu'" packages/web --include=*.php | grep -v vendor            # nothing
```

The three `tests/**/bootmenu-*.php` files keep their names. They describe the
subject under test, which is still "the boot menu", and renaming them would add
diff noise to a commit whose value is being small enough to review whole.

---

## Plugins do not live under `FOG\`

Raised while settling the taxonomy, and it belongs here because Commit 1 can
foreclose it by accident.

`VERIFIED` — this is greenfield. **Zero** of `fog-plugins`' 202 PHP files
declare a namespace, and no plugin ships a vendor tree. There is nothing to
migrate; there is only a convention to set.

```
grep -rl '^namespace ' /home/telliott/fog-plugins --include='*.php' | grep -v vendor | wc -l   # 0
find /home/telliott/fog-plugins -name autoload.php -path '*vendor*' | wc -l                    # 0
```

`VERIFIED` — the loading seam already exists.
`Initiator::_registerPluginAutoloaders()` (`commons/init.php:505`) registers a
plugin's own `vendor/autoload.php`, **last** in the chain, refusing any loader
that claims a namespace core already claims. So a plugin can own a PSR-4
namespace today without any change to HARD constraint 2 — the classMap keeps
serving `.class.php` by filename for plugins that do not ship one.

**Recommended: one root namespace per plugin, keyed on the plugin node —
`FOGPlugin\Ldap\`, `FOGPlugin\Oidc\`.** Not `FOG\Plugins\Ldap`, for three
reasons:

1. `FOG\` is core's namespace. A third-party declaring inside it is squatting,
   and it re-creates precisely the shadowing ADR 0009 closes by *refusing* a
   colliding plugin directory rather than shadowing it.
2. Core needs to be able to tell its own classes from someone else's. The
   reverse bridge, the classMap collision rule and `_claimsOf()` all key on
   that distinction.
3. The plugin node is already unique by construction — ADR 0009 refuses a
   colliding directory name — so it is the natural disambiguator, and it needs
   no registry.

Bundled and third-party plugins use the **same** convention deliberately.
Bundled-versus-external is a *distribution* fact, not a naming one, and
blurring it is what ADR 0009 exists to prevent.

**The consequence for Commit 1, which is why this is settled now:** write the
reverse bridge as *"bare name → the FQCN this file actually declares"*, never
as *"bare name → `FOG\` + canonical"*. The second hard-codes the assumption
that everything under a scanned root is core, and breaks the day a plugin
declares `FOGPlugin\Ldap\LdapManager` and something references it bare. It is
a one-line difference and it costs nothing to make now.

This wants its own ADR once the convention is confirmed; it is recorded here so
Commit 1 does not preclude it.

---

## What moves, what stays

| | Count | Where |
|---|---|---|
| Move | **202** | `lib/{fog,db,client,service,reg-task,router}/*.class.php` → `src/<same>/<Class>.php`, `System` included |
| Stay — ADR 0013 exclusions | 2 | `lib/router/altorouter.class.php`, `altotransformer.class.php` — upstream name, authorship, MIT license |
| Stay — HARD, discovery-named | 46 | 26 `.page.php`, 10 `.hook.php`, 9 `.report.php`, 1 `.event.php` |
| Stay — generated | 1 | `lib/fog/config.class.php` |

`VERIFIED` — the 46 discovery-named files are reached only through
`FOGBase::fileitems()`, which has exactly three call sites, all of which derive
a class name from a basename. PSR-4 does not do discovery, so they cannot move.

```
grep -rn 'fileitems(' --include=*.php packages/web | grep -v 'function fileitems'
# fogpagemanager.class.php:284 ('pages')  reportmanagement.page.php:41 ('reports')
# eventmanager.class.php:380  (hooks/events)
```

`VERIFIED` — the installer's `--oldcopy` lowercase sweep matches
`*[A-Z]*.class.php` and four siblings, none of which matches `Host.php`. PSR-4
files under `src/` are safe from it, and `tests/lowercase-class-filenames.test.php`
already says so in its scope note.

```
sed -n '10361,10372p' lib/common/functions.sh
```

### `Config` does not move

It is generated by the installer into `lib/fog/config.class.php`, is untracked,
and is gitignored by the pattern `*config.class.php`. Moving it would mean six
`functions.sh` edits and a `.gitignore` pattern that stops matching a file
holding the database password. The classMap arm survives for the discovery
files and the plugin roots anyway, so `Config` keeps resolving through it
unchanged.

The line this draws is worth stating: **source moves, generated files stay.**

**Amended 2026-08-27 — it moved, to `commons/`, and the line above is why.**

The decision it was weighed against at the time was `src/Base/Config.php`, and
that is still the wrong answer for exactly the reason given: `*config.class.php`
is a GLOB, it matches at any path, and a PSR-4 destination needs a hand-written
path entry protecting `DATABASE_PASSWORD`, both FTP passwords and
`FOG_SCHEMA_INSTALL_TOKEN`. What the original text missed is that those are not
the only two options.

`commons/config.class.php` keeps every property the "stay" argument was
protecting -- the glob still matches with no `.gitignore` edit, the file stays
out of the PSR-4 tree, it stays global-namespaced so `new Config()` in
`init.php` needs no import, and `Initiator::_scanClassFiles()` walks `commons/`
(it excludes only `service/` and `vendor/`) so the classMap keys it `config`
exactly as before. And it puts the file beside `fogpaths.php`, the installer's
*other* generated runtime file, which has lived in `commons/` since GH-850.

What changed to make the move worth doing at all: after the PSR-4 move
`packages/web/lib/fog/` held nothing but `index.php`. A whole directory, kept
alive for one generated file.

So the line survives with a second clause: **source moves, generated files
stay -- and generated files live in `commons/`.**

Sites this touched, none of them optional:

| Where | What |
|---|---|
| `functions.sh` heredoc | write destination |
| `functions.sh` `--oldcopy` sweep | the explicit KEEP is **deleted**, so a previous install's config is removed rather than preserved |
| `functions.sh` `SVC_password` fallback | reads an installed tree, so it tries both spellings |
| `functions.sh` post-install banner | the path printed with the schema token |
| `bin/fog-node-key.php`, `bin/schema-manifest.php` | both read an installed tree; both try `commons/` then `lib/fog/` |
| `tests/oldcopy-retires-moved-classes.test.sh` | inverted: pins that the old path is REMOVED and `commons/` is untouched |
| `tests/generated-config-is-untracked.test.sh` | new, and it reads the write path out of `functions.sh` rather than naming it |

The `--oldcopy` keep is the one that mattered. Left in place it preserves the
previous install's config -- that install's database password and schema token,
readable in the web root, while a different file is the one actually in use,
surviving every future upgrade.

### `System` moves in its own PR, after `fog-workflows`

`VERIFIED` — its path is hard-wired in **11 functional sites** in this
repository and **3** in `FOGProject/fog-workflows`.

```
grep -rn 'system\.class\.php' bin lib .githooks | grep -v '^\s*#'
grep -rn 'system.class.php' /home/telliott/fog-workflows --include='*.yml'
# fogproject-tests.yml:483, update-lang-fix-psr-and-sync-version.yml:402,433
```

`fog-version.sh`, `apply-fog-version.sh`, `pre-commit` and `pre-push` gate every
commit and push in this repository; `fog-version.sh` runs under `set -e` and
`grep`s the file directly, so a moved `System` wedges the repo against its own
fix. The workflows are shared by every fogproject branch, and `dev-branch` has
no `src/`, so they need a branch-conditional probe — the same shape as the
existing `composer_tree` probe at `fogproject-tests.yml:183`.

ADR 0009's ordering rule applies unchanged: **land the `fog-workflows` change
first**, then the two-file move plus the 11 shell/hook edits in one PR.

---

## Components outside `packages/web` that this touches

The move is confined to `packages/web`, but four things reach into it by
absolute path. This is the whole list, so it does not have to be re-derived.

| Component | Needs a change? | When |
|---|---|---|
| `bin/psr4-scan.php` TABLE | on every new class | standing, gated by `tests/psr4-layout.test.php` |
| `lib/common/functions.sh` — `--oldcopy` restore | **yes** | Commit 5 |
| `.githooks/{pre-commit,pre-push,lib/fog-version.sh,lib/apply-fog-version.sh}` | **yes**, `System` only | `System` PR |
| `bin/{installfog,updatefog,fetch-plugins}.sh`, `lib/common/{config,utils,functions}.sh` | **yes**, `System` only | `System` PR |
| `FOGProject/fog-workflows` (3 sites) | **yes**, `System` only | **before** the `System` PR |
| `bin/schema-manifest.php`, `bin/fog-node-key.php` | no — they read `Config`, which stays | — |
| `copybacktrunk.sh` (git → web) | no | — |
| `c2svn.sh` (web → git) | no, but see the warning below | — |
| `.githooks/lib/update-language.sh` | no | — |
| `.php-cs-fixer` via `psrfix()` | no | — |

`VERIFIED` — the two that look like they should need a change and do not:

- **`copybacktrunk.sh` is an exclude-list rsync with `--delete`, not an
  allowlist** (`:247`), so `src/` deploys with no edit and the old
  `lib/**/*.class.php` are deleted from the docroot on the first deploy after
  the move. Its two `lib/fog/config.class.php` references (`:177` exclude,
  `:253` re-copy) are about the *generated* Config, which does not move — this
  is the "source moves, generated files stay" line paying for itself.
- **`update-language.sh` already covers `src/`**: its find is
  `packages/web/ -name '*.php' -not -path '*/vendor/*'`, and `--no-location`
  plus `msgcat --sort-output` mean the move produces no `.pot` churn at all.
  Confirmed: `grep -c '^#: ' messages.pot` is 0.

**Warning worth stating once.** `c2svn.sh` rsyncs `/var/www/fog/ → packages/web`
with `--delete` and only two excludes (`:61`). CLAUDE.md already says never run
it after `copybacktrunk.sh`; after this move the consequence is much larger than
before — run from a server that has not been redeployed, it deletes `src/` out
of the repository and restores every `lib/**/*.class.php`. It is not a script
that needs changing; it is a script that needs the existing rule obeyed.

The `System` sequencing is the only genuinely cross-repository ordering, and it
is ADR 0009's rule unchanged: **`fog-workflows` first**, teaching the three
sites to find `System` at either path (the same shape as the existing
`composer_tree` probe at `fogproject-tests.yml:183`), and only then the move
here. Reversed, `fog-version.sh` runs under `set -e` and greps a file that is
not there, which wedges every commit and push in this repository — including
the one that would fix it.

---

## The commit sequence

Tree green after each.

### Commit 0 — the scan tool

`bin/psr4-scan.php`: emits the move manifest (source → target, derived from the
declared type name), and refuses if any file declares zero or more than one
type, if any target collides case-insensitively, or if any basename is claimed
twice. It is the tool the tests below call in `--check` mode, the same
arrangement as `bin/namespace-fog-classes.php` and
`tests/namespaced-tree.test.php`.

No files move. Reversible: delete one file.

### Commit 1 — the two-way bridge

`packages/web/commons/init.php` only:

- reverse arm in `autoload()`: bare name → lowercase map → **the FQCN that
  file actually declares**. Never `'FOG\\' . $canonical` — that hard-codes
  "everything scanned is core" and forecloses the plugin namespace convention
  above, and it is also what makes move 2 need no bridge change at all;
- **consulted before the classMap**, so core cannot be shadowed;
- `src/` folded into the existing scan, cache key and `forgetClassFileList()`.

Tests: new `tests/psr4-bridge.test.php` covering namespaced / bare / lowercase
resolution, the fall-through to plugin-only classes, the missing-alias
diagnostic, cache round-trip and invalidation, and a plugin file named after a
core class failing to shadow it. It runs against a miniature tree in the temp
directory rather than `packages/web`, because it deliberately creates a plugin
file named after a core class and leaving one of those in a real tree is the
defect under test.

**Correction to an earlier draft of this plan**, which said
`tests/autoload-core-wins.test.php` would be *rewritten* against the new
mechanism. It should not be, and the two tests are not interchangeable. The
classMap's core-wins rule keeps mattering after the move, because the map
still holds the 46 discovery-named files and the generated `config.class.php`;
that test keeps guarding it. What it stops covering is the classes that move,
for which the guarantee is ORDER rather than preference — and that is what the
new test holds. Deleting either leaves a plugin able to shadow some part of
core. A scope note in the old test now says so.

**Second correction, found once files were actually in `src/`.** The reverse
arm alone is not the whole bridge. `_bridgeNamespaced()` answers a *namespaced*
request whose casing Composer will not match — PHP class names are
case-insensitive, Composer's PSR-4 prefix match is not, so `fog\Image` reaches
FOG's autoloader — and it resolved that against the classMap only. Once core
left the map, every non-canonical casing of a core class stopped resolving, and
a plugin shipping `class/host.class.php` became the sole claimant for
`fog\Host`. It now consults `srcFileList()` first and the classMap second, for
exactly the ordering reason the bare arm does. Caught by
`tests/autoload.test.php`'s existing `class_exists('fog\Image')` check, which
is the only place in the suite that probes a non-canonical namespace casing.

No files move — the bridge is inert until something is in `src/`. Reversible.

### Commit 2 — the move

**Correction, made while doing it: Commits 2, 3 and 4 are one commit, not
three.** The split below was written as though each stage left a tree someone
could check out. It does not. The moves alone leave every `require` in `tests/`
pointing at a path that no longer exists and Composer's classmap naming files
that are gone; the test rewrite alone points at files that have not moved yet.
Each intermediate state is red, which breaks this plan's own "green after every
commit" gate and makes a bisect through the middle of the migration useless.
They are one atomic change — the moves, the `page.class.php` anchor,
`composer.json`, `composer dump-autoload -o`, and the test-path rewrite —
and the rest of this section describes what that one commit contains.

201 `git mv`, zero content edits, plus **one** real edit:
`lib/fog/page.class.php:483` does `include '../management/other/index.php'`,
which resolves today only because `include_path` is built from the dirnames of
class files (`init.php:163`) and `lib/fog/..` lands on `packages/web/`. After
the move no `lib/*` dirname makes `..` land there. It would fall back to the
calling script's directory and *happen* to still work from `management/index.php`
and `api/index.php` — luck, not design. Anchor it on `BASEPATH`.

`composer.json` gains the taxonomy roots. `bin/namespace-fog-classes.php`'s
walk and EXCLUDE list follow the tree.

Reversible: `git mv` back.

### Also in Commit 2 — test paths

`VERIFIED` — **86 test files** carry **183** occurrences of
`lib/<dir>/<name>.class.php`. Rewritten mechanically from Commit 0's manifest.

```
grep -rlE "lib/(fog|db|client|service|reg-task|router)/[a-z0-9_-]+\.class\.php" tests/ | wc -l   # 86
grep -rhoE "lib/(fog|db|client|service|reg-task|router)/[a-z0-9_-]+\.class\.php" tests/ | wc -l  # 183
```

### Also in Commit 2 — regenerate

`composer dump-autoload`. Churn is confined to `packages/web/vendor/composer/*`,
which CI reports rather than enforces.

### Commit 5 — the `--oldcopy` retirement sweep

`VERIFIED` — `configureHttpd()` restores the whole backup into the fresh
webroot and *then* lays the new files over it (`lib/common/functions.sh`, the
`FOG_copy_back_old == yes` block), and the only retirement list in the
installer, `retired_web_other`, is scoped to `management/other/`.

```
grep -n 'retired_web_other' lib/common/functions.sh    # 10416, 10429, 10432, 10439
```

`INFERRED` — so an `--oldcopy` upgrade leaves all 202 pre-migration
`lib/**/*.class.php` on disk beside `src/`. With Commit 1's ordering they are
inert for **classes** (the bridge answers first), but `classFileList()` still
walks them, and stale `.hook.php` / `.page.php` files from a prior release come
back and re-register — a feature silently reappearing.

Prune retired core paths from the restored backup. This is a pre-existing
latent problem for any dropped file; this move is what makes 201 of them stale
at once.

**Done, and the shape it took.** Asked of the source tree rather than named,
for the reason `retired_web_other` gives one directory further in: a hand-kept
list of retired paths drifts from what is shipped, and that drift is the bug
the list was added to fix. So any `lib/<dir>/*.class.php` in the restored tree
that `$webdirsrc` does not also ship is deleted, between the restore and the
new tree being laid over it. `maxdepth 2` keeps a bundled plugin's
`lib/plugins/<name>/class/` out of it — that is the plugin release's to
manage. `lib/fog/config.class.php` is the one keep, because it is generated
into that directory later in the same function and so is never in
`$webdirsrc`.

`tests/oldcopy-retires-moved-classes.test.sh` lifts the loop out and runs it
against a fixture, the same way `tests/webroot-preserved-files.test.sh` does.
Six checks, each confirmed by mutation: dropping the still-shipped guard,
inverting it, dropping the config keep, widening the maxdepth, and moving the
block after the new-tree copy each turn exactly the expected check red.

`messages.pot` needs no thought: `update-language.sh` uses `--no-location` and
`msgcat --sort-output`, and its `find` already covers `src/`.

```
grep -c '^#: ' packages/web/management/languages/messages.pot    # 0
```

### Separate PR — `System`

`fog-workflows` branch-probe first, then the two-file move and 11 shell/hook
edits together.

---

## Verification

**No schema change. No database migration. Nothing irreversible.** Every
commit is a `git mv` or a file edit.

**Run, against a shadow tree booted on the live lab database.** Two probes,
both in `~/scripts/background_scripts/`, neither of which writes to
`/var/www` or to the live `/opt/fog/cache`:

- `psr4_shadow_boot_probe.php` boots `origin/working-1.6` and this branch from
  two shadow trees and prints what each one *derived* from where files live:
  discovery (26 pages, 10 hooks, 1 event, 9 reports — plus the plugin tree's),
  all 52 `Route::$validClasses` resolving and pairing with their managers
  through `getManager()`'s string arithmetic, the `include_path`, and four
  live table reads plus a model loaded through the ORM. **The only diff is
  the version string and four now-empty `lib/` directories leaving the
  include_path.** Nothing in the tree does a path-relative include, so that
  shrink reaches nothing.
- `psr4_plugin_shadow_probe.php` puts two plugins under a redirected
  `FOG_PLUGIN_DIR`: an ordinary one, which loads under both spellings as one
  type, and one shipping `class/host.class.php`. Core's `src/Items/Host.php`
  wins, `Host(1)` still loads through the ORM, and discovery sees both.

  **The collision is reachable, which is the part worth writing down.**
  Swapping the two arms in `autoload()` so the classMap is consulted first
  makes `FOG\Host` resolve to the plugin's stub — the supply-chain shape ADR
  0009's collision rule exists to refuse. `tests/psr4-bridge.test.php` goes
  red under the same mutation (3 of 18).

  It only bites if the **bare** name is asked first. Composer answers the
  exactly-cased `FOG\Host` out of `src/` without this autoloader running at
  all, and the file's own `class_alias()` then declares the bare name — so a
  probe that asks the namespaced spelling first reports a pass it did not
  earn, which is what the first draft of that probe did. `getClass()`,
  `Route::$validClasses` and every plugin `extends Host` ask for the bare
  name, so the ordering rule is protecting the path that is actually used.

**Run again on the DEPLOYED tree** (`1.6.0-beta.4200`), which is the only
place the bundled plugin root exists — `lib/plugins` is gitignored staging in
the checkout, filled by `bin/fetch-plugins.sh`.

- All 10 UI pages and all 7 grids render and return rows; every REST route
  answers 200 (`/fog/<route>`, not `/fog/api/<route>` — the `api/index.php`
  script is what the vhost rewrites *to*, and a first pass reading 501 was
  the probe using the wrong path, not a regression).
- All 15 bundled plugins are discovered under both roots and all 63 of their
  class files resolve (`psr4_live_plugin_discovery.php`).
- The six that are installed and active — ldap, location, ntfy, oidc, ou,
  windowskey — each render their page, appear in the sidebar (their menu hook
  fired) and answer on their own REST class (their `API_VALID_CLASSES` hook
  fired). Three separate failure modes, checked separately.

**The gate ADR 0009 names, run end to end.** `helloworld` was bundled but not
installed. Before: its page 308s, no sidebar entry, `/helloworld` 501. After
activate + install through the UI's own actions, **on the very next request,
same session, no restart and no TTL wait**: page 200, sidebar entry present,
`/helloworld` 200. That is `installdb()` running the schema, the class-file
list being invalidated, the plugin's classes autoloading and both its hooks
registering — the consequence note in ADR 0009 that says a freshly installed
plugin otherwise stays invisible for the length of the TTL while everything
downstream silently no-ops and reports success. The lab was put back
afterwards (`psr4_revert_helloworld.sh`); the `helloWorld` table stays,
because uninstall is forward-only by design.

**Uploading an archive**, with both of ADR 0009's switches on. A throwaway
third-party plugin — `psr4probe`, a manifest, a model, a manager, a hook and
a page — was tarred, uploaded through Plugin Management, staged (checksum and
manifest returned for confirmation), committed into `/opt/fog/plugins`,
discovered, activated and installed. On the next request its page rendered
**from the external root**, its sidebar entry appeared and its REST class
answered.

The page is the interesting part. It is a GLOBAL-namespace class doing
`extends FOGPage` and `self::getClass('Host', 1)` — bare core names, the
spelling all 168 inheritances in `fog-plugins` use — and it printed
`Host(1) is test` off the live database. After the move a bare core name
resolves only through the reverse arm in `Initiator::autoload()`, so that
one line is the arm working from outside the web tree entirely.

Everything was removed afterwards and the lab put back: plugin uninstalled
and forgotten, files deleted, `psr4probe` table dropped,
`FOG_PLUGIN_UI_INSTALL_ENABLED` back to `0`. The root switch is Tom's to
revoke (`bin/fog-plugin-uploads.sh disable`).

### What that found

The first upload had a mis-declared page class — `namespace FOG; class
Psr4ProbeManagement` with no `class_alias` back — and it **took the whole
admin UI down**: `get_class_vars()` on a name nothing declares is an uncaught
TypeError out of `FOGPageManager`'s constructor, so every page 500'd with an
empty body, not just the plugin's. Deleting the files did not fix it either,
because the class-file list is TTL-cached and kept naming them.

Pre-existing, not caused by the move, but reachable from outside the
repository since ADR 0009 and now fixed here with the same two guards
`startClassFromFiles()` already carries — see
`tests/page-discovery-survives-bad-file.test.php`.

The gate, in order of what is most likely to break quietly:

1. **A plugin installs through the UI into `/opt/fog/plugins` and loads.**
   Its classes `extends Hook` by bare name, so this is the direct end-to-end
   proof of the reverse bridge on the path ADR 0009 exists for. Then repeat
   with a plugin shipping `class/host.class.php` and confirm core still wins.
2. **Render real pages against the live database and diff against
   `origin/working-1.6`.** ADR 0013 records why this is not optional: 226
   classes resolved under both spellings and the tree could not render a page.
   Resolution tests are exactly the tests a resolution bug passes.
3. `git ls-files 'packages/web/lib/**.class.php'` → **2**.
4. `sh tests/run-all.sh`, plus `php tests/autoload.test.php /var/www/html/fog-1.6`
   in its live-server mode.
5. **Mutate the new bridge test and watch it go red** — delete the reverse arm,
   then delete the before-the-classMap ordering. An assertion that has only ever
   been green is an assertion we have no evidence about (F-39, F-41).

### On PHPStan

It is **not** part of this work, and landing it first would mislead rather than
help.

The premise behind "a static analyzer watches while 202 files move" is that the
move breaks references that then have to be chased. It does not: no caller is
edited anywhere, because no class name changes. What PHPStan would report is
whatever `scanDirectories` told it to — point it at `lib/` and it loses every
moved class, point it at both and it finds everything whether or not the move
is complete. The signal would be a property of the neon file, not of the
application.

It is also structurally blind to the mechanism this migration turns on:
`class_alias` and a runtime bridge are invisible to static analysis, so the one
thing that must be proven here is the one thing it cannot see.

The completeness check actually wanted is item 3 above — exact, not heuristic,
one command. And `tests/all-classes-load.test.php` already *declares* every
class in a child process, which is stronger than resolving it.

PHPStan gets **cheaper and better** afterwards: PSR-4 with one class per file
means its config is `autoload: psr-4` pointing at `composer.json`, instead of
`scanDirectories` over 250 files with five non-standard suffixes. Separate
work, with its own baseline decision, sequenced after.

---

## Next piece of work, queued behind this one proving out

**Removing the `class_alias` lines.** Deliberately not in this pass: while 202
files are moving, a stale reference to a bare name still resolves, so a bad move
and a missed reference look identical. Removing the safety net in the same pass
destroys the diagnostic.

**It is larger than it looks, and F-47 is why.** `new $string` resolves from the
global namespace, so *every* string-driven instantiation in the tree — not just
plugin `extends` — works today because of the alias and breaks without it,
whatever the namespace shape. That includes `FOGController::getManager()`, the
`FOGManagerController` inverse derivation, `Route::$validClasses`' 52 lowercase
strings and all ~350 `getClass()` literals.

So the sequence is:

- **fully qualify the strings first**, as its own step and its own commit —
  this is the bulk of the work, and it is safe to do *while the aliases are
  still in place*, which is the whole reason to keep them through this move;
- amend ADR 0013 §2 — the *"supported for all of 1.6"* clause is the decision
  being reversed. F-23 already records that nothing else in that ADR freezes the
  plugin contract, and that 1.6.0 is unreleased;
- sweep `fog-plugins` for the 168 bare-name `extends`;
- only then delete the 202 `class_alias` lines and the reverse bridge arm from
  `Initiator`.

Doing those in any other order removes the net before the work it is holding.

**Done 2026-08-27, and the last bullet was wrong.** `Initiator::
_bridgeNamespaced()` **stays**. The 46 discovery-named classes under `lib/`
declare a flat `namespace FOG;` and keep their own `class_alias`, because
`FOGPageManager::loadPageClasses()` and `EventManager::load()` derive the class
name from `basename($file)` and PSR-4 does not do discovery. Composer maps
`FOG\` onto `src/`, so a core file's `use FOG\ReportManagement;` resolves to
`src/ReportManagement.php`, which does not exist — the bridge is the only thing
that answers it.

Removing something else turned out to be necessary instead: the built-in
`spl_autoload()` registered behind `Initiator::autoload()`. A refusal is only a
`return`, so PHP carried on to that fallback, which probes include_path by
basename — and include_path covers the plugin roots. A plugin shipping
`class/host.class.php` answered the bare `Host` the moment core stopped
answering it.

Two more string-driven sites the "fully qualify the strings first" step missed,
both silent and both access-adjacent: `Authorization::_scopeClassVars()`
(`class_exists($node)`, so object scoping loses its model) and `PluginRunner`
(`is_subclass_of($class, 'PluginTask')` — a class name in a string names the
GLOBAL class, so every plugin task is skipped). Full account in ADR 0013's
2026-08-27 amendment.
