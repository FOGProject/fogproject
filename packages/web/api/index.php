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
// If a session was active, kill it.
if (count($_SESSION) > 0) {
    session_write_close();
    session_destroy();
    unset($_SESSION);
}
// Allow to process in background as needed.
ignore_user_abort(true);
// Allow infinite time to process as this is an api.
set_time_limit(0);
// Set up our current valid classes.
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
    'multicastsessions',
    'nodefailure',
    'notifyevent',
    'os',
    'oui',
    'plugin',
    'powermanagement',
    'printerassociation',
    'printer',
    'pxemenuoptions',
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
/**
 * Create a hook event so people can add to the
 * valid class elements.
 */
$HookManager
    ->processEvent(
        'API_VALID_CLASSES',
        array('validClasses' => &$validClasses)
    );
/**
 * ##################################################
 * # Functions below simply perform common actions. #
 * ##################################################
 */
/**
 * Function prints the appropriate error codes
 * if status or info is requested from the system.
 *
 * @param string $info The string to test.
 *
 * @return void
 */
$status = function ($info) {
    HTTPResponseCodes::breakHead(
        HTTPResponseCodes::HTTP_SUCCESS
    );
};
/**
 * Checks if the class callers are valid. If not
 * it will die with not implemented.
 *
 * @param string $class The class to test.
 *
 * @return void
 */
$checkvalid = function ($class) use ($validClasses) {
    $classname = strtolower($class);
    if (!in_array($classname, $validClasses)) {
        HTTPResponseCodes::breakHead(
            HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
        );
    }
};
/**
 * This is a commonizing element so list/search/getinfo
 * will operate in the same fasion.
 *
 * @param string $classname The name of the class.
 * @param object $class     The class to work with.
 *
 * @return object|array
 */
$getter = function ($classname, $class) {
    global $HookManager;
    switch ($classname) {
    case 'user':
        $data = array(
            'id' => $class->get('id'),
            'name' => $class->get('name'),
            'createdTime' => $class->get('createdTime'),
            'createdBy' => $class->get('createdBy'),
            'type' => $class->get('type'),
            'display' => $class->get('display')
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
                'hostscreen' => $class->get('hostscreen')->get(),
                'hostalo' => $class->get('hostalo')->get(),
                'inventory' => $class->get('inventory')->get(),
                'imagename' => $class->getImageName(),
                'pingstatus' => $class->getPingCodeStr()
            )
        );
        break;
    case 'image':
        $data = FOGCore::fastmerge(
            $class->get(),
            array(
                'os' => $class->get('os')->get(),
                'imagepartitiontype' => $class->get('os')->get(),
                'imagetype' => $class->get('imagetype')->get(),
                'imagetypename' => $class->getImageType()->get('name'),
                'imageparttypename' => $class->getImagePartitionType()->get('name'),
                'osname' => $class->getOS()->get('name'),
                'storagegroupname' => $class->getStorageGroup()->get('name')
            )
        );
        break;
    case 'storagenode':
        $data = FOGCore::fastmerge(
            $class->get(),
            array(
                'storagegroup' => $class->get('storagegroup')->get()
            )
        );
        break;
    default:
        $data = $class->get();
    }
    $HookManager
        ->processEvent(
            'API_GETTER',
            array(
                'data' => &$data,
                'classname' => &$classname,
                'class' => &$class
            )
        );
    return $data;
};
/**
 * Common printer of our data.
 *
 * @param mixed $data The data to encode.
 *
 * @return void
 */
$printer = function ($data) {
    echo json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    exit;
};
/**
 * ##################################################
 * #           The meat and potatoes now.           #
 * ##################################################
 */
// Instantiate the router object
$router = new AltoRouter;
// Set base path to what is found here.
$router->setBasePath(
    rtrim(
        WEB_ROOT,
        '/'
    )
);
// Create "checker" just to see if all is up and well.
$router->get(
    '/system/[status|info:info]/?',
    $status,
    'status'
);
$expandedClasses = sprintf(
    '[%s:class]',
    implode('|', $validClasses)
);
/**
 * Get/update item. /<class>/:<id>/
 */
$router->map(
    'GET|POST|PUT',
    "/${expandedClasses}/[i:id]/?",
    /**
     * Function enables individual object manipulation.
     * 
     * @param string $class  The class to work with.
     * @param int    $id     The class id.
     * @param string $method The method used for the request.
     *
     * @return void
     */
    function (
        $class,
        $id,
        $method
    ) use (
        $checkvalid,
        $getter,
        $printer
    ) {
        // Allows our hooks.
        global $HookManager;
        // Check valid object to test.
        $checkvalid($class);
        // Lowercase the class name.
        $classname = strtolower($class);
        // Get the current object.
        $class = new $class($id);
        // If not valid report not found and exit.
        if (!$class->isValid()) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_NOT_FOUND
            );
        }
        // If this is a put or post request, perform actions.
        if (in_array($method, array('PUT', 'POST'))) {
            // Decode the input.
            $vars = json_decode(
                file_get_contents('php://input'),
                true
            );
            // Loop our input.
            foreach ($vars as $key => $val) {
                // We don't allow editing the id.
                if ($key == 'id') {
                    continue;
                }
                // Update the respective key.
                $class->set($key, $val);
            }
            // Store the data and recreate.
            // If failed present so.
            if ($class->save()) {
                $class = new $class($id);
            } else {
                HTTPResponseCodes::breakHead(
                    HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
        // Set our store up.
        $data = array();
        // Get our data.
        $data = $getter($classname, $class);
        // Enable hooks to get in and adjust as needed.
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
        // Print the data.
        $printer($data);
    },
    'objEdit'
);
/**
 * Search element. /<class>/search/<whattosearch>/
 */
$router->get(
    "/${expandedClasses}/search/[*:item]/?",
    /**
     * Function handles search.
     *
     * @param string $class The class to work with.
     * @param mixed  $item  The item we want to search for.
     *
     * @return void
     */
    function (
        $class,
        $item
    ) use (
        $checkvalid,
        $getter,
        $printer
    ) {
        global $HookManager;
        $checkvalid($class);
        $classname = strtolower($class);
        $_REQUEST['crit'] = $item;
        $classman = FOGCore::getClass($class)->getManager();
        $data = array();
        $data[$classname.'s'] = array();
        foreach ($classman->search('', true) as &$class) {
            $data[$classname.'s'][] = $getter($classname, $class);
            unset($class);
        }
        $printer($data);
    },
    'objSearch'
);
$router->get(
    "/${expandedClasses}/?",
    function ($class) use ($checkvalid, $getter, $printer) {
        global $HookManager;
        $checkvalid($class);
        $classname = strtolower($class);
        $classman = FOGCore::getClass($class)->getManager();
        $data = array();
        $data[$classname.'s'] = array();
        foreach ($classman->find() as &$class) {
            $data[$classname.'s'][] = $getter($classname, $class);
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
        $printer($data);
    },
    'objList'
);
$match = $router->match();
if ($match && is_callable($match['target'])) {
    call_user_func_array($match['target'], $match['params']);
} else {
    HTTPResponseCodes::breakHead(
        HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
    );
}
