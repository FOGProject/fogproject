<?php
/**
 * Isolated web endpoint for exercising the real HostManagement AD renderer
 * and POST handler with a scratch MariaDB only. Never use against production.
 */

use FOG\Auth\CSRF;
use FOG\Base\HookManager;
use FOG\Items\Host;
use FOG\Items\User;
use FOG\Pages\HostManagement;

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/tests/lib/fog-test-harness.php';
FogTestHarness::boot('directory-operation-ui');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
\Initiator::language('ja');
$fake = FogTestHarness::fakeDb();
$pdo = new \PDO(
    getenv('MJ1_DB_DSN'),
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD'),
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($database) || 0 !== strpos($database, 'fog_mj1')) {
    http_response_code(500);
    exit('scratch database required');
}
$pdo->exec('CREATE TABLE IF NOT EXISTS hosts ('
    . 'hostID INT NOT NULL PRIMARY KEY, hostName VARCHAR(255) NOT NULL, '
    . 'hostADDomain VARCHAR(255) NOT NULL, hostUseAD TINYINT NOT NULL, '
    . 'hostAgentFingerprint VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS hostDirectory ('
    . 'hdHostID INT NOT NULL PRIMARY KEY, hdRejoinConfig TEXT NULL, '
    . 'hdRejoinState TEXT NULL) ENGINE=InnoDB');
$pdo->exec("INSERT INTO hosts VALUES (701, 'UI-SCRATCH', 'corp.example', 1, '"
    . str_repeat('f', 64) . "') ON DUPLICATE KEY UPDATE hostName='UI-SCRATCH'");
$pdo->exec('INSERT IGNORE INTO hostDirectory (hdHostID) VALUES (701)');
if (isset($_GET['reset']) && '1' === (string)$_GET['reset']) {
    $pdo->exec('UPDATE hostDirectory SET hdRejoinConfig=NULL,hdRejoinState=NULL WHERE hdHostID=701');
}
$fake->pdo = $pdo;
$user = (new User())->set('id', 1)->set('name', 'ui-scratch-operator');
$GLOBALS['currentUser'] = $user;
FogTestHarness::setStatic('FOGBase', 'FOGUser', $user);
$knownEvents = new \ReflectionProperty(HookManager::class, 'knownEvents');
$knownEvents->setAccessible(true);
$knownEvents->setValue(null, ['HOST_EDIT_AD_FIELDS' => true]);

$host = (new Host())
    ->set('id', 701)
    ->set('name', 'UI-SCRATCH')
    ->set('useAD', 1)
    ->set('ADDomain', 'corp.example')
    ->set('ADOU', 'OU=Computers,DC=corp,DC=example')
    ->set('ADUser', 'join-user')
    ->set('ADPass', '');
$page = (new \ReflectionClass(HostManagement::class))->newInstanceWithoutConstructor();
foreach ([
    'obj' => $host,
    'childClass' => 'Host'
] as $property => $value) {
    $reflection = new \ReflectionProperty(FOG\Base\FOGPage::class, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($page, $value);
}
$GLOBALS['node'] = 'host';

if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
    header('Content-Type: text/html; charset=UTF-8');
    $handler = new \ReflectionMethod(HostManagement::class, 'hostADPost');
    $handler->invoke($page);
    $saved = FOG\Agent\DirectoryRejoin::settings($host);
    echo '<p id="saved-config">' . htmlspecialchars(
        json_encode($saved['config'], JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
}

$render = new \ReflectionMethod(HostManagement::class, 'hostAD');
$render->setAccessible(true);
$_SESSION['FOG_LANG'] = 'ja';
\Initiator::language($_SESSION['FOG_LANG']);
if ('ドメイン参加状態' !== _('Domain Join Status')) {
    error_log(sprintf(
        'directory UI gettext probe failed: locale=%s domain=%s result=%s',
        (string)setlocale(LC_MESSAGES, '0'),
        textdomain('messages'),
        _('Domain Join Status')
    ));
    http_response_code(500);
    exit('Japanese gettext catalog is not active');
}
ob_start();
$render->invoke($page);
$form = ob_get_clean();
$form = preg_replace_callback(
    '/(<form\b[^>]*\baction=")[^"]*(")/i',
    static function ($matches) {
        return $matches[1] . '/tests/fixtures/directory-operation-ui.php'
            . $matches[2];
    },
    $form,
    1
);
$csrf = htmlspecialchars(CSRF::token(), ENT_QUOTES, 'UTF-8');
$form = str_replace('</form>', '<input type="hidden" name="_csrf" value="'
    . $csrf . '"></form>', $form);
if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }
$workspace = dirname(__DIR__, 2);
$css = [];
foreach (['bootstrap5.min.css', 'adminlte4.min.css', 'fog-default-ui.min.css'] as $file) {
    $path = realpath($workspace . '/packages/web/management/css/' . $file);
    if (false === $path || 0 !== strpos($path, $workspace . DIRECTORY_SEPARATOR)) {
        http_response_code(500);
        exit('workspace stylesheet missing');
    }
    $css[] = '<link rel="stylesheet" href="/packages/web/management/css/'
        . rawurlencode($file) . '">';
}
echo '<!doctype html><html lang="ja"><head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>隔離UI検証</title>' . implode('', $css)
    . '</head><body class="p-4"><main class="container-fluid">'
    . '<h1>Host AD UI scratch</h1>' . $form . '</main>'
    . '<script src="/packages/web/management/js/jquery.min.js"></script>'
    . '<script>window.Common=window.Common||{};Common.iCheck=function(match){'
    . 'match=match||"input";$(match).filter(":checkbox, :radio")'
    . '.addClass("form-check-input");};'
    . '$(function(){Common.iCheck("#active-directory-form input");});</script>'
    . '</body></html>';
