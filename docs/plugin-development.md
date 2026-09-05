# Building a FOG Plugin — Start to Finish

This guide walks you from an empty directory to a working, installable FOG
plugin on the **working‑1.6** framework. It uses a complete, runnable example
plugin — **`helloworld`** — which lives in
[`FOGProject/fog-plugins`](https://github.com/FOGProject/fog-plugins) and
lands at `packages/web/lib/plugins/helloworld/` once the plugins are fetched.
Copy that directory, rename it, and you have a head start.

> The bundled plugins are no longer committed to this repository. A fresh
> clone has no `packages/web/lib/plugins/` at all until `bin/fetch-plugins.sh`
> populates it from the release `FOG_PLUGINS_VERSION` pins — which the
> installer does for you. Run it by hand if you just want the tree.

> **Scope:** this targets the working‑1.6 plugin framework (the `formFields` /
> `makeInput` page helpers, the `addPost`/`editPost` JSON pattern, and the
> non‑destructive `schema()` migration contract). The 1.5.x line renders pages
> differently (raw HTML strings, `*page.class.php` file names) and lacks the
> `schema()` migration mechanism; this guide does not cover it.

---

## 1. What a plugin is

A FOG plugin is just a directory containing PHP classes that FOG
auto‑discovers. There is no build step and no registration list to edit — drop
the directory in, activate the plugin in the UI (**Plugin Management**), and it
works.

There are two places that directory can live, and which one you pick matters:

| Root | For | Survives a FOG upgrade? |
|---|---|---|
| `packages/web/lib/plugins/<name>/` | plugins bundled with FOG itself, sourced from `FOGProject/fog-plugins` | No — the tarball re‑lays this tree |
| `/opt/fog/plugins/<name>/` (`FOG_PLUGIN_DIR`) | **everything third‑party** | Yes |

The installer's `configureHttpd()` does `rm -rf` on the web root before laying
the new one down, so a plugin installed into `lib/plugins/` is deleted by the
next `installfog.sh` run without warning. `FOG_PLUGIN_DIR` sits outside the web
root precisely so that cannot happen. See
[ADR 0009](adr/0009-plugins-become-installable-artifacts.md).

Bundling is not a route third parties take. `lib/plugins/` is filled from a
pinned `fog-plugins` release, so putting a plugin there means opening a PR
against that repository and waiting for FOG to pin a release containing it.
Ship your own archive instead — see §11a.

Discovery, the class autoloader and the routing all treat the two roots
identically; the only difference an external plugin sees is that its `js/`,
`css/` and `images/` are reached through a symlink FOG maintains for it (see
§10).

A typical plugin provides:

- a **model** (one entity / table row),
- a **manager** (the table + its migrations),
- a **page** (the UI and its form POST handlers),
- **hooks** (menu entry, JS injection, API exposure, …),
- **JS** files (one per sub‑page).

The running example, `helloworld`, manages a trivial entity with a `name` and a
`description`, end to end.

---

## 2. Mental model (how the pieces connect)

- **Boot chain.** Every entry point loads `commons/base.inc.php` →
  `commons/init.php` → `LoadGlobals`, which sets the shared singletons
  (`FOGBase::$DB`, `$HookManager`, `$EventManager`, `$currentUser`).
- **Autoloader.** `Initiator` turns a class name into a path and loads that
  file. Core's `FOG\Items\Host` is `packages/web/src/Items/Host.php`; your
  `FOG\Plugins\HelloWorld\Items\HelloWorld` is
  `<root>/helloworld/src/Items/HelloWorld.php`. Nothing is scanned, nothing is
  guessed and there is no `spl_autoload` fallback — the path **is** the name,
  which is why it has to be exact:

  > **`<plugin>/src/<Bucket>/<Class>.php` declares
  > `FOG\Plugins\<Segment>\<Bucket>\<Class>`.** File name equals class name,
  > character for character; `strtolower(<Segment>)` equals the plugin's
  > directory name.

  That is the same PSR-4 arrangement core uses on itself, and it is the one
  thing to get right before writing any class — §7a is the long version.
- **Routing.** The whole UI is driven by `?node=<x>&sub=<y>&id=<n>`. `node` maps
  to a page class (`helloworld` → `HelloWorldManagement`, matched by its
  `public $node = 'helloworld'`), and `sub` maps to a method on it
  (`sub=add` → `add()`, `sub=addPost` → `addPost()`, `sub=list` → the inherited
  DataTables list).
- **ORM.** Models declare `$databaseTable` and `$databaseFields`
  (friendly‑name → column). You then use `get()/set()/save()/load()/destroy()`,
  or `new HelloWorld(42)` to auto‑load by id.
- **Hooks/events.** Cross‑cutting integration is done by registering callbacks
  against named events: `self::$HookManager->register('EVENT', [$this, 'fn'])`
  and firing with `processEvent('EVENT', ['data' => &$data])`.

---

## 3. Directory layout

```
<root>/helloworld/                  # <root> = lib/plugins (bundled) or /opt/fog/plugins
├── config/
│   └── plugin.config.php            # the manifest ($fog_plugin[...])
├── src/                             # ALL PHP, laid out exactly like core's src/
│   ├── Items/
│   │   └── HelloWorld.php           # …\Items\HelloWorld      (model, FOGController)
│   ├── Managers/
│   │   └── HelloWorldManager.php    # …\Managers\…          (manager + schema())
│   ├── Pages/
│   │   └── HelloWorldManagement.php # …\Pages\…             (FOGPage)
│   ├── Hooks/
│   │   ├── AddHelloWorldMenuItem.php  # menu entry + search/objects
│   │   ├── AddHelloWorldJS.php        # JS injection
│   │   └── AddHelloWorldAPI.php       # REST API exposure
│   └── Tasks/
│       └── HelloWorldHeartbeat.php  # …\Tasks\…             (PluginTask)     
├── js/
│   ├── fog.helloworld.list.js
│   ├── fog.helloworld.add.js
│   └── fog.helloworld.edit.js
└── vendor/                          # optional — Composer dependencies, see 7b
    └── autoload.php
```

Everything under `src/` is `FOG\Plugins\HelloWorld\<Bucket>\<Class>`. Five
bucket names are **enumerated** — core lists the directory to find things to
register, so a class only takes effect if it is in the right one:

| Bucket | Holds | What core does with it |
|---|---|---|
| `Pages/` | `FOGPage` subclasses | routes `?node=<x>` to them |
| `Hooks/` | `Hook` subclasses | constructs them so they can register callbacks |
| `Events/` | `Event` subclasses | constructs them |
| `Reports/` | report pages | lists them in the Reports menu — see §9a |
| `Tasks/` | `PluginTask` subclasses | runs them on their interval — see ADR 0010 |

Every other bucket is **autoload-only**: `Items/`, `Managers/`, `Util/`, or
anything else you invent. Nothing enumerates them, so the name is yours to
choose — it just has to match the namespace. The convention above is core's,
and following it means someone who knows core knows your plugin.

The directory name **is** the plugin's machine name and routing `node`. Keep it
lowercase and use it consistently (`$fog_plugin['name']`, each hook's
`public $node`, the page's `public $node`). The namespace segment is that same
name with whatever casing reads well — `helloworld/` → `FOG\Plugins\HelloWorld`,
`ldap/` → `FOG\Plugins\LDAP` — and the only rule is that lowercasing it gets
you back to the directory name.

---

## 4. Step by step

### 4.1 `config/plugin.config.php` — the manifest

`Plugin::readManifest()` `include`s this file during discovery and reads the
`$fog_plugin` array.

```php
$fog_plugin = [];
$fog_plugin['name']        = 'helloworld';           // == directory name
$fog_plugin['description'] = 'Skeleton example plugin …';
$fog_plugin['menuicon']    = 'fa fa-cube fa-fw';     // "fa …" => icon; else <img src>
$fog_plugin['version']     = '1.0.0';                // your plugin's own version
$fog_plugin['fog_min']     = '1.6.0';                // oldest FOG it runs on
$fog_plugin['fog_max']     = '1.7.0';                // newest FOG it runs on
$fog_plugin['requires']    = ['location'];           // other plugins, by directory name
$fog_plugin['author']      = 'Your Name';
$fog_plugin['homepage']    = 'https://example.org/my-plugin';
```

| Key | Required | What it does |
|---|---|---|
| `name` | yes | Machine name and routing `node`. Must equal the directory name, lowercase. |
| `description` | no | Shown in Plugin Management. |
| `menuicon` | no | Defaults to `fa fa-plug fa-fw`. |
| `version` | no | Yours to number. Shown in the grid, stored in `plugins.pVersion`. Nothing compares it to anything. |
| `fog_min` / `fog_max` | no | The FOG range you support. An absent bound is no bound. |
| `requires` | no | Plugins that must be active first. |
| `author`, `homepage` | no | Attribution. |

**Every key except `name` is optional**, so a plugin written before the manifest
existed keeps working untouched.

#### How the compatibility range is enforced

- Activating or installing a plugin outside its range is **refused**, with the
  reason in the error toast. The whole batch is refused, not just the offending
  plugin — a partial success reported as "Plugins activated!" is worse than a
  clean failure.
- If a FOG upgrade moves the server out of the range of a plugin that is
  already active, the next boot **deactivates it** and logs why. `installed` and
  `pSchema` are left alone, so its tables and applied migrations survive and
  re‑activating once you ship a compatible build is one click.
- Only the **numeric core** of a version is compared. `FOG_VERSION` is
  `1.6.0-beta.3318` on a beta and `version_compare()` sorts that *below*
  `1.6.0`, so comparing raw would refuse `fog_min = '1.6.0'` on the entire beta
  branch.
- Plugins turned on in the same batch satisfy each other's `requires`, so the
  order you tick the checkboxes in doesn't matter.

> `menuicon_hover` and `entrypoint` used to appear here. Nothing ever read
> either one — no plugin has ever shipped the `html/run.php` that `entrypoint`
> named, and routing goes through the `node` → page‑class mapping. Both are
> gone; if your manifest still sets them they are simply ignored.

### 4.2 Model — `src/Items/HelloWorld.php`

```php
namespace FOG\Plugins\HelloWorld\Items;

class HelloWorld extends \FOG\Base\FOGController
{
    protected $databaseTable = 'helloWorld';
    protected $databaseFields = [
        'id'          => 'hwID',
        'name'        => 'hwName',
        'description' => 'hwDesc',
    ];
    protected $databaseFieldsRequired = ['name'];
}
```

That's the entire ORM contract. `$databaseFields` maps friendly names (used in
code and in the API) to real column names. `$databaseFieldsRequired` is enforced
on `save()`.

### 4.3 Manager + migrations — `src/Managers/HelloWorldManager.php`

The manager owns table creation and **schema evolution**. This is the most
important part to get right, so it gets its own section (§5). The shape:

```php
namespace FOG\Plugins\HelloWorld\Managers;

class HelloWorldManager extends \FOG\Base\FOGManagerController
{
    public $tablename = 'helloWorld';

    public function createSql()
    {
        return \FOG\Items\Schema::createTable(/* … */);
    }

    public function schema()
    {
        return [
            $this->createSql(),     // step 0 — create the table
            // append future steps here, never reorder/remove
        ];
    }

    public function install()
    {
        $res = \FOG\Items\Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
}
```

### 4.4 Page — `src/Pages/HelloWorldManagement.php`

The page extends `\FOG\Base\FOGPage`, declares `public $node = 'helloworld'`, and sets the
list columns in its constructor:

```php
public function __construct($name = '')
{
    $this->name = 'Hello World Management';
    parent::__construct($this->name);
    $this->headerData = [_('Name'), _('Description')];
    $this->attributes = [[], []];
}
```

You do **not** write a list/`index()` method — `FOGPage` provides it. The list
page renders a DataTable whose JSON comes from `?node=helloworld&sub=list`; the
columns are produced by the router from your model fields, so the column keys
available to the JS are `mainlink` (the linked name), `id`, and every field by
its **friendly name** (here `description`).

Forms are built with helpers and rendered with `formFields()`:

| Helper | Purpose |
|---|---|
| `self::makeFormTag(...)` | opening `<form>` |
| `self::makeLabel($class, $for, $text)` | a `<label>` |
| `self::makeInput($class, $name, $placeholder, $type, $id, $value, $required)` | an `<input>` |
| `self::makeTextarea(...)` | a `<textarea>` |
| `self::makeButton($id, $text, $class)` | a `<button>` |
| `self::selectForm($name, $items, $selected, ...)` | a `<select>` |
| `self::formFields($fields)` | renders a `[label => field]` array |
| `self::tabFields($tabData, $obj)` | the tabbed edit layout |
| `self::makeTabUpdateURL($tab, $id)` | the POST URL for a tab |

**The POST pattern.** `addPost()` and `editPost()` return JSON and follow the
same skeleton every time:

```php
public function addPost()
{
    self::checkAuthAndCSRF();                 // ALWAYS first
    header('Content-type: application/json');
    $name = trim(filter_input(INPUT_POST, 'name'));   // never raw $_POST

    $serverFault = false;
    try {
        // validate, then build + save the model …
        if (!$obj->save()) { $serverFault = true; throw new Exception(_('…')); }
        $code = \FOG\Router\HTTPResponseCodes::HTTP_CREATED;
        $msg  = json_encode(['msg' => _('…'), 'title' => _('…')]);
    } catch (Exception $e) {
        $code = $serverFault
            ? \FOG\Router\HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
            : \FOG\Router\HTTPResponseCodes::HTTP_BAD_REQUEST;
        $msg  = json_encode(['error' => $e->getMessage(), 'title' => _('…')]);
    }
    http_response_code($code);
    echo $msg;
    exit;
}
```

> Set `$serverFault = true` **only** when the failure is server‑side (a failed
> `save()`), so genuine failures return `500` and validation errors return
> `400`. Getting this backwards is a real bug we've fixed before.

The **edit** page uses tabs. `edit()` builds `$tabData` and calls
`tabFields()`; each tab has a `generator` closure that renders its body
(`helloworldGeneral()`), and `editPost()` dispatches on the global `$tab` to the
matching `*GeneralPost()` that mutates `$this->obj` before the shared `save()`.

### 4.5 Hooks — `src/Hooks/*.php`

Each hook is a small class extending `\FOG\Base\Hook`, with `public $node`, that registers
callbacks **in its constructor**. Use `registerInstalled()` — it applies the
"only when this plugin is installed" guard for you and takes an ordered list of
`[event, method]` pairs:

```php
public function __construct()
{
    parent::__construct();
    $this->registerInstalled([
        ['MAIN_MENU_DATA', 'menuData'],
        ['PERMISSION_REGISTRY_DATA', 'permData'],
    ]);
}
```

A listener may also be a **closure**, which is the shape the three
authentication seams in §7 use — one callback, registered inline, no method to
name:

```php
self::$HookManager->register('LOGIN_PAGE_PROVIDERS', function ($args) { /* ... */ });
```

Both shapes are supported and nothing else is: a bare function name and
`[SomeClass::class, 'staticMethod']` are refused. Prefer `registerInstalled()`
when a hook registers several callbacks, since it also carries the
installed-plugin guard.

**`$active` decides whether your callbacks run.** `Hook` inherits
`public $active = true;` from `Event`, so you get it for free and only need to
declare it if you want the hook off. It is read from the class's declared
default when the file is loaded, and from the live property each time an event
fires — so a hook is free to compute it in its constructor, and a plugin can
turn one of its own hooks off. A closure obeys the `$active` of the hook it was
written inside; a closure with no `$this` — a `static function`, or one created
outside a hook — has no owner and always runs.

> Before 1.6.0 a hook whose file path contained the string `plugins` was
> force-activated regardless of its flag, so `$active` was decorative for every
> plugin hook. See `docs/adr/0017-hook-dispatch-contract.md`.

The example ships three hooks:

- **Menu** (`AddHelloWorldMenuItem`) — `MAIN_MENU_DATA` adds the sidebar entry;
  `SEARCH_PAGES` makes it searchable; `PAGES_WITH_OBJECTS` enables the
  edit/delete object flow. (`SUB_MENULINK_DATA` would add extra sub‑links such
  as Export/Import — omitted here.)
- **JS** (`AddHelloWorldJS`) — `PAGE_JS_FILES` injects `fog.<node>.<sub>.js` for
  the current sub‑page.
- **API** (`AddHelloWorldAPI`) — `API_VALID_CLASSES` exposes the node over REST
  so `/fog/helloworld` reuses the same ORM as the UI.

> **Name API classes after your permission node.** Access to a REST class is
> resolved to `<node>.<action>` by matching the class name against the nodes
> registered through `PERMISSION_REGISTRY_DATA`. A class is claimed by a node
> when it either *is* the node (`site`) or *starts with* the node and ends in
> `association` (`sitehostassociation`) — the same shape core uses for
> `groupassociation` → `group`. Longest match wins.
>
> A class no node claims is restricted to administrators and logs a line
> naming it. That is deliberate: an unmapped class used to be readable and
> writable by **any** authenticated user regardless of role. If your endpoint
> is admin-only when you did not intend it to be, check the log and rename the
> class (or register the node) rather than working around it.

> **Register your node, or your page is admin-only.** The same stance applies
> to the management page, not just the REST classes: a node absent from the
> permission registry resolves to `unmapped.<node>`, which no role can be
> granted, so only a holder of `*` can reach it — and its sidebar entry is
> hidden from everyone else. It logs one line per node per request naming
> what to register. Firing `PERMISSION_REGISTRY_DATA` is therefore not
> optional; a plugin that skips it is not "ungated", it is unreachable.
>
> ```php
> public function permData($arguments)
> {
>     $arguments['registry'][$this->node] = ['view', 'create', 'edit', 'delete'];
> }
> ```

### 4.6 JavaScript — `js/fog.helloworld.*.js`

One file per sub‑page (`list`, `add`, `edit`), each an IIFE. The **list** file
registers the server‑side DataTable and the create modal; its `columns[].data`
keys must match the list endpoint (`mainlink`, then your field names) and their
order must match `$headerData`. The **add**/**edit** files wire the form buttons
to `processForm()` (which POSTs and shows notifications) and, on edit, the delete
confirm modal to `$.apiCall(... &sub=delete ...)`.

Shared helpers you'll use: `Common.node`, `Common.id`, `Common.search`,
`$.apiCall()`, `$.deleteSelected()`, `<form>.processForm()`,
`$('#dataTable').registerTable()`.

---

## 5. Database & migrations (the important part)

FOG has **no automatic per‑column migration**. `Schema::createTable()` emits
`CREATE TABLE IF NOT EXISTS`, which does nothing on a table that already
exists — so simply adding a column to `createSql()` will **not** reach existing
installs. Use the **`schema()` contract** instead.

**`schema()` returns an ordered, append‑only list of steps.** Each step is a SQL
string (or a closure returning SQL). On install/upgrade the framework
(`Plugin::installdb()`) calls:

```php
Schema::applyUpdates($manager->schema(), $applied);
```

where `$applied` is the count stored in the plugin's `pSchema` column. Only
steps from index `$applied` onward run, and the new count is saved back. So:

> **To add a column later, append a new step. Never reorder or delete existing
> steps** — the applied count is positional.

```php
public function schema()
{
    return [
        // 0 — create the table
        $this->createSql(),
        // 1 — added later; runs once on upgrade, skipped thereafter
        "ALTER TABLE `helloWorld` ADD COLUMN `hwColor` VARCHAR(255) NULL",
    ];
}
```

`applyUpdates()` is defensive: it ignores "already exists / duplicate column /
duplicate key / unknown column / duplicate entry" errors, so re‑running is
safe. A closure step may return a string to signal a hard error and stop.

Seed data (e.g. default `globalSettings` rows) is just another step — return the
`INSERT` SQL, or a closure for anything that needs runtime values (see
`persistentgroupsmanager`'s `schema()` for the closure pattern).

**Retiring a table works the same way: append a `DROP`, never delete the
`CREATE`.** Deleting step 3 renumbers everything after it, so an install that
had applied four steps now believes it has applied a different four. The same
goes for *editing* a step in place — an install that already passed it never
sees the change, which is invisible until somebody upgrades from an older
version. The `ldap` plugin's steps 10–16 exist to repair exactly that.

> **Legacy note.** Older plugins implement a destructive `install()` that calls
> `uninstall()` (drop) then recreates. New plugins should implement `schema()`
> (the framework prefers it and falls back to `install()` only when `schema()`
> is absent). The example provides both; its `install()` just applies the schema
> from `0`.

---

## 6. Lifecycle

1. **Discovery.** `Plugin::getPlugins()` scans **both** plugin roots, reads each
   manifest, and upserts a row in the `plugins` table. It also maintains the
   asset symlink for external plugins and deactivates any active plugin that
   this FOG version has moved out of range.
2. **Activation.** An admin enables the plugin in **Plugin Management**. Its
   `node` is added to `FOGBase::$pluginsinstalled`, which is what every hook
   constructor checks before registering. Refused if `fog_min`/`fog_max`/
   `requires` say it cannot run here.
3. **Install / upgrade.** `Plugin::installdb()` runs `schema()` via
   `applyUpdates()` and tracks `pSchema`. This is idempotent and
   non‑destructive — safe to run on every upgrade.
4. **Uninstall.** Inherited `uninstall()` drops the table; override it if you
   need to clean up settings, associations, or users you created.
5. **Code removed.** Deleting the plugin directory does **not** delete its row.
   Discovery only ever walks directories that exist, so nothing would visit
   the row again to clean it up — and absence is not reliably permanent (an
   unmounted external root, or the web tree mid-upgrade, makes every plugin
   vanish at once). The row keeps its state and its `pSchema` count, so
   putting the code back resumes exactly where it left off.

   Plugin Management badges such a row **Missing**, refuses to activate or
   install it, and offers **Forget selected** to delete the row deliberately.
   Forget leaves the plugin's tables behind and says so: what to drop is
   described by `schema()`, which is part of the code that has gone.

---

## 7. Settings

Global configuration lives in the `globalSettings` table.

- Read: `FOGBase::getSetting('FOG_PLUGIN_HELLOWORLD_FOO')`
- Write: `FOGBase::setSetting('FOG_PLUGIN_HELLOWORLD_FOO', $value)`
- Naming: `ALL_CAPS_WITH_UNDERSCORES`, prefixed `FOG_PLUGIN_<NAME>_…`.
- Create defaults as a `schema()` seed step (an `INSERT` into `globalSettings`).

---

## 7a. Where a class lives and what it is called

**Read this before you write a class.** A plugin is laid out exactly like core
and discovered by exactly the mechanism core uses on itself: PSR-4 under
`src/`, bucketed by kind, no filename suffixes and no scanning. Core is
reachable **only by its fully qualified name** — bare `FOGController`, `Host`,
`Hook` resolve to nothing — and so is your own code.

### The rule

**`<plugin>/src/<Bucket>/<Class>.php` declares
`FOG\Plugins\<Segment>\<Bucket>\<Class>`.** Three halves, all load-bearing:

1. **File name equals class name**, character for character. `HelloWorld.php`
   declares `HelloWorld` — not `helloworld.php`, not `HelloWorld.class.php`.
2. **Directory path equals namespace tail.** A file in `src/Managers/` declares
   `…\Managers\…`. Move the file, change the namespace.
3. **`strtolower(<Segment>)` equals the plugin's directory name.** The segment
   carries readable casing — `helloworld/` → `FOG\Plugins\HelloWorld`, `ldap/`
   → `FOG\Plugins\LDAP` — but lowercasing it must land back on the directory,
   because the directory name is also `plugins.pName`, the `?node=` value, the
   `<node>.view` permission string and your `js/` URL.

Both 1 and 3 are checked by a test in fog-plugins' own suite, so a mistake is a
red build rather than a page that silently never appears.

```php
// src/Items/HelloWorld.php
namespace FOG\Plugins\HelloWorld\Items;

class HelloWorld extends \FOG\Base\FOGController {}
```

```php
// src/Managers/HelloWorldManager.php
namespace FOG\Plugins\HelloWorld\Managers;

class HelloWorldManager extends \FOG\Base\FOGManagerController {}
```

```php
// src/Pages/HelloWorldManagement.php
namespace FOG\Plugins\HelloWorld\Pages;

class HelloWorldManagement extends \FOG\Base\FOGPage {}
```

A model and its manager sit in **different** buckets and that is fine:
`FOGController::getManager()` derives the manager as
`qualify(shortName($this) . 'Manager')` — a *short* name, resolved through the
same map that answers `getClass('HelloWorld')` — so it finds
`…\Managers\HelloWorldManager` from `…\Items\HelloWorld` without either
class naming the other.

This is what every bundled plugin does — copying one gets you the house
style. The names you reference on core are bucketed the same way, matching the
directory they live in under `src/`; there is no flat `FOG\Host`:

| Bare name you used to write | Now |
|---|---|
| `FOGController` | `\FOG\Base\FOGController` |
| `FOGManagerController` | `\FOG\Base\FOGManagerController` |
| `FOGPage` | `\FOG\Base\FOGPage` |
| `Hook` / `Event` | `\FOG\Base\Hook` / `\FOG\Base\Event` |
| `PluginTask` | `\FOG\Base\PluginTask` |
| `FOGBase` / `FOGCore` | `\FOG\Base\FOGBase` / `\FOG\Base\FOGCore` |
| `Route` | `\FOG\Router\Route` |
| `HTTPResponseCodes` | `\FOG\Router\HTTPResponseCodes` |
| `Schema` | `\FOG\Items\Schema` |
| `Authorization` | `\FOG\Auth\Authorization` |
| `Host`, `Image`, `User`, `TaskType`, … | `\FOG\Items\<Name>` |
| `HostManager`, `TaskTypeManager`, … | `\FOG\Managers\<Name>Manager` |

A `use` import works too, and is the better shape if you name a class many
times — it sits below your own `namespace` declaration, not instead of it:

```php
namespace FOG\Plugins\HelloWorld\Items;

use FOG\Base\FOGController;

class HelloWorld extends FOGController {}
```

Both forms are correct. The FQCN form is what most of the bundled plugins use,
so copying one of them gets you the house style.

### Discovery reads bucket directories, not filenames

There are no `.page.php` / `.hook.php` / `.event.php` / `.report.php` /
`.task.php` suffixes any more, and nothing walks your plugin looking for them.
A page is a page because it is in `src/Pages/`; core lists that directory —
`FOGBase::pluginitems('Pages')`, the exact counterpart of `coreitems('Pages')`
— and derives the class name from the path. Same for the other four enumerated
buckets. Anything else you ship is simply autoloaded when something names it.

Bare spellings still work wherever **core** does the resolving:
`FOGController::getManager()`, `FOGPage::$childClass`, `Route::_newEntity()`,
`Authorization`'s object-scope lookup and a variable handed to `getClass()` all
take a short name. Core builds that map from the file *paths* now rather than
by reading every plugin file, so you never spell the namespace out anywhere
except the `namespace` declaration itself.

That is about names core is *given*. Names **you** write are a different
question: spell those fully qualified rather than routing them through
`getClass()`, per ADR 0043 and the section below.

**`class_alias()` is not needed and should not be used.** It was the workaround
for a gap that no longer exists — discovery once derived a bare class name from
`basename($file)`, so a namespaced plugin had to alias itself back into the
global namespace or never register. Shipping one now just adds a second,
redundant name for the same class.

### A plugin on the pre-1.6 layout is refused, out loud

This replaced the old `class/ pages/ hooks/ …` layout, and it is not
backwards compatible — that was decided deliberately (see
[ADR 0035](adr/0035-a-plugin-is-laid-out-like-core.md)). One consistent way to
write FOG code was judged worth breaking third-party plugins written against
the old shape.

The failure is at least a loud one. A plugin directory with no `src/` but with
a `class/` gets one line in the log naming itself and this guide, rather than
being silently absent the way a missing class used to be. **Porting is a move
and a namespace line**, per the table in §11b — no logic changes.

### Two plugins, one class name, no collision

Two plugins can each ship a class called `Settings` without one shadowing the
other, and so can a plugin and core: the FQCNs differ, so the paths differ, so
both files load. This is now *structural* rather than a rule anyone enforces —
`FOG\Plugins\Alpha\Items\Settings` and `FOG\Items\Settings` cannot resolve
to the same file, because each name derives its own.

The one keyspace still shared is the **short** name, used by `getClass()` and
friends. Core wins it: a plugin class whose short name matches a core class is
reachable only by its FQCN. Pick a distinctive name and the question never
arises.

### Name the class, do not fetch it by string

**Write `new \FOG\Plugins\LDAP\Managers\LDAPGroupManager()`, not
`self::getClass('LDAPGroupManager')`** (ADR 0043). The factory erased the
type — it is declared `@return object|mixed` — so nothing could check what you
then did with the result, and it was never a substitution seam: `qualify()`
consults core's map before the plugins', so a bare name can only ever resolve
to one class. Core was converted in the same sweep, and
`tests/getclass-literals.test.php` there refuses a literal `getClass()`.

Fully qualified rather than imported, because this repository's
`tests/core-references-are-qualified.test.php` refuses a bare core name
outright — a plugin tree is fetched on its own and cannot assume core's
class list is anywhere nearby.

`getClass()` itself has not gone and still resolves a bare name through
`FOGBase::qualify()`. Reach for it when the class is named by a **variable**,
which is the one thing `new` cannot express, and for
`getClass('X', '', true)`, which returns the default properties rather than an
instance.

### The one place a bare name still bites

Core resolves a bare name wherever *it* does the resolving: `getClass()` with a
variable, `getManager()`, discovery, `$childClass`, `Route::_newEntity()`,
`Authorization`. A class name **your own code** holds in a plain string is
resolved exactly as written, with no such mapping behind it — so a raw
`new $someString` or `is_subclass_of($x, 'SomeClass')` naming a bare plugin
class is not. Spell those fully qualified
(`\FOG\Plugins\LDAP\Managers\LDAPGroupManager`).

### What the failure looks like

A bare core name does not fail quietly. `Initiator::autoload()` recognizes it
and writes one line before giving up:

```
FOG autoloader: "FOGController" is a core class and core is no longer aliased
into the global namespace. Use FOG\Base\FOGController -- either as a `use`
import or fully qualified. See ADR 0013.
```

and the request then dies with `Class "FOGController" not found`. The flat
spelling gets its own line:

```
FOG autoloader: "FOG\Host" is not a class. Core is namespaced per bucket
under src/; use FOG\Items\Host. See ADR 0013.
```

If a plugin written against an earlier 1.6 beta has stopped loading, that log
line is why, and this section is the fix.

### `get_class($this)` returns a namespaced name

Unchanged advice, and now true of plugin classes too — it bites whatever
*produces* a class name rather than consumes one: comparing it to a literal,
building a column name or an array key from it, putting it in a filename or a
log line:

```php
// Wrong: 'FOG\Plugins\HelloWorld\Items\HelloWorld' -- comparison silently fails.
if (get_class($obj) === 'HelloWorld') { /* ... */ }

