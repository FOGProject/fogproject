<?php
/**
 * "CSV (All)" POSTs its DataTables request instead of putting it on the URL
 * (GH-1861).
 *
 * THE BUG THIS GUARDS. The management "CSV (All)" button navigated to
 * ?node=host&sub=exportAll&<dt.ajax.params()>. The host grid has 49 columns,
 * and each column adds six bracketed keys, so the URL was about 11 KB.
 * Apache's LimitRequestLine default is 8190 bytes, so the export answered
 * "Request-URI Too Long" and no file came back.
 *
 * Usage: php tests/export-all-posts-its-request.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('export-all-posts-its-request');

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

$read = new \ReflectionMethod(\FOG\Base\FOGPage::class, '_exportRequest');
$read->setAccessible(true);

// A host-grid request as the browser builds it: every column, a search and
// a sort, encoded the way $.param() encodes it.
$fields = (new \ReflectionClass(\FOG\Items\Host::class))
    ->getDefaultProperties()['databaseFields'];
$columns = [['data' => 'primac']];
foreach (array_keys($fields) as $key) {
    if ($key !== 'id') {
        $columns[] = ['data' => $key];
    }
}
foreach ($columns as &$column) {
    $column += [
        'name' => '',
        'searchable' => 'true',
        'orderable' => 'true',
        'search' => ['value' => '', 'regex' => 'false'],
    ];
}
unset($column);
$sent = [
    'draw' => '3',
    'columns' => $columns,
    'order' => [['column' => '1', 'dir' => 'asc']],
    'start' => '0',
    'length' => '25',
    'search' => ['value' => 'lab', 'regex' => 'false'],
];
$body = http_build_query($sent);

/*
 * 1. The request is too long for a URL, so a body is the only safe place.
 */
$t->check(
    'host request exceeds Apache LimitRequestLine (8190 bytes)',
    strlen($body) > 8190
);

/*
 * 2. exportAll() reads the request from the POST body.
 */
$got = $read->invoke(null, $body, []);
$t->check('every column arrives', count($got['columns'] ?? []) === count($columns));
$t->check('the search arrives', ($got['search']['value'] ?? null) === 'lab');
$t->check('the sort arrives', ($got['order'][0]['column'] ?? null) === '1');
$t->check('paging is forced off', $got['start'] === 0 && $got['length'] === -1);

/*
 * 3. A caller that still sends the query string keeps working.
 */
$got = $read->invoke(null, '', ['search' => ['value' => 'old']]);
$t->check('empty body falls back to the query string', ($got['search']['value'] ?? null) === 'old');
$t->check('fallback forces paging off too', $got['start'] === 0 && $got['length'] === -1);

/*
 * 4. The button posts, and leaves the request off the URL.
 */
$js = (string)file_get_contents($web . '/management/js/fog/fog.common.js');
preg_match('/exportButtons = \[.*?CSV \(All\).*?action: (function.*?\n      \})/s', $js, $m);
$action = $m[1] ?? '';
$t->check('CSV (All) action found', $action !== '');
$t->check('CSV (All) does not navigate', strpos($action, 'window.location') === false);
$t->check('CSV (All) posts through fogPostDownload()', strpos($action, 'fogPostDownload(') !== false);
$t->check(
    'the request goes in the body, not the action URL',
    (bool)preg_match("/'&sub=exportAll',\s*body\s*\)/", $action)
);
$t->check(
    'fogPostDownload() submits a POST form',
    (bool)preg_match("/function fogPostDownload\(action, body\) \{.*?form\.method = 'post';/s", $js)
);

FogTestHarness::cleanup();
$t->finish();
