# A plugin is laid out like core

## Status

accepted, and implemented on `working-1.6` together with fog-plugins v1.6.23.
The two halves are not separable: old core with new plugins, and new core with
old plugins, are each a server whose plugins do not load.

Supersedes the plugin half of [ADR 0013](0013-flat-fog-namespace-and-the-reverse-alias-abi.md)'s
2026-08-31 amendment, which said in terms that namespacing the plugins was
"namespacing and not a second PSR-4 move". It was a second PSR-4 move; this
records that decision and why the first pass stopped short of it.

## Context

Core finished its own PSR-4 migration first. Every class in the web tree is
`packages/web/src/<Bucket>/<Class>.php` declaring `FOG\<Bucket>\<Class>`, the
autoloader derives the path from the name, and `FOGBase::coreitems($bucket)`
enumerates pages, hooks, reports and events by reading the bucket directory.

Plugins were left half-migrated. fog-plugins v1.6.22 gave every plugin class a
namespace — `FOG\Plugins\<Plugin>\<Class>` — but did not move a single file.
`ldap/class/ldapmanager.class.php` still declared `LDAPManager`, still found
by a filename suffix, and the namespace said `FOG\Plugins\...` while
Composer's own map said `FOG\` means `packages/web/src/`. Two conventions in
one tree, and the one a plugin author had to learn was the older one.

That mismatch cost more than tidiness:

- **Six filename suffixes had to be scanned for.** `Initiator` walked the
  whole web tree plus the external plugin root with a
  `*.(class|page|hook|event|report|task).php` regex, keyed the result on a
  lowercased basename, and cached it. That map was the most expensive part of
  the request bootstrap and the reason `filelist.*.json` exists.
- **The map was one flat key space shared by core and every plugin.** Two
  files claiming one key were resolved by whichever the directory walk reached
  first — readdir order, which differs per install. A bundled plugin shipping
  `class/authorization.class.php` could replace core's `Authorization` on some
  installs and not others. `autoload()` grew a core-wins rule for it, and
  `psr4-bridge.test.php` grew a second guard for the classes core-wins could
  no longer reach.
- **Discovery derived a class name from a basename and then resolved it.** A
  plugin whose filename and class name disagreed named a class no file
  declared, and `get_class_vars()` on that name is an uncaught `TypeError` out
  of `FOGPageManager`'s constructor — a bodyless 500 on every page of the
  admin UI, not just the plugin's own. Since [ADR 0009](0009-plugins-become-installable-artifacts.md)
  that is reachable by uploading a plugin through the browser.
- **A plugin that got it wrong failed silently.** ADR 0009 already names this:
  *"The contracts are conventions. Class name must equal filename; directory
  name must equal the routing node… Real rules, enforced by silent failure."*

## Decision

**A plugin lays its PHP out exactly as core does.**

```
ldap/
  config/plugin.config.php        manifest — unchanged
  js/fog.ldap.*.js                assets — unchanged
  src/
    Items/LDAP.php                FOG\Plugins\LDAP\Items\LDAP
    Managers/LDAPManager.php      FOG\Plugins\LDAP\Managers\LDAPManager
    Pages/LDAPManagement.php      FOG\Plugins\LDAP\Pages\LDAPManagement
    Hooks/AddLDAPLogin.php        FOG\Plugins\LDAP\Hooks\AddLDAPLogin
    Reports/LDAP_Report.php       FOG\Plugins\LDAP\Reports\LDAP_Report
    Tasks/LDAPSync.php            FOG\Plugins\LDAP\Tasks\LDAPSync
    Util/LDAPHelper.php           FOG\Plugins\LDAP\Util\LDAPHelper
```

Two rules, both gated in CI by fog-plugins' `tests/plugin-layout.test.php`:

1. **`<plugin>/src/<Bucket>/<Class>.php` declares
   `FOG\Plugins\<Segment>\<Bucket>\<Class>`.** Class name equals file name,
   case-sensitively, exactly as under `packages/web/src`.
2. **`strtolower(<Segment>) === <directory name>`.** The directory stays
   lowercase — it is `plugins.pName`, `?node=ldap`, the `ldap.view` permission
   string and the JS path in `Hook::injectPluginJS()` — while the namespace
   segment carries readable casing: `FOG\Plugins\LDAP`, not
   `FOG\Plugins\Ldap`. No schema migration, no URL change, no RBAC churn.

