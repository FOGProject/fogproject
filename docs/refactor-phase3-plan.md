# PHASE 3 — Namespacing

Plan only. Five PRs, in order. Baseline: `working-1.6` @ `61360dc24`.

**Evidence method** is the same as the Phase 0 plan: PHP's own tokenizer over
`git ls-files` output, plus live prototypes run against this checkout. Claims are
marked `VERIFIED` (I ran it), `INFERRED` (I reasoned it), `UNKNOWN` (I can't see
it from here). Phases 0 and 1 are complete; this document assumes both.

---

## The headline: the brief's numbers are wrong, and the job is smaller than it says

Five measurements, all `VERIFIED`, that change the shape of the work:

| The brief assumes | Measured on `61360dc24` |
|---|---|
| 227 classes | **248 classes + 1 interface + 2 traits**, across 346 tracked PHP files |
| `getClass()` is 491 sites / 136 names | **97 distinct literals, 350 literal sites**, plus 74 dynamic sites in 23 files |
| "derive the dependency graph, don't guess at leaves" | Graph is nearly flat: **233 of 248 are leaves**, max depth 3, only **18 classes have any in-repo subclass** |
| `get_class()` risk is diffuse and scary | **47 producer sites in 18 files.** Complete, enumerable, and fixable before anything is namespaced |
| `getClass()` must become a short-name→FQCN map | **It needs no change at all.** A reverse `class_alias` in each namespaced file does the same job for free — `VERIFIED` by prototype |

The last row is the one that collapses the estimate. The brief's plan was to build
a resolution map so 350 call sites never have to be edited. But a file that
declares `namespace FOG;` and ends with `class_alias(__NAMESPACE__.'\Host', 'Host');`
keeps answering to the bare name — so *nothing that consumes a class name needs
editing*, `getClass()` included. What breaks is only the code that **produces** a
name, and that is the 47 sites.

**So Phase 3 is: fix 47 sites, then add two lines per file, 248 times.**

---

## Six findings

### F1 — The inheritance graph is flat, so "leaves first" is almost "everything at once"

`VERIFIED`, tokenizer over `git ls-files "*.php"`, following `extends`, `implements`
and `use <Trait>`:

- Depth 0: 30, depth 1: 33, depth 2: 168, depth 3: 20. Nothing deeper.
- 233 of 248 classes have **no in-repo subclass at all**.
- The 18 that do, with their dependent counts:

| Class | Subclasses | File |
|---|---:|---|
| `FOGManagerController` | 60 | `lib/fog/fogmanagercontroller.class.php` |
| `FOGController` | 58 | `lib/fog/fogcontroller.class.php` |
| `FOGPage` | 24 | `lib/fog/fogpage.class.php` |
| `FOGBase` | 24 | `lib/fog/fogbase.class.php` |
| `FOGService` | 10 | `lib/service/fogservice.class.php` |
| `Hook` | 10 | `lib/fog/hook.class.php` |
| `FOGClient` | 10 | `lib/client/fogclient.class.php` |
| `ReportManagement` | 9 | `lib/pages/reportmanagement.page.php` |
| `TypeAdapterFactory`, `CompressManagerFactory` | 4 each | `lib/db/mysqldump.class.php` (vendored) |
| `Event`, `TaskingElement` | 2 each | |
| `FOGCron`, `TaskType`, `EventManager`, `FOGPageRender`, `FOGPagePost` | 1 each | |

