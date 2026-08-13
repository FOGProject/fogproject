# Per-object scoping is a plugin boundary layered on native RBAC

## Status

accepted

## Context

Native RBAC (ADR 0005) grants **verb-level** permissions — `host.edit`,
`image.*`, global `*` — and deliberately shipped with "no per-instance
scoping in v1": a user who can edit hosts can edit *every* host. Multi-team
and multi-site deployments need a narrower boundary, where a site-scoped
admin only sees and touches the hosts, users, groups and user groups that
belong to their site.

That boundary is inherently about *which objects*, not *which verbs*, and it
is site-specific knowledge. Core should not learn about sites, and an
installation that does not care about site scoping should pay nothing for it.
So the boundary lives in the existing `site` plugin, and core exposes only a
generic seam the plugin fills in.

## Decision

Add a generic, **default-allow** object-scope seam to `Authorization`,
consulted *after* the verb check has already passed. Core owns the seam; the
`site` plugin owns the meaning.

**Core (generic, no site knowledge):**

- `Authorization::objectInScope($node, $id, $userID)` returns true for
  `$id < 1` (list/create/mass — nothing to scope yet), true for unrestricted
  users, otherwise fires the `OBJECT_SCOPE_CHECK` hook with a
  `&$allowed` (default true) and returns it. With no listener the boundary
  does not exist and behaviour is unchanged.
- "Unrestricted" (`Authorization::isUnrestricted`) = an implicit admin (no
  role) **or** a holder of global `*`. These bypass scoping entirely.
- Four choke points enforce it:
  1. **Single-object web** — `FOGPageManager::render()` →
     `requirePageObjectScope($node, $id)`.
  2. **Single-object REST** — `Route::runMatches()` →
     `requireApiObjectScope($class, $id)`.
  3. **Mass delete** — `FOGPage` deletemulti →
     `requirePageObjectScopeMass($node, $ids)`.
  4. **List/search visibility** — web AJAX lists via `AJAX_DATA_DISPLAY_CHANGE`;
     REST list/search via `API_MASSDATA_MAPPING`.

**Site plugin (the meaning):**

- Scope covers four object types: `host`, `user`, `group`, `usergroup`.
  Assignment is explicit, via its own association tables
  (`sitehostassociation`, `siteuserassociation`, `sitegroupassociation`,
  `siteusergroupassociation`) — a group's or user group's site is its *own*
  row, not inferred from its member hosts/users.
- Scope logic is single-sourced on the `Site` model: `userSiteIDs($userID)`
  (cached; **empty = deny-all**), `inScope()`, `filterInScope()`.
- `SiteScopeCheck` (on `OBJECT_SCOPE_CHECK`) enforces the single-object and
  mass-delete boundary; `ListSiteHosts` (web) and `FilterSiteMassData` (REST)
  enforce list/search visibility.
- **Deny-all is strict:** a restricted user with no site assignment sees an
  empty list and is denied every scoped object.

**Web-vs-REST gate.** The REST list filter must run on genuine REST calls but
not double-filter the web AJAX path. It is gated on `Route::$apiRequest`
(set only when `api/index.php` constructs a `Route`), **not** `self::$ajax`:
`$ajax` is derived from the client-set `X-Requested-With` header, so a REST
client could flip it and escape filtering; `$apiRequest` cannot be spoofed.

## Consequences

- Default-allow keeps core site-agnostic: uninstalling the `site` plugin
  removes the object boundary and leaves the verb permissions (ADR 0005)
  intact. Unknown nodes/classes stay allowed, matching the RBAC stance.
  > **Superseded in part by ADR 0009.** The second sentence no longer holds:
  > unknown nodes and classes now fail *closed* on both the API and page
  > paths. The default-allow **object-scope** seam described here is
  > unchanged — it is the verb layer that changed stance, not this one.
- Group and user-group scope is deliberately explicit (own association rows).
  Deleting a site clears all four association types; deleting a scoped object
  clears its association via `DELETEMASS_API`.
- The REST list path is unpaginated by default
  (`FOGManagerController::limit` adds no `LIMIT` unless the caller sends
  DataTables `start`/`length`, which only the web UI does), so
  `FilterSiteMassData` trims the whole result set and the kept-row count is
  the true total. A custom REST client that *does* send paging params would
  get an off record count — never out-of-scope data, only a wrong total.
- Unrestricted users (implicit admins, `*` holders) are unaffected; the
  boundary only ever narrows a user who already holds a restricting role.