// Right: 'HelloWorld', namespaced or not.
if (self::shortName($obj) === 'HelloWorld') { /* ... */ }
```

`FOGBase::shortName()` takes an object or a class-name string, strips any
namespace prefix, and is a no-op on a name that has none.

### History

ADR 0013 originally kept a `class_alias()` in every core file re-exporting it
into the global namespace, and called that alias the 1.6 plugin ABI. **All 202
were deleted before 1.6.0 shipped and the ADR is amended accordingly** — there
was no released 1.6 for the promise to have been made to, and carrying the shim
through a major version bought compatibility with nothing.

Plugins then followed in two steps, both before 1.6.0. First the namespace:
every plugin class moved into `FOG\Plugins\<Plugin>`, discovered by reading
each file's `namespace` declaration, with the files left where they were. That
half-migration is what this section used to describe, and it was the confusing
state — the namespace said `FOG\Plugins\…` while the file said
`class/ldapmanager.class.php`, and core carried a whole scan-and-cache
mechanism to reconcile the two. Then the layout followed the namespace
(ADR 0035), which deleted that mechanism rather than replacing it: the path is
derived from the name, so there is nothing left to scan, read or cache.

## 7b. Composer dependencies

Your plugin may ship its own `vendor/`. Put it beside `config/`, exactly where
`composer install` leaves it, and FOG registers it at boot:

```
<root>/helloworld/
├── composer.json
├── vendor/
│   └── autoload.php               # FOG requires this if it is present
├── config/
...
```

Nothing else is needed — no hook, no manifest entry. Run `composer install` in
your plugin directory, ship the resulting `vendor/` inside the archive, and
your packages are autoloadable everywhere your plugin's PHP runs.

Two rules make that safe.

**Core wins.** Plugin loaders are registered last, after core's own Composer
loader and after FOG's class map. A name core can resolve is resolved by core,
whatever your `vendor/` contains.

**One copy of a package, project-wide.** A plugin whose `vendor/` claims a
namespace or class name already provided by core — or by a plugin that loaded
earlier — is **refused**: its loader is unregistered and a line explaining why
goes to the PHP error log. Only that plugin's vendored classes stop resolving;
the rest of it, and the rest of FOG, keep working.

This is not tidiness. Two plugins vendoring different majors of one package
both declare the same class names, and whichever registered first wins — the
other silently runs against a version it was never tested against. For an
authentication or crypto library that is a security bug, so FOG makes it a
loud failure instead.

**What core provides, depend on rather than vendor:**

| Package | Since | For |
|---|---|---|
| `firebase/php-jwt` | 1.6.0 | JWT decode and JWKS parsing |

Declare it in your `composer.json` as usual and mark it `"replace"`-free — just
do not commit your own copy into `vendor/`. If you need a version core does not
ship, raise it with the project rather than working around it; a second copy is
exactly what the refusal is there to stop.

**Caveat.** Composer's `files` autoload runs the moment the file is required,
so a package with side effects at load time may have executed before a refusal
takes effect. Keep boot-time side effects out of vendored code you expect FOG
to load.

## 7c. Authentication extension points

Three seams a plugin can use to add a way of signing in. Each is gated, and in
every case **declaring nothing means denied** — none of them is a way to make
something reachable by accident.

### A route — `API_PLUGIN_ROUTES`

```php
self::$HookManager->register('API_PLUGIN_ROUTES', function ($args) {
    $args['routes'][] = [
        'name'       => 'oidcCallback',           // bare identifier
        'method'     => 'GET',
        'path'       => '/ext/oidc/callback',     // must be under /ext/
        'handler'    => ['FOG\OidcRoutes', 'callback'],
        'auth'       => 'public',                 // default 'required'
    ];
    $args['routes'][] = [
        'name'       => 'oidcConfig',
        'method'     => 'POST',
        'path'       => '/ext/oidc/config',
        'handler'    => ['FOG\OidcRoutes', 'saveConfig'],
        'permission' => 'oidc.edit',              // required when not public
    ];
});
```

| Rule | Why |
|---|---|
| Path must start with `/ext/` | Core mints new top-level paths from its API class list, so today's free path is not tomorrow's. Under `/ext/` the two namespaces cannot meet |
| Route name is registered as `ext:<name>` | The name is what the permission layer keys on; without a prefix a route called `status` would inherit core's "no check" |
| `auth` is `'public'` only when it is exactly that string | A typo, or a truthy value someone thought meant "needs auth", must not open a route |
| A public route must be a literal path | The unauthenticated test is an exact string match, so a path with `[i:id]` in it cannot be expressed there |
| No `permission` and not public → **403**, not 404 | The route still registers, so you get a log line telling you what to declare instead of a silent miss |

Registered after every core route, so a core path always matches first.

### A session-less page node — `PAGE_EXEMPT_NODES`

Every page node is permission-checked. That is right for a settings page and
impossible for the page a visitor reaches *before* they have a session.

```php
self::$HookManager->register('PAGE_EXEMPT_NODES', function ($args) {
    $args['nodes'][] = 'oidcstart';
});
```

You may only exempt a node **nothing else owns**. A node that is in the
permission registry — core's or another plugin's — is refused, because
"exempt" and "check this permission" are contradictory instructions about the
same node and the permissive one would win. Give the pre-authentication page a
node of its own.

### A login button — `LOGIN_PAGE_PROVIDERS`

```php
self::$HookManager->register('LOGIN_PAGE_PROVIDERS', function ($args) {
    $args['providers'][] = [
        'label' => _('Sign in with Acme'),
        'url'   => '/fog/ext/oidc/start',
        'icon'  => 'fa fa-key',
    ];
});
```

The start URL must be a **site-absolute path** or an **`https://` URL**.
`javascript:`, `data:`, protocol-relative `//host` and plain `http://` are all
refused — this is the one page every unauthenticated visitor sees. Label and
icon are escaped; the icon is additionally restricted to plain class
characters.

