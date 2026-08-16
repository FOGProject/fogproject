# A flat `FOG\` namespace, and the reverse alias as the 1.6 plugin ABI

## Status

accepted

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

### 3. Four files are never converted

`lib/db/mysqldump.class.php`, `lib/router/altorouter.class.php` and
`lib/router/altotransformer.class.php` are vendored, and are swap candidates for
their Packagist releases; hand-editing them makes those swaps harder, and
`mysqldump.class.php` declares thirteen types in one file besides.
`commons/init.php` is the autoloader, and a namespaced autoloader that has to
load itself is a bootstrap problem in exchange for nothing.

References to those four from converted files are backslash-qualified.

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
