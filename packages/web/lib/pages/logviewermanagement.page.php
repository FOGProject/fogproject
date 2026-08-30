<?php
/**
 * Log Viewer.
 *
 * PHP version 7.4+
 *
 * @category LogViewerManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Base\FOGPage;
use FOG\Router\Route;

/**
 * Log Viewer.
 *
 * Its own node rather than a tab on the About/FOG Configuration page, so it
 * can sit in the sidebar under Logging beside Activity and the Audit Log --
 * the three read-only views of what this server has been doing. It was the
 * odd one out: the same question as those two, answered from a page about
 * kernels, PXE menus and settings.
 *
 * MOVING IT DOES NOT MOVE ITS GATE. Authorization::NODE_ALIASES maps
 * `logviewer` onto the `settings` node -- exactly what `about` maps to -- so
 * everyone who could read the logs before still can and nobody new can.
 * Giving it a permission node of its own would have been a widening or a
 * narrowing depending on who holds what, and neither was asked for.
 *
 * @category LogViewerManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LogViewerManagement extends FOGPage
{
    /**
     * The node this page answers for.
     *
     * @var string
     */
    public $node = 'logviewer';
    /**
     * Initializes the log viewer page.
     *
     * @param string $name the name to construct with.
     */
    public function __construct($name = '')
    {
        // Honored rather than discarded. Every FOGPage takes a name and this
        // one ignored it, which is why static analysis called the parameter
        // unused -- a hook or a plugin constructing the page with a label
        // would silently get this one instead.
        $this->name = ('' !== trim((string)$name)) ? $name : _('Log Viewer');
        parent::__construct($this->name);
    }
    /**
     * Gets and displays log files.
     *
     * Variadic to match FOGPage::index(...$args) -- PHP rejects the
     * declaration outright otherwise, so the class does not load at all.
     *
     * @param mixed ...$args unused, present for signature compatibility
     *
     * @return void
     */
    public function index(...$args)
    {
        $StorageGroups = Route::getList('storagegroup');
        // Declared up front. It is filled per storage node in the loop below
        // and read by node name when the <option> list is built, so a server
        // with no enabled node reached that read with $ip never defined.
        $ip = [];

        // Log selector.
        $logtype = _('error');
        // The node is a PARAMETER, not a by-reference capture.
        //
        // It used to be captured with `use (&$StorageNode)` and assigned only
        // inside the loop below, which meant the closure dereferenced null on
        // any path reaching it first -- a hook, an empty group list, a future
        // edit -- for a bodyless 500 on the page whose whole job is showing
        // you what went wrong. That was a baselined `Cannot access property
        // $name on null`, carried unfixed for as long as the baseline has
        // existed.
        //
        // Guarding the capture does not work: a by-ref use is exactly what
        // static analysis cannot follow, so it narrows the variable to null
        // and then calls the guard itself dead code. Passing it in removes
        // the question -- the closure cannot be called without a node, and
        // the analyzer can see that.
        //
        // Left untyped on purpose. A StorageNode hint reads better but makes
        // the analyzer resolve the class and then reject every field access
        // on it: FOGController serves $databaseFields through __get, so
        // ->name is not a declared property and never will be. Untyped is
        // what the rest of the pages do with ORM objects.
        $logparse = function ($log, $StorageNode) use (
            &$files,
            &$logtype
        ) {
            $str = sprintf(
                _('%s %s log (%s)'),
                (
                    preg_match('#nginx#i', $log) ?
                    'NGINX' :
                    (
                        preg_match('#apache|httpd#', $log) ?
                        'Apache' :
                        (
                            preg_match('#fpm#i', $log) ?
                            'PHP-FPM' :
                            ''
                        )
                    )
                ),
                $logtype,
                basename($log)
            );
            $files[$StorageNode->name][_($str)] = $log;
        };
        foreach ($StorageGroups as &$StorageGroup) {
            if (count($StorageGroup->enablednodes ?: []) < 1) {
                continue;
            }
            $StorageNode = $StorageGroup->masternode;
            Route::logfiles($StorageNode->id);
            $fogfiles = json_decode(
                Route::getData(),
                true
            );
            try {
                $apacheerrlog = preg_grep(
                    '#(error[\_|\.]log$)#i',
                    $fogfiles
                );
                $apacheacclog = preg_grep(
                    '#(access[\_|\.]log$)#i',
                    $fogfiles
                );
                list(
                    $filedeletelogname,
                    $imagereplicatorlogname,
                    $imagesizelogname,
                    $multicastlogname,
                    $pinghostlogname,
                    $pluginrunnerlogname,
                    $retentionrunnerlogname,
                    $schedulerlogname,
                    $servicelogname,
                    $snapinhashlogname,
                    $snapinreplicatorlogname,
                ) = self::getSetting([
                    'FILEDELETEQUEUELOGFILENAME',
                    'IMAGEREPLICATORLOGFILENAME',
                    'IMAGESIZELOGFILENAME',
                    'MULTICASTLOGFILENAME',
                    'PINGHOSTLOGFILENAME',
                    'PLUGINRUNNERLOGFILENAME',
                    'RETENTIONRUNNERLOGFILENAME',
                    'SCHEDULERLOGFILENAME',
                    'SERVICEMASTERLOGFILENAME',
                    'SNAPINHASHLOGFILENAME',
                    'SNAPINREPLICATORLOGFILENAME',
                ]);
                $multicastlog = preg_grep(
                    '#('.$multicastlogname.'$)#i',
                    $fogfiles
                );
                $multicastlog = array_shift($multicastlog);
                $schedulerlog = preg_grep(
                    '#('.$schedulerlogname.'$)#i',
                    $fogfiles
                );
                $schedulerlog = array_shift($schedulerlog);
                $imgrepliclog = preg_grep(
                    '#('.$imagereplicatorlogname.'$)#i',
                    $fogfiles
                );
                $imgrepliclog = array_shift($imgrepliclog);
                $imagesizelog = preg_grep(
                    '#('.$imagesizelogname.'$)#i',
                    $fogfiles
                );
                $imagesizelog = array_shift($imagesizelog);
                $snapinreplog = preg_grep(
                    '#('.$snapinreplicatorlogname.'$)#i',
                    $fogfiles
                );
                $snapinreplog = array_shift($snapinreplog);
                $snapinhashlog = preg_grep(
                    '#('.$snapinhashlogname.'$)#i',
                    $fogfiles
                );
                $snapinhashlog = array_shift($snapinhashlog);
                $pinghostlog = preg_grep(
                    '#('.$pinghostlogname.'$)#i',
                    $fogfiles
                );
                $pinghostlog = array_shift($pinghostlog);
                $filedeletequeuelog = preg_grep(
                    '#('.$filedeletelogname.'$)#i',
                    $fogfiles
                );
                $filedeletequeuelog = array_shift($filedeletequeuelog);
                $pluginrunnerlog = preg_grep(
                    '#('.$pluginrunnerlogname.'$)#i',
                    $fogfiles
                );
                $pluginrunnerlog = array_shift($pluginrunnerlog);
                $retentionrunnerlog = preg_grep(
                    '#('.$retentionrunnerlogname.'$)#i',
                    $fogfiles
                );
                $retentionrunnerlog = array_shift($retentionrunnerlog);
                $svcmasterlog = preg_grep(
                    '#('.$servicelogname.'$)#i',
                    $fogfiles
                );
                $svcmasterlog = array_shift($svcmasterlog);
                $imgtransferlogs = preg_grep(
                    '#('.$imagereplicatorlogname.'.transfer)#i',
                    $fogfiles
                );
                $snptransferlogs = preg_grep(
                    '#('.$snapinreplicatorlogname.'.transfer)#i',
                    $fogfiles
                );
                $files[$StorageNode->name] = [
                    (
                        $svcmasterlog ?
                        _('Service Master') :
                        null
                    )=> (
                        $svcmasterlog ?
                        $svcmasterlog :
                        null
                    ),
                    (
                        $multicastlog ?
                        _('Multicast') :
                        null
                    ) => (
                        $multicastlog ?
                        $multicastlog :
                        null
                    ),
                    (
                        $schedulerlog ?
                        _('Scheduler') :
                        null
                    ) => (
                        $schedulerlog ?
                        $schedulerlog :
                        null
                    ),
                    (
                        $imgrepliclog ?
                        _('Image Replicator') :
                        null
                    ) => (
                        $imgrepliclog ?
                        $imgrepliclog :
                        null
                    ),
                    (
                        $imagesizelog ?
                        _('Image Size') :
                        null
                    ) => (
                        $imagesizelog ?
                        $imagesizelog :
                        null
                    ),
                    (
                        $snapinreplog ?
                        _('Snapin Replicator') :
                        null
                    ) => (
                        $snapinreplog ?
                        $snapinreplog :
                        null
                    ),
                    (
                        $snapinhashlog ?
                        _('Snapin Hash') :
                        null
                    ) => (
                        $snapinhashlog ?
                        $snapinhashlog :
                        null
                    ),
                    (
                        $pinghostlog ?
                        _('Ping Hosts') :
                        null
                    ) => (
                        $pinghostlog ?
                        $pinghostlog :
                        null
                    ),
                    (
                        $filedeletequeuelog ?
                        _('File Delete Queue') :
                        null
                    ) => (
                        $filedeletequeuelog ?
                        $filedeletequeuelog :
                        null
                    ),
                    (
                        $pluginrunnerlog ?
                        _('Plugin Runner') :
                        null
                    ) => (
                        $pluginrunnerlog ?
                        $pluginrunnerlog :
                        null
                    ),
                    (
                        $retentionrunnerlog ?
                        _('Retention Runner') :
                        null
                    ) => (
                        $retentionrunnerlog ?
                        $retentionrunnerlog :
                        null
                    ),
                ];
                // foreach rather than array_map: the closure is called for
                // its side effect on $files and array_map's return was
                // discarded, so the map was only ever a loop wearing a hat --
                // and it cannot pass the node through.
                foreach ((array)$apacheerrlog as $log) {
                    $logparse($log, $StorageNode);
                }
                $logtype = _('access');
                foreach ((array)$apacheacclog as $log) {
                    $logparse($log, $StorageNode);
                }
                foreach ((array)$imgtransferlogs as &$file) {
                    $str = self::stringBetween(
                        $file,
                        'transfer.',
                        '.log'
                    );
                    $str = sprintf(
                        '%s %s',
                        $str,
                        _('Image Transfer Log')
                    );
                    $files[$StorageNode->name][$str] = $file;
                    unset($file);
                }
                foreach ((array)$snptransferlogs as &$file) {
                    $str = self::stringBetween(
                        $file,
                        'transfer.',
                        '.log'
                    );
                    $str = sprintf(
                        '%s %s',
                        $str,
                        _('Snapin Transfer Log')
                    );
                    $files[$StorageNode->name][$str] = $file;
                    unset($file);
                }
                $files[$StorageNode->name] = array_filter(
                    (array)$files[$StorageNode->name]
                );
            } catch (\Exception $e) {
                $files[$StorageNode->name] = [
                    $e->getMessage() => null,
                ];
            }
            $ip[$StorageNode->name] = $StorageNode->ip;
            self::$HookManager->processEvent(
                'LOG_VIEWER_HOOK',
                [
                    'files' => &$files,
                    'StorageNode' => &$StorageNode
                ]
            );
            unset($StorageGroup);
        }
        unset($StorageGroups);

        ob_start();
        echo '<select name="logtype" class="fog-select2" id="logToView">';
        foreach ($files as $nodename => &$filearray) {
            $first = true;
            foreach ((array)$filearray as $value => &$file) {
                if ($first) {
                    printf(
                        '<option disabled> ------- %s ------- </option>',
                        $nodename
                    );
                    $first = false;
                }
                printf(
                    '<option value="%s||%s"%s>%s</option>',
                    \Initiator::e(base64_encode((string)($ip[$nodename] ?? ''))),
                    \Initiator::e($file),
                    (
                        isset($_POST['logtype']) && $value == $_POST['logtype'] ?
                        ' selected' :
                        ''
                    ),
                    \Initiator::e($value)
                );
                unset($file);
            }
            unset($filearray);
        }
        unset($files);
        echo '</select>';
        $logSelector = ob_get_clean();

        // Line Selector
        $vals = [
            10,
            25,
            50,
            100,
            250,
            500,
            1000
        ];
        ob_start();
        echo '<select name="n" class="form-control" id="linesToView">';
        foreach ((array)$vals as $i => &$value) {
            printf(
                '<option value="%s"%s>%s</option>',
                \Initiator::e($value),
                (
                    $value == filter_input(
                        INPUT_POST,
                        'n',
                        FILTER_SANITIZE_NUMBER_INT
                    ) ?
                    ' selected' :
                    ''
                ),
                \Initiator::e($value)
            );
            unset($value);
        }
        unset($vals);
        echo '</select>';
        $lineSelector = ob_get_clean();

        $this->title = _('FOG Log Viewer');

        // One self-relabeling toggle, not a pause/resume pair -- pausing the
        // live tail destroys nothing so Pause never belonged on the left, and
        // only ever one of the two was pressable. Labels are the shared
        // "Pause/Resume Reload" pair so this button reads identically to the
        // task and multicast panes. Sole right-side button, so primary.
        $buttons = self::makeReloadToggle(
            'logreload-toggle',
            'btn btn-primary float-end'
        );

        echo self::makeFormTag(
            '',
            'logviewer-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '<hr/>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'logToView',
            _('File')
        );
        echo $logSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'linesToView',
            _('Lines')
        );
        echo $lineSelector;
        echo '</div>';
        echo '<div class="col-sm-4">';
        echo self::makeLabel(
            'col-sm-3 col-form-label',
            'reverse',
            _('Reverse')
            . ' '
            . self::makeInput(
                '',
                'reverse',
                '',
                'checkbox',
                'reverse'
            )
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body" id="logsGoHere">';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
}

// The autoloader resolves a page by its BARE class name (ADR 0013), so a
// namespaced page needs this or FOGPageManager logs "does not declare" and
// the node 404s.
class_alias(__NAMESPACE__ . '\\LogViewerManagement', 'LogViewerManagement');
