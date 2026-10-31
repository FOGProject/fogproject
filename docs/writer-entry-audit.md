# U2-06 writer-entry audit

## Lead reconciliation of the current candidate

The finding below describes the sealed U2-05 baseline and was reproduced by
`u206-repro-05` on PHP 7.4/8.3. It is corrected in this U2-06 candidate:
`Host::destroy(non-id)` now acquires the existing fence before resolving the
original predicate, preflights the complete parent set, and retains that fixed
set through the original cascade and parent DELETE. The default ID path and
non-Host paths are unchanged. `u206-fixed-06` passes all 3 checks on both PHP
versions, including the all-inactive positive. Independent source review found
no new unchecked predicate-arrival deletion window. The final full-run outcome
and source hashes are recorded outside this source tree in the U2-06 report.

The lead additionally reproduced a stale-model empty-target variant in
`u206-stale-repro-10` on both PHP versions: the model's former image predicate
matched no current Host, leaving an empty decommission-owner set before the
ID cascade. The final candidate explicitly requires the model ID to belong to
the current fixed predicate set, otherwise it refuses before hooks or SQL.
The regression now has 4 checks. `u206-final-09`'s 67-program PASS predates this
additional variant and is retained as intermediate, not final-candidate proof.

W10/W12's baseline defect is therefore resolved, not a remaining regular-writer
bypass. W13's single edit/create routes were independently traced by the lead:
`preflightModels` precedes association side effects and model save, under the
route scope; the same helper and lower SQL guard have deterministic real-PDO
joining/upsert tests. Unexecuted endpoint/authentication Cartesian combinations
remain a bounded coverage limitation, not an unguarded alternate SQL path.
W08's unbounded selector permutations likewise share the guarded SQL selection
and original-WHERE/fixed-PK intersection; unsupported shapes fail closed.
Arbitrary plugin raw PDO/view/procedure writers and external AD administrators
are outside this guard contract. Hook external side effects are not rolled back.

The following original audit text and base line references are retained to
identify the reproduced defect and avoid confusing static review with runtime.

Scope: read-only tracing of regular product writer entry points in the sealed U2-05 base, with the authorized regression source added at `tests/u206-host-destroy-key.test.php`. W definitions follow `D:/Work/fog/pr-prep/20261031-u2-04-p2-approval/cumulative-scope.md` and `D:/Work/fog/pr-prep/20261031-u2-03-p2-rereview/approval-package.md`; detailed original writer rows are in `D:/Work/fog/pr-prep/20261031-u2-02a-fence-design/writer-matrix.md`.

This is source and test-inventory evidence, not a final runtime report. No tests were run for this audit. `writer-wiring-receipt.json` is intermediate and was not used as proof. Final lock/permit semantics remain with the lead review.

## Finding

One regular product entry violates the approved full-target preflight ordering: `FOG\Items\Host::destroy($key)` first cascades the loaded Host by ID, then calls the generic parent destroy using `$key` (`packages/web/src/Items/Host.php:295–309`). For a non-unique key such as `imageID` (`hostImage`, `Host.php:54–58`), an inactive Host A and protected Host B can share the value. The first `Route::deletemass('host', ['id' => A])` may delete A and its dependencies; only afterward does the parent DELETE enumerate the remaining matching rows and refuse on B through `FOGController::destroy` / `DirectoryNameFence::beforeSQL` (`packages/web/src/Base/FOGController.php:1336–1418`; `packages/web/src/Agent/DirectoryNameFence.php:605–633`). B remains protected, but the request has already partially decommissioned A.

The new reproducer checks byte-for-byte row snapshots for both Hosts, HostDirectory, Tasks, placement intents, the mapped and installed-FK dependencies, plus the shared image. It also covers an all-inactive non-ID positive and a mixed-target `Route::deletemass` control. The current product source is expected to fail the first assertion before a product fix; this audit did not execute it.

## W01–W15 entry map

