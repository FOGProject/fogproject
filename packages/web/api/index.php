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
session_write_close();
session_destroy();
unset($_SESSION);
ignore_user_abort(true);
set_time_limit(0);
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
        $code = HTTPResponseCodes::HTTP_SUCCESS;
    } else {
        $code = HTTPResponseCodes::HTTP_NOT_IMPLEMENTED;
    }
    HTTPResponseCodes::breakHead(
        $code
    );
};
$router = new Router;
// Set base path to what is found here.
$router->setBasePath(trim(WEB_ROOT, '/'));
// Create "checker" just to see if all is up and well.
$router->get(
    '/system/[a:info]/?',
    $status,
    'status'
);
$router->map(
    'GET|POST|PUT',
    '/[a:class]/[i:id]/?',
    function ($class, $id, $method) use ($validClasses) {
        global $HookManager;
        $classname = strtolower($class);
        if (!in_array($classname, $validClasses)) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
        }
        $class = new $class($id);
        if (!$class->isValid()) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_NOT_FOUND
            );
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
                $code = HTTPResponseCodes::HTTP_SUCCESS;
            } else {
                $code = HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR;
            }
            HTTPResponseCodes::breakHead(
                $code
            );
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
            $data = FOGCore::fastmerge(
                $class->get(),
                array(
                    'ADPass' => (string)FOGCore::aesdecrypt(
                        $class->get('ADPass')
                    ),
                    'productKey' => (string)FOGCore::aesdecrypt(
                        $class->get('productKey')
                    ),
                    'primac' => $class->get('mac')->__toString(),
                    'imagename' => $class->getImageName(),
                    'hostscreen' => '',
                    'hostalo' => '',
                    'inventory' => ''
                )
            );
            break;
        default:
            $data = $class->get();
        }
        $HookManager
            ->processEvent(
                'API_INDIVDATA_MAPPING',
                array(
                    'data' => &$data,
                    'classname' => &$classname,
                    'class' => &$class,
                    'method' => &$method
                )
            );
        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
        exit;
    },
    'objEdit'
);
$router->get(
    '/[a:class]/?',
    function ($class) use ($validClasses) {
        global $HookManager;
        $classname = strtolower($class);
        if (!in_array($classname, $validClasses)) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
        }
        $classman = new $class;
        $classman = $classman->getManager();
        $data = array();
        $data[$classname.'s'] = array();
        foreach ($classman->find() as &$class) {
            switch ($classname) {
            case 'image':
                $data[$classname.'s'][] = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'imagetypename' => $class
                            ->getImageType()
                            ->get('name'),
                        'imageparttypename' => $class
                            ->getImagePartitionType()
                            ->get('name'),
                        'osname' => $class
                            ->getOS()
                            ->get('name'),
                        'storagegroupname' => $class
                            ->getStorageGroup()
                            ->get('name')
                    )
                );
                break;
            case 'host':
                $data[$classname.'s'][] = FOGCore::fastmerge(
                    $class->get(),
                    array(
                        'ADPass' => (string)FOGCore::aesdecrypt(
                            $class->get('ADPass')
                        ),
                        'productKey' => (string)FOGCore::aesdecrypt(
                            $class->get('productKey')
                        ),
                        'primac' => $class->get('mac')->__toString(),
                        'imagename' => $class->getImageName(),
                        'hostscreen' => '',
                        'hostalo' => '',
                        'inventory' => ''
                    )
                );
                break;
            default:
                $data[$classname.'s'][] = $class->get();
            }
            unset($class);
        }
        $HookManager
            ->processEvent(
                'API_MASSDATA_MAPPING',
                array(
                    'data' => &$data,
                    'classname' => &$classname,
                    'classman' => &$classman,
                    'method' => &$method
                )
            );
        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
        exit;
    },
    'objList'
);
$match = $router->match();
if ($match && is_callable($match['target'])) {
    call_user_func_array($match['target'], $match['params']);
} else {
    HTTPResponseCodes::breakHead(
        HTTPResponseCodes::HTTP_NOT_FOUND
    );
}
