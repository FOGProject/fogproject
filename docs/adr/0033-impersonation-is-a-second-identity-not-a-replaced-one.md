# Impersonation is a second identity, not a replaced one

## Status

accepted, and implemented on `working-1.6` as schema 398 (`auditLog.alActedAs`,
`auditLog.alSpanID` and the `FOG_IMPERSONATION_NOTIFY` setting).

## Context

"My dates are in the wrong timezone" is not answerable by reading the
database. The preference is per user, the rendering depends on it, and the
only reliable way to see what somebody sees is to be them for a moment. So an
administrator needs to become another user, look, fix the preference, and drop
back.

That is a small feature with one large hazard. FOG binds `$GLOBALS['currentUser']`
once per request, and `FOGBase::$FOGUser` is a **reference** to that global
(`FOGBase::_init()`). One assignment therefore moves permissions, site scope,
`displayTimeZone()`, `displayTheme()`, the sidebar and the menu together --
which is exactly what impersonation wants -- and it moves `Audit::_actor()`
with them, which is exactly what impersonation must not do. An audit row
naming somebody who did not act is worse than no audit row at all: it destroys
repudiation for the one person who cannot disprove it.

Two further hazards are specific to this codebase and are the reason this is
written down rather than defaulted:

- **A permission check is not a scope check.** RBAC answers "may this user do
  X"; the site plugin (ADR 0006/0019) answers "which objects does this user
  reach". A Site A administrator and a Site B user can hold literally the same
  permission strings. Set arithmetic over permissions alone would let the
  first become the second.
- **An internal invariant check whose answer depends on who is asking.**
  Commit `273f5f954` fixed a lockout where the "is there still an
  administrator" guard counted administrators through a scoped read. Every
  such check has to be audited for *which* identity it reads, because
  impersonation makes the current identity a thing an operator chose.

## Decision

**`$_SESSION['FOG_USER']` keeps holding the REAL administrator.** The mask
lives in `FOG_IMPERSONATE`, the bracket id in `FOG_IMPERSONATE_SPAN`, and
`FOG\Auth\Identity` is the only thing that reads any of the three.

That direction is the single most load-bearing choice here, and it is chosen
for how it fails. Anything that reads `FOG_USER` and was never found during
this work keeps naming the administrator, which is **true**; anything on the
view side that was missed shows the administrator their own view, which is
**visible and harmless**. Store the target there instead and every reader
nobody found attributes to the target, silently.

`FOG_AUTH_SOURCE` is deliberately not touched: it records how *this session*
was made, and the session was made by the administrator's credential. Rewriting
it would corrupt the break-glass counting `User::establishSession()` exists to
keep honest.

**Who may be impersonated is decided by two independent tests, and both must
pass.** They are not collapsed into one because they answer different
questions and neither implies the other:

| test | question | source |
|---|---|---|
| permission subset | is every permission the target holds one the impersonator already holds? | `Authorization::getPermissions()`, expanded against `Authorization::registry()` |
| site subset | does the target reach any site the impersonator does not? | `SiteScope::userSiteIDs()` |

Two edges are decided here rather than left to fall out of set arithmetic:

- **An administrator holding `*` may impersonate anybody, including another
  administrator.** `*` is answered directly, not expanded and compared,
  because that is what `can()` does: a `*` holder satisfies permission strings
  no registry declares, so expanding it would make an administrator look
  *narrower* than they are and refuse on a stale plugin grant.
- **A target in a catch-all site is refused to an impersonator who is not**,
  and an unscoped impersonator is refused nothing on site grounds. When no
  sites exist at all the site test is inert, which is the single-site install.

**Starting a span takes `impersonate.start`.** The subset tests decide *who*
you may become; they are not a grant. Without a permission of its own, every
account able to administer anything could impersonate everything beneath it,
with no way to hand the capability to a helpdesk role and withhold it from
another. There is deliberately **no `impersonate.end`**: leaving must never be
permission-checked, or impersonating a user who holds no roles traps the
administrator as them.

