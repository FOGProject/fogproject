# Building a FOG Plugin — Start to Finish

This guide walks you from an empty directory to a working, installable FOG
plugin on the **working‑1.6** framework. It uses a complete, runnable example
plugin — **`helloworld`** — that ships alongside this document at
[`packages/web/lib/plugins/helloworld/`](../packages/web/lib/plugins/helloworld/).
Copy that directory, rename it, and you have a head start.

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
| `packages/web/lib/plugins/<name>/` | plugins bundled with FOG itself | No — the tarball re‑lays this tree |
| `/opt/fog/plugins/<name>/` (`FOG_PLUGIN_DIR`) | **everything third‑party** | Yes |

The installer's `configureHttpd()` does `rm -rf` on the web root before laying
the new one down, so a plugin installed into `lib/plugins/` is deleted by the
next `installfog.sh` run without warning. `FOG_PLUGIN_DIR` sits outside the web
root precisely so that cannot happen. See
[ADR 0009](adr/0009-plugins-become-installable-artifacts.md).

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
- **Autoloader.** `Initiator` scans `BASEPATH` recursively, adds every
  directory containing a `*.{class,page,hook,event,report}.php` file to the PHP
  `include_path`, then registers PHP's **default** `spl_autoload`. That default
  autoloader **lowercases the class name** to find the file. So:

  > **The filename must be `strtolower(ClassName)` + the suffix.**
  > `class HelloWorldManagement` ⇒ `helloworldmanagement.page.php`.
  > `class AddHelloWorldJS` ⇒ `addhelloworldjs.hook.php`.

  (Class names in code are PascalCase; the files on disk are all‑lowercase.)
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
│   └── plugin.config.php          # the manifest ($fog_plugin[...])
├── class/
│   ├── helloworld.class.php        # HelloWorld         (model, FOGController)
│   └── helloworldmanager.class.php # HelloWorldManager  (manager + schema())
├── pages/
│   └── helloworldmanagement.page.php  # HelloWorldManagement (FOGPage)
├── hooks/
│   ├── addhelloworldmenuitem.hook.php # menu entry + search/objects
│   ├── addhelloworldjs.hook.php       # JS injection
│   └── addhelloworldapi.hook.php      # REST API exposure
└── js/
    ├── fog.helloworld.list.js
    ├── fog.helloworld.add.js
    └── fog.helloworld.edit.js
```

The directory name **is** the plugin's machine name and routing `node`. Keep it
lowercase and use it consistently (`$fog_plugin['name']`, each hook's
`public $node`, the page's `public $node`).

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

### 4.2 Model — `class/helloworld.class.php`

```php
class HelloWorld extends FOGController
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

### 4.3 Manager + migrations — `class/helloworldmanager.class.php`

The manager owns table creation and **schema evolution**. This is the most
important part to get right, so it gets its own section (§5). The shape:

```php
class HelloWorldManager extends FOGManagerController
{
    public $tablename = 'helloWorld';

    public function createSql() { return Schema::createTable(/* … */); }

    public function schema()
    {
        return [
            $this->createSql(),     // step 0 — create the table
            // append future steps here, never reorder/remove
        ];
    }

    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
}
```

### 4.4 Page — `pages/helloworldmanagement.page.php`

