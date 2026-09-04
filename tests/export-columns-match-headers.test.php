<?php
/**
 * Every export page's JS column list matches the headers the server emits.
 *
 * FOGPage::export() builds the <th> row from _buildExportColumns(), and each
 * page's fog.<node>.export.js hands DataTables a POSITIONAL column list for
 * that same row. DataTables walks each <th>, looks up aoColumns[i], and
 * raises error 18 "Incorrect column count" the moment the two disagree --
 * which under the default errMode is an alert, no table and no toolbar
 * buttons. So a field added to a model's $databaseFields silently breaks the
 * export page for that class until somebody adds the matching JS entry.
 *
 * That is not hypothetical: archID (schema step 372) broke Host and Image,
 * sectorsize broke Image, apionly broke User and `order` broke Group -- four
 * of the nine export pages were dead at once, and nothing in the suite saw
 * it, because every test read one side or the other.
 *
 * This reads BOTH sides and compares them, including the order, since a
 * mismatched order mislabels columns without changing the count (the Host
 * page had sbenrollcert and sbenrollvia the wrong way round).
 *
 * The server side is the REAL _buildExportColumns(), invoked by reflection
 * rather than re-derived from $databaseFields, so the secret stripping, the
 * primac prepend, the trailing associations column and the *_EXPORT_ITEMS
 * hook are all the ones that actually run.
 *
 * Usage: php tests/export-columns-match-headers.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('export-columns-match-headers');
FogTestHarness::fakeDb();

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$jsFiles = glob($web . '/management/js/fog/*/fog.*.export.js');

$t->check(
    'the export pages were found',
    count($jsFiles) > 0
);

// Any page class will do as the vehicle: _buildExportColumns() reads nothing
// but $this->childClass, which FOGPage::__construct sets to ucfirst($node).
$pageClass = new \ReflectionClass('FOG\\Pages\\HostManagement');
$childClass = new \ReflectionProperty('FOG\\Base\\FOGPage', 'childClass');
$childClass->setAccessible(true);
$build = new \ReflectionMethod('FOG\\Base\\FOGPage', '_buildExportColumns');
$build->setAccessible(true);

foreach ($jsFiles as $file) {
    $node = basename(dirname($file));

    $page = $pageClass->newInstanceWithoutConstructor();
    $childClass->setValue($page, ucfirst($node));
    list(, , $columns) = $build->invoke($page);
    $server = [];
    foreach ($columns as $column) {
        $server[] = $column['dt'];
    }

    // The JS side, read positionally exactly as DataTables consumes it.
    preg_match_all(
        "/data:\s*'([A-Za-z0-9_]+)'/",
        file_get_contents($file),
        $matches
    );
    $client = $matches[1];

    $t->check(
        sprintf(
            '%s export: %d columns on both sides%s',
            $node,
            count($server),
            count($server) === count($client)
            ? ''
            : ' (js has ' . count($client) . ': '
              . 'missing ' . (implode(
                  ',',
                  array_diff($server, $client)
              ) ?: 'none')
              . ', extra ' . (implode(
                  ',',
                  array_diff($client, $server)
              ) ?: 'none') . ')'
        ),
        count($server) === count($client)
    );
    $t->check(
        sprintf(
            '%s export: columns in the same order%s',
            $node,
            $server === $client
            ? ''
            : "\n    server: " . implode(', ', $server)
              . "\n    client: " . implode(', ', $client)
        ),
        $server === $client
    );
}

$t->finish();