### Turning a proven identity into a session — `establishSession($source)`

Once your callback has proven who somebody is, hand the identity to FOG and
**say how you proved it**:

```php
$user = new \FOG\Items\User($uid);
$user->establishSession('oidc');
```

`$source` names the mechanism, not the account. It is recorded in the login
history entry and kept in the session, where `User::sessionAuthSource()` reads
it back. Two things need it: an audit that can distinguish an identity-provider
sign-in from a local password one, and the break-glass rule that local password
login keeps working when a provider is down — which has to be able to count
sessions by how they were made.

It is deliberately **not** the same thing as `users.uAuthSource`. That column is
a property of the *account* and says which directory owns it; `$source` is a
property of *this request*. An account owned by LDAP can still be signed in by
something else, and that is exactly the case worth being able to see.

Use a plain lowercase slug, up to 32 characters of `a-z0-9_-` starting
alphanumeric — normally just your plugin's name. Anything else is recorded as
`unknown` rather than passed through, because the value reaches an audit trail.
Omitting the argument means `password`, so existing callers are unchanged.

### What an auth plugin owes the install

Three rules. The first two are enforced — you will get an exception, not a
warning — and the third is a pattern you will get wrong by omission if nobody
tells you it exists. All three are ADR 0014.

**Only stamp `users.uAuthSource` on accounts you created.** Writing that column
takes local password login away from the account (`User::passwordValidate()`
refuses a local credential for any account carrying one). On an account you
provisioned that is correct — its password is a token nobody has seen, and the
stamp stops the leftover row becoming a login after your plugin is removed. On
an account an admin created it silently removes their password, which is the
thing an identity-provider outage has to fall back on. Both bundled plugins
check: LDAP returns early for an account that exists and is not already its
own, and OIDC stamps only what it provisioned itself.

