# Storage Node Selection Hooks

`StorageGroup` decides which storage node a host talks to for a given
operation. Two methods do the picking:

- **`getOptimalStorageNode()`** — chooses the best *deploy* node in the group
  (the online, enabled node with the lowest client load).
- **`getMasterStorageNode()`** — chooses the group's *master* node (used for
  captures, multicast, and replication sources).

Both fire a `HookManager` event just before they return, so a plugin or hook
can **inspect or override the chosen node** — including supplying a node when
FOG's own logic found none.

| Method | Event name |
|--------|------------|
| `getOptimalStorageNode()` | `OPTIMAL_STORAGE_NODE` |
| `getMasterStorageNode()` | `MASTER_STORAGE_NODE` |

---

## What the event passes

Each event receives the same three arguments, all **by reference**:

| Key | Type | Meaning |
|-----|------|---------|
| `StorageGroup` | `StorageGroup` | The group the selection is being made on (`$this`). |
| `StorageNodes` | `stdClass` | The decoded candidate node list FOG considered. |
| `StorageNode` | `StorageNode` \| `null` | FOG's chosen node, or `null` if none qualified. |

A hook overrides the result by **reassigning `$arguments['StorageNode']`**.
Because FOG's `processEvent` passes the argument array with reference elements,
that reassignment propagates back into the method. This is the same mechanism
the bundled **Location** plugin already uses to steer node selection from the
`HOST_NEW_SETTINGS` / `BOOT_TASK_NEW_SETTINGS` events.

After the event, the method validates the (possibly replaced) node:

```php
if (empty($StorageNode) || !$StorageNode->isValid()) {
    throw new Exception(_('No nodes available'));        // optimal
    // or _('No master nodes available') for the master node
}
return $StorageNode;
```

So a hook may also **rescue** a failed selection by setting a valid
`StorageNode` where FOG would otherwise have thrown.

---

## Behavior when no hook is registered

Unchanged. With no listener, the chosen node passes straight through and the
methods behave exactly as before — the same node is returned, and the same
exception is thrown when nothing qualifies. (`isValid()` on the chosen node is
equivalent to the old `empty($winner)` / `empty($masternode)` check.)

Simply referencing either event name also auto-registers it under
**FOG Configuration → Hook & Event listing**, like every other FOG event.

---

## Example: override the optimal node from a plugin

```php
class MyStorageHook extends Hook
{
    public $name = 'MyStorageHook';
    public $description = 'Pick a storage node my own way';
    public $active = true;
    public $node = 'mystorageplugin';

    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'OPTIMAL_STORAGE_NODE',
            [$this, 'pickNode']
        );
    }

    public function pickNode($arguments)
    {
        /** @var StorageGroup $StorageGroup */
        $StorageGroup = $arguments['StorageGroup'];

        // ...your selection logic, e.g. by subnet, weighting, etc...
        $chosen = new \FOG\Items\StorageNode($someNodeId);

        if ($chosen->isValid()) {
            $arguments['StorageNode'] = $chosen;   // override FOG's choice
        }
    }
}
```

Register the hook the usual way (a `*.hook.php` file whose class name matches
the filename, in `lib/hooks/` or inside a plugin's `hooks/` directory).

The `MASTER_STORAGE_NODE` event works identically — just register on that name
instead.

---

## Call sites affected

Because the hooks live inside `StorageGroup` itself, *every* caller of these
two methods is covered, including paths that don't fire the surrounding
`*_NEW_SETTINGS` events — e.g. host tasking, the boot menu, the task queue, and
the Capone/Location plugins.

See [`packages/web/lib/fog/storagegroup.class.php`](../packages/web/lib/fog/storagegroup.class.php).
