<?php
/**
 * Registering a host with 2+ MAC addresses must actually create the host
 * (GH-1725).
 *
 * FOG\Items\Host::addMAC() batch-inserts into `hostMAC` using the host's OWN
 * id, so it requires the host to already be saved --
 * FOGManagerController::insertBatch() runs every batch row through
 * _assertBatchForeignKeys(), which throws "Required database field is
 * empty: `hostMAC`.hostID" the moment a row carries no id at all.
 *
 * FOG\Client\RegisterClient::json() used to chain
 * ->addPriMAC($PriMAC)->addMAC($MACs) onto a brand-new, UNSAVED Host, so any
 * client reporting two or more MAC addresses hit that guard on its very
 * first check-in. The client protocol answers an uncaught error with an
 * empty 200 (FOGClient's constructor wraps the whole dispatch in a
 * try/throw-as-json), so this was invisible on the wire: a multi-NIC
 * machine's pending registration simply never appeared, silently, while a
 * single-NIC machine (one MAC, no batch insert, addPriMAC() alone) worked
 * every time. The fix reorders json() to save() the host FIRST -- so it has
 * a real id -- and only then calls addMAC() for the rest.
 *
 * WHAT THIS PINS:
 *
 *  1. Registering with three MACs returns ['complete' => true]. It must not
 *     throw, and must not fall into the ['error' => 'db'] branch save()
 *     failing would produce.
 *  2. The hostMAC insert this triggers actually carries a real, positive
 *     hostID -- not empty, not zero. This is the assertion that pins the
 *     bug directly: before the fix, _assertBatchForeignKeys() threw before
 *     any such INSERT was ever issued, so there was nothing to find here at
 *     all.
 *  3. The single-MAC case still works (addPriMAC() alone, no batch insert),
 *     which is the control proving the harness itself is sound and that
 *     this file is not just failing to reach the code under test.
 *
 * HOW THE REQUEST IS DRIVEN. FOGBase::getHostItem() and
 * RegisterClient::json() read the incoming mac/hostname with
 * filter_input(INPUT_GET/INPUT_POST, ...) -- which, under the CLI SAPI this
 * suite runs under, never sees $_GET/$_POST no matter what a test writes
 * into them (PHP only populates filter_input()'s buffer from a real
 * request; CLI has none). So this test overrides `filter_input()` in the
 * two namespaces that call it on this path (FOG\Base and FOG\Client) --
 * PHP's normal fallback-to-global-function rule for an unqualified call
 * means both namespaces see the override in preference to the built-in --
 * and backs it with the SAME $_GET-shaped array the real request would
 * carry, so the test is still "populate the request and drive the real
 * code", just with the one primitive that CLI cannot exercise stood in for.
 *
 * FOGClient's own CONSTRUCTOR is not driven at all --
 * newInstanceWithoutConstructor(), same as
 * tests/printer-grants-reach-the-client.test.php, and for the same reason:
 * it resolves a client SESSION (module gating, host-alive bookkeeping) that
 * is a different question from what json() does, and reaching it would
 * need a running module registry this fixture has no reason to fake.
 *
 * DB-free: FogFakeDb answers the two families of read this path issues --
 * Route::listem()'s raw-PDO path (HostManager::getHostByMacAddresses(),
 * parseMacList()'s existing/ignored-mac checks, the default-module lookup)
 * is told there is nothing out there by zeroing FogFakePdo's row/count
 * defaults, and FOGController::save() needs no responder at all: it takes
 * the new id from FogFakeDb::insertId() (a fixed 1) and sets it on the
 * object directly, never rereading the row.
 *
 * Usage: php tests/client-register-multi-mac.test.php
 * Exit status 0 = pass, 1 = fail.
 */

// Bracketed namespace blocks so the two overrides can live beside the global
// harness code in one file. Function existence is resolved at CALL time, not
// compile time, so declaring these before the harness is booted (which is
// what actually calls them) is all that is required.
namespace FOG\Base {

