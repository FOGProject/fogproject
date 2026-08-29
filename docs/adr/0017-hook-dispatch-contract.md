# The hook dispatch contract

## Status

accepted

## Context

The hook system is FOG's plugin ABI. Every bundled plugin and every plugin
neither the project nor its maintainer has ever read registers callbacks
through `EventManager::register()` and receives them through
`HookManager::processEvent()`. ADR 0013 made a promise about *one* part of that
surface — the reverse `class_alias` in each namespaced file — and said nothing
about the rest of it, because the rest of it had never been written down.

A scan of the four files (`eventmanager`, `hookmanager`, `event`, `hook`) found
thirteen defects, recorded as F-11 through F-26 in `docs/refactor-facts.md` and
argued in `docs/hook-event-plan.md`. Most were fixed without anything
observable changing. Five were decisions, because fixing them changes what a
plugin can rely on, and four of those leave no trace in the code afterward —
which is what this ADR is for.

Three findings frame the rest:

- **No core hook or event has ever loaded on any FOG server.** All eleven files
  under `lib/hooks` and `lib/events` declare `public $active = false;`. They are
  worked examples an admin opts into. Every live listener on every install comes
  from a plugin, so "what plugins can rely on" *is* the contract, not a subset
  of it.
- **`register()`'s error handler was itself fatal.** It interpolated
  `$listener[0]` inside the `catch` meant to swallow a bad listener, so any
  non-array object — a Closure, say — raised an `\Error` that
  `catch (\Exception)` does not catch, during `LoadGlobals`. HTTP 500, empty
  body, every entry point, until the file was deleted from disk.
- **`docs/plugin-development.md` documented that exact shape three times**, in
  the §7 examples for the Phase 2 authentication seams. Nothing in
  `fog-plugins` follows the guide, which is why it survived.

## Decision

### 1. A hook listener is `[Hook, 'method']` or a Closure

Both have an **owner**: the object for the pair, and for a closure whatever
`$this` it was written inside, recovered with
`ReflectionFunction::getClosureThis()`. A closure declared in a hook
constructor is owned by that hook.

A closure with no bound `$this` — a `static function`, or one created outside a
hook — has no owner and always runs; registering it is the opt-in.

Anything else is refused, and the refusal is logged naming the offending class
and event. A bare function name and `[Class::class, 'staticMethod']` have no
owner, and an owner is what a listener needs in order to have an `$active` at
all.

The Closure form is admitted because the authoring guide has documented it
since ADR 0014 and it is the natural shape for a plugin contributing a single
route or login button. Making the documentation true was cheaper than making
it false.

### 2. `$active` decides whether a listener runs, and nothing else does

Read from the class's declared default at load, and from the live property at
dispatch. Both by truthiness, so there is one notion of active read the same
way at both ends.

It used to be neither of those things. At load, a regular expression over the
file's source text looked for the literal `$active = true;` — `\s?` is
zero-or-one, the match was case-sensitive, and it could not tell a comment from
code, so `public $active  = true;` with two spaces was inactive, `TRUE` was
inactive, and `public $active = false;` with `= true;` written in the comment
above it was *active*. At dispatch, any listener whose file path contained the
substring `plugins` was force-activated regardless of its flag.

A value computed in a constructor is deliberately still not consulted at load —
that was true of the regex too — but it *is* honoured at dispatch, which is why
the dispatch-time read stays rather than gating construction on the flag.

### 3. A plugin's file path is not part of the activation decision

The force-activation is deleted. Its reason had expired: when `capone` was the
only plugin, plugins had no hooks of their own, and hooks were adopted into the
plugin system by copying core's example hooks — every one of which declares
`$active = false`. Force-truthing anything on a plugin path made a copied
example work without its author noticing the flag. Plugins have shipped their
own hooks for years and all 87 bundled hook and event files set
`$active = true`, so the net catches nothing.

It was never free. It made `$active` decorative for every plugin hook — a
plugin could not turn one of its own hooks off — and it was a bare `stripos`
over the whole path, so an install whose base directory contained the string
would have force-activated core's hooks too.

### 4. Hooks and events are peers that share a base class for historical reasons

