# A flat `FOG\` namespace, and the reverse alias as the 1.6 plugin ABI

## Status

accepted

## Amended 2026-09-04 — the literals this record counts are gone

[ADR 0043](0043-a-class-is-named-at-the-call-site-not-fetched-by-string.md)
retired the literal `getClass('X')`, so the "~520 literals" and "~350
literals" counted below are now `new` expressions and the roughly 150 inside
`fog-plugins` are fully qualified. Nothing here is withdrawn: `qualify()`, its
short-name map and the core-before-plugins order are unchanged and still
load-bearing — they now serve names held in **variables**, which is what
`Route`, `Authorization` and `OpenAPI` hand it, rather than names spelled out
at the call site.

## Amended 2026-08-31 — plugins are namespaced too, and the alias advice is withdrawn

> **Superseded the same day by [ADR 0035](0035-a-plugin-is-laid-out-like-core.md),
> in one respect: the file shape.** This amendment shipped as fog-plugins
> v1.6.22 and said in terms that namespacing was "not a second PSR-4 move".
> It was. v1.6.23 moves every plugin class to
> `<plugin>/src/<Bucket>/<Class>.php` declaring
> `FOG\Plugins\<Segment>\<Bucket>\<Class>`, which is core's own layout, and
> `strtolower(<Segment>)` is the directory name rather than the segment being
> `ucfirst()` of it. Everything else below still holds — plugins are
> namespaced, the alias advice is withdrawn, `qualify()` is the single seam,
> and core is consulted first — so the amendment is left standing with this
> note rather than rewritten.

