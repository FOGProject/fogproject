<?php
/**
 * Index/handler for api subsystem.
 *
 * PHP Version 5
 *
 * @category APIHandler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Index/handler for api subsystem.
 *
 * @category APIHandler
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
$validClasses = array(
    'clientupdate',
    'dircleaner',
    'greenfog',
    'greenfogassociation',
    'groupassociation',
    'group',
    'history',
    'hookevent',
    'hostautologout',
    'host',
    'hostscreensettings',
    'imageassociation',
    'image',
    'imagepartitiontype',
    'imagetype',
    'imaginglog',
    'inventory',
    'ipxe',
    'keysequence',
    'macaddressassociation',
    'moduleassociation',
    'module',
    'multicastsessionsassociation',
    'multicastsessionsmanager',
    'nodefailure',
    'notifyevent',
    'os',
    'oui',
    'plugin',
    'powermanagement',
    'printerassociation',
    'printer',
    'pxemenuoptionsmanager',
    'scheduledtask',
    'service',
    'snapinassociation',
    'snapingroupassociation',
    'snapinjob',
    'snapin',
    'snapintask',
    'storagegroup',
    'storagenode',
    'tasklog',
    'task',
    'taskstate',
    'tasktype',
    'usercleanup',
    'user',
    'usertracking',
    'virus'
);
$HookManager
    ->processEvent(
        'API_VALID_CLASSES',
        array('validClasses' => &$validClasses)
    );
$router = new Router;
$router->map(
    'GET|POST',
    '/[a:class]/[i:id]/?',
    function ($class, $id) use ($validClasses) {
        $class = strtolower($class);
        if (!in_array($class, $validClasses)) {
            echo json_encode(_('Invalid item passed'));
            http_response_code(404);
        }
        $class = new $class($id);
        if (!$class->isValid()) {
            echo json_encode(_('Invalid object identifier'));
            http_response_code(500);
        }
        echo json_encode($class->get());
    },
    'objEdit'
);
$router->map(
    'GET',
    '/[a:class]/?$',
    function ($class) use ($validClasses) {
        $class = strtolower($class);
        if (!in_array($class, $validClasses)) {
            echo json_encode(_('Invalid item passed'));
            http_response_code(404);
        }
        $classman = new $class;
        $classman = $classman->getManager();
        $data = array();
        $data[$class.'s'] = array();
        foreach ($classman->find() as &$obj) {
            $data[$class.'s'][] = $obj->get();
            unset($obj);
        }
        echo json_encode($data);
    },
    'objList'
);
$match = $router->match();
if ($match && is_callable($match['target'])) {
    call_user_func_array($match['target'], $match['params']);
} else {
    http_response_code(404);
}