    /**
     * Stands in for the built-in filter_input() wherever FOG\Base calls it
     * unqualified -- which, per PHP's namespaced-function fallback rule, is
     * every call in this namespace once this function exists. See the
     * file-level docblock for why the built-in cannot be driven from a CLI
     * test at all.
     *
     * @param int    $type    INPUT_GET, INPUT_POST, ...
     * @param string $name    the field name
     * @param mixed  ...$rest ignored -- nothing on this path passes a filter
     *
     * @return string|null
     */
    function filter_input($type, $name, ...$rest)
    {
        return \FogTestRequestInput::read($type, $name);
    }
}

namespace FOG\Client {

    /**
     * Same stand-in, for RegisterClient::json()'s own hostname read.
     *
     * @param int    $type    INPUT_GET, INPUT_POST, ...
     * @param string $name    the field name
     * @param mixed  ...$rest ignored -- nothing on this path passes a filter
     *
     * @return string|null
     */
    function filter_input($type, $name, ...$rest)
    {
        return \FogTestRequestInput::read($type, $name);
    }
}

namespace {

    require_once __DIR__ . '/lib/fog-test-harness.php';

    // Fully qualified at each use rather than imported. The import is valid
    // PHP -- it sits in this same bracketed namespace block -- but
    // tests/no-bare-core-references.test.php only recognizes a top-level
    // `use`, so a bare short name here reads to that gate as the
    // class-not-found it is guarding against. FQCN is the dominant idiom in
    // this directory anyway (ADR 0013 section 2).

    /**
     * The fake request $_GET/$_POST that FOG\Base\filter_input() and
     * FOG\Client\filter_input() above read from, so a test still "sets
     * $_GET" in the ordinary sense -- it just goes through a lookup the
     * override consults instead of the real superglobal, which CLI never
     * lets filter_input() see.
     */
    class FogTestRequestInput
    {
        /** @var array */
        public static $get = [];

        /** @var array */
        public static $post = [];

        /**
         * @param int    $type INPUT_GET or INPUT_POST
         * @param string $name the field name
         *
         * @return string|null
         */
        public static function read($type, $name)
        {
            if (INPUT_POST === $type) {
                return self::$post[$name] ?? null;
            }
            if (INPUT_GET === $type) {
                return self::$get[$name] ?? null;
            }
            return null;
        }
    }

