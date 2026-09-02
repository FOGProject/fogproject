<?php
/**
 * The search box's query, as _searchRows() actually builds it.
 *
 * Three things the sidebar search got wrong before this was written, each
 * of which a static grep of the source could pass while the query was
 * broken, so this test captures the SQL and the bound parameters the fake
 * database is handed and asserts on those:
 *
 *  1. The term was interpolated into LIKE unescaped, so "100%" matched
 *     every row and "a_c" matched "abc".
 *  2. The id column was matched with LIKE '%term%' for every term, which
 *     casts each row's id to a string to fail on a term like "lab".
 *  3. There was no ORDER BY, so the five rows a LIMIT kept were whichever
 *     five InnoDB found first, not the ones starting with the term.
 *
 * Plus the query form of the route: /unisearch?q=term&limit=n, which is
 * what the box sends now that the term no longer travels in the path.
 *
 * PHP version 7.4+
 */
require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('unisearch-ranks');
$db = FogTestHarness::fakeDb();
$t = new FogChecks();

/**
 * The queries the fake database was handed. A class rather than an array
 * captured by reference so the reads below are typed by last(), not
 * narrowed to the empty literal the static analyser sees assigned.
 */
final class QueryCapture
{
    /** @var array<int, array{sql: string, params: array<string, mixed>}> */
    private $list = [];

    /**
     * @param string               $sql    The statement.
     * @param array<string, mixed> $params Its bound parameters.
     *
     * @return void
     */
    public function add($sql, array $params)
    {
        $this->list[] = ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->list);
    }

    /**
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function last()
    {
        $n = count($this->list);
        return $n > 0 ? $this->list[$n - 1] : ['sql' => '', 'params' => []];
    }

    /**
     * @return void
     */
    public function reset()
    {
        $this->list = [];
    }
}
$cap = new QueryCapture();
$db->responder = function ($sql, $params) use ($cap) {
    if (false === stripos($sql, 'LIKE :item2')) {
        return null;
    }
    $cap->add($sql, $params);
    return [];
};

$rows = new \ReflectionMethod('FOG\Router\Route', '_searchRows');
$rows->setAccessible(true);

// --- 1. Escaping ---------------------------------------------------------
$cap->reset();
$rows->invoke(null, 'host', '100%_!', 5);
$t->check('a host search issued one query', 1 === $cap->count());
$q = $cap->last();
$t->check(
    'LIKE metacharacters in the term are escaped with !',
    '%100!%!_!!%' === ($q['params']['item2'] ?? null)
);
$t->check(
    'every LIKE names its escape character',
    substr_count($q['sql'], 'LIKE :item2 ESCAPE \'!\'') === 1
    && substr_count($q['sql'], 'LIKE :item3 ESCAPE \'!\'') === 1
    && substr_count($q['sql'], 'LIKE :prefix ESCAPE \'!\'') === 1
);
$t->check(
    'the MAC arm receives the same escaped term',
    '%100!%!_!!%' === ($q['params']['item3'] ?? null)
);

// --- 2. The id arm -------------------------------------------------------
$t->check(
    'a non-numeric term does not touch the id column',
    false === strpos($q['sql'], '`hostID` = :item1')
    && false === strpos($q['sql'], '`hostID` LIKE')
    && !array_key_exists('item1', $q['params'])
);
$cap->reset();
$rows->invoke(null, 'host', '42', 5);
$q = $cap->last();
$t->check(
    'a whole-number term matches the id exactly',
    false !== strpos($q['sql'], '`hostID` = :item1 OR `hostName` LIKE :item2')
    && '42' === ($q['params']['item1'] ?? null)
);
$t->check(
    'the id arm is inside the parenthesised match clause',
    1 === preg_match('/WHERE \(`hostID` = :item1 OR /', $q['sql'])
);

// --- 3. Ranking ----------------------------------------------------------
$cap->reset();
$rows->invoke(null, 'image', 'lab', 5);
$q = $cap->last();
$t->check(
    'prefix matches sort first, then by name',
    false !== strpos(
        $q['sql'],
        "ORDER BY (`imageName` LIKE :prefix ESCAPE '!') DESC, `imageName` ASC"
    )
);
$t->check(
    'the prefix parameter is the escaped term with a trailing wildcard only',
    'lab%' === ($q['params']['prefix'] ?? null)
);
$t->check(
    'ORDER BY precedes the LIMIT',
    strpos($q['sql'], 'ORDER BY') < strpos($q['sql'], 'LIMIT 5')
);
// hosts GROUP BY the name; the ORDER BY must follow it, not split it.
$cap->reset();
$rows->invoke(null, 'host', 'lab', 5);
$q = $cap->last();
$t->check(
    'on hosts the ORDER BY follows the GROUP BY',
    strpos($q['sql'], 'GROUP BY `hosts`.`hostName`') < strpos($q['sql'], 'ORDER BY')
);

// --- 4. The query form of the route ----------------------------------------
$t->check(
    'unisearch() takes no arguments for the query form',
    (new \ReflectionMethod('FOG\Router\Route', 'unisearch'))
        ->getParameters()[0]->isDefaultValueAvailable()
);
$dispatcher = \FOG\Router\Route::newDispatcher(function ($r) {
    $define = new \ReflectionMethod('FOG\Router\Route', 'defineRoutes');
    $define->setAccessible(true);
    $define->invoke(null, $r);
});
foreach (['/unisearch', '/search'] as $uri) {
    $res = $dispatcher->dispatch('POST', $uri);
    $vars = $res[2] ?? [];
    $t->check(
        "$uri dispatches to unisearch with no path term",
        \FastRoute\Dispatcher::FOUND === $res[0]
        && 'unisearch' === ($res[1]['name'] ?? null)
        && !array_key_exists('item', $vars)
    );
}
// And the JS sends it that way: body fields, not path segments.
$js = (string)file_get_contents(
    __DIR__ . '/../packages/web/management/js/fog/fog.common.js'
);
$fn = strpos($js, 'function setupUniversalSearch()');
$body = substr($js, $fn, strpos($js, "\n}\n", $fn) - $fn);
$t->check(
    'the sidebar box posts q and limit as fields',
    false !== strpos($body, 'return {q: params.term, limit: resultLimit};')
    && false !== strpos($body, 'url: baseURL,')
    && false === strpos($body, "baseURL + '/'")
);

$t->finish();
