# A class is named at the call site, not fetched by string

## Status

accepted

## Context

`FOGBase::getClass('Host')` was how core instantiated almost everything: 459
call sites across 120 files spelled every model, manager, hook and page as a
quoted string handed to a factory. The factory ran the name through
`FOGBase::qualify()` and then `ReflectionClass::newInstance()`.

That existed for a reason, and the reason is written down in
`docs/refactor-brief.md`:

> **`getClass()` has 491 call sites and 136 distinct names.** Convert it to a
> short-name-to-FQCN map so those call sites never need editing. This is the
> whole reason the migration is tractable: one chokepoint controls the
> overwhelming majority of class references.

Phase 3 renamed 226 files into `FOG\<Bucket>\<Class>` and needed the tree to
keep working after every commit. One function that translated a bare name into
whatever the namespace happened to be that week is exactly the right tool for
that, and it worked — the migration landed without touching those 459 sites.

The migration is finished. What the literal call sites are left paying is a
standing cost with nothing on the other side of it.

**The type is erased.** `getClass()` is declared `@return object|mixed`, so
PHPStan cannot check anything done to the result and no editor can follow one
to a definition. That is not theoretical: converting the tree turned up **90
PHPStan errors on a baseline that reports zero**, every one of them
pre-existing and previously unreachable. Among them —

| Found | What it was |
|---|---|
| `Schema::dropDuplicateData()` | `@return void` on a method whose body ends `return $queries;`, and `@param string $table` on one that is indexed `$table[0]`, `$table[1]`, `$table[2]`. 74 of the 90. |
| `FOGController::destroy()`, `Module::save()` | `@return object` on methods that `return false;`, so every `if (!$x->destroy())` guard in the tree read as dead code |
| `StorageNode::get()` | `@return object` on a method that returns trimmed **strings**, which is why four `trim()` calls looked like `trim(object)` |
| `HostManagement::pendingMacsAjax()` | `$errt` assigned only inside two branches of a `try`, then read unconditionally in the `catch` |

None of those are new defects. They are what a decade of annotations drifting
behind their bodies looks like when something finally reads them.

**It is not a substitution seam.** That is the usual argument for a factory,
and it does not hold here. `qualify()` consults the core map *before* the
plugin map, and ADR 0013 says that ordering is load-bearing: "it is what stops
a plugin answering a core name." Nothing can ever answer `getClass('Host')`
with a different class. There is no behavior to preserve beyond resolving the
name — which `use FOG\Items\Host;` already does, at compile time, checkably.

The one exception is the **test harnesses**, which do substitute through it:
`tests/lib/bootmenu-harness.php` declares a stub `FOGBase` whose `getClass()`
returns cut-down stand-ins. That seam is real, but it is not the factory's — it
is the harness replacing `FOGBase` wholesale, and `tests/lib/stub-buckets.php`
already re-exports flat stubs under their bucketed names precisely so a direct
`new` finds them. Two of them (`KeySequence`, `Ipxe`) existed only as arms of
the harness's `getClass()` switch and needed promoting to classes; the golden
iPXE output is byte-identical either way.

## Decision

**A class named by a literal is instantiated with `new`.** In `packages/web/src`
that means a `use` import and a bare `new Host()` — the house style already, 255
of 289 files carry an import block. In files that declare no namespace, and
throughout `fog-plugins` (whose own
`tests/core-references-are-qualified.test.php` refuses a bare core name), it
means an inline `new \FOG\Items\Host()`.

**`getClass()` stays, for the shape `new` cannot express: a class named by a
variable.** 56 sites, and they are the ones that matter — `Route`,
`Authorization::_scopeClassVars()`, `OpenAPI::_entitySchema()` and
`FOGPage::$childClass` hold a lowercase string that arrived over the API or in
a URL and turn it into a class through `qualify()`. Nothing else can do that,
and narrowing the function to that job is what makes the job legible.

Two literal forms survive because they have no `new` equivalent at all:

- `getClass('X', '', true)` returns `ReflectionClass::getDefaultProperties()`
  rather than an instance. Three sites.
- `getClass('ReflectionClass', ...)`, which the factory special-cases.

`tests/getclass-literals.test.php` enforces this. It already checked that a
literal names a class spelled exactly as declared; it now also refuses a
literal `getClass()` that is neither of the two forms above, and its
scan-sanity anchor moved from counting literals to counting declarations —
counting literals would have made the gate weaker every time it succeeded.

## Consequences

**The 90 findings had to be dealt with rather than deferred**, because the
`phpstan` check is required and the baseline was zero. Seven were corrected
annotations that now match their bodies, one was the undefined `$errt`, and one
— a `!$StorageNode` guard after a method that throws instead of returning null
— is genuinely dead and was left in place with a baseline entry saying why.
Deleting defensive code on the strength of a docblock, in a tree where this
change just proved the docblocks unreliable, is the wrong direction.

**Eight tests read the source as text and pinned the old spelling** —
`strpos($post, "getClass('GroupPowerManagement')")` and similar. They were
updated to the new spelling, not loosened to accept both; accepting both would
let the old form back in through the gate's own blind spot.

**Plugin authors are affected**, and `docs/plugin-development.md` says so. A
bare `getClass('LDAPGroupManager')` inside a plugin used to be the documented
way to reach a class without spelling its namespace. It still resolves — the
function is unchanged — but the documented advice is now `new
\FOG\Plugins\LDAP\Managers\LDAPGroupManager()`, and fog-plugins was converted
in the same sweep.

**The rewrite was mechanical and is reproducible.** `bin/getclass-to-new.php`
is the tool, kept for the same reason fog-plugins keeps
`bin/qualify-core-references.php`: it is how the sweep was done, it can be
re-run against a plugin tree or a long-lived branch, and it resolves names
through the same core-then-plugins order `qualify()` does, so it cannot
silently repoint a call at a plugin class. It is token-based rather than
regex-based, which is what keeps it off the `getClass(` occurrences inside
strings, docblocks and commented-out code — of which this tree has 30.

**What this does not do is make the tree type-safe.** It makes 459 call sites
*visible* to a checker that was previously blind to them. The 90 errors are the
first instalment of that, not the last: every future change to a method these
call sites touch is now checked, which is the point.
