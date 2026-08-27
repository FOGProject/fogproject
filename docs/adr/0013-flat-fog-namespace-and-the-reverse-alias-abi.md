# A flat `FOG\` namespace, and the reverse alias as the 1.6 plugin ABI

## Status

accepted

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
autoloader recognises a bare core name and says which FQCN to use.

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
most-travelled path in the ORM, run by every model and every manager.

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
