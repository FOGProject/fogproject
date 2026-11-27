# A hostless task is authorized by a boot token

## Status

accepted

## Context

Forum 18086 reported that a deploy to an unregistered host leaves no useful
record. The host picks "Deploy Image" from the iPXE menu. The imaging works.
The imaging log shows nothing for the run.

The cause is in `IpxeBootMenu::falseTasking()`. It sends the host to FOS with
`capone=1`, but it creates no Task. FOS has nothing to report against. Every FOS
endpoint finds its Task through the host, and an unregistered host has no row in
`hosts`. The interim fix wrote one "Started" row into `taskLog` and nothing
after it. Progress, completion, and errors were still lost.

A pending host has a related bug. `setTasking()` gives a pending host a normal
task. That task can never check in, because FOS runs with `capone=1` and
sends no host identity the task can use.

The imaging log needs a real Task for the run. A Task today requires a host.
The decision below removes that requirement for one narrow case. It also
needs a way for FOS to prove that it belongs to the Task. An unregistered
host has no key and no token of its own.

## Decision

**An unregistered or pending host that picks Deploy or Join gets a real Task
with no host.** The Task has these properties:

- `taskHostID` is `NULL`, not `0`. Foreign key `fk_tasks_taskHostID` (schema
  step 390) refuses `0`, because no host has that ID. `FOGController::save()`
  also refuses a required field that is `0`.
- `taskMAC` holds the MAC address that the iPXE menu saw.
- `taskTokenHash` holds `hash('sha256', $raw)`. The column is `char(64)`,
  indexed (not unique), and its default is the empty string.
- The Task has the image, the storage node, the storage group, and the menu
  user in `createdBy`. `falseTasking()` picks the node. The name is
  `Unregistered - <MAC>`.
- The Task starts in the Queued state. The check-in moves it to In Progress as
  for any Task. The slot count in `StorageNode.php` counts In Progress Tasks, so
  a Task that starts In Progress would deadlock the slot count. A Queued
  start also means `maxClients` now applies to unregistered deploys. That is a
  visible change.
- A pending host is treated as an unregistered host. It gets the same hostless
  Task and `capone=1`.

**The raw token is `bin2hex(random_bytes(32))`: 64 hex characters.** The
server stores only the hash. It puts the raw token on the kernel line as
`fogtoken=<raw>`. FOS posts it back as the `fogtoken` field. The server finds
the Task by indexed equality on `sha256(raw)`.

**The token is tried before the host on every FOS endpoint.** The endpoints are
check-in, progress, completion, error, and blame. When a request carries a
`fogtoken`, the server does this:

- It looks for a Task with that hash, a `NULL` host, an active state (Queued,
  Checked In, In Progress), and a `taskMAC` that is one of the MACs in the
  request.
- It uses that Task if it finds one. The host in the request is a new, invalid
  `Host`.
- It runs the normal host flow, unchanged, if it finds none.

All three conditions must hold. A correct token with a wrong MAC fails. A
correct MAC with no token takes the host path.

**The token dies when the Task leaves the active states.** There is no other
expiry and no revoke call. A Complete, Canceled, or Failed Task no longer
matches the lookup. The one exception is the duplicate-completion check
(`TaskQueue::ackIfAlreadyComplete`). It acknowledges a repeat completion
for the newest hostless Complete Task with that hash and MAC within 86400
seconds. That keeps a FOS retry from reading as an error.

**The REST API can neither set nor read `taskTokenHash`.** It is in
`Route::sensitiveAlwaysFields` and in the server-owned fields. It is not in the
grid JSON or in the OpenAPI document. `Task::isValid()` and `save()` accept a
Task that has no host only when `taskTokenHash` is not empty. So the API cannot
create a hostless Task.

**The 24 hour reaper ends a silent hostless Task in a new state, No Report.**
The reaper moves a hostless token Task that is still Queued after 24 hours to
No Report. It counts from the create time. A Task that left Queued has
reported, so the reaper leaves it alone. No Report is not Failed. A Failed Task says
imaging failed. This Task says only that FOS never reported. The imaging may
have worked. The reaper writes the reason "FOS sent no report within 24 hours;
an init older than this server does not report for an unregistered host". The
existing reaper for Tasks with no host skips hostless token Tasks, so the two
rules do not collide.

**The server refuses Deploy and Join for an unregistered host when the menu
hides that item from it.** The `qihost=1` and `sessionJoin` handlers use the
same visibility rule as the menu builder (`regMenu` 0, 1, 2, 6, and the
`FOG_REGISTRATION_ENABLED` setting). One method holds the rule, and both
callers use it. Without this, a host could type the menu URL and get a Task
that the administrator chose not to offer. The refusal text is "This host must
be registered before it can be imaged." The server issues no Task and no
token. A registered host is not affected.

**A failure to issue the token does not block imaging.** The server logs a
fault. The host boots as before, with no token.

## Consequences

**FOS must ship before the server.** The installer pulls the latest FOS
release. A server that issues tokens needs an init that sends them.

**An older init yields No Report.** The older init ignores `fogtoken`. The
imaging runs. The Task stays Queued, and the reaper moves it to No Report after
24 hours. The log shows the run, with no progress. That is better than the
interim fix. It is not the full record.

**A token is a bearer credential for one run on the LAN.** FOS to server
traffic can use plain HTTP. Anyone who reads the kernel line or the traffic
can use the token. The exposure is smaller than the exposure the system
already accepts for a registered host:

- A registered host is identified by its MAC alone on most endpoints. A MAC is
  public on the wire and trivial to forge.
- A token is scoped to one active hostless Task, tied to one MAC, and dead when
  that Task ends.
- A token can report on that one Task. It cannot reach a host record, a host
  key, or any other Task.

`handleError` in FOS masks `fogtoken=` when it prints `/proc/cmdline`, so the
token stays out of the error screen and the logs.

**The schema grows by two columns and one index** (`taskMAC`, `taskTokenHash`,
`idx_taskTokenHash`). `taskHostID` becomes nullable. A new row in `taskStates`
adds No Report. The code that lists terminal states must include it. That
includes the imaging report, the active-task counts, and the UI state colors.

**Code that reads `taskHostID` must expect `NULL`.** The check-in, check-out,
and error paths guard on a valid host. The host-only steps are skipped for a
hostless Task: the key and token reset, the host deployed stamp, the directory
join, the email, and the host update. The Host column in the UI shows
`<MAC> (unregistered)`. `Blame` skips the node failure count, because a
failure on an unregistered host does not prove the node is at fault.

**Unregistered deploys now respect `maxClients`.** A node at its limit queues
the deploy as it queues a registered one.

## Alternatives rejected

- **Write one log row at the start and no Task.** This was the interim fix.
  The log shows that a run started and never shows how it ended. It gives
  no progress, no completion, no error, and no cancel. It also adds a special
  case for the Task ID `0` to the report. The run needs a real Task so that
  every FOS endpoint can report against it.
- **A separate log-token endpoint.** A new endpoint would let FOS post log
  rows with a token. It would copy the check-in, progress, completion, and
  error logic into a second path. The two paths would drift apart. A Task
  with a token reuses the one path that registered hosts use.
- **Use `taskHostID` `0` for "no host".** It needs no schema change to the
  column. The foreign key `fk_tasks_taskHostID` refuses it, and the required
  field rule in `FOGController::save()` refuses it. Removing the key to
  allow `0` would remove the check for every real Task. `NULL` is the value
  that the foreign key already allows.