**`User::save()` can throw.** Core refuses a write that would leave nobody able
to administer FOG without a directory — so the very first account you try to
convert on a fresh install, which is usually `fog`, is exactly the one that
will be refused. Let the exception reach the user with your own context added;
do not catch and continue, because continuing means reporting a sign-in that
did not happen.

**Record what you granted, or you cannot take it back.** A directory is
authoritative and is re-read on every login, so removing somebody from a group
has to downgrade them next time. That needs an answer to "which of this user's
roles are mine to remove?", and the two obvious answers are both wrong:

- *Remove everything not currently granted* — silently revokes whatever an
  admin attached by hand, and leaves no way to give a directory user anything
  extra.
- *Derive the managed set from your mapping tables* — deleting a mapping stops
  the role being yours, so it is never taken away. Removing a mapping would
  leave everyone who had it holding the role forever.

So keep a per-user record of what you granted (`ldapUserGrant`,
`oidcUserGrant`) and diff against **that**. It survives the mapping being
deleted, which is the whole point. Write it *after* the user is saved — a
just-provisioned account has no id before then.

## 8. Security & output conventions

- **Output:** wrap every user‑controlled value with `Initiator::e($value)` when
  echoing into HTML. All output also passes through the global
  `sanitizeOutput` buffer.
- **Input:** use `filter_input(INPUT_POST, 'key')` (or the already‑sanitized
  superglobals) — never raw `$_POST`/`$_GET`.