    FogTestHarness::boot('client-register-multi-mac');
    $db = FogTestHarness::fakeDb();
    // MACAddress::setMAC() logs a rejected mac through self::$FOGCore->debug();
    // a real request populates that from LoadGlobals, which this DB-free
    // bootstrap does not run. Same fix as uboot-tftp-sync.test.php.
    FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());

    // Route::listem() reaches the raw PDO handle directly (see the harness
    // docblock), not FogFakeDb::query(). Zeroing both defaults means every
    // lookup this path makes through it -- the by-mac host lookup, the
    // existing/ignored-mac filters inside parseMacList(), the default-module
    // lookup -- answers "nothing", which is exactly the state a brand-new
    // machine's MACs are in.
    $db->pdo->rowCount = 0;
    $db->pdo->countValue = 0;

    /**
     * Registers a host reporting the given MAC addresses and returns the
     * json() payload.
     *
     * @param FogFakeDb $db       the fake
     * @param string    $hostname the hostname the client reports
     * @param array     $macs     the MAC addresses, in order
     *
     * @return array
     */
    function registerHost($db, $hostname, array $macs)
    {
        FogTestRequestInput::$get = [
            'hostname' => $hostname,
            'mac' => implode('|', $macs),
            'newService' => '1',
            'json' => '1',
        ];
        FogTestRequestInput::$post = [];

        $db->log = [];
        $db->responder = function ($sql, $params = []) {
            // globalSettings: FOG_ENFORCE_HOST_CHANGES,
            // FOG_QUICKREG_MAX_PENDING_MACS and (from parseMacList())
            // FOG_QUICKREG_PENDING_MAC_FILTER. None of them need a real
            // value for this path -- answering nothing leaves getSetting()
            // returning null, which every read here treats as "off".
            if (false !== strpos($sql, '`globalSettings`')) {
                return [];
            }
            return null;
        };

        // Caught, rather than allowed to escape, purely so the failure
        // READS as a failure. The bug's symptom here is the batch guard
        // throwing out of json(), which as an uncaught fatal aborts the
        // whole file and reports no check at all -- the run still fails,
        // but CI shows a stack trace instead of which invariant broke.
        // Turning it into a payload lets the checks below name it.
        try {
            $payload = (new \ReflectionClass(\FOG\Client\RegisterClient::class))
                ->newInstanceWithoutConstructor()
                ->json();
        } catch (\Throwable $e) {
            $payload = ['error' => 'threw: ' . $e->getMessage()];
        }
        $db->responder = null;

        return $payload;
    }

    $t = new FogChecks();

    // ------------------------------------------------------------------
    // 1/2. Three MACs: must complete, and the hostMAC insert it triggers
    // must carry a real hostID.
    // ------------------------------------------------------------------
    $macs = ['00:11:22:33:44:01', '00:11:22:33:44:02', '00:11:22:33:44:03'];
    $payload = registerHost($db, 'multinictest', $macs);

    $t->check(
        'registering with three MACs completes',
        ['complete' => true] === $payload
    );

    // insertBatch()'s multi-row VALUES clause is what addMAC() -- and only
    // addMAC() -- produces; addPriMAC() saves a single row through
    // FOGController::save() instead, whose template has no comma-joined
    // tuple list. So the batch statement is picked out by shape (more than
    // one parenthesized VALUES tuple), not merely by naming the table, to
    // be sure this is the INSERT the bug actually broke.
    $hostMacBatchInsert = '';
    foreach ($db->log as $sql) {
        $sql = (string)$sql;
        if (false !== strpos($sql, '`hostMAC`')
            && 0 === stripos(ltrim($sql), 'INSERT')
            && false !== strpos($sql, '),(')
        ) {
            $hostMacBatchInsert = $sql;
        }
    }
    $t->check(
        // Before the fix this INSERT never ran at all --
        // _assertBatchForeignKeys() threw first, and the whole
        // registration died with it. Finding the statement is therefore
        // itself part of what this pins, not just its shape.
        'a batch INSERT against hostMAC was issued for the extra MACs',
        '' !== $hostMacBatchInsert
    );

    // The bound hostID has to come from the params, not the SQL text.
    // insertBatch() binds it under the caller's FRIENDLY field name plus a
    // per-row index (`:hostID_0`, `:hostID_1`, ...), not the database
    // column name -- see the comment above insertBatch()'s value-building
    // loop for why the two differ here.
    $capturedHostIds = [];
    $db->log = [];
    $db->responder = function ($sql, $params = []) use (&$capturedHostIds) {
        if (false !== strpos($sql, '`globalSettings`')) {
            return [];
        }
        if (false !== strpos($sql, '`hostMAC`')
            && 0 === stripos(ltrim($sql), 'INSERT')
            && false !== strpos($sql, '),(')
        ) {
            foreach ($params as $key => $val) {
                if (1 === preg_match('/^hostID_\d+$/', $key)) {
                    $capturedHostIds[] = $val;
                }
            }
        }
        return null;
    };
    // Same reason as the catch in registerHost(): with the defect present
    // this is where the batch guard throws, and letting it escape would
    // abort the file instead of failing the two checks below by name.
    try {
        (new \ReflectionClass(\FOG\Client\RegisterClient::class))
            ->newInstanceWithoutConstructor()
            ->json();
    } catch (\Throwable $e) {
        $capturedHostIds = [];
    }
    $db->responder = null;

    $t->check(
        'the batch insert bound one hostID per extra MAC',
        2 === count($capturedHostIds)
    );
    $t->check(
        'every hostMAC row in that batch carries a positive integer hostID',
        count($capturedHostIds) > 0 && count($capturedHostIds) === count(
            array_filter(
                $capturedHostIds,
                static function ($id) {
                    return false !== filter_var(
                        $id,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );
                }
            )
        )
    );

    // ------------------------------------------------------------------
    // 3. The control: a single MAC -- addPriMAC() alone, no batch insert at
    // all -- worked before the fix and must keep working after it.
    // ------------------------------------------------------------------
    $payload = registerHost($db, 'singlenictest', ['00:11:22:33:44:0a']);
    $t->check(
        'registering with a single MAC still completes',
        ['complete' => true] === $payload
    );

    $t->finish();
}