**Enumerated buckets**, which core walks to register things: `Hooks`, `Pages`,
`Events`, `Reports`, `Tasks`. **Autoload-only buckets**: `Items`, `Managers`,
`Util`, and anything else an author invents. Identical to how core treats its
own `src/`.

**Report classes keep their underscored names** (`LDAP_Report`), because
`ReportManagement::reportTitle()` derives the menu key as
`str_replace('_', ' ', strtolower($short))` and core's own reports are
`Fleet_Report.php`. Renaming them would break the agreement between the report
class, the `REPORT_TITLE_DATA` hook key, the base64 `f` parameter and the
JS `case` label — so matching core is also the safe option.

**A plugin still on the pre-1.6 layout is refused, and refused loudly.** It
contributes no classes and `Initiator::_scanPluginSource()` logs it by name,
once, naming the layout it needs. It is still *discovered* — a plugin is a
directory with `config/plugin.config.php`, and nothing else — so it is still
listed in Plugin Management, which is what makes the diagnosis reachable
without a shell.

## Consequences

**The core-side machinery gets smaller, not larger.** Because layout is now
enforced, the autoloader *derives* the path rather than searching for it:
`FOG\Plugins\LDAP\Managers\LDAPManager` is
`<root>/ldap/src/Managers/LDAPManager.php`, at most one `is_file()` per plugin
root, with nothing read, scanned or cached to know it. Retired outright:

| gone | what it was |
|---|---|
| `Initiator::classFileList()` and its `filelist.*.json` cache | a recursive walk of the whole tree for six filename suffixes |
| `Initiator::$classMap` and its core-wins collision rule | the flat basename key space core and plugins shared |
| `Initiator::_scanClassFiles()`, `_scanRoots()`, `_readFileListCache()`, `_isPluginPath()` | the scan and its supporting cast |
| `set_include_path()` / `spl_autoload_extensions()` at boot | an include_path built from that scan, for a built-in resolver no longer registered |
| `FOGBase::fileitems()` | the filter over that list every discovery consumer used |
| `_bridgeNamespaced()`'s resolution arm | it answered the flat `namespace FOG;` files; there are none left |

and `Initiator::pluginFileList()` — a bounded stat walk of
`<plugin>/src/<Bucket>/*.php`, for discovery only — replaces the
`file_get_contents` of all 176 plugin files that v1.6.22 needed to learn what
namespace each declared.

Discovery gains one helper beside `coreitems()`:

```php
FOGBase::pluginitems(string $bucket): array
```

and every consumer collapses from a two-shape merge to a bucket lookup:

| consumer | before | after |
|---|---|---|
| `FOGPageManager::loadPageClasses()` | `fastmerge(coreitems('Pages'), fileitems('.page.php','pages'))` | `fastmerge(coreitems('Pages'), pluginitems('Pages'))` |
| `EventManager::load()` | the same, twice, keyed on `$fileExtension`/`$fileDirectory` | `coreitems($this->fileBucket)` + `pluginitems($this->fileBucket)` |
| `ReportManagement::loadCustomReports()` | the same, `.report.php` | `pluginitems('Reports')` |
| `PluginRunner::_discoverTasks()` | `glob('<plugin>/tasks/*.task.php')` | `glob('<plugin>/src/Tasks/*.php')` |

`FOGBase::classFromDiscoveredFile()` stops stripping a suffix and looking the
result up: it derives the FQCN from the path, for core and plugin alike. That
removes the failure class where discovery could name a class no file declares.

**Shadowing is now structural rather than a rule.** Core's classes are under
`FOG\`, a plugin's under `FOG\Plugins\`, and neither is reachable by a bare
name. The one flat space they still share is the BARE spelling, and exactly
one thing decides it: `FOGBase::qualify()` consults core's map before the
plugin one. That ordering is the whole guarantee, and it matters because
`Authorization::_scopeClassVars()` resolves a node to its model through
`qualify()` — a wrong answer there is access control silently testing the
wrong table.

**Third-party plugins written against the old layout break.** Accepted
explicitly, by the maintainer, as the price of one way to write FOG code. The
break is loud (a named diagnostic per plugin) rather than silent, which is
strictly better than the previous behavior — a plugin whose pages simply never
registered, with nothing anywhere saying why.

**The two repositories move together.** fog-plugins v1.6.23 ships the new
layout and `FOG_PLUGINS_VERSION` is bumped in the same fogproject pull request
that lands this, for the same reason [ADR 0009](0009-plugins-become-installable-artifacts.md)
gives: the pin is what makes a plugin release and a core release one shippable
thing.