| W | Current regular entry and guard | Isolated runtime-test source present | Remaining risk / coverage boundary |
|---|---|---|---|
| W01 | `DirectoryOperation::start/claim` (`packages/web/src/Agent/DirectoryOperation.php:406–477`). Claim enters `DirectoryRejoin::change`, then `DirectoryNameFence::assertClaim` before task consumption and `started` state. | `tests/u2-p2-claim-race.test.php`, `u2-p2-claim-barriers.test.php`, `u2-p2-placement-claim-barriers.test.php` | DB-only concurrent fixtures; no real agent/native operation. Final fence semantics are lead-owned. |
| W02 | `DirectoryRejoin::change` (`DirectoryRejoin.php:96+`) takes `authorityScope` and performs its host/HostDirectory authority transaction there. | `tests/u2-p2-report-barrier.test.php`, `u2-p2-transaction-crash.test.php`, `u2-p2-intent-lifecycle.test.php` | Tests are not evidence until final runner results; lead owns transaction/lock review. |
| W03 | `DirectoryOperation::observe/plan` (`DirectoryOperation.php:102`, `:273`) update operation observations and ready plans through the dedicated state writer. | `tests/agent-directory-operation-db.test.php`, `tests/u2-p2-claim-barriers.test.php` | Source path traced; no additional regular SQL writer found outside the dedicated authority path. |
| W04 | `DirectoryOperation::report` (`DirectoryOperation.php:496+`) and legacy `DirectoryRejoin::report` (`DirectoryRejoin.php:271+`) use the dedicated state transition path. | `tests/u2-p2-report-barrier.test.php`; `tests/agent-directory-operation-db.test.php` | No live Agent/native result; final transition acceptance remains with lead. |
| W05 | `DirectoryRejoin::configureTarget` (`DirectoryRejoin.php:335+`) writes through `change`; UI POST is `Pages/HostManagement.php::hostADPost`. | `tests/agent-directory-retry-protection-db.test.php`, `tests/host-ad-ui-save.test.php`, `tests/u2-p2-claim-barriers.test.php` | Test source covers protected Retry preservation; runtime status is not asserted here. |
| W06 | `FOGController::save` (`packages/web/src/Base/FOGController.php:649+`) is the common upsert; `Host::save` delegates to it (`Items/Host.php:316+`). `PDODB::query` calls `beforeSQL`, which resolves Host PK/name unique candidates and guards the rows before SQL. | `tests/u2-p2-writers.test.php`, `tests/u2-p2-upsert-where.test.php` | These cover stale models and PK/name collision cases. Ordinary Host facts updates are deliberately outside the sensitive lock when only non-authority columns change. |
| W07 | `HostDirectory::save` removes generic config/state fields then delegates to the same base save (`Items/HostDirectory.php:86–95`). The SQL guard checks old/new owner and PK, blocks authority history replacement, and requires the dedicated authority writer for generic state/config changes (`DirectoryNameFence.php:537–558`). | `tests/u2-p2-writers.test.php`, `tests/u2-p2-upsert-where.test.php`, `tests/u2-p2-cascade.test.php` | Same-owner facts and protected owner/reparent cases have source assertions. No other regular model save route bypass was found. |
| W08 | `FOGManagerController::update/perform_update` (`Base/FOGManagerController.php:2186–2365`) builds the original WHERE; `beforeSQL` resolves candidates, preflights payloads, and binds the actual UPDATE to original predicate AND fixed PKs (`DirectoryNameFence.php:605–665`). | `tests/u2-p2-upsert-where.test.php`, `tests/u2-p2-like-compat.test.php`, `tests/u2-p2-barriers.test.php` | `upsert-where` explicitly leaves unbounded empty-WHERE and broad non-Host override cases uncovered (`:261`). Add a sensitive empty-WHERE case if that legal Host/HD contract remains in scope. |
| W09 | `FOGManagerController::insertBatch` (`Base/FOGManagerController.php:2030–2175`) captures physical-table override; first sensitive SQL preflights all tuples/chunks before insert (`DirectoryNameFence.php:700–758`). | `tests/u2-p2-batch.test.php`, `tests/u2-p2-batch-overrides.test.php` | Includes protected tuple after the 500-row boundary and supported benign override; normal multi-chunk SQL failure remains non-atomic by design. |
| W10 | Generic `FOGController::destroy` (`Base/FOGController.php:1336–1418`) has a row-selection/fixed-PK SQL guard (`DirectoryNameFence.php:605–633`). `Route::delete` delegates to `deletemass` (`Router/Route.php:7044–7060`). | `tests/u2-p2-writers.test.php` exercises ID deletion; new `tests/u206-host-destroy-key.test.php` exercises non-ID `Host::destroy` and Route’s non-unique bulk predicate. | **Concrete defect above:** Host override performs ID cascade before generic non-ID target resolution. Existing generic SQL guard is too late to prevent that earlier side effect. |
| W11 | `Route::deletemass` (`Router/Route.php:9922–10025`) resolves IDs and calls `preflightDecommission` before destroy hooks; after mutable `DELETEMASS_API`, it preflights dependency selections, then recursive deletes and `_deleteRows` run under route scope. Final dependency DELETEs are bound to owner-checked PK sets (`DirectoryNameFence.php:784–804`, `:869–974`). | `tests/u2-p2-hook-owner.test.php`, `tests/u2-p2-cascade.test.php`, new `tests/u206-host-destroy-key.test.php` control | Hook tests cover added outside-owner/ownerless dependencies before dependency preflight. The new Host override sequence bypasses that whole-predicate ordering for non-ID destroy. |
| W12 | `Host::destroy` delegates to Route (`Items/Host.php:295–310`); incoming actual FK CASCADE closure is checked before hooks and again at parent SQL (`DirectoryNameFence.php:507–555`, `:784–867`). HostDirectory standalone deletion is independently guarded. | `tests/u2-p2-cascade.test.php`, `tests/u2-p2-hook-owner.test.php`, `tests/u2-p2-writers.test.php` | Tested actual FK closure includes HostDirectory and both AgentWake endpoints, shared protected owner, ownerless hook dependency, and inactive deletion. Does not enumerate every installed schema/plugin dependency in runtime tests. |
| W13 | JSON `Route::edit/create/_joiningUpdate` each opens a route scope and invokes model preflight before association/save writes (`Router/Route.php:6170–6204`, `:6489–6527`, `:10566–10607`). Single saves retain the lower-level guard. `Route::delete` uses W11. | `tests/u2-p2-joining-preflight.test.php`; generic route permission tests remain separate. | No dedicated real-PDO Host `Route::edit` and `Route::create` target-collision test was found. Add those if acceptance requires endpoint-level evidence beyond the model/upsert tests. |
| W14 | `PDODB::query` opens SQL scope and calls `DirectoryNameFence::beforeSQL` on the captured link (`packages/web/src/Db/PDODB.php:422–465`); intent/authority paths use dedicated fence entry points. | `tests/u2-p2-fence-session.test.php`, `tests/u2-p2-barriers.test.php`, `tests/u2-p2-transaction-crash.test.php` | Entry wiring traced only. Lock ownership, poisoning, release, and same-PDO conclusions are reserved to the lead’s independent review. |
| W15 | Product Move path is `DirectoryPlacement::ensure` → `DirectoryPlacementIntent::ensure` → `DirectoryPlacementIntent::dispatch` → the sole `FOGLdap::moveTo` call (`DirectoryPlacementIntent.php:550–620`). `FOGLdap::moveTo` consumes the dispatch permit before LDAP rename (`Net/FOGLdap.php:695–699`). | `tests/u2-p2-placement-flow.test.php`, `tests/u2-p2-native-target-placement.test.php`, `tests/u2-p2-legacy-placement-claim-order.test.php`, `tests/u2-p2-intent-faults.test.php`, `tests/u2-p2-move-aba-seam.test.php` | Source call-site search found one core `moveTo` caller; tests use fake LDAP/isolated DB, not real AD. Permit semantics are lead-owned. |

## Focused A follow-up

1. Run `tests/u206-host-destroy-key.test.php` on both supported PHP runtimes and capture the first mixed-target test as a pre-fix failure. Do not interpret the later positive/control assertions as evidence if the runner aborts at the first assertion.
2. Resolve the `Host::destroy(non-id)` ordering so the complete predicate target set is guarded before the first per-Host cascade. Preserve inactive all-match behavior and Route mass behavior.
3. Re-run the new file after the product decision, then retain the W10/W12 regression alongside the existing ID and shared-owner cascade tests.
4. Before final W13 acceptance, decide whether endpoint-level Host edit/create tests are required; no regular product call bypass was found in those wired paths, but their P2 real-PDO endpoint coverage is absent.
