# Role-based permissions are native; the accesscontrol plugin is retired

## Status

accepted

## Context

FOG's only permission mechanism was the accesscontrol plugin, and it never
actually enforced anything: its "rules" mapped roles to menu keys but no
choke point consulted them, so every logged-in user (and every API token)
had full administrative power. Proper multi-user operation needs real
authorization, and it should be something FOG simply *has*, not an optional
plugin.

Native RBAC landed in four phases on `working-1.6`:

- **P1** — data model (`roles`, `roleUserAssoc`, `rolePermissions`, schema
  302–306) and the `Authorization` service.
- **P2** — Role Management UI and per-user role assignment.
- **P3** — UI enforcement at the single page-dispatch choke point
  (`FOGPageManager::render()`), menu filtering, and a warning banner for
  role-less users.
- **P4** — API enforcement in `Route::runMatches()`, with token-authenticated
  requests bound to the token's owner.

This ADR covers the final phase (P5): what happens to the plugin.

## Decision

Delete `lib/plugins/accesscontrol/` from the tree and remove its
`plugins`-table row via schema step 307.

Migration semantics (decided with the feature, recorded here):

- **Permissions are `<node>.<action>` strings** (`host.edit`, `image.*`,
  global `*`); actions are view/create/edit/delete/task. No per-instance
  scoping in v1.
- **Roles and user↔role assignments carry over.** The native tables are the
  plugin's own `roles`/`roleUserAssoc` tables, adopted in place (same IDs,
  same names). Schema 303 relaxes the plugin's one-role-per-user unique
  index to a composite (role,user) unique, so users can now hold multiple
  roles; permissions are the union.
- **Menu-key rules do NOT migrate.** They were cosmetic-only (never
  enforced), so any pre-existing role with no permission rows is seeded
  with `*` — preserving its prior *effective* (full) access rather than
  inventing restrictions the admin never chose. The plugin's `rules` and
  `roleRuleAssoc` tables are left in the database untouched; admins may
  drop them manually.
- **Users with no role at all are implicit administrators** and see a
  warning banner. Upgrades therefore change nobody's access until an admin
  assigns roles.
- **Unknown nodes / API classes default to allow** — a single, deliberate
  compatibility stance for plugin pages and future entities, flippable in
  one place (`Authorization`).

## Consequences

- The plugin's uninstall path (which dropped the shared tables — a standing
  footgun) dies in the same release the tables go native, so there is no
  window where "uninstall plugin" can destroy native role data.
- Headless integrations authenticating with a user API token now inherit
  that user's role permissions. Scripts that must remain unrestricted
  should use a role-less or Administrator API user (release-notes item).
- The `site` plugin still probes `in_array('accesscontrol',
  $pluginsinstalled)` in `addsitefiltersearch.hook.php`; the result feeds a
  dead local variable, so it now just evaluates false. Left as-is.
- Databases that never installed the plugin get identical tables from the
  same schema steps; step 307's DELETE is a no-op there.
