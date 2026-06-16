# Group Shared State

A **group** owns no settings of its own — it is a lens over its **member hosts**.
The group edit page therefore *derives* what it shows from the union of its
members, so an admin can see what hosts already have in common before changing
anything.

> **Two kinds of shared state:**
> 1. **Associations** (snapins, printers, modules) — a host either *has* the
>    item or not, shown as a tri‑state checkbox: **All / Some / None** member
>    hosts have it.
> 2. **Configuration values** (Active Directory, auto‑logout, kernel/general
>    fields, default printer) — a host holds a *value*, shown as a muted
>    **`Hosts: …`** hint: the shared value when every member agrees, or
>    `(varies)` when they differ.
>
> Acting on a group still pushes to **all** member hosts — but config fields are
> **no‑clobber**: a blank field leaves each host's value alone, so you only
> change what you intend to.

---

## Table of contents

- [Associations (tri‑state)](#associations-tri-state)
  - [What "has it" means](#what-has-it-means)
  - [The badge and Has/Missing drill‑down](#the-badge-and-hasmissing-drill-down)
  - [Toggling](#toggling)
- [Configuration values (shared hints)](#configuration-values-shared-hints)
  - [The no‑clobber convention](#the-no-clobber-convention)
  - [Active Directory](#active-directory)
  - [Auto‑logout](#auto-logout)
  - [General fields](#general-fields)
  - [Default printer](#default-printer)
- [Out of scope](#out-of-scope)

---

## Associations (tri‑state)

The **Snapins**, **Printers**, and **Modules** tabs each render every item with a
coverage checkbox:

| State | Checkbox | Meaning |
|-------|----------|---------|
| **All** | checked | every member host has the item |
| **Some** | indeterminate | at least one, but not all, hosts have it |
| **None** | unchecked | no host has it |

A **`n / total`** badge sits next to each checkbox (e.g. `5 / 15`).

### What "has it" means

- **Snapins, printers** — a host "has it" when the association row exists. The
  printer **default** flag (`paIsDefault`) is ignored here; it's a separate
  shared *value* (see [Default printer](#default-printer)).
- **Modules** — a host counts only when the module is **enabled**
  (`moduleStatusByHost.msState = 1`). A disabled override pulls the item out of
  *All* into *Some*. This asymmetry is deliberate (see `docs/adr/0001`): showing
  a module as "All hosts have it" while some have it disabled would mislead.

### The badge and Has/Missing drill‑down

Clicking the badge expands an on‑demand row listing, for that one item:

- **Hosts with this (n)** — the member hosts that have it, and
- **Hosts without it (n)** — the member hosts that don't.

The *without* set is exactly what a push‑to‑all will change. The lists are
fetched per item, so large groups stay responsive.

### Toggling

Clicking the checkbox acts on **all** member hosts:

```
None  --click-->  All        (add to every host)
Some  --click-->  All        (add to the hosts that lack it; modules flip
                              a disabled override back to enabled)
All   --click-->  None       (remove from every host)
```

So an indeterminate item resolves to *All* first; the destructive
remove‑from‑all only happens on a second click through the checked state.

---

## Configuration values (shared hints)

Per‑host configuration fields show a muted hint beneath the control:

| Hint | Meaning |
|------|---------|
| `Hosts: bzImage (all)` | every member host holds that value |
| `Hosts: (varies)` | member hosts differ |
| `Hosts: (empty on all)` | none of the hosts have a value |

The hint is **information only** — it never prefills the input.

### The no‑clobber convention

Saving a group config tab pushes to all member hosts, but:

- **Blank field** → leave each host's value **unchanged** (no clobber).
- Literal **`NULL`** (case‑insensitive) → **clear** the field on every host.
- **Any other value** → push that value to every host.

This is what lets you, say, set one kernel argument across a group without
wiping every other per‑host field.

### Active Directory

- **Domain joining** is a tri‑state select: **No change** (leave each host's
  join state alone), **Enable on all**, or **Disable on all**.
- Domain / OU / username follow the no‑clobber convention above. The password's
  32‑asterisk placeholder means "unchanged".
- Selecting **Enable on all** populates the blank fields from the FOG AD
  defaults (same as the host page) — only when you choose it, never just from
  existing state.
- A **Current member‑host AD state** summary shows join/domain/OU/username
  uniformity above the form.

### Auto‑logout

Blank by default (the global minimum is shown only as a placeholder). A blank
save leaves each host's auto‑logout alone; a number pushes to all (under five
minutes disables it). The hint reads `Hosts: N min (all)`, `(varies)`, or
`(default on all)`.

### General fields

Kernel, kernel arguments, init, primary disk, BIOS/EFI exit, and product key
each carry a `Hosts: …` hint. The kernel/args/init/disk inputs prefill from the
**group's own template** (the group stores these); the hint reports the
*members'* state independently. Push still honors the no‑clobber convention.

### Default printer

The printer **Default** selector shows a `Hosts default: <printer> (all)`,
`(varies)`, or `(none on all)` hint. Setting a default is an explicit action
that only touches member hosts which have that printer associated.

---

## Out of scope

- **Force reboot** is a global setting (`FOG_TASK_FORCE_REBOOT`) and a per‑task
  option, not per‑host configuration, so it has no group shared‑state control.
