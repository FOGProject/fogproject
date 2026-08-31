# Plugin Schema Migrations

Bundled plugins ship their own database tables. Historically a plugin's
schema was only created when an admin clicked **Install**, and re-installing
**dropped and recreated** the tables (losing data). There was no way to
deliver a schema change — a new column, a new index — to a plugin that was
already installed: a FOG upgrade left the old plugin tables untouched.

This document describes the mechanism that replaces that. Plugins now declare
their schema as an **ordered, append-only list of migration steps** (mirroring
how FOG's core schema works in `commons/schema.php`). Steps are applied
**incrementally and non-destructively**, and each plugin tracks how many steps
it has applied so an upgrade only runs what is new.

---

## The contract

A table-owning plugin's **top-level manager** implements one method:

```php
public function schema()
{
    return [
        // 0
        $this->createSql(),
        // 1  (added in a later release)
        "ALTER TABLE `mytable` ADD COLUMN `myCol` VARCHAR(40) NULL",
    ];
}
```

Rules:

1. **The list is flat and append-only.** One list covers *every* table the
   plugin owns. New schema changes are **always appended to the END**. Never
   insert, reorder, or delete existing entries — the index of each step is its
   identity.
2. **Each step is a SQL string or a callable.** A callable runs arbitrary PHP
   and returns `true` on success or an **error string** on failure (used when a
   value must be resolved at run time — see *Seed data* below).
3. **Steps must be idempotent / additive.** Use `CREATE TABLE IF NOT EXISTS`
   (via `Schema::createTable($name, true, ...)`) and `ALTER TABLE ... ADD ...`.
   Never `DROP`. The runner tolerates "already exists / does not exist" errors
   (see *Idempotency*), so re-running a step is safe.

Each table's `CREATE TABLE` lives in a `createSql()` method on the manager that
owns that table, and the top-level `schema()` aggregates them. A single-table
plugin's `schema()` is just `[$this->createSql()]`.

`install()` becomes a thin, non-destructive wrapper:

```php
public function install()
{
    $res = Schema::applyUpdates($this->schema(), 0);
    return $res['error'] === null;
}
```

`uninstall()` is unchanged — it remains the **only** path that drops tables,
and only fires from the explicit **Uninstall** button.

---

## How steps are applied

`Schema::applyUpdates(array $steps, int $applied): array`
(`src/Items/Schema.php`) is the shared runner. It runs steps from index
`$applied` onward, returns `['applied' => int, 'error' => string|null]`, and is
used by both the install path and the upgrade path so there is one code path.

### Version tracking

Each plugin row (`plugins` table) has a **`pSchema`** column — an integer count
of how many of its `schema()` steps have been applied. `applyUpdates()` advances
it. Comparison:

```
applied (plugins.pSchema)  <  count(manager->schema())   ⇒  upgrade pending
```

This check is **independent of `FOG_SCHEMA`** (the core schema version). A plugin
self-reports whether it is behind by comparing its own stored count to the number
of steps its *code* defines. `Plugin::needsSchemaUpdate()` is exactly that
comparison.

### Idempotency

`applyUpdates()` tolerates the same MySQL error codes the core Schema Updater
does, so additive steps are safe to re-run:

| Code | Meaning |
|------|---------|
| 1050 | Table already exists |
| 1054 | Unknown column |
| 1060 | Duplicate column name |
| 1061 | Duplicate key name |
| 1062 | Duplicate entry |
| 1091 | Can't DROP; doesn't exist |

A callable step that runs its own queries should follow the same philosophy
(e.g. tolerate `1062` when seeding a row with an explicit primary key).

---

## The user-facing flow (notify + one-click)

There is **no silent auto-apply** — consistent with how FOG already gates schema
changes behind an explicit action and a backup reminder.

1. When an installed plugin's `schema()` defines more steps than its `pSchema`,
   it is "behind."
2. The **dashboard** shows a warning banner: *"N plugin(s) need a database
   update."* (computed on dashboard load via
   `PluginManager::getPluginsNeedingUpdate()`).
3. The **Plugin Management** list shows an amber **"Update available"** button on
   that plugin's row.
4. The admin applies it, **individually** (click the row's button) or in **bulk**
   (select rows → **Install/Update ▾ → Update selected**). Both hit the
   `plugin/upgrade` action, which runs `Plugin::installdb()` for each *installed*
   selected plugin. The table redraws and the flag clears.

`Plugin::installdb()` applies pending steps from `pSchema` forward and saves the
new count. It is non-destructive. (Plugins that have not yet adopted `schema()`
fall back to their legacy `install()`.)

---

## Adding a schema change to an existing plugin

1. Append the new step to the **end** of the top-level manager's `schema()`:

   ```php
   public function schema()
   {
       return [
           $this->createSql(),                       // 0
           "ALTER TABLE `mytable` ADD COLUMN ...",   // 1  ← new
       ];
   }
   ```

2. That's it. Installed copies of the plugin will report "update available" and
   the admin applies it. No `FOG_SCHEMA` bump is required for detection.

   > **Note:** the bundled migration that adds the `pSchema` column *does* ride
   > along with a `FOG_SCHEMA` bump (it is a core table change). Subsequent
   > *plugin* schema changes do not need one — detection is per-plugin.

---

## Special cases

### Seed / default data

If a step inserts default rows or settings, it must be safe to run on a system
that already has them (an existing install upgrading from `pSchema = 0`):

- **Rows with explicit primary keys** (e.g. accesscontrol's roles/rules): a plain
  `INSERT` is fine — a re-run hits `1062` and is skipped. Existing rows are
  preserved.
- **Settings without a unique key** (e.g. capone, ldap — `globalSettings.settingKey`
  is only indexed, not unique): insert **only if absent**, so an admin's
  customized value is never overwritten. Use a callable step that checks
  `SettingManager::exists($key, '', 'name')` before inserting.
- **Run-time values** (e.g. accesscontrol's Administrator→fog-user row): resolve
  the value inside a callable step and tolerate the duplicate-entry error.

### Triggers (persistentgroups)

A trigger has no data, so drop-and-recreate is non-destructive but not covered by
the idempotency skip-list. Model it as a callable step that does
`DROP TRIGGER IF EXISTS` then `CREATE TRIGGER`. A future trigger change ships as a
**new appended step** that drops-and-recreates with the new definition.

### Plugins that own no table

`taskstateedit` / `tasktypeedit` edit existing core tables and own nothing. They
have no `schema()` method, so they never report "update available" and
`installdb()` falls back to their no-op `install()`. Nothing to do.

---

## Key files

| File | Role |
|------|------|
| `src/Items/Schema.php` | `Schema::applyUpdates()` — the shared idempotent runner |
| `src/Items/Plugin.php` | `Plugin::installdb()`, `Plugin::needsSchemaUpdate()`; `pSchema` field map |
| `src/Managers/PluginManager.php` | `PluginManager::getPluginsNeedingUpdate()` |
| `src/Pages/PluginManagement.php` | `upgrade`/`upgradePost` action; list JSON enrichment |
| `src/Pages/DashboardPage.php` | "needs update" dashboard banner |
| `management/js/fog/plugin/fog.plugin.list.js` | "Update available" badge + bulk Update button |
| `commons/schema.php` | core migration that adds the `plugins.pSchema` column |
| `lib/plugins/location/src/Managers/LocationManager.php` | reference implementation |