**Why this matters:** `extends \FOGController` from inside `namespace FOG;` works
whether or not `FOGController` has been namespaced yet — the leading `\` is what
Phase 0.1's prefixing pass already guaranteed for globals, and here we add it for
FOG classes too. So a class can be namespaced independently of its parent, and the
"leaves first" ordering the brief asks for is a *convenience*, not a constraint.

`INFERRED` caveat: the scanner keys declarations by name, so
`tests/grid-order-clause.test.php`'s stub `FOGBase` and `DatabaseManager` shadowed
the real ones in the raw output. `VERIFIED` by grep that the real `FOGBase` is
`lib/fog/fogbase.class.php:24`; the counts above are corrected. Two of the 30
depth-0 entries and two of the 18 internal nodes are test stubs, not production
classes.

### F2 — `getClass('Storage')` is dead code, and removing it breaks nothing

The brief asks to remove the `global $sub` steering at `fogbase.class.php:557-563`
and to say what that breaks. **Answer: nothing.** `VERIFIED` three ways:

1. `getClass('Storage')` has **zero callers.** The only literal `'Storage'` in a
   `getClass()`/`getManager()` argument position anywhere in the tree is absent —
   the 97 literals include `StorageNode` and `StorageGroup`, never `Storage`.
2. The guard is `$class === 'Storage'`, **case-sensitive `===`**. So even a
   hypothetical `'storage'` misses it.
3. The other reachable path is `ucfirst($node)` (`fogpage.class.php:352`), and
   there is no node named `storage` — the nodes are `storagegroup` and
   `storagenode`. `ucfirst()` cannot produce `Storage` from either.

It is also the only `global` in `getClass()`, and it makes the factory's result
depend on request state, which is exactly the sort of thing that turns a
namespacing pass into a debugging session. Delete it in its own commit, ahead of
everything else, so if I am wrong the bisect is one line long.

### F3 — `getClass()` needs no map. Reverse aliasing does the whole job.

`VERIFIED` by prototype (`scratchpad/nsproto/`, run against PHP on this box). A
file namespaced as `FOG\Host` with a trailing `class_alias(__NAMESPACE__.'\Host', 'Host');`:

| Consumer | Result |
|---|---|
| `new Host()` unqualified | works |
| `class_exists('host')`, `class_exists('HOST')` | `true` — **PHP class lookup stays case-insensitive through the alias** |
| `$obj instanceof $str` where `$str = 'Host'` | `true` |
| `new ReflectionClass('Host')` | works; `->getName()` returns `FOG\Host` |
| `getManager()` → `get_class($this).'Manager'` | returns `FOG\HostManager` — **resolves correctly** |
| `preg_replace('#_?Manager$#','',get_class($this))` | returns `FOG\Host` — resolves correctly |
| `in_array('Host', get_declared_classes(), true)` | **`false`** — the one that does not survive |

`getClass()` also needs no map for the two edge cases it already carries:
`getClass('DateTimeZone')` (a PHP built-in through FOG's factory, 1 site) and the
`reflectionclass` special case at `:551` both keep working, because
`new \ReflectionClass($string)` resolves aliases and built-ins alike.

**This is the single biggest simplification against the brief.** No map to build,
no map to keep in sync with the tree, no 350 call sites to review, and the
migration becomes commutative — files can be namespaced in any order, and a
half-migrated tree is fully functional.

### F4 — 47 producer sites, in 18 files, are the entire blast radius

`VERIFIED` — every `get_class()`, `__CLASS__`, `::class` and `static::class` in
tracked PHP, excluding `vendor/`, excluding `tests/`, and excluding the ~50
`[__CLASS__, 'method']` AltoRouter callables (which are consumers and safe):

| File | Sites |
|---|---:|
| `lib/fog/fogcontroller.class.php` | 17 |
| `lib/fog/fogpagemanager.class.php` | 5 |
| `lib/service/fogservice.class.php` | 4 |
| `commons/init.php` | 4 |
| `lib/fog/host.class.php`, `fogbase.class.php`, `eventmanager.class.php` | 2 each |
| 11 further files | 1 each |

The Phase 0 plan already enumerated what each one derives; that analysis stands
and is not repeated here. The point that is new is the **count**: this is a
bounded, reviewable list, not an open-ended hazard. Every one of them can be fixed
*before* a single file is namespaced, and each fix is independently testable
because the fixes are no-ops on a non-namespaced tree.

The four highest-consequence ones, restated because they set PR 3.1's scope:

- `eventmanager.class.php:63` — `switch (get_class($this))` with a throwing
  `default`. Namespaced without a fix, **no hook and no event registers, silently.**
- `authorization.class.php:1014` (reached from `fogcontroller.class.php:924`) —
  `switch (strtolower(get_class($this)))` with `default: return;`. Namespaced
  without a fix, **the last-administrator lockout guard stops running.**
- `fogcontroller.class.php:1386,1445,1520,1525` — class name becomes a **DB column
  name**. Namespaced without a fix, every association write targets `fog\hostID`.
- `fogmanagercontroller.class.php:156` → `:1102`, `:1124` — class name becomes an
  HTML `name=` attribute and a `Route::listem()` argument checked against
  `Route::$validClasses`, where `fog\host` is not a member.

The fix in every case is the same shape and is worth stating once: **derive the
short name, not the FQCN.** A single helper — `FOGBase::shortName($objOrClass)`,
returning `substr(strrchr('\\'.$fqcn, '\\'), 1)` — replaces `get_class($this)` at
each site. It is a no-op today (no namespaces, so `strrchr` finds only the prefix
we prepend) and correct after. That is what makes PR 3.1 shippable on its own.

### F5 — Six case-inconsistent `getClass()` literals, one a genuine typo

`VERIFIED`, comparing every `getClass()`/`getManager()` string literal against the
declared class names:

| Literal | Sites | Actually declared as |
|---|---:|---|
| `MACAddressASsociationManager` | 1 | `MACAddressAssociationManager` — **a typo**, capital S mid-word |
| `filedeletequeue` | 3 | `FileDeleteQueue` |
| `filedeletequeuemanager` | 2 | `FileDeleteQueueManager` |
| `snapinjob` | 2 | `SnapinJob` |
| `iPXE` | 1 | `Ipxe` |
| `DateTimeZone` | 1 | *(a PHP built-in, not a FOG class — correct as written)* |

All six work today and, per F3, **all six keep working after namespacing**, because
alias lookup is case-insensitive. So this is hygiene, not a blocker — but it is
hygiene with a real cost if left: the typo means a reader grepping for
`MACAddressAssociationManager` misses a live call site, and the lowercase forms
mean the tree teaches the wrong convention. Fix them in their own commit, before
the namespacing, so the namespacing diff stays purely mechanical.

`INFERRED`: these six are the only ones. The scan covers literal arguments to
`getClass`/`getManager` only; a class name reaching `new $var` through a variable
would not be compared. 74 such dynamic sites exist across 23 files.

### F6 — Plugins are 169 files deep in these base classes, and the alias is what saves them

`VERIFIED` against the `fog-plugins` checkout: 64 classes extend `Hook`, 19 extend
`FOGManagerController`, 19 extend `FOGController`, 14 extend `FOGPage`, 8 extend
`ReportManagement`, 7 extend `Event`, plus `TaskType`/`TaskTypeManager` subclasses.

None of that needs to change, in 1.6, ever — `class Foo extends Hook` keeps
resolving through the reverse alias. That is the entire compatibility story, and
it is why the alias is not optional and not a transitional convenience: **it is the
1.6 plugin ABI.** Removing it is a 1.7 breaking change, not a Phase 3 cleanup.

`VERIFIED` end to end by prototype, on the call that matters most: a plugin task
file written the pre-namespace way and **never edited** still satisfies
`is_subclass_of($class, 'PluginTask')` at `pluginrunner.class.php:226` after
`PluginTask` becomes `FOG\PluginTask`. That one call gates every plugin background
task (ADR 0010), so it is the load-bearing proof of the whole approach.

The one place plugins *do* get hurt is F4's category — a plugin that does
`get_class($this)` and compares it to a literal. `UNKNOWN` how many do; the 169
tracked files can be swept, but third-party plugins in `/opt/fog/plugins` cannot.
This is what the deprecation note in PR 3.5 exists to warn about.

---

## The namespace layout: flat `FOG\`, and here is why your instinct loses

You said you lean toward mirroring the directory structure —
`FOG\Model\Host`, `FOG\Manager\HostManager`, `FOG\Page\HostManagement`,
`FOG\Hook\…` — and asked for an argument rather than agreement.

**Recommendation: flat `FOG\`.** Three reasons, in descending order of how much
they cost to get wrong.

### 1. Models and managers derive each other's names arithmetically. Nested breaks it.

`VERIFIED` at `fogcontroller.class.php:1364`:

```php
public function getManager()
{
    $man = get_class($this).'Manager';
    return new $man;
}
```

and the inverse at `fogmanagercontroller.class.php:156`:

```php
$this->childClass = preg_replace('#_?Manager$#', '', get_class($this));
```

Under flat, `FOG\Host` + `'Manager'` → `FOG\HostManager`, which exists —
`VERIFIED` by prototype. Under nested, it produces `FOG\Model\HostManager`, which
does not exist, and `new $man` is a fatal on the single most-travelled path in the
ORM.

You could fix it — rewrite both derivations to move between namespaces. But that
means the layout decision *creates* two new instances of exactly the bug class
Phase 3 is trying to eliminate, in the two methods every model and every manager
runs. Flat leaves both correct without touching them.

This alone decides it. `FOG\Model` and `FOG\Manager` cannot be separate namespaces
without rewriting the ORM's name derivation.

### 2. Nested invalidates the Phase 0.2 bridge, which is already shipped and documented

The bridge deliberately refuses nested names — `if (strpos($short, '\\') !== false) return;`
— with the comment "a nested request is Phase 3's shape and must miss here rather
than silently resolve to the wrong file." That was written to keep the option open,
and taking it now means rewriting the bridge and re-documenting `FOG\Host` in
`docs/plugin-development.md` as having been wrong. Any plugin author who took the
Phase 0.3 advice and wrote `FOG\Host` gets broken by a layout we chose after
telling them to write it.

### 3. The directory structure isn't the taxonomy you'd want anyway

`lib/fog/` holds models, managers, the base classes, `Route`'s peers, `SiteScope`,
`Initiator`'s helpers and a dozen utilities. Mirroring it gives `FOG\Fog\Host`.
Mirroring the *conceptual* taxonomy instead means inventing a mapping that no
directory expresses, maintaining it by hand, and having 248 chances to file a class
in the wrong bucket — where the penalty for guessing wrong is a class that does not
resolve.

### What flat costs, honestly

One namespace with 248 names, and no compiler-enforced separation between a model
and a page. That is a real loss, and it is the argument for your instinct. But the
separation it would buy is advisory — PHP does not stop `FOG\Page\HostManagement`
from doing database work — and the tree already communicates the same thing through
filenames and the `*Manager` / `*Management` suffixes, which are load-bearing
(they drive the two derivations above and `FOGPageManager`'s discovery).

**Where I would revisit this:** genuinely new code in `packages/web/src/`, which
Phase 0.3 created and left empty precisely so new work does not inherit the legacy
tree's constraints. `FOG\Auth\OidcProvider` for Phase 2 is fine — it has no
arithmetic name derivation and no legacy consumers. The rule is
*flat for the migrated legacy tree, nested for new subsystems in `src/`*, and that
is a defensible line rather than a compromise: the flat namespace is a mirror of
what already exists, not a design.

---

## PR sequence

Five PRs. Each is green on its own, and 3.0–3.2 are all shippable without ever
starting 3.3 — which is the point. If Phase 3 gets abandoned halfway, the tree is
strictly better than it started.

> **Status, 2026-08-16: Phase 3 is complete.** `working-1.6` @ `5c3bbf69f`,
> suite 23 → 27 tests. #1098 (dead `Storage` steering), #1099 (`shortName()` and
> the 35 derivation sites), #1100 (the case-inconsistent literals), #1101
> (`PluginTask` was unloadable — found while verifying, fixed separately),
> #1102 (the 226-file migration), #1103 (ADR 0013 and the plugin guidance).
>
> Two things below did not survive contact and are worth reading against what
> actually happened. The plan sized 3.3 as batches; it went in one pass, because
> the bridge makes a half-converted tree work and file order stops mattering.
> And the plan's verification section was wrong about what would be sufficient:
> the migration passed every test in `tests/` *and* resolved all 226 classes
> under both spellings while being unable to render a page. See ADR 0013.

### PR 3.0 — Remove the dead `Storage` steering

One commit, six lines deleted from `fogbase.class.php:556-563`, plus the `global $sub`
that only it used. Comment the removal with the three-way proof from F2.

```
git ls-files '*.php' | xargs grep -n "getClass('Storage')\|getClass(\"Storage\")"   # no output
sh tests/run-all.sh                                                                  # 23/23
```

### PR 3.1 — Stop deriving strings from FQCNs (the load-bearing PR)

The one that has to be right. Nothing here is namespacing; every change is a no-op
on today's tree, which is what makes it independently reviewable and independently
shippable.

**C1.1** — add `FOGBase::shortName($objOrClass)` plus a unit test pinning it
against both `Host` and `FOG\Host`.

**C1.2** — convert the 47 sites. Each gets a one-line comment saying what the
derived string is *for* (a column name, a switch case, an HTML attribute), because
that is the fact a future reader needs and cannot recover from the code.

**C1.3** — add `tests/class-name-derivation.test.php`: a scanner that fails if any
tracked PHP file outside `vendor/` reaches `get_class()` without going through
`shortName()`. This is the gate that stops the bug class coming back, and it is
worth more than the fixes themselves.

```
php tests/class-name-derivation.test.php    # exit 0, "47 sites, 0 unguarded"
sh tests/run-all.sh                         # 24/24
```

Live verification, because three of these sites are invisible to a unit test — do
these on the lab before merging: register a hook (proves `eventmanager.class.php:63`),
delete a user down to the last admin (proves the `authorization.class.php:1014`
lockout guard), save a host with a group association (proves the column-name
derivation), and run a snapin replication (proves `fogservice.class.php:437`).

### PR 3.2 — Fix the six case-inconsistent literals

Six edits. `MACAddressASsociationManager` is a typo; the rest are lowercase forms.
Separate PR so PR 3.3's diff can be honestly described as mechanical.

### PR 3.3 — Namespace the tree

Per file, exactly two lines: `namespace FOG;` after the docblock, and
`class_alias(__NAMESPACE__ . '\\<Name>', '<Name>');` at the end. Plus a `\` in
front of every remaining unqualified reference — which Phase 0.1 already did for
globals, so what remains is FOG-class references, and `bin/prefix-global-classes.php`
can be extended to a `--fog` mode that uses the tokenizer's own declaration list
instead of `ReflectionClass::isInternal()`.

Batching, derived from F1 rather than guessed:

| Batch | Contents | Count |
|---|---|---:|
| A | The 8 real base classes (`FOGBase`, `FOGController`, `FOGManagerController`, `FOGPage`, `FOGService`, `Hook`, `FOGClient`, `ReportManagement`) + the 2 traits | 10 |
| B | Models and managers, alphabetically, ~20 per commit | ~120 |
| C | Pages, hooks, events, reports | ~70 |
| D | Services, client modules, reg-task, router | ~40 |
| E | **Excluded — do not namespace** | 8 |

Batch A goes **first**, not last. That inverts the brief's "leaves first", and it
is deliberate: F1 shows the leaves are independent of their parents either way, and
doing the 8 bases first means every subsequent batch is exercising the finished
state of the thing all 233 leaves inherit from. Leaves-first would mean 233 files
validated against a base class that then changes.

Batch E is the exclusion list, and it needs stating explicitly:

- `lib/db/mysqldump.class.php` (13 classes in one file, vendored `ifsnop/mysqldump-php`)
  and `lib/router/altorouter.class.php` (vendored `altorouter/altorouter`) — both
  are swap candidates for their Packagist releases per the Phase 0.3 follow-ups.
  Namespacing them by hand makes those swaps harder, not easier.

  **Both follow-ups are now closed, in opposite directions**, and the exclusion
  list has moved with them (see ADR 0013 §3). `mysqldump.class.php` really was a
  vendored copy of v2.12 with one substantive local change, the swap happened, and
  it is now a converted FOG subclass — which also removed the "13 classes in one
  file" fact this list cites. `altorouter.class.php` is a **fork**, not a copy:
  324 of 357 code lines differ from every upstream tag and from `master`, and
  `route.class.php` depends in 29 places on a `__call()` upstream does not have.
  It stays excluded, but because of the license and attribution it carries, not
  because a swap is pending.
- `Initiator` (`commons/init.php`) — it *is* the autoloader. A namespaced
  autoloader that has to load itself is a bootstrap problem for no benefit.
- Anything under `packages/web/vendor/`.

**Verification per commit** is `sh tests/run-all.sh` plus one addition: extend
`tests/autoload.test.php` to assert, for every declared class, that both the bare
name and `FOG\<name>` resolve to the same class entry. That single assertion
catches a missing `class_alias`, a misspelled alias, and a file that got
`namespace` without the alias — which are the only three ways a batch commit can
go wrong.

### PR 3.4 — ADR 0013 and the plugin guidance

`docs/adr/0013-flat-fog-namespace-and-the-reverse-alias-abi.md` — the layout
decision and its rejected alternatives, the reverse alias as the 1.6 plugin ABI,
and the F4 bug class with its test gate.

`docs/plugin-development.md` gains a section: write `FOG\Foo` in new code, bare
`Foo` keeps working for all of 1.6, and — the part that actually matters —
**`get_class($this)` now returns `FOG\Foo`, so any plugin comparing it to a string
literal must be updated.** With the `shortName()` helper named as the fix.

### PR 3.5 — Draft 1.7 deprecation note

Not a deprecation, a *note*: bare global class names continue to work throughout
1.6, and the earliest they could be removed is 1.7. Committed now so the window is
on record from the release that introduces the aliases, rather than announced later
when someone wants to remove them.

Draft text is in the ADR; the one-line version is: *"FOG 1.6 declares its classes
in the `FOG\` namespace and aliases every one of them into the global namespace.
The aliases are supported for all of 1.6. Plugins should adopt `FOG\` names now;
the aliases will be reviewed for removal in 1.7, with at least one minor release of
notice."*

---

## Alternatives considered and rejected

**Build the short-name→FQCN map the brief asks for.** Rejected on F3: the reverse
alias gets the same result with no map to build, no map to keep in sync as classes
are added, and no 350 call sites to review. A map would also be *worse* on one
axis — it only covers names that go through `getClass()`, whereas the alias also
covers `new $var`, `instanceof $str`, `is_subclass_of()`, and the 74 dynamic sites
the map could never enumerate.

**Namespace leaves first, per the brief.** Rejected on F1 — the graph is flat
enough that ordering barely exists, and doing the 8 bases first means every later
batch validates against a finished base. The brief's instinct was right for a deep
graph; this graph is three levels.

**Do PR 3.1's fixes inline, during the namespacing.** Rejected. It is the
difference between a mechanical diff you can verify by token equivalence and a
semantic diff you have to read. It also means the `authorization.class.php` lockout
guard and the `eventmanager.class.php` registration switch get fixed and namespaced
in one commit — so if either regresses, the bisect can't tell you which half did it.

**Skip the aliases; edit every reference instead.** Rejected — 169 tracked plugin
files plus every third-party plugin in `/opt/fog/plugins`, which no plan can
enumerate. The alias is the only mechanism that covers code we cannot see.

**Do it in 1.7 instead, as a clean break.** Rejected, but it is the strongest
alternative and worth stating. The case for waiting is that a clean break needs no
aliases and no compatibility window. The case against is that Phase 2 (OIDC) wants
to write new namespaced code in `src/`, and having half the codebase namespaced and
half not, with no bridge, is worse than either end state. Phase 0 already paid for
the bridge; using it now is cheaper than carrying it unused through a whole release.

---

## Irreversible steps / data migrations

**None.** No schema change, no `FOG_SCHEMA` bump, no `globalSettings` row, no
`FOG_BCACHE_VER` bump (no JS or CSS is touched). Every PR reverts cleanly with
`git revert`.

The one **soft** irreversibility is PR 3.4's deprecation note: once published, the
alias support window is a promise to plugin authors. That is a commitment, not a
code change, and it is the reason PR 3.5 exists as its own reviewable thing rather
than a paragraph inside the ADR.

---

## UNKNOWN

1. **Third-party plugins in `/opt/fog/plugins`.** A first-class autoload root
   whose contents no plan can enumerate. F6's `get_class()` hazard applies to them
   and the only mitigation available is the PR 3.4 note.
2. **Whether `fog-workflows` CI would need a change.** Not visible from this
   checkout. It is where `tests/class-name-derivation.test.php` would actually gate
   anything, so this is the same unknown Phase 0 carried and did not close.
3. **The 74 dynamic `getClass($var)` sites in 23 files.** Covered by aliasing as
   *consumers*, so they are safe — but whether any of them also *compares* the
   variable to a literal was not scanned. Worth a targeted read during PR 3.1.
4. **`packages/web/management/js/`.** Only PHP was audited. `fogpagemanager.class.php:161`
   builds `js/fog/{$node}/fog.{$node}.list.js` from the node name, and the JS may
   embed node strings in AJAX URLs. Node names are not class names and do not
   change here — `INFERRED` safe, not verified.
5. **`fog-plugins`' own `get_class()` sites.** The inheritance counts in F6 are
   `VERIFIED`; a producer-site sweep of that repo has not been run. It should be
   PR 3.4's first step.

---

## Which claim, if false, would hurt most?

**"The reverse `class_alias` makes every consumer of a class name keep working."**

Everything in this plan is sized on it. If it is wrong, PR 3.3 stops being two
lines per file and becomes a full call-site audit — and the plugin compatibility
story collapses entirely, because 169 tracked plugin files plus every unknown
third-party plugin depend on `extends Hook` continuing to resolve.

I have `VERIFIED` it against fifteen consumer shapes — F3's nine, plus a second
prototype covering the long tail I initially left as `INFERRED`. The second run
matters most, because it proves the plugin ABI end to end: a plugin file written
the pre-namespace way and **never edited** still satisfies
`is_subclass_of($class, 'PluginTask')` at `pluginrunner.class.php:226` after
`PluginTask` becomes `FOG\PluginTask`. That single call gates every plugin
background task (ADR 0010), and it was the most plausible way for this plan to be
quietly wrong.

Also `VERIFIED` through the alias: `is_subclass_of()` with either spelling of the
second argument, `get_class_vars()`, `class_implements()`, callable strings, and
`ReflectionClass` against the alias.

**The one shape the alias does not cover is `get_declared_classes()`**, and there
is exactly one site: `fogpagemanager.class.php:307`,
`in_array($className, get_declared_classes())`. The Phase 0 analysis already
established that this comparison **never matches today** — it is case-sensitive
against a lowercase basename — so it is inert before and after. Verify that on the
lab during PR 3.3 rather than trusting the reading twice.

So the residual risk is not the mechanism; it is coverage. The thing worth doing
before PR 3.3 starts is not reviewing the 248 mechanical files, it is grepping the
tree for any consumer shape **not** in the fifteen already proven.

Second place: **"`getClass('Storage')` has no callers."** If wrong, PR 3.0 breaks
storage node or storage group resolution. It is one commit and trivially
revertible, which is exactly why it is its own PR.