**Every bundled plugin class declares `FOG\Plugins\<Plugin>\<Class>`.** The
`<Plugin>` segment is `ucfirst()` of the plugin's own directory name, applied
mechanically with no lookup table and no exceptions, so `ldap/class/
ldapmanager.class.php` declares `FOG\Plugins\Ldap\LDAPManager` and
`helloworld/` gets `FOG\Plugins\Helloworld`. The subdirectory is not part of
it: every class in one plugin shares one flat namespace, for the same reason
core's models and managers share one — `FOGController::getManager()` is
`qualify(shortName($this) . 'Manager')`, so a `Model\` / `Manager\` split
would stop resolving.

This reverses the clause in the 2026-08-30 amendment below:

> **Nothing changes for plugins.** They keep the
> `<plugin>/<dir>/<name>.<type>.php` shape, keep the global namespace (ADR
> 0009), and a namespaced plugin page still requires its own `class_alias`.

The first half stands and the second half does not. The file shape is
unchanged — the discovery extensions are how a page, hook, event, report or
task is *found*, so this is namespacing and not a second PSR-4 move — but
plugins are no longer global and no longer need an alias.

> **The sentence above is what ADR 0035 reverses.** It was a second PSR-4
> move, one release later: with a layout the tree can enforce, a plugin class
> is *found* by deriving its path from its name, exactly as core's is, and
> the discovery extensions stop being load-bearing at all.

**Why the alias advice had to go rather than merely being tidied.** It was a
rule whose failure mode was the whole admin UI. A plugin page that declared a
namespace without aliasing itself back did not declare the class its filename
promised; `FOGPageManager::loadPageClasses()` then called `get_class_vars()` on
a name nothing declares, which is an uncaught `TypeError` in PHP 8, thrown from
a constructor `management/index.php` builds before it can render anything. One
third-party plugin, 500 on every page, recovery only from a shell. Documenting
a footgun more clearly is not the same as removing it.

**What made removing it possible.** Discovery had already been taught to route
every derived name through `FOGBase::qualify()` (previous amendment), so there
was exactly one place that had to learn a second map — and `qualify()` is also
what `getClass()`'s ~520 literals, `getManager()`, `FOGPage::$childClass`,
`Route::_newEntity()` and `Authorization::_scopeClassVars()` all go through.
Teaching that one function meant no call site changed, in core or in the
plugins: the roughly 150 `self::getClass('LDAPGroupManager')` literals inside
`fog-plugins` still read exactly as they did.

**Core is consulted first, and that order is the guarantee.**
`Initiator::srcClassMap()` then `Initiator::pluginShortMap()`. Before plugins
were namespaced the same property came from `autoload()` answering `src/` ahead
of the plugin roots; it now has to hold one layer up, where the name is still a
string. Its failure mode is the worst in the tree and is completely silent:
`Authorization::_scopeClassVars()` resolves a node to its model through
`qualify()`, so a plugin winning the bare name `host` is access control testing
the wrong table, with nothing logged.

**The map is READ from each file, never derived from its path.** This is the
one implementation decision that is invisible in the code and load-bearing in
the field. The path says what a plugin class *should* be called; only the file
says whether it declares that name. Deriving would hand `qualify()` a
`FOG\Plugins\` name for a plugin still written globally, and the
`class_exists()` that follows would be false for a class sitting right there —
breaking every unconverted third-party plugin at once. Because the declaration
is read, such a plugin produces no entry, falls through to the bare-name
`$classMap`, and loads exactly as before. **Plugins are installable artifacts
FOG does not control (ADR 0009), so this change is additive or it is wrong.**

**What plugins gain.** Two plugins may now each ship `class/settings.class.php`.
Under the global namespace those folded to one key in `Initiator::$classMap`,
the autoloader picked one, and the loser silently got the wrong class. Today's
bundled tree happens to have zero duplicate basenames across all 176 plugin
classes — that was luck, not a property. A consequence worth naming: the
basename-collision warning in `autoload()` is now *suppressed* when both
colliding files are namespaced plugin classes, because nothing is shadowed and
a warning for a legal configuration is how a log stops being read.

**What is unchanged, and deliberately.** Plugins still reference core by
leading-backslash FQCN — all 403 references across the tree already did, and a
leading backslash means the same thing inside a namespace, which is why the
plugin-side diff is one line per file. `FOG\Plugins\` is the only prefix core
claims: a plugin under a namespace of its own keeps the older contract and must
still `class_alias()` itself back, because discovery still finds it by filename.

**Gated by** `tests/plugin-namespace.test.php` here (the derivation, the
autoload arm, `qualify()`'s precedence, the diagnostics, cache invalidation, and
that a global-namespace plugin still resolves) and
`tests/plugins-are-namespaced.test.php` in `fog-plugins` (every class file
declares the namespace its path implies, and none declares a `class_alias`).
Both were mutation-verified rather than merely observed green.

## Amended 2026-08-31 — decision 3's "no swap is coming" is superseded for the router

**The AltoRouter fork is gone.** `lib/router/altorouter.class.php` and
`altotransformer.class.php` are deleted; `Route.php` now builds its dispatcher
with `nikic/fast-route`, a Composer dependency. Decision 3 below said, of
these two files: *"No swap is coming; the reason for the exclusion is the
license and the attribution, not a pending change."* That line is reversed.

**Why now, and why this is a different swap than the one decision 3
considered.** Decision 3 evaluated one thing: taking `altorouter/altorouter`
itself off Packagist in place of the fork. It correctly rejected that —
`Route::defineRoutes()`'s `->get()/->post()` fluent chain exists only because
of `__call()`, which the fork added and upstream AltoRouter never had, so the
swap it was asked about really would have been "reimplement the fork on top
of the package," which is worse than keeping the fork. This is a different
question: not "which AltoRouter," but "does routing need to be AltoRouter at
all." FastRoute has none of the fork's shape to reproduce, because
`Route.php` doesn't lean on it — the API surface actually used against the
router object was four touches, and reverse-routing
(`generate()`/named-route lookups) had zero callers anywhere in the tree.
There was no fluent chain to keep faithful to; there was a `[target, params,
name]` result contract to preserve, which does not depend on which library
produces it.

**A dedicated regression test existed specifically to force this
conversation, and had to be deleted to do it.**
`tests/altorouter-fork-not-vendored.test.php` counted `->verb()` call sites
in `Route.php` and failed under ten, precisely so that a `defineRoutes()`
rewritten onto a different registration shape would not slip past the gate
quietly. Its own docblock: *"If a future FOG genuinely wants the Packagist
package, this test is what it has to argue with, and rewriting Route's table
into separate map() calls is what would let it. That is the intended
conversation."* This amendment is that conversation, recorded rather than
worked around — the test is deleted, not patched to keep passing.

**What stayed true and drove the design.** The license/attribution reasoning
in decision 3 is still correct as far as it goes — moving someone else's
fork into `FOG\` would still misattribute it, if the fork still existed to
move. It doesn't any more, so that reasoning is moot rather than wrong.
`mysqldump.class.php`'s earlier swap (also referenced in decision 3) is the
closer precedent: a fork that turned out to be almost entirely additive
becomes a thin FOG layer over a real package, once actually measured.

Route's own `[target, params, name]` contract is unchanged, so nothing
downstream of `Route::setMatches()` — permission resolution, the OpenAPI
route-coverage test, plugin route registration via
`Route::pluginRoutes()`/`API_PLUGIN_ROUTES` — needed to change. Plugin
authors declare routes exactly as before; `Route.php` translates
AltoRouter-syntax path strings (`[i:id]`, `[literal|literal]`, dynamic
class-list alternations) into FastRoute's `{name:regex}` syntax internally,
via `Route::_toFastRoutePattern()`, so the documented plugin route shape
never had to change either.

## Amended 2026-08-30 — the last flat classes are bucketed, and the flat namespace is gone

**There is no `namespace FOG;` in core any more.** The 52 discovery-named
classes — 28 pages, 10 hooks, 13 reports, 1 event — that the 2026-08-27
amendment below kept flat under `lib/` are now PSR-4 files under
`src/{Pages,Hooks,Reports,Events}`, declaring `FOG\Pages\HostManagement`,
`FOG\Hooks\BootItem`, `FOG\Reports\Audit_Report`, `FOG\Events\HostList`.
Their `class_alias` trailers are deleted with them. `lib/` now holds only the
two AltoRouter files and the plugin roots.

**The reason they stayed is gone, and it was never the reason it looked
like.** The amendment below says PSR-4 "does not do discovery", which is true
and is not the constraint. The constraint was that the three discovery sites
derived a **bare** class name from `basename($file)` and resolved it from the
global namespace — so the files needed an alias, and an alias is what the rest
of the migration was retiring. Discovery had to learn a second file shape
before the classes could move; it had not been taught one. That is a property
of the loader, not of PSR-4.

**What discovery does now.** Each site reads two sources and merges them:

| Site | Core | Plugins |
|---|---|---|
| `FOGPageManager::loadPageClasses()` | `FOGBase::coreitems('Pages')` | `fileitems('.page.php', 'pages')` |
| `EventManager::load()` (and `HookManager`) | `coreitems($this->fileBucket)` | `fileitems($ext, $dir)` |
| `ReportManagement::loadCustomReports()` | `coreitems('Reports')` | `fileitems('.report.php', 'reports')` |

`coreitems()` filters `Initiator::srcFileList()`, which is already built and
already cached, so this costs no extra walk. The name is then derived by
`FOGBase::classFromDiscoveredFile()`, which strips the discovery extension if
the file carries one and `.php` otherwise, then hands the result to
`qualify()`. That one call spans both shapes without branching on provenance:
`qualify()` maps a name `src/` declares onto its FQCN and passes anything else
through, so a core class resolves under the only name it now has and a
plugin's global-namespace class resolves exactly as it always did.

**Nothing changes for plugins.** They keep the
`<plugin>/<dir>/<name>.<type>.php` shape, keep the global namespace (ADR
0009), and a namespaced plugin page still requires its own `class_alias` —
`fileitems()` and the bare-name derivation are still what find it. The one
edit a plugin needs is to any reference to a class that moved, and in
`fog-plugins` that was a single name in eight files: `\FOG\ReportManagement`
became `\FOG\Pages\ReportManagement`.

**The bridge's job is now empty, and it is kept anyway.**
`Initiator::_bridgeNamespaced()` existed to answer the flat `FOG\<Name>` for
the 52. With them bucketed, `srcClassMap()` holds every one, so the bridge's
first arm refuses the flat spelling with an error naming the correct FQCN
instead of resolving it. That refusal is the whole remaining value — a plugin
still spelling `\FOG\ReportManagement` gets a log line telling it what to
write, rather than a bare class-not-found at the call site.

**One user-visible contract had to be preserved by hand.** A report's filename
was lowercase (`audit_report.report.php`) and three things read it as such: the
menu label, the base64 `f` URL parameter, and the keys of
`Authorization::REPORT_NODES`, which is a permission gate. The PSR-4 filename
is `Audit_Report.php`, so `loadCustomReports()` now lowercases what it used to
get lowercased for free. Without that one call the same report answers to a
different URL and a different permission node than it did before — a silent
authorization change, not a cosmetic one.

**What is gated.** `bin/psr4-scan.php` gained four `RULES` entries deriving the
bucket from ancestry — `ReportManagement => Reports` before `FOGPage => Pages`,
since a report's chain reaches both — so `tests/psr4-layout.test.php` now
covers all 272 classes rather than 220 with 52 excluded by extension.

## Amended 2026-08-27 — decisions 1 and 2 are both superseded

**Decision 1 (a flat `FOG\` namespace) no longer holds.** Every class under
`packages/web/src/` now declares `namespace FOG\<Bucket>;` matching the
directory it sits in: `FOG\Items\Host`, `FOG\Managers\HostManager`,
`FOG\Db\PDODB`. `bin/namespace-fog-classes.php --check`, wired into
`tests/namespaced-tree.test.php`, enforces the file's directory and its
namespace agreeing.

**Decision 2 (the reverse alias as the 1.6 plugin ABI) is retired.** All 202
`class_alias()` trailers under `packages/web/src/` are deleted. Core is
reachable only by its namespaced name. This is the clause being reversed:

> This alias is **the 1.6 plugin ABI.** It is supported for all of 1.6.
> Removing it is a breaking change and cannot happen before 1.7.

Reversed because 1.6.0 is unreleased. There is no shipped 1.6 for the promise
to have been made to, and carrying a compatibility shim through a major version
for compatibility with nothing is a cost with no payer. Nothing else in this
ADR freezes the plugin contract.

**What replaced it, in order of how much work each was:**

- Core names its own dependencies with `use` imports — 211 of them across 92
  files in `lib/`, `commons/`, `service/`, `api/`, `management/` and
  `packages/service`. Imports rather than inline qualification because the
  volume is lopsided: one `lib/` file carries 165 references to
  `HTTPResponseCodes`.
- `FOGBase::qualify()` translates a bare short name to its FQCN for the
  string-driven paths, which `use` cannot reach: `getClass()`'s ~350 literals,
  `FOGController::getManager()`, `Route::_newEntity()` for `$validClasses`'
  52 lowercase strings, and `FOGPage`'s `$childClass`.
- `fog-plugins` qualifies every reference to a core class — 574 across 163
  files, pinned by `tests/core-references-are-qualified.test.php`, which needs
  no fogproject checkout to run.

**Three things the retirement broke that nothing was watching, all found by
running it rather than by reading it.** They are the reason this is written
down as more than a version bump:

1. `Authorization::_scopeClassVars()` resolved a node to its model with a bare
   `class_exists($node)`. Without the alias it is simply false, `$vars` stays
   null, and `objectInScope()` has no table to test against — access control
   that cannot find its own model, silently, with nothing logged.
2. `PluginRunner` filtered tasks with `is_subclass_of($class, 'PluginTask')`.
   A class name **in a string** is resolved as written, with no namespace
   applied and no `use` consulted, so the literal named the global
   `\PluginTask`. Without the alias every plugin task is silently skipped.
3. The built-in `spl_autoload()` was registered behind `Initiator::autoload()`
   as a fallback. `autoload()` refusing a bare core name is only a `return`,
   and PHP then carries on down the chain — where the built-in probes
   include_path by basename, and include_path covers the plugin roots. So a
   plugin shipping `class/host.class.php` answered the bare `Host` the moment
   core stopped answering it. Core winning the classMap collision does not
   help: core is not in that map at all, so there is no collision to resolve.
   The fallback is removed; see decision 2b.

**One thing the plan got wrong, which is the load-bearing correction.**
`docs/composer-psr4-plan.md` said to delete "the 202 `class_alias` lines and
the reverse bridge arm from `Initiator`". The bridge arm
(`Initiator::_bridgeNamespaced()`) **stays**. The 46 discovery-named classes
under `lib/` — 26 pages, 10 hooks, 9 reports, 1 event — declare a flat
`namespace FOG;` and keep their own `class_alias`, because
`FOGPageManager::loadPageClasses()` and `EventManager::load()` derive the class
name from `basename($file)` and PSR-4 does not do discovery. Composer maps
`FOG\` onto `src/`, so `use FOG\ReportManagement;` in a core file resolves to
`src/ReportManagement.php`, which does not exist. The bridge is what answers
it, and deleting it breaks every core file that imports one of the 46.

### 2b. The built-in `spl_autoload()` fallback is removed

`Initiator::__construct()` no longer calls the bare `spl_autoload_register()`.
See failure 3 above: with core absent from both the classMap and the global
namespace, that fallback was a plugin's route to supplying a core class.

What it covered, it no longer needs to. `include_path` and the classMap are
both derived from `classFileList()`, so the only thing the probe could find
that the map could not is a file added to an already-scanned directory since
the cache was written — and that window is closed at both ends:
`Plugin::install()` calls `forgetClassFileList()`, and the cache expires on
`FILELIST_TTL` regardless.

### What plugin authors have to change

`extends Host` becomes `extends \FOG\Items\Host`, or a `use` import.
`getClass('Host')` is unaffected — that string goes through
`FOGBase::qualify()`. A plugin's own classes stay in the global namespace and
are still found by basename (ADR 0009), and `class_alias` on a plugin's own
page class is still **required**, because that is how
`loadPageClasses()` finds it.

The error is legible rather than a class-not-found at the call site: the
autoloader recognizes a bare core name and says which FQCN to use.

### The argument against nesting was wrong, and this is the correction

The section *"Why flat, when mirroring the directories is the obvious
instinct"* rests on `FOGController::getManager()`:

> Under a flat namespace, `Host` + `'Manager'` gives `HostManager`, which
> resolves. Under a split one it gives `Model\HostManager`, which does not
> exist.

**It does not.** `FOGBase::shortName()` (`src/Base/FOGBase.php:543`) is
`strrpos($name, '\\')` and returns everything after the LAST separator, so
`shortName($this)` on a `FOG\Items\Host` returns `Host`, not
`FOG\Items\Host`. The concatenation yields the bare `HostManager`, a string,
and `new $man` resolves a string from the GLOBAL namespace — where decision 2's
alias has put it. `FOGManagerController`'s inverse `preg_replace('#_?Manager$#',
'', ...)` is fed the same short name and is equally unaffected. Neither
derivation ever saw a namespace, under either layout.

So the price the ADR declined to pay was never charged. No derivation had to be
taught to move between namespaces, and the two new instances of the
assembled-string bug class it feared were not created.

### What DID break, which the ADR did not anticipate

Three sites built a **collaborator's** name from the **caller's**
`__NAMESPACE__`, which is only correct while every class shares one namespace:

| Site | Built | Should be |
|---|---|---|
| `Auth/Authorization.php:1799` | `FOG\Auth\host` | the bare `$node` |
| `Service/FOGReplicator.php:126` | `FOG\Service\Image` | `FOG\Items\Image` |
| `Service/FOGItemScanner.php:148` | `FOG\Service\Image` | `FOG\Items\Image` |

The `Authorization` one is the instructive failure: `class_exists()` would have
been permanently false, so `_scopeClassVars()` would have silently returned
null on every object-scope lookup, with no error anywhere. The other two fatal
loudly and the suite caught them. That asymmetry — a guard that fails closed and
says nothing, next to two that crash — is the same shape as #1215/#1216, and it
is the reason `__NAMESPACE__` is now used for exactly one thing in `src/`:
a file naming ITSELF, in its own `class_alias`. All 202 remaining uses are that.

The rule from *"Where this does not apply"* survives inverted: the tree is now
nested throughout `src/`, and `__NAMESPACE__` concatenation is the construct
that must not appear, not the layout.

Removing the aliases remains a separate, later decision; nothing here touches
the *"supported for all of 1.6"* clause in decision 2.

## Context

Phase 3 of the refactor moves FOG's 226 class files into a namespace. Two
decisions had to be made before a single file could move: what shape the
namespace takes, and what happens to the tens of thousands of existing
references to the bare names.

The second question is the larger one. FOG's classes are referenced by name from
places this repository cannot see: 169 tracked files in `FOGProject/fog-plugins`
inherit from `Hook`, `FOGController`, `FOGManagerController`, `FOGPage`,
`ReportManagement`, `Event`, `TaskType` and `PluginTask`, and an unknowable
number of third-party plugins under `FOG_PLUGIN_DIR` do the same. A migration
that required every one of them to be edited is not a migration; it is a fork.

### What survives a rename and what does not

The useful split is **consumes** versus **produces**, and it is sharper than it
first looks.

Anywhere PHP *consumes* a string as a class name — `new $s`,
`$obj instanceof $s`, `class_exists($s)`, `is_subclass_of($s, ...)`,
`get_class_vars($s)`, `new ReflectionClass($s)`, `[$s, 'method']` — the lookup is
a case-insensitive hit against the global class table. `class_alias()` puts a
real entry in that table, so **an alias saves all of them.**

Anywhere PHP *produces* a name — `get_class()`, `::class`, `__CLASS__`,
`ReflectionClass::getName()`, `get_declared_classes()` — it returns the
**declared** name and never the alias. **An alias saves none of them.**

That asymmetry is the whole design. It means the migration splits cleanly into a
part that is free (every consumer) and a part that has to be found and fixed by
hand (every producer). The producers were fixed first and separately, in #1099:
47 sites in 18 files, routed through `FOGBase::shortName()` and held by
`tests/class-name-derivation.test.php`.

## Decision

### 1. A flat `FOG\` namespace

Every converted class is `FOG\<Name>` — `FOG\Host`, `FOG\HostManager`,
`FOG\HostManagement`, `FOG\HostAddVncLink`. No sub-namespaces.

### 2. Each file aliases its own name back into the global namespace

```php
namespace FOG;