- **CSRF/auth:** call `self::checkAuthAndCSRF()` at the top of every state‑
  changing POST handler.
- **Instantiation:** `new \FOG\Plugins\HelloWorld\Items\HelloWorld()`, fully
  qualified. Not `self::getClass('HelloWorld')` — see ADR 0043.
- **Translation:** wrap UI strings in `_('…')`.
- **Secrets in your table:** if a column holds a credential — an API token, a
  webhook URL, a bind password — declare it through `API_SENSITIVE_FIELDS` or
  it is emitted in REST payloads and by the unauthenticated boot endpoint.
  Two tiers:

  | Tier | Stripped from | Use when |
  |---|---|---|
  | `fields` | lists and expanded relations | a client legitimately reads it back on a direct single GET (as fog-client does with `host.ADPass`) |
  | `always` | everything, single GET included | nothing outside the web tier ever needs it |

  ```php
  public function declareSensitiveFields($arguments)
  {
      $arguments['always'][$this->node][] = 'bindPwd';
  }
  ```

  Prefer `always` unless you can name the consumer that reads the field back.

- **Columns only your code should write:** `Route::edit()` and `Route::create()`
  copy a JSON body straight into your model's `databaseFields`, so by default
  anyone who can reach the API can set any column you declare. Declare the ones
  your server-side code maintains — a token you issue, a counter, a timestamp
  you stamp — through `API_SERVER_OWNED_FIELDS`:

  ```php
  public function declareServerOwnedFields($arguments)
  {
      $arguments['fields'][$this->node][] = 'apiToken';
  }
  ```

  A request that tries to **change** one gets a 400. A request that carries the
  same value it already had is let through, so a client that GETs your object
  and PUTs the whole thing back keeps working — don't "fix" that by rejecting
  the field's mere presence.

  This is a different question from `API_SENSITIVE_FIELDS`, which is about what
  leaves the server. A field can be one, the other, or both: `host.ADPass` is
  sensitive but genuinely settable over the API, so it is only in the first list.

