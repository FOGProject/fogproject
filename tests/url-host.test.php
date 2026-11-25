<?php
/**
 * Pins FOGBase::urlHost(), the helper that brackets an IPv6 literal where a
 * host becomes the host part of a URL or of an NFS 'host:/path' target.
 *
 * FOGBase.php cannot be loaded on its own, so this extracts the real method
 * from the source and runs that text. A test of a copy would pass on a
 * method nobody ships. The same extract-and-run covers two callers the
 * boot-menu harness cannot reach: FOGBase::wakeUp() and
 * StorageNode::loadLocation_url().
 *
 * It also fails if the boot-menu harness copy of urlHost() differs from the
 * real method, because the boot-menu tests run that copy.
 *
 * Usage: php tests/url-host.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web/src/';

/**
 * Returns the source text of one method, from its signature to the closing
 * brace at four-space indent.
 *
 * @param string $file   the file to read
 * @param string $method the method name
 *
 * @return string
 */
function urlHostSourceOf($file, $method)
{
    $src = file_get_contents($file);
    if (!preg_match(
        '/    public (?:static )?function ' . preg_quote($method, '/')
        . '\(.*?\n    }\n/s',
        $src,
        $m
    )) {
        fwrite(STDERR, "FAIL: $method() not found in $file\n");
        exit(1);
    }
    return $m[0];
}

$urlHost = urlHostSourceOf($web . 'Base/FOGBase.php', 'urlHost');
$fails = [];

/*
 * Drift: the harness copy must be the real body, whitespace aside.
 */
$norm = function ($s) {
    return preg_replace('/\s+/', ' ', trim($s));
};
$harness = urlHostSourceOf(__DIR__ . '/lib/bootmenu-harness.php', 'urlHost');
if ($norm($harness) !== $norm($urlHost)) {
    $fails[] = 'the harness urlHost() differs from FOGBase::urlHost()';
}

eval('class UrlHostUnderTest {' . $urlHost . '}');

/*
 * filter_var(FILTER_FLAG_IPV6) rejects a zone id, so 'fe80::1%eth0' is not
 * bracketed and passes through unchanged. A zone id is not valid in a URL
 * host without RFC 6874 '%25' escaping, so leaving it alone is the narrow
 * choice. The case is pinned so a change to it is deliberate.
 */
$cases = [
    'IPv6 loopback' => ['::1', '[::1]'],
    'IPv6 ULA' => ['fd00::1', '[fd00::1]'],
    'IPv6 full form' => [
        '2001:db8:0:0:0:0:0:1',
        '[2001:db8:0:0:0:0:0:1]',
    ],
    'IPv6 with spaces around it' => ['  fd00::1 ', '[fd00::1]'],
    'IPv6 with a zone id' => ['fe80::1%eth0', 'fe80::1%eth0'],
    'IPv4' => ['10.0.0.1', '10.0.0.1'],
    'IPv4 with spaces around it' => [' 10.0.0.1 ', '10.0.0.1'],
    'a name' => ['fog.example.com', 'fog.example.com'],
    'already bracketed' => ['[fd00::1]', '[fd00::1]'],
    'empty string' => ['', ''],
    'null' => [null, ''],
];
foreach ($cases as $label => [$in, $want]) {
    $have = UrlHostUnderTest::urlHost($in);
    if ($have !== $want) {
        $fails[] = sprintf(
            '%s: urlHost(%s) gave %s, expected %s',
            $label,
            var_export($in, true),
            var_export($have, true),
            var_export($want, true)
        );
    }
}

/*
 * StorageNode::loadLocation_url(): the node address is the host of the URL.
 */
eval(
    'class LocationUrlUnderTest {'
    . ' public static $httpproto = "http"; public $location_url; public $d = [];'
    . ' public function get($k) { return $this->d[$k]; }'
    . ' public function set($k, $v) { $this->d[$k] = $v; return $this; }'
    . $urlHost
    . urlHostSourceOf($web . 'Items/StorageNode.php', 'loadLocation_url')
    . '}'
);
$expected = [
    'fd00::2' => 'http://[fd00::2]/fog',
    '10.0.0.2' => 'http://10.0.0.2/fog',
    'node.example.com' => 'http://node.example.com/fog',
];
foreach ($expected as $ip => $want) {
    $n = new LocationUrlUnderTest();
    $n->d = ['ip' => $ip, 'webroot' => 'fog'];
    $n->loadLocation_url();
    if ($n->location_url !== $want) {
        $fails[] = "loadLocation_url($ip) gave {$n->location_url}, expected $want";
    }
}

/*
 * FOGBase::wakeUp(): every URL handed to the request layer, node and web
 * host alike, has to be a full wakeEmUp URL with a bracketed IPv6 host.
 */
class Route
{
    public static function getList()
    {
        return [(object)['id' => 1]];
    }
    public static function getItem()
    {
        return (object)['id' => 1, 'online' => true, 'ip' => 'fd00::2'];
    }
}
class WolRequests
{
    public $urls = [];
    public function process($urls)
    {
        $this->urls = $urls;
    }
}
eval(
    'class WolUnderTest {'
    . ' public static $httpproto = "http"; public static $FOGURLRequests;'
    . ' public static function parseMacList($m) { return $m; }'
    . ' public static function getSetting($k) { return "fd00::1"; }'
    . ' public static function nodeSecret() { return "s"; }'
    . $urlHost
    . urlHostSourceOf($web . 'Base/FOGBase.php', 'wakeUp')
    . '}'
);
WolUnderTest::$FOGURLRequests = new WolRequests();
WolUnderTest::wakeUp(['00:11:22:33:44:55']);
$wantUrls = [
    'http://[fd00::2]/fog/management/index.php?node=client&sub=wakeEmUp',
    'http://[fd00::1]/fog/management/index.php?node=client&sub=wakeEmUp',
];
if (WolUnderTest::$FOGURLRequests->urls !== $wantUrls) {
    $fails[] = 'wakeUp() urls were ' . json_encode(
        WolUnderTest::$FOGURLRequests->urls
    ) . ', expected ' . json_encode($wantUrls);
}

if ($fails) {
    foreach ($fails as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}
printf("url-host: %d cases and the caller checks passed\n", count($cases));
echo "PASS\n";
exit(0);