class Host extends FOGController { /* ... */ }

class_alias(__NAMESPACE__ . '\Host', 'Host');
```

This alias is **the 1.6 plugin ABI.** It is supported for all of 1.6. Removing
it is a breaking change and cannot happen before 1.7.

> **Superseded 2026-08-27.** Retired before 1.6.0 shipped; see the amendment at
> the top of this file. The alias survives only on the 46 discovery-named
> files under `lib/`, where `loadPageClasses()` requires it.

### 3. Three files are never converted

`lib/router/altorouter.class.php` and `lib/router/altotransformer.class.php`
keep upstream's name, authorship and MIT license, and moving someone else's
class into `FOG\` misattributes it. `commons/init.php` is the autoloader, and a
namespaced autoloader that has to load itself is a bootstrap problem in
exchange for nothing.

References to those three from converted files are backslash-qualified.

**Amended after the fact.** This section originally listed four files and gave
"vendored, and swap candidates for their Packagist releases" as the reason for
three of them. Both halves of that turned out to be wrong in opposite
directions, and the entries have been corrected rather than left:

- `lib/db/mysqldump.class.php` **was** a vendored copy — upstream v2.12 with
  one substantive local change in 2388 lines — and the swap happened. It is now
  a short FOG subclass of `ifsnop/mysqldump-php` and is converted like any other
  core class, so it is off this list. That also retired the "thirteen types in
  one file" clause, which was the last thing in the tree making PSR-4
  impossible.
- The two altorouter files are a **fork**, not a copy. 324 of 357 code lines
  differ from every upstream tag and from `master`: `__call()` for
  `->get()`/`->post()`/…, on which `route.class.php` depends in 29 places,
  fluent setters, transformers, default params and case-insensitive matching.
  Taking the Packagist release would mean reimplementing the fork on top of it.
  No swap is coming; the reason for the exclusion is the license and the
  attribution, not a pending change.

  > **Superseded 2026-08-31.** Both files are deleted; routing runs on
  > `nikic/fast-route`. See the amendment at the top of this file — this was
  > never the swap this paragraph rejected (that was "which AltoRouter", not
  > "does it need to be AltoRouter"), but the "no swap is coming" line still
  > has to be recorded as reversed rather than left standing.

## Why flat, when mirroring the directories is the obvious instinct

The obvious layout is `FOG\Model\Host`, `FOG\Manager\HostManager`,
`FOG\Page\HostManagement`. It loses on a concrete, load-bearing fact rather than
on taste.

**`FOGController::getManager()` derives the manager's class name from the
model's own** (`fogcontroller.class.php`):

```php
$man = self::shortName($this) . 'Manager';
return new $man;
```

and `FOGManagerController::__construct()` does the exact inverse:

```php
$this->childClass = preg_replace('#_?Manager$#', '', self::shortName($this));
```

Under a flat namespace, `Host` + `'Manager'` gives `HostManager`, which resolves.
Under a split one it gives `Model\HostManager`, which does not exist — on the
most-traveled path in the ORM, run by every model and every manager.

That could be fixed, by teaching both derivations to move between namespaces.
But doing so would *create* two new instances of the exact bug class Phase 3
exists to remove: a class name assembled as a string, in code where getting it
wrong fails silently. Paying that price to buy a directory-shaped taxonomy is a
bad trade.

Two further costs, smaller but real:

- The Phase 0.2 bridge deliberately refuses nested names, and
  `docs/plugin-development.md` has been telling plugin authors to write
  `FOG\Host` since it shipped. A nested layout breaks anyone who took that
  advice, over a decision made after the advice was given.
- `lib/fog/` holds models, managers, base classes, `SiteScope`, `Route`'s peers
  and a dozen utilities. Mirroring the directories literally gives `FOG\Fog\Host`;
  mirroring a *conceptual* taxonomy instead means inventing a mapping no
  directory expresses, maintaining it by hand, and having 226 chances to file a
  class in the wrong bucket — where the penalty for guessing wrong is a class
  that does not resolve.

**What flat costs, honestly:** one namespace with 226 names, and no
compiler-enforced separation between a model and a page. That separation would
have been advisory anyway — PHP does not stop a page from doing database work —
and the tree already communicates the same thing through filenames and the
`*Manager` / `*Management` suffixes, which are load-bearing because the two
derivations above depend on them.

**Where this does not apply:** genuinely new code under `packages/web/src/`,
which Phase 0.3 created empty for exactly this reason. `FOG\Auth\OidcProvider`
is fine — nothing there derives a name arithmetically and nothing legacy
consumes it. The rule is *flat for the migrated legacy tree, nested for new
subsystems in `src/`*.

## Consequences

### For plugin authors

Nothing breaks, and nothing is required. `class MyHook extends Hook` keeps
resolving through the alias, on every version of 1.6.

Two things to know:

- **`FOG\Foo` is the forward-compatible spelling.** Write it in new code. Bare
  `Foo` works for all of 1.6.
- **`get_class($this)` now returns `FOG\Foo`.** If your plugin compares it to a
  string literal, or uses it to build a column name, an array key or a filename,
  it must be updated. `FOGBase::shortName($this)` returns the bare name and is
  the supported way to do it. This is the *only* source-level change 1.6 asks of
  a plugin, and it only affects plugins that produce a class name rather than
  consume one.

### Partial conversion is safe, which is why it could be batched

A converted file's unqualified `extends FOGController` asks the autoloader for
`FOG\FOGController`, and `Initiator::_bridgeNamespaced()` answers that whether or
not `fogcontroller.class.php` has itself been converted. A half-converted tree is
fully functional and file order does not matter.

The bridge needed one change to support this: once a file declares the
namespaced name *itself*, there is nothing to bridge, and aliasing again emits
`Cannot declare class FOG\X, because the name is already in use` on every class
load.

### `get_declared_classes()` is the one consumer an alias does not cover

It produces names rather than consuming them, so an alias never appears in its
output. There is exactly one site, `fogpagemanager.class.php:307`, and it was
already inert before this change — it compares case-sensitively against a
lowercased basename and has never matched.

### What is now gated

| Test | Holds |
|---|---|
| `tests/namespaced-tree.test.php` | every class file is namespaced and aliased; no converted file references the four excluded classes unqualified |
| `tests/class-name-derivation.test.php` | every `get_class()`/`__CLASS__` goes through `shortName()` or is a marked consumer |
| `tests/all-classes-load.test.php` | every declared class can actually be declared |
| `tests/getclass-literals.test.php` | every `getClass()` literal matches a declared class name exactly |

## The failure this cost, and why it is written down

Commit 2 of the migration — the 226 files themselves — passed all 26 tests in
`tests/`, and every one of the 226 classes resolved under both the bare and the
namespaced spelling. It also could not render a single page.

From inside `namespace FOG;`, `Initiator::e($x)` resolves to `FOG\Initiator`.
That class does not exist, and for `Initiator` it never can: `commons/init.php`
is not a `*.class.php` file, so it is not in the autoloader's map at all and no
bridge can reach it. There were 133 such references across 26 files, 132 of them
to `Initiator` — which is FOG's output escaper, and therefore on essentially
every page FOG renders.

Nothing in the test suite could see it, because every test in `tests/` either
scans source text or resolves class names, and the failure was neither. It was
found by booting the tree against the live server's database and diffing the
result against `origin/working-1.6` run from the same directory.

The generalisable lesson, and the reason `tests/namespaced-tree.test.php` exists:
**"every class resolves" is not "the application works."** A refactor that
changes name resolution needs at least one check that actually executes the
application against real data, because resolution tests are exactly the tests
that a resolution bug can pass.

## References

- `docs/refactor-phase3-plan.md` — the plan, its evidence, and the alternatives
- #1098 dead `Storage` steering, #1099 `shortName()` and the 47 derivation sites,
  #1100 the case-inconsistent literals, #1102 this migration
- ADR 0009 (plugins become installable artifacts) for why plugin code lives
  outside this repository and cannot be swept