---

## 8a. Object scope — narrowing *which* objects a user reaches

Permissions (§4.5) say what a user may **do**. Object scope says which objects
they may do it **to**. Core owns the seam; sites are core's own answer to it,
and your plugin can add its own boundary on any dimension you like — a
department, a customer, a tenant.

**Two halves, and you almost always want both.** A single-object check that
holds while the list is unfiltered is not a boundary: the user is refused the
host edit page and shown every host on the way to it, names, MACs and all.

| Event | Asked | Answer by setting |
|---|---|---|
| `OBJECT_SCOPE_CHECK` | may this user reach object `id` of `node`? | `$arguments['allowed'] = false` |
| `API_SCOPE_WHERE` | bound a list, as SQL | `$arguments['where'] = '<fragment>'` |
| `API_SCOPE_IDS` | bound a list, as ids | `$arguments['ids'] = [1, 2, …]` |

```php
public function scopeWhere($arguments)
{
    if (!in_array($this->node, (array)self::$pluginsinstalled)) {
        return;
    }
    // No acting user means no boundary. The service daemons and the boot
    // endpoints reach the read routes with nobody logged in, and narrowing
    // those to a department would break imaging rather than protect anything.
    if (!self::$FOGUser || !self::$FOGUser->isValid()) {
        return;
    }
    if ('host' !== $arguments['classname']) {
        return;
    }
    // $idExpr is the caller's own id column, already quoted and qualified,
    // so you need not know the table name or worry about ambiguity in a join.
    $arguments['where'] = sprintf(
        'EXISTS (SELECT 1 FROM `deptHosts` WHERE `dhHostID` = %s '
        . 'AND `dhDeptID` IN (%s))',
        $arguments['idExpr'],
        implode(',', array_map('intval', $this->deptIDsFor(self::$FOGUser)))
    );
}
```

**Prefer `API_SCOPE_WHERE`.** It costs one expression whatever the fleet size.
`API_SCOPE_IDS` reads every object the user may see into PHP on every request,
and is only consulted when nothing answered the fragment event. Register both
if you like — the fragment wins and the id list is not even asked for.

**Mind the tri-states. They are not the same tri-state.**

| | means "no boundary" | means "you may see nothing" |
|---|---|---|
| `API_SCOPE_WHERE` | leave `where` as `null` — **`''` is read as silence** | the literal fragment `'1=0'` |
| `API_SCOPE_IDS` | leave `ids` as `null` | `[]` |

The trap both ways round is that "unbounded" and "entitled to nothing" are both
falsy. `if (!$ids)` is true for `null` *and* `[]`, so a listener or a caller
written that way shows every object on the server to the one user entitled to
none. Say `'1=0'`, never `''`.

**What you cannot do.** Composition is deny-wins: core ANDs your fragment onto
its own and intersects your id list with its own, so you can only ever
**narrow**. You cannot grant a user an object core denies them — otherwise any
plugin could hand out another site's hosts by answering an event. A global `*`
holder is exempt from your boundary exactly as they are from core's.

**Don't read through `getIds()`/`getNames()` inside a listener** to compute your
boundary. Those are scoped reads, so they arrive back at your own listener; core
guards the re-entry and answers the nested read with its own boundary alone, but
you will get an answer you did not expect. Query your tables directly, as the
example above does.

Full reasoning, including why the events kept their `API_` prefix now that they
fire for page routes too: `docs/adr/0006-site-object-scope-boundary.md`.

---

## 9. Common hook events

| Event | Purpose |
|---|---|
| `MAIN_MENU_DATA` | add the top‑level sidebar entry (`hook_main[node] = [label, icon]`) |
| `SUB_MENULINK_DATA` | add sub‑links (Export/Import/…) under the node |
| `REPORT_TITLE_DATA` | name your report in the Reports menu — see §9a |
| `SEARCH_PAGES` | make the node searchable |
| `PAGES_WITH_OBJECTS` | enable the object (edit/delete) flow for the node |
| `PAGE_JS_FILES` | inject JS files for the current page |
| `PERMISSION_REGISTRY_DATA` | register the node and its actions — **required**, see §4.5 |
| `API_VALID_CLASSES` | expose the node over the REST API (name classes after your permission node — see §4.5) |
| `API_SENSITIVE_FIELDS` | keep credential columns out of API and boot-endpoint output — see §8 |
| `OBJECT_SCOPE_CHECK` | deny **one** object the acting user would otherwise reach — see §8a |
| `API_SCOPE_WHERE` / `API_SCOPE_IDS` | bound a **list** to the objects the acting user may see — see §8a |
| `API_SERVER_OWNED_FIELDS` | refuse API writes to columns your own code maintains — see §8 |
| `<NODE>_ADD_FIELDS` / `_GENERAL_FIELDS` | let others extend your forms |
| `<NODE>_ADD_POST` / `_EDIT_POST` / `_ADD_SUCCESS` / `_ADD_FAIL` | extension points around your saves |
| `HOST_MASSEDIT_FIELDS` / `HOST_MASSEDIT_APPLY` | change one of your own values across a whole host selection — see §9b |

