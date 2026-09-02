# 39. A booting machine is identified by its MAC first and its firmware second, and the firmware ships in log mode

Date: 2026-09-02
Status: Accepted
Issue: #198

## Context

FOG keys a host on its MAC address. That fails wherever the MAC is not the
machine's own: a USB NIC shared around a bench during imaging, a docking
station, a laptop line with no onboard Ethernet at all. Several physical
machines then present one MAC and the database can only see one host.

Issue #198 has been open since 2018. One attempt shipped: `getHostItem()`
resolved the SMBIOS UUID before the MAC and took the first hit. MSI boards
that all report `FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF` re-identified every
one of them as the same host, and the code was commented out wholesale,
where it stayed for seven years. Everything proposed since (a hash of
several fields, the disk serial through a custom iPXE, an admin-chosen
identifier per site) was rejected on the thread for a stated reason and
none was built.

Three facts settled the shape of what is built now.

- iPXE exposes the UUID, system serial, board serial and chassis asset tag
  as named settings (`${uuid}`, `${serial}`, `${board-serial}`, `${asset}`)
  with no iPXE change. FOS already stores the same four from dmidecode.
- The deploy path hands FOS the host's **stored** primary MAC on the kernel
  line (`IpxeBootMenu`, the `mac=` argument), so resolving the host once at
  iPXE boot is enough. FOS and the FOG client need no change.
- Fleet data reported on the thread in June 2026: the board serial is unique
  on Asus, HP, Lenovo and Surface hardware and `none` on VMware and QEMU;
  placeholders seen in the wild include `000000000`, `To Be Set By OEM`,
  `Enter Serial`; one laptop line's UUIDs share a fixed prefix; a Dell
  detachable stores its system serial in the board-serial field wrapped in
  slashes. The vendors are not consistent and no lab can enumerate them.

## Decision

**The MAC stays the identity. Firmware can override it only when it is
sure, and only when told to.**

1. **The boot script sends all four fields** with every `boot.php` request,
   alongside the MAC list it always sent.
2. **`SmbiosIdentity::pick()` decides, and it never guesses.** Each field
   scores one point, compared per field, on canonicalized values. A value
   that is empty, a known firmware placeholder, or one character repeated
   scores nothing. The winner must hold the top score alone; a tie is "no
   opinion". The asset tag, the only field a person sets, breaks ties and
   cannot win by itself. The class is pure so the rules are testable with
   arrays (`tests/smbios-host-identity.test.php`) and so the boot-menu
   render harness, which stubs everything else, can load the real thing.
3. **`FOG_HOST_IDENTIFY_SMBIOS` is a three-way switch and ships as `log`.**
   `off` ignores the values. `log` lets the MAC decide and writes one line
   to the error log whenever the firmware would have chosen differently, or
   found a host where the MAC found none. `enforce` lets a unique firmware
   match win, and still logs it. A missing row is `off`.
4. **The decision runs on the iPXE boot path only** (`IpxeBootMenu`), not
   in `getHostItem()`, the chokepoint every caller shares. Only iPXE sends
   the values in the clear; FOS's inventory POST reuses the field names for
   base64 payloads, and its check-in sends a MAC that already came from the
   resolved host. Deciding once, where the values originate, is enough.
5. **Boot fills the inventory row.** A host created in the UI, by CSV or
   over the API has no inventory row until it has run a full FOS inventory,
   so nothing could find it by firmware. The boot path now creates the row
   with just the identity fields when it is missing, and fills empty fields
   on an existing one. It never overwrites a serial FOS stored; when the two
   disagree it logs the pair. The UUID keeps its existing rule: a
   well-formed UUID that differs replaces the stored one (motherboard swap),
   validated on every write (Aisle 019).
6. **The four columns get indexes** (schema step 413). The lookup runs on
   every boot request and `inventory` had none.

## Why log mode first

The 2018 attempt failed on hardware nobody had tested. The thread's own
survey, five to six thousand machines by one user, is still in progress.
There is no lab that enumerates what vendors write into SMBIOS; the fleet
is the lab. `log` mode turns every FOG 1.6 install into a reporter: the
error log names each machine where firmware and MAC disagree, and each
field where iPXE and dmidecode read different bytes. A site flips to
`enforce` when its own log says the firmware is trustworthy, and back to
`off` with one setting if it is not. That is the difference between a
toggle and a revert.

## Alternatives rejected

- **Enforce by default.** What was shipped and reverted before. The guard
  is far stronger now, but the vendor data is not in yet.
- **A hash of several fields.** Rejected on the thread in 2019: identical
  or blank fields across a batch hash to one value. Per-field scoring with
  placeholders dropped is the same idea without that failure.
- **Require two matching fields.** Would exclude every VM: VMware and QEMU
  report `none` for the board serial and often a placeholder system
  serial, leaving the UUID alone. A single usable field wins when it is
  the only candidate; the tie rule handles the rest.
- **Decide in `getHostItem()`.** The chokepoint is shared with FOS and the
  client, whose requests carry the same field names with different
  contents. It would run a wasted query per inventory POST that could not
  change the answer.
- **Overwrite serials at boot.** Would hide the iPXE-versus-dmidecode
  disagreements that log mode exists to surface, by ping-ponging the value
  between the two writers.

## Consequences

- A CSV-imported host becomes firmware-findable after its first PXE boot,
  not its first image.
- The error log gains two new line shapes, both prefixed `FOG host
  identity`. Documentation for the setting should point administrators at
  them.
- `HostManager::getHostByUuidAndSerial()` keeps its signature (gaining an
  optional asset tag) and now works. Its only historical caller was a
  comment.
- SMBIOS values from an unauthenticated `boot.php` POST are written to
  inventory, as the UUID already was. They are canonicalized and limited to
  printable ASCII within the column; the Inventory report escapes on
  output. A crafted POST can fill an empty serial on a host it knows the
  MAC of, which is the same exposure the UUID write has carried since it
  was validated, and cannot overwrite a value FOS stored.
