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
$status = function ($info) {
    if (in_array($info, array('status', 'info'))) {
        die(http_response_code(200));
    } else {
        die(http_response_code(501));
    }
};
$router = new Router(
    array(
        array(
            'GET',
            '/system/[a:info]?/?',
            $status,
            'status'
        ),
    ),
    trim(WEB_ROOT, '/')
);
$router->map(
    'GET|POST|PUT',
    '/[a:class]/[i:id]/?',
    function ($class, $id, $method) use ($validClasses) {
        $classname = strtolower($class);
        if (!in_array($classname, $validClasses)) {
            http_response_code(501);
        }
        $class = new $class($id);
        if (!$class->isValid()) {
            die(http_response_code(404));
        }
        if (in_array($method, array('PUT', 'POST'))) {
            $vars = json_decode(
                file_get_contents('php://input'),
                true
            );
            foreach ($vars as $key => $val) {
                if ($key == 'id') {
                    continue;
                }
                $class->set($key, $val);
            }
            if ($class->save()) {
                die(http_response_code(200));
            }
            die(http_response_code(500));
        }
        $data = array();
        switch ($classname) {
        case 'user':
            $data = array(
                'id' => (int)$class->get('id'),
                'name' => (string)$class->get('name'),
                'display' => (string)$class->get('display'),
            );
            break;
        case 'host':
            $data = array(
                'id' => (int)$class->get('id'),
                'name' => (string)$class->get('name'),
                'macs' => (array)$class->getMyMacs(),
                'description' => (string)$class->get('description'),
                'ip' => (string)$class->get('ip'),
                'imageID' => (int)$class->get('imageID'),
                'building' => (string)$class->get('building'),
                'useAD' => (bool)$class->get('useAD'),
                'ADDomain' => (string)$class->get('ADDomain'),
                'ADOU' => (string)$class->get('ADOU'),
                'ADUser' => (string)$class->get('ADUser'),
                'ADPass' => (string)FOGCore::aesdecrypt(
                    $class->get('ADPass')
                ),
                'ADPassLegacy' => (string)$class->get('ADPassLegacy'),
                'productKey' => (string)FOGCore::aesdecrypt(
                    $class->get('productKey')
                ),
                'printerLevel' => (string)$class->get('printerLevel'),
                'kernelArgs' => (string)$class->get('kernelArgs'),
                'kernel' => (string)$class->get('kernel'),
                'kernelDevice' => (string)$class->get('kernelDevice'),
                'init' => (string)$class->get('init'),
                'pending' => (bool)$class->get('pending'),
                'biosexit' => (string)$class->get('biosexit'),
                'efiexit' => (string)$class->get('efiexit'),
                'enforce' => (bool)$class->get('enforce'),
                'inventory' => $class->get('inventory')->get(),
                'groups' => (array)$class->get('groups'),
                'printers' => (array)$class->get('printers'),
                'snapins' => (array)$class->get('snapins'),
                'modules' => (array)$class->get('modules'),
                'powermanagementtasks' => (array)$class->get('powermanagementtasks'),
            );
            break;
        default:
            $data = $class->get();
        }
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    },
    'objEdit'
);
$router->map(
    'GET',
    '/[a:class]/?',
    function ($class) use ($validClasses) {
        $classname = strtolower($class);
        if (!in_array($classname, $validClasses)) {
            echo json_encode(_('Invalid item passed'));
            http_response_code(404);
        }
        $classman = new $class;
        $classman = $classman->getManager();
        $data = array();
        $data[$classname.'s'] = array();
        foreach ($classman->find() as &$class) {
            switch ($classname) {
            case 'host':
                $data[$classname.'s'][] = array(
                    'id' => (int)$class->get('id'),
                    'name' => (string)$class->get('name'),
                    'macs' => (array)$class->getMyMacs(),
                    'description' => (string)$class->get('description'),
                    'ip' => (string)$class->get('ip'),
                    'imageID' => (int)$class->get('imageID'),
                    'building' => (string)$class->get('building'),
                    'useAD' => (bool)$class->get('useAD'),
                    'ADDomain' => (string)$class->get('ADDomain'),
                    'ADOU' => (string)$class->get('ADOU'),
                    'ADUser' => (string)$class->get('ADUser'),
                    'ADPass' => (string)FOGCore::aesdecrypt(
                        $class->get('ADPass')
                    ),
                    'ADPassLegacy' => (string)$class->get('ADPassLegacy'),
                    'productKey' => (string)FOGCore::aesdecrypt(
                        $class->get('productKey')
                    ),
                    'printerLevel' => (string)$class->get('printerLevel'),
                    'kernelArgs' => (string)$class->get('kernelArgs'),
                    'kernel' => (string)$class->get('kernel'),
                    'kernelDevice' => (string)$class->get('kernelDevice'),
                    'init' => (string)$class->get('init'),
                    'pending' => (bool)$class->get('pending'),
                    'biosexit' => (string)$class->get('biosexit'),
                    'efiexit' => (string)$class->get('efiexit'),
                    'enforce' => (bool)$class->get('enforce'),
                    'inventory' => $class->get('inventory')->get(),
                    'groups' => (array)$class->get('groups'),
                    'printers' => (array)$class->get('printers'),
                    'snapins' => (array)$class->get('snapins'),
                    'modules' => (array)$class->get('modules'),
                    'powermanagementtasks' => (array)
                        $class->get('powermanagementtasks'),
                );
                break;
            default:
                $data[$class.'s'][] = $class->get();
            }
            unset($class);
        }
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    },
    'objList'
);
$match = $router->match();
if ($match && is_callable($match['target'])) {
    call_user_func_array($match['target'], $match['params']);
} else {
    http_response_code(404);
}