Fire your own events with `&`‑by‑reference args so listeners can mutate them
(see the example's `HELLOWORLD_*` events).

### 9b. Changing a value across many hosts — `HOST_MASSEDIT_*`

Every other editing extension point a plugin has is a **per-object** edit with
a loop behind it. The ABI has a bulk *read* seam (`API_MASSDATA_MAPPING`) and a
bulk *delete* seam (`DELETEMASS_API`), and between them sat the operation
neither covers: changing a value across a set. `HOST_MASSEDIT_*` is that seam
(ADR 0038 decision 13).

It is why `location` and `ou` each shipped a whole second hook file whose only
job was to set one value across a group's members — and those files always
clobbered, because there was no way to say *leave this host alone*. Both were
converted to this seam in fog-plugins #36 and are the worked example to copy:
`AddLocationHost::massEditFields()` / `massEditApply()`.

**A tab on the Group page must be a grant.** Since the group split (ADR 0038)
the Group page carries only what a group *grants* to its members — snapins,
printers, modules, power schedules. Those are set-shaped: two groups'
contributions union instead of fighting, and a host added later receives them
without anyone re-saving. A plugin that injects a tab on `node=group` through
`PLUGINS_INJECT_TABDATA` must offer that same shape. If your value is a single
field a host holds one of — a location, an OU, a flag — it is not a grant, and
pushing it from a group tab is exactly the write-to-every-member loop this
seam replaced. Put it on `HOST_MASSEDIT_*` and inject your host tab on
`node=host` only. Core cannot check this for you (a tab is opaque HTML), so
the rule is enforced by review, and the tell is visible: `FOGPage::tabFields()`
does not draw the Plugins tab at all when nothing injects for a node, and a
stock 1.6 install shows no Plugins tab on the Group page. If yours puts one
there, ask whether what it holds is a grant.

**Contribute a field** — fired when the Mass Edit form is built, and again
when it is applied, with the same arguments both times so the two can never
disagree about which keys exist:

```php
public function massEditFields($arguments)
{
    $hostIDs = $arguments['hostIDs'];   // the selection, for your hint
    $arguments['fields']['myplugin_thing'] = [
        'label' => _('My Thing'),
        'input' => '<input class="form-control" '
            . 'name="value[myplugin_thing]" value=""/>',
        'hint'  => $this->sharedHint($hostIDs),
    ];
}
```

Three things about that array:

- **`name="value[<your key>]"`.** That is what the resolver reads.
- **Render it EMPTY.** There is no read path in this form. A control
  pre-filled from a selection has to answer *"pre-filled with which host's
  value"*, and there is no honest answer when they differ. Say what the hosts
  hold in the `hint` instead, where *(varies)* is sayable —
  `FOG\Util\SharedHostValues` computes exactly that in one query.
- **Do not draw an action control.** Core draws it. A plugin that drew its own
  could ship a two-state field, and a two-state field in a mass edit is the
  defect this whole design is about: it looks identical to a correct one until
  somebody's values are gone.

**Apply it** — you receive the host ids and the **resolved** instructions for
your own keys only, already reduced to one of three states:

```php
public function massEditApply($arguments)
{
    foreach ($arguments['actions'] as $key => $instruction) {
        switch ($instruction['action']) {
            case 'leave':                       // the default, always
                break;
            case 'set':
                $this->writeAll($arguments['hostIDs'], $instruction['value']);
                break;
            case 'clear':
                $this->clearAll($arguments['hostIDs']);
                break;
        }
    }
}
```

You never parse a sentinel, because there is no sentinel. Anything the request
got wrong — a missing action, a misspelled one, a key you never offered, a
value that arrived as an array — has already resolved to `leave` before you
see it. `FOG\Util\MassEdit` fails closed, and *leave alone* is what closed
means.

**Write in one statement, not a loop.** The core half sends one
`UPDATE ... WHERE hostID IN (...)` for four hundred hosts, and the row-backed
half is one delete plus one `insertBatch`. A `foreach` calling `save()` per
host is the shape the group page had, and it is what ADR 0038 exists to
remove.

### 9a. Naming your report

A file at `<plugin>/src/Reports/<Class>.php` becomes an entry in the Reports
menu automatically. **Report class names keep their underscores** — `OU_Report`,
`LDAP_Report` — exactly as core names its own (`src/Reports/Fleet_Report.php`),
because the menu key, the base64 `f` URL parameter and the permission node are
all derived from the file name with underscores turned into spaces and the whole
thing lowercased. Renaming `OU_Report` to `OuReport` would move the report to a
different URL under a different permission node.

The label it gets, if you do nothing, is that same derived name title-cased —
so `OU_Report.php` appears as "Ou Report" while the page it opens is headed
"Export OUs". Two names for one screen.

Name it once, in a hook, and both agree:

```php
$this->registerInstalled([
    ['REPORT_TITLE_DATA', 'reportTitle'],
]);

public function reportTitle($arguments)
{
    // Keyed by the FILE name with underscores as spaces, lower case --
    // the same key the menu and the base64 `f` parameter use.
    $arguments['titles']['ou report'] = _('Export OUs');
}
```

Then let the report itself read the same map, so the heading cannot drift
away from the menu entry again:

```php
public function file()
{
    $this->title = self::reportTitle();   // derived from the class name
    ...
}
```

The event fires **once per request**, not once per label: the map is memoized
after the first build. A listener registered later in the request does not
appear.

**The rows, and the export.** Put your query in `reportRows()`, returning the
DataTables envelope, and let `ReportManagement::getList()` emit it:

```php
protected function reportRows()
{
    \FOG\Router\Route::listem('ou');
    return (array) json_decode(\FOG\Router\Route::getData(), true);
}
```

That is what the "CSV (All)" toolbar button serves — the DataTables export
buttons can only see the rows the browser is currently holding, which on a
serverSide table is one page. Ask for the button with `fullExport` once
`reportRows()` is in place:

```js
$('#ou-report-table').registerReportTable(columns, {fullExport: true});
```

A report that still overrides `getList()` must NOT pass `fullExport`: the
export serves `reportRows()`, so it would hand back an empty file rather than
an error.

---

## 10. Gotchas (learned the hard way)

- **`CREATE TABLE IF NOT EXISTS` never alters a live table.** Add columns via a
  new `schema()` step, not by editing `createSql()`.
- **Core is FQCN-only.** `extends FOGController` is a fatal error, not a
  deprecation — see §7a. The autoloader logs one line naming the class and the
  name to use before the request dies, so check the error log first.
- **The class list is cached, and the installer drops it.** Both roots are
  listed once and memoized to `/opt/fog/cache/` for 300 seconds, so a file you
  add by hand can take that long to be seen. `installfog.sh` clears the cache
  for you; while developing, delete `/opt/fog/cache/*.json` or wait it out.
- **Path equals name.** `src/<Bucket>/<Class>.php` declares
  `FOG\Plugins\<Segment>\<Bucket>\<Class>`, and `strtolower(<Segment>)` is
  the plugin's directory name. A mismatch means the class does not load — the
  autoloader derives one path and only one. No `class_alias()` needed, not even
  for a page/hook/event/report/task. §7a has the shape.
- **A class in the wrong bucket is loadable but invisible.** Autoloading and
  discovery are separate: a `FOGPage` subclass under `src/Items/` resolves fine
  if something names it, and is never routed, because only `src/Pages/` is
  listed for pages. If your page exists but `?node=` 404s, check the directory
  before you check the code.
- **Your manager's name is checked at install.** If `src/Managers/` holds a
  file whose base name matches the manager the plugin expects but which
  declares something else, install refuses outright rather than reporting
  success having created nothing.
- **`menuicon`** beginning with `fa` is rendered as a font‑awesome icon;
  anything else is treated as an `<img>` `src`.
- **`$serverFault`** must be `true` only for server‑side failures, so HTTP
  status codes are honest (`500` vs `400`).
- **Hook constructors must early‑return** when the node isn't in
  `$pluginsinstalled`, or your hooks run for a plugin that isn't enabled.
  `registerInstalled()` does this for you.
- **A registration that fails is logged, not thrown.** `register()` catches an
  unusable listener, writes one line naming the class and the event, and
  returns — so a typo in a method name costs you a hook that silently never
  fires. If a callback isn't running, check the log before you check the event
  name.
- **List columns** in the JS must match `$headerData` order and the keys the
  router emits (`mainlink`, `id`, friendly field names).
- **An external plugin's assets are served through a symlink FOG maintains for
  you.** `/opt/fog/` is outside the document root, so the browser cannot reach
  it; every discovery pass (re)creates `lib/plugins/<name>` →
  `/opt/fog/plugins/<name>` so `../lib/plugins/<name>/js/…` resolves for a
  bundled and an external plugin alike. You do nothing — reference your assets
  the normal way and `Hook::injectPluginJS()` emits the right URL. Apache needs
  `Options +FollowSymLinks` (the installer sets it); nginx follows regardless.

---

## 11. Install & test your plugin

1. Copy `helloworld/` to `/opt/fog/plugins/<yourname>/` (or, for a plugin you
   intend to bundle with FOG itself, `packages/web/lib/plugins/<yourname>/`) and
   rename the directory, the classes, the files (each file keeps its class's
   exact name), the `FOG\Plugins\<Segment>` in every `namespace` line, every
   `$node`, and the `$fog_plugin['name']`.
2. Deploy to the web root (e.g. `copybacktrunk.sh "" "" "1.6"`) — only needed
   for a bundled plugin; `/opt/fog/plugins/` is already live.
3. In the UI: **Plugin System → Plugin Management → install/activate** your
   plugin.
4. Confirm: the sidebar entry appears, **Create New** saves a row (check the
   table exists and `pSchema` advanced), **list** shows it, **edit** updates it,
   **delete** removes it.
5. Quick static checks while developing:
   `php -l <file>` on each PHP file and `node --check <file>` on each JS file.

---

## 11a. Shipping your plugin to other people

Package it as a `.tar.gz` containing **one directory, named for the plugin**:

```
tar czf myplugin-1.0.0.tar.gz myplugin/
```

Publish the archive's `sha256sum` alongside it — Plugin Management shows the
checksum of what was uploaded so an admin can compare the two before
installing.

Admins have two routes:

- **`git clone` or untar into `/opt/fog/plugins/`** as root. Always available,
  nothing to switch on.
- **Plugin Management → Upload plugin.** Off by default; see below.

### The archive must survive validation

FOG unpacks the archive somewhere the autoloader does not look, reads the
manifest, and shows the admin what it found *before* anything is installed. It
is refused outright if:

| | |
|---|---|
| it isn't a readable `.tar.gz` | opened as a tar regardless of the file name |
| it holds anything other than exactly one top-level directory | including a stray file at the archive root |
| an entry path is absolute or contains `..` | an interior `..` gets past PharData; FOG checks anyway |
| there is no `<name>/config/plugin.config.php` | |
| the manifest's `name` isn't the directory name | |
| the plugin is outside its own `fog_min`/`fog_max` range | |
| a **bundled** plugin already has that name | a bundled plugin always wins the collision, so the upload could never load |
| it is over 64 MB | `post_max_size` usually bites first |

Symlinks need no rule: `PharData` writes them out as empty regular files, so
they cannot escape — but it also means **a plugin that relies on a symlink will
install subtly broken.** Don't ship one.

Uploading a plugin that is already in `/opt/fog/plugins` is an upgrade; the
admin is warned that files will be replaced, and the old copy is only deleted
once the new one is in place. Installing the files does **not** install or
activate the plugin — the admin still does that from the same page, so "the
files are here" and "this code is running" stay separate decisions.

### Turning uploads on (admins)

Two independent switches, both required:

1. `FOG_PLUGIN_UI_INSTALL_ENABLED` in **FOG Configuration → FOG Settings →
   Plugin System**.
2. `sudo bin/fog-plugin-uploads.sh enable`, which makes `/opt/fog/plugins`
   writable by the web server (and relabels it for SELinux). `disable` and
   `status` do what they say.

The upload route also needs the **`plugin.install`** permission, which is
deliberately *not* part of `plugin.edit`: activating a plugin that is already
on disk and adding new executable code to the server are different authorities.

> **Understand what you are turning on.** A plugin is PHP that FOG autoloads
> and runs as the web user. Making its directory web-writable means any
> file-write bug anywhere in FOG can put executable code on the server. That is
> why step 2 is a root command rather than something the settings page can do
> for itself — and why leaving uploads off and using `git clone` is a perfectly
> good answer.

---

## 11b. Porting a plugin from the pre-1.6 layout

If your plugin has a `class/` directory rather than a `src/` one, it is on the
layout that shipped before 1.6.0 and it will not load. The port is a file move
and a `namespace` line; **no logic changes**, and nothing about the manifest,
the routing node, the permission strings, the JS or the database moves with it.

Move each file, keeping its class's exact name and dropping the suffix:

| Was | Is now | Because it extends |
|---|---|---|
| `class/helloworld.class.php` | `src/Items/HelloWorld.php` | `FOGController` |
| `class/helloworldmanager.class.php` | `src/Managers/HelloWorldManager.php` | `FOGManagerController` |
| `class/oidcflow.class.php` | `src/Util/OIDCFlow.php` | `FOGBase` — no bucket of its own |
| `pages/helloworldmanagement.page.php` | `src/Pages/HelloWorldManagement.php` | |
| `hooks/addhelloworldjs.hook.php` | `src/Hooks/AddHelloWorldJS.php` | |
| `events/somethinghappened.event.php` | `src/Events/SomethingHappened.php` | |
| `reports/ou_report.report.php` | `src/Reports/OU_Report.php` | keep the underscores — §9a |
| `tasks/helloworldheartbeat.task.php` | `src/Tasks/HelloWorldHeartbeat.php` | |

`class/` splits three ways because it used to hold everything; which bucket a
file belongs in is decided by what its class extends, not by what it is called.

Then, in each file, extend the namespace with the bucket:

```php
-namespace FOG\Plugins\Helloworld;
+namespace FOG\Plugins\HelloWorld\Items;
```

Two things to watch while you do it:

- **The segment may need recasing.** It used to be `ucfirst()` of the directory
  name, so `ldap/` was `FOG\Plugins\Ldap`. Any casing is allowed now as long
  as lowercasing it returns the directory name, and the bundled plugins took
  the chance to become `FOG\Plugins\LDAP`, `FOG\Plugins\OIDC`,
  `FOG\Plugins\WOLBroadcast`. Recasing is optional; if you do it, do it in
  every file at once.
- **Any FQCN you spell out in your own code moves too** — a `use` import of one
  of your own classes, a class name in a string, an `is_subclass_of()`. Core
  resolves short names for you (§7a), but a literal you wrote is a literal.

Use `git mv` so the history follows the file. If you carried a `class_alias()`
to make discovery find a namespaced class, delete it; it has nothing left to
do.

---

## 12. Reference plugins

- **`helloworld`** — this guide's minimal, complete CRUD example.
- **`subnetgroup`** — a clean real CRUD plugin (model→class relationship,
  Export/Import, `schema()`).
- **`persistentgroups`** — a plugin that is nothing but a `schema()` closure
  step (it installs a MySQL trigger). No page, no model, no hooks: proof that
  none of those are mandatory.
- **`ldap`** — authentication/integration plugin (custom hooks beyond CRUD).
- **`oidc`** — the reference for §7c. A plugin that adds a *route* rather than
  a resource, contributes a login button, and turns a proven identity into a
  session. Also a six‑table plugin: provider, identity links, groups, two
  association tables and the grant record.

When in doubt, copy the closest existing plugin and adapt it — the conventions
above are followed consistently across all of them.