The page extends `FOGPage`, declares `public $node = 'helloworld'`, and sets the
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
        $code = HTTPResponseCodes::HTTP_CREATED;
        $msg  = json_encode(['msg' => _('…'), 'title' => _('…')]);
    } catch (Exception $e) {
        $code = $serverFault
            ? HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR   // 500 = our fault
            : HTTPResponseCodes::HTTP_BAD_REQUEST;            // 400 = bad input
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

### 4.5 Hooks — `hooks/*.hook.php`

Each hook is a small class extending `Hook`, with `public $node`, that registers
callbacks **in its constructor, guarded by the install check**:

```php
public function __construct()
{
    parent::__construct();
    if (!in_array($this->node, (array)self::$pluginsinstalled)) {
        return;                       // do nothing unless this plugin is installed
    }
    self::$HookManager->register('MAIN_MENU_DATA', [$this, 'menuData']);
}
```

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
`accesscontrolmanager`'s `schema()` for the closure pattern).

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

---

## 7. Settings

Global configuration lives in the `globalSettings` table.

- Read: `FOGBase::getSetting('FOG_PLUGIN_HELLOWORLD_FOO')`
- Write: `FOGBase::setSetting('FOG_PLUGIN_HELLOWORLD_FOO', $value)`
- Naming: `ALL_CAPS_WITH_UNDERSCORES`, prefixed `FOG_PLUGIN_<NAME>_…`.
- Create defaults as a `schema()` seed step (an `INSERT` into `globalSettings`).

---

## 8. Security & output conventions

- **Output:** wrap every user‑controlled value with `Initiator::e($value)` when
  echoing into HTML. All output also passes through the global
  `sanitizeOutput` buffer.
- **Input:** use `filter_input(INPUT_POST, 'key')` (or the already‑sanitized
  superglobals) — never raw `$_POST`/`$_GET`.
- **CSRF/auth:** call `self::checkAuthAndCSRF()` at the top of every state‑
  changing POST handler.
- **Instantiation:** prefer `self::getClass('HelloWorld')` /
  `self::getClass('HelloWorldManager')` over `new`.
- **Translation:** wrap UI strings in `_('…')`.

---

## 9. Common hook events

| Event | Purpose |
|---|---|
| `MAIN_MENU_DATA` | add the top‑level sidebar entry (`hook_main[node] = [label, icon]`) |
| `SUB_MENULINK_DATA` | add sub‑links (Export/Import/…) under the node |
| `SEARCH_PAGES` | make the node searchable |
| `PAGES_WITH_OBJECTS` | enable the object (edit/delete) flow for the node |
| `PAGE_JS_FILES` | inject JS files for the current page |
| `API_VALID_CLASSES` | expose the node over the REST API (name classes after your permission node — see §4.5) |
| `<NODE>_ADD_FIELDS` / `_GENERAL_FIELDS` | let others extend your forms |
| `<NODE>_ADD_POST` / `_EDIT_POST` / `_ADD_SUCCESS` / `_ADD_FAIL` | extension points around your saves |

Fire your own events with `&`‑by‑reference args so listeners can mutate them
(see the example's `HELLOWORLD_*` events).

---

## 10. Gotchas (learned the hard way)

- **`CREATE TABLE IF NOT EXISTS` never alters a live table.** Add columns via a
  new `schema()` step, not by editing `createSql()`.
- **Filename = `strtolower(ClassName)` + suffix.** A mismatch means the class
  silently won't autoload.
- **`menuicon`** beginning with `fa` is rendered as a font‑awesome icon;
  anything else is treated as an `<img>` `src`.
- **`$serverFault`** must be `true` only for server‑side failures, so HTTP
  status codes are honest (`500` vs `400`).
- **Hook constructors must early‑return** when the node isn't in
  `$pluginsinstalled`, or your hooks run for a plugin that isn't enabled.
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
   rename the directory, the classes, the files (lowercased), every `$node`, and
   the `$fog_plugin['name']`.
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

## 12. Reference plugins

- **`helloworld`** — this guide's minimal, complete CRUD example.
- **`subnetgroup`** — a clean real CRUD plugin (model→class relationship,
  Export/Import, `schema()`).
- **`site`** — a five‑table plugin, and the reference for object scoping via
  `OBJECT_SCOPE_CHECK`. Its `schema()` shows how to retire a table you shipped
  (steps are immutable: step 3 creates it, step 4 drops it).
- **`persistentgroups`** — a plugin that is nothing but a `schema()` closure
  step (it installs a MySQL trigger). No page, no model, no hooks: proof that
  none of those are mandatory.
- **`ldap`** — authentication/integration plugin (custom hooks beyond CRUD).

When in doubt, copy the closest existing plugin and adapt it — the conventions
above are followed consistently across all of them.
