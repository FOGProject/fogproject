<?php
/**
 * The facts an agent reports about its own host stay inside their bounds.
 *
 * Facts ride the poll request (design 0006): hardware inventory into the
 * existing `inventory` row, the installed-program list into `hostSoftware`.
 * Both are written from a body a host controls, which is what these checks
 * are about. Four bounds, each one a way the feature could go wrong quietly
 * rather than loudly:
 *
 * - Inventory is a whitelist of properties, not a filter. The `inventory`
 *   table also carries primaryUser and the two asset tags, which are an
 *   admin's to set; passing a reported block into set() unchecked would let
 *   a host rewrite its own asset tag.
 * - Every whitelisted property has to be a real one. A typo would be
 *   silently ignored by FOGController::set() and the field would just never
 *   arrive -- a bug with no error anywhere.
 * - The program list is normalized before it reaches the unique index. A
 *   duplicate would abort the insert mid-chunk and roll back the poll.
 * - A gzip body decompresses under a cap. The expansion ratio is the
 *   caller's to choose, so the web server's upload limit is no limit on
 *   what decoding costs.
 *
 * DB-free: the harness's fake connection stands in, and only pure
 * normalization is exercised -- nothing here writes a row.
 *
 * Usage: php tests/agent-facts.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-facts');
FogTestHarness::fakeDb();

$t = new FogChecks();

/**
 * The property names a FOGController subclass actually maps to columns.
 *
 * @param string $class the fully qualified class name
 *
 * @return string[]
 */
function mappedFields($class)
{
    $prop = (new \ReflectionClass($class))->getProperty('databaseFields');
    $prop->setAccessible(true);

    return array_keys((array)$prop->getValue(new $class()));
}

// ---------------------------------------------------------------- inventory

$whitelist = \FOG\Agent\InventoryFacts::FIELDS;
$mapped = mappedFields(\FOG\Items\Inventory::class);

$t->check(
    'every whitelisted inventory property is a real column'
        . ': ' . implode(', ', array_diff($whitelist, $mapped)),
    [] === array_diff($whitelist, $mapped)
);

// The properties an admin owns. A host reporting its own hardware has no
// business setting who uses it or what asset tag it carries.
$adminOwned = ['id', 'hostID', 'primaryUser', 'other1', 'other2',
    'createdTime', 'deleteDate'];
$leaked = array_intersect($whitelist, $adminOwned);
$t->check(
    'no admin-owned inventory property is settable by an agent'
        . ([] === $leaked ? '' : ': ' . implode(', ', $leaked)),
    [] === $leaked
);

// The whitelist and the agent's own struct have to agree, or a field is
// gathered on the host and dropped on arrival. This is the list in
// docs/design/0006-inventory.md section 3.
$expected = ['sysman', 'sysproduct', 'sysversion', 'sysserial', 'sysuuid',
    'systype', 'biosvendor', 'biosversion', 'biosdate', 'mbman',
    'mbproductname', 'mbversion', 'mbserial', 'mbasset', 'cpuman',
    'cpuversion', 'cpucurrent', 'cpumax', 'mem', 'hdmodel', 'hdserial',
    'hdfirmware', 'caseman', 'casever', 'caseserial', 'caseasset',
    'gpuvendors', 'gpuproducts'];
sort($expected);
$got = $whitelist;
sort($got);
$t->check('the inventory whitelist is the documented field set', $expected === $got);

// ----------------------------------------------------------------- software

/**
 * Runs SoftwareFacts::_clean, which is private because nothing outside the
 * reconcile should call it, but is the whole of the input handling.
 *
 * @param array $list the reported programs
 *
 * @return array identity => normalized row
 */
function cleanList(array $list)
{
    return FogTestHarness::callStatic(
        \FOG\Agent\SoftwareFacts::class,
        '_clean',
        [$list]
    );
}

$cleaned = cleanList(
    [
        ['name' => 'bash', 'version' => '5.2', 'source' => 'rpm'],
        // The same identity twice: a host can report it, and the unique
        // index would reject the second one mid-insert.
        ['name' => 'bash', 'version' => '5.2', 'source' => 'rpm'],
        // Same name, different version: two rows, deliberately. An upgrade
        // reads as one version closed and another opened.
        ['name' => 'bash', 'version' => '5.3', 'source' => 'rpm'],
        // Same name and version from another manager: also two rows.
        ['name' => 'bash', 'version' => '5.2', 'source' => 'dpkg'],
    ]
);
$t->check('a duplicated program collapses to one row', 3 === count($cleaned));

$t->check('a nameless entry is dropped', [] === cleanList([['version' => '1.0']]));
$t->check('a non-array entry is dropped', [] === cleanList(['just a string', 42]));

$long = cleanList([['name' => str_repeat('x', 400), 'source' => 'rpm']]);
$row = reset($long);
$t->check(
    'an overlong name is truncated to the column width, not refused',
    255 === strlen($row['name'])
);

$dates = cleanList(
    [
        ['name' => 'a', 'source' => 'rpm', 'install_date' => '2024-07-02'],
        ['name' => 'b', 'source' => 'rpm', 'install_date' => 'not a date'],
        ['name' => 'c', 'source' => 'rpm', 'install_date' => "2024-07-02' OR 1=1"],
        ['name' => 'd', 'source' => 'rpm'],
    ]
);
$t->check(
    'a well-formed install date is kept',
    '2024-07-02' === $dates[array_keys($dates)[0]]['install_date']
);
foreach (['b', 'c', 'd'] as $i => $name) {
    $key = array_keys($dates)[$i + 1];
    $t->check(
        "a date that is not one becomes NULL ($name)",
        null === $dates[$key]['install_date']
    );
}

// ------------------------------------------------------------ gzip decoding

/**
 * Runs Route::_gunzip, the request-body decoder.
 *
 * @param string $raw the compressed body
 *
 * @return string
 */
function gunzip($raw)
{
    return FogTestHarness::callStatic(\FOG\Router\Route::class, '_gunzip', [$raw]);
}

$body = json_encode(['agent_version' => '1.0.0', 'software' => range(1, 500)]);
$t->check('a gzip body round trips', $body === gunzip(gzencode($body)));
$t->check('a body that is not gzip decodes to nothing', '' === gunzip('not gzip at all'));

// The bomb: small in, enormous out. It has to decode to nothing rather than
// to a body truncated at the cap, because a truncated body can still be
// valid JSON describing a different, smaller request.
$cap = \FOG\Router\Route::MAX_DECOMPRESSED_BODY;
$t->check(
    'a body over the cap decodes to nothing, not to a truncated body',
    '' === gunzip(gzencode(str_repeat('a', $cap + 1)))
);
$t->check(
    'a body at the cap still decodes',
    $cap === strlen(gunzip(gzencode(str_repeat('a', $cap))))
);

// -------------------------------------------------------------- the mapping

foreach (\FOG\Agent\State::FACT_REPORTS as $kind => $class) {
    $t->check(
        "the $kind fact kind maps to a class that can store one",
        class_exists($class) && method_exists($class, 'report')
    );
}

$t->finish();
