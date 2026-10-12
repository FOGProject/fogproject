<?php
/**
 * A CSV from "CSV (All)" imports back without editing (GH-1851).
 *
 * THE BUG THIS GUARDS. exportAll() starts the file with a UTF-8 byte-order
 * mark. importPost() read it with fgetcsv(), which keeps the BOM, so the
 * first header cell was "\xEF\xBB\xBFprimac". That is not a header token, so
 * auto-detect said "no header", the header row was imported as host #1, and
 * its "primac" value failed with "No viable macs to use". Ticking "First row
 * is a header" failed too: the required "primac" column was missing.
 *
 * The second half: every importer message ended in a literal "<br/>", and a
 * toast body is textContent, so the user saw the markup. Messages now end in
 * "\n" and the toast body shows those as line breaks.
 *
 * Usage: php tests/import-reads-exported-csv.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('import-reads-exported-csv');

/**
 * Reaches the two protected statics with their real signatures, so the
 * by-reference header map is typed rather than lost through reflection.
 */
abstract class ImportCsvProbe extends \FOG\Base\FOGPage
{
    /**
     * @param resource $fh
     *
     * @return void
     */
    public static function skipBom($fh)
    {
        self::skipUtf8Bom($fh);
    }

    /**
     * @param mixed $row
     * @param array $tokens
     * @param array $map
     *
     * @return bool
     */
    public static function header($row, array $tokens, array &$map)
    {
        $unknown = [];
        return self::parseCsvHeader($row, $tokens, false, $map, $unknown);
    }
}

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

// The host header exactly as exportAll() writes it: BOM, then primac and
// the field keys, through fputcsv().
$fields = (new \ReflectionClass(\FOG\Items\Host::class))
    ->getDefaultProperties()['databaseFields'];
unset($fields['id']);
$keys = array_keys($fields);
$header = array_merge(['primac'], $keys);
$row = array_merge(['00:11:22:33:44:55'], array_fill(0, count($keys), ''));
$tokens = array_map('strtolower', $header);

$csv = function ($bom) use ($header, $row) {
    $fh = fopen('php://memory', 'w+b');
    if ($bom) {
        fwrite($fh, "\xEF\xBB\xBF");
    }
    fputcsv($fh, $header);
    fputcsv($fh, $row);
    rewind($fh);
    return $fh;
};
$readHeader = function ($fh) use ($tokens) {
    ImportCsvProbe::skipBom($fh);
    $map = [];
    $first = fgetcsv($fh, 1000, ',');
    $isHeader = ImportCsvProbe::header($first, $tokens, $map);
    return [$isHeader, $map, $first];
};

/*
 * 1. An exported file (with a BOM) is detected as a header, and primac maps
 *    to the first column.
 */
list($isHeader, $map, $first) = $readHeader($csv(true));
$t->check('exported CSV: first row is detected as a header', $isHeader === true);
$t->check(
    'exported CSV: primac maps to column 0',
    isset($map['primac']) && $map['primac'] === 0
);
$t->check('exported CSV: first cell carries no BOM', $first[0] === 'primac');

/*
 * 2. A file with no BOM is not shortened: the first three bytes stay.
 */
list($isHeader, $map, $first) = $readHeader($csv(false));
$t->check('plain CSV: header still detected', $isHeader === true);
$t->check('plain CSV: first cell intact', $first[0] === 'primac');

/*
 * 3. A headerless file with a BOM keeps its first MAC readable.
 */
$fh = fopen('php://memory', 'w+b');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, $row);
rewind($fh);
ImportCsvProbe::skipBom($fh);
$data = fgetcsv($fh, 1000, ',');
$t->check('headerless BOM CSV: first MAC is clean', $data[0] === '00:11:22:33:44:55');

/*
 * 4. importPost() skips the BOM right after it opens the upload.
 */
$src = (string)file_get_contents($web . '/src/Base/FOGPage.php');
preg_match('/function importPost\(\).*?\n    \}\n/s', $src, $m);
$body = $m[0] ?? '';
$t->check('importPost() body found', $body !== '');
$t->check(
    'importPost() calls skipUtf8Bom() on the handle it opened',
    (bool)preg_match(
        '/\$fh = fopen\(\$file, \'rb\'\);\s*self::skipUtf8Bom\(\$fh\);/',
        $body
    )
);

/*
 * 5. No importer message carries HTML; the toast body shows "\n" breaks.
 */
$t->check('importPost() emits no <br/>', strpos($body, '<br') === false);
$js = (string)file_get_contents($web . '/management/js/fog/fog.common.js');
$t->check(
    'toast body keeps line breaks (white-space: pre-line)',
    (bool)preg_match("/bodyEl\\.style\\.whiteSpace = 'pre-line'/", $js)
);

FogTestHarness::cleanup();
$t->finish();