`Hook extends Event`, so `$listener instanceof Event` — the only type check
separating the two — accepted a hook as an event listener. `notify()` would
then call `Event::onEvent()` on it, and the inherited default printed the event
name into the response.

A `Hook` is now refused where an event listener is expected, and
`Event::onEvent()`'s default does nothing.

**Superseded by #1203.** `Hook` extends `FOGBase` directly and the two are
genuine peers; the boilerplate they actually share — `$name`, `$description`,
`$active`, the three log settings, `log()` and the constructor — moved to the
`Listener` trait, which both use. `$obj instanceof Event` now answers what it
always meant to.

The blast-radius argument that held this back was made on its own evidence
before the change landed: zero `instanceof Event` in core outside the two
dispatch classes, zero in `packages/service`, and zero across the 72 hooks and
15 events in `fog-plugins`; every hook in both trees declares its own `$active`
and calls `parent::__construct()`; and exactly two files in the estate use the
log settings, both core and both `$active = false`. `log()` went into the trait
rather than staying on `Event` because `FOGBase` declares a `log()` with an
identical signature and a different job, so a hook that lost `Event`'s copy
would not have failed — it would have quietly called that one.

### 5. `notify()` on a HookManager is an error

It is refused and logged, naming `processEvent()`. It previously returned
`true` having invoked nothing, because it iterates listeners as objects while
`HookManager` stores arrays.

Delegating to `processEvent()` was considered and rejected. The two have
genuinely different calling conventions — `processEvent()` merges an `event`
key into the payload and calls a named method that can mutate its arguments
through references, `notify()` passes a copy to a fixed method and discards the
result — so quietly making one behave as the other blurs the boundary
decision 4 sharpens.

## Consequences

**For plugin authors.** The documented closure form works. `$active` means what
it says, in a plugin as in core. A registration that fails says which class and
which event, in one line, instead of naming neither.

**What can break.** Exactly one thing: a plugin hook that declares
`$active = false` and relied on the force-activation to run anyway. All 87
bundled files set it true, and `Event::$active` defaults to `true`, so a hook
that simply omits the property is unaffected — only an explicit `false` is, and
writing `false` while expecting the hook to run is not a coherent intent.

Second-order, and smaller: a third-party hook file dropped into `lib/hooks`
whose `$active` line has unusual spacing starts working, and one whose comment
contains `= true;` stops. `configureHttpd()` removes the web tree on upgrade,
so files there do not survive an upgrade in any case.

**For performance.** Nothing here was done for speed, and the measurement says
that was right: the whole subsystem costs 1–2 ms of an 11–543 ms page render,
and the per-listener `ReflectionClass` everyone assumes is the expensive part
totalled 14–168 µs per request. That reflection is gone regardless — it existed
only to feed the path substring — which is the deletion doing the work rather
than an optimization.

**This is not an ADR 0013 question.** That ADR's promise concerns the reverse
`class_alias` specifically, and 1.6.0 is unreleased. There is no shipped 1.6
plugin ABI, which is why none of the five decisions needed a deprecation
window.

## Alternatives considered

**Fix the authoring guide instead of accepting closures.** Cheaper, and wrong:
the guide describes the seam a third-party identity provider is most likely to
use, and the shape it describes is the ergonomic one. The only technical
objection — that a closure has no `$active` — dissolved once
`getClosureThis()` turned out to recover the owning hook.

**Narrow the force-activation rather than deleting it**, by asking
`Initiator::_isPluginPath()` instead of running `stripos` over the whole path.
That removes the base-directory misfire and leaves `$active` decorative for
every plugin hook, which is the half that actually costs something.

**Gate plugin hook construction on `$active` too**, so the flag is read in one
place and the dispatch-time check disappears. Rejected: it would break a hook
that computes `$active` in its constructor — `$this->active =
self::getSetting('FOO');` is the natural way to write "on only when this
setting is enabled" — which works today for plugin hooks. No core or bundled
plugin does it, so this protects third-party code only, and the cost of
protecting it is one property read per listener.

**Rewrite the two managers as one dispatcher with two listener kinds.** The
right shape — `load()`, `register()` and dispatch all branched on which manager
they were, which is a class doing two jobs — but it moves every line a plugin
can observe at once, and every defect found was fixable without it.