**What may be done while masked is an ALLOWLIST: reads, plus the impersonated
user's own preferences.** Not a list of forbidden operations. ADR 0021's
account of the `storagenode.pass` leak ends "naming them per route is what hid
this" -- a refusal list must be re-audited every time somebody adds a route,
and an allowlist need not be. It also closes a hole no refusal list could see:
`FOGController::save()` auto-fills `createdBy` from `self::$FOGUser`, which is
the mask, so an ordinary create performed mid-span would stamp the target's
name onto the row itself, in a column no audit change repairs.

The gate sits at both authorization choke points -- `requirePagePermission()`
and `requireApiPermission()` -- and it is keyed on the node/route name as well
as the permission string, because every `EXEMPT_NODES` entry resolves to *no*
permission at all. `schema` is the case that matters: a gate reading only the
permission would wave a whole-database rewrite straight through.

**The audit records a bracketed span, not scattered rows.** `impersonation.start`
and `impersonation.end` are ordinary `alType` values; both carry the same
`alSpanID`, and every row written inside the bracket carries `alActedAs` (the
target) alongside `alCreatedBy` (the administrator, always). A span whose end
never arrives -- browser closed, session expired -- is still resolvable: it is
a start with no matching end for that span id, which is one indexed query.

**The impersonated user is told, on their next sign-in, that their account was
viewed**, controlled by `FOG_IMPERSONATION_NOTIFY` and on by default. This
needs no column: it is derivable from the newest `impersonation.start` naming
them against the newest `auth.login` for them, both already indexed.

**The mode line is emitted by the page shell, not by any page.**
`management/other/index.php` is the only thing in FOG that emits a `<body>`,
so a bar there is on every page by construction rather than by every page
remembering. It is fixed, full width, `z-index: 2000` (above Bootstrap's modal
at 1055 and offcanvas at 1045), `#c2410c` to `#9a1c1c` -- a color belonging to
no existing status meaning, so it reads as a *mode* rather than as an alert
about something that just happened. It is not dismissible and there is no
control to hide it, because the failure it exists to prevent is an
administrator who forgets.

**Impersonation is exactly one level deep.** `Identity::begin()` refuses while
a span is open, so "impersonate another" is end-then-start, never a swap, and
the audit never has to answer "acting as B, who was being acted as by A".

## Consequences

**The identity audit is part of the feature, not a follow-up.** The checks
that were read for *which* identity they consult:

| check | reads | verdict |
|---|---|---|
| `Authorization::adminExistsGiven()`, `_userIDs()`, `_externalUsers()`, `rolesHolding()` | direct SQL, no scope | safe |
| `FOGBase::hasFogUsers()` | direct SQL | safe |
| `SiteScope::sitesInUse()`, `catchAllID()`, `_hasNoPrincipal()` | direct SQL | safe |
| `FOGBase::isSchemaAdmin()` | the CURRENT user | identity-dependent -- and why `schema` is refused to a span by name |
| `Authorization::assertCanGrant()` | the CURRENT user | identity-dependent, **correctly** -- but unreachable behind a span, since every grant route is a write |
| `MulticastSession::activeCount()`, `HostManager::getHostByMacAddresses()` | scoped reads used as counts | latent instances of the same pattern, unrelated to impersonation, not changed here |

**The gate is deny-by-default, so a route added tomorrow is refused to a span
until somebody names it.** That is the intended cost: a new read-only endpoint
will not work behind a mask until it is added to
`Authorization::IMPERSONATION_ALLOWED_API_ROUTES`, and the failure mode is a
403 an administrator can see rather than a write they cannot.

**Nothing about this reaches 1.5.** The audit trail (ADR 0021) does not exist
on `dev-branch`, so a span there could not be recorded, and an impersonation
that cannot be attributed is the thing this ADR exists to prevent.
