<?php
/**
 * Task type class.
 *
 * PHP version 5
 *
 * @category TaskType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task type class.
 *
 * @category TaskType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskType extends FOGController
{
    const DEPLOY = 1;
    const CAPTURE = 2;
    const DEBUG = 3;
    const MEMTEST = 4;
    const TEST_DISK = 5;
    const DISK_SURFACE_TEST = 6;
    const RECOVER = 7;
    const MULTICAST = 8;
    const INVENTORY = 10;
    const PASSWORD_RESET = 11;
    const ALL_SNAPINS = 12;
    const SINGLE_SNAPIN = 13;
    const WAKE_UP = 14;
    const DEPLOY_DEBUG = 15;
    const CAPTURE_DEBUG = 16;
    const DEPLOY_NO_SNAPINS = 17;
    const FAST_WIPE = 18;
    const NORMAL_WIPE = 19;
    const FULL_WIPE = 20;
    const DEBUGTASKS = [
        self::DEBUG,
        self::MULTICAST,
        self::DEPLOY_DEBUG,
        self::CAPTURE_DEBUG
    ];
    const SNAPINTASKS = [
        self::ALL_SNAPINS,
        self::SINGLE_SNAPIN
    ];
    const DEPLOYTASKS = [
        self::DEPLOY,
        self::DEPLOY_DEBUG,
        self::DEPLOY_NO_SNAPINS,
        self::MULTICAST
    ];
    const WIPETASKS = [
        self::FAST_WIPE,
        self::NORMAL_WIPE,
        self::FULL_WIPE
    ];
    const CAPTURETASKS = [
        self::CAPTURE,
        self::CAPTURE_DEBUG
    ];

    /**
     * The database table for task type.
     *
     * @var string
     */
    protected $databaseTable = 'taskTypes';
    /**
     * The database fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ttID',
        'name' => 'ttName',
        'description' => 'ttDescription',
        'icon' => 'ttIcon',
        'kernel' => 'ttKernel',
        'kernelArgs' => 'ttKernelArgs',
        'type' => 'ttType',
        'isAdvanced' => 'ttIsAdvanced',
        'access' => 'ttIsAccess',
        'initrd' => 'ttInitrd'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'icon'
    ];
    /**
     * Gives the list of icons.
     *
     * @param mixed $selected the current selected item
     *
     * @return string
     */
    public function iconlist($selected = '')
    {
        $selected = trim($selected);
        $fh = fopen(
            '../management/other/_variables.scss',
            'rb'
        );
        if (!$fh) {
            return _('Icon File not found');
        }
        while (($line = fgets($fh)) !== false) {
            if (!preg_match('#^\$fa\-var\-#', $line)) {
                continue;
            }
            $match = preg_split(
                '#[:\s|:^\s]+#',
                trim(
                    preg_replace(
                        '#[\$\"\;\\\]|fa\-var\-#',
                        '',
                        $line
                    )
                )
            );
            $match[0] = trim($match[0]);
            $match[1] = trim($match[1]);
            $icons[$match[0]] = sprintf(
                '&#x%s',
                $match[1]
            );
            unset($match);
        }
        fclose($fh);
        if (!count($icons)) {
            return _('No icons found');
        }
        ksort($icons);
        ob_start();
        echo '<select class="form-control fa" id="icon" name="icon">';
        foreach ((array) $icons as $name => &$unicode) {
            printf(
                '<option value="%s"%s>%s %s</option>',
                $name,
                $selected == $name ? ' selected' : '',
                $unicode,
                $name
            );
            unset($unicode, $name);
        }
        unset($icons);

        return sprintf(
            '%s</select>',
            ob_get_clean()
        );
    }
    /**
     * Returns the icon for this task or type.
     *
     * @return string
     */
    public function getIcon()
    {
        return (
            $this instanceof Task ?
            $this->getTaskType()->get('icon') :
            $this->get('icon')
        );
    }
    /**
     * Returns if this is an imaging task.
     *
     * @return bool
     */
    public function isImagingTask()
    {
        return (bool) (
            $this->isDeploy()
            || $this->isCapture()
        );
    }
    /**
     * Returns if this a capture task.
     *
     * @param bool $nums To return ids?
     *
     * @return bool|array
     */
    public function isCapture($nums = false)
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        if ($nums) {
            return [
                self::CAPTURE,
                self::CAPTURE_DEBUG
            ];
        }

        return $this->isValid()
            && (
                in_array(
                    $this->get($id),
                    [
                        self::CAPTURE,
                        self::CAPTURE_DEBUG
                    ]
                )
                || preg_match(
                    '#type=(2|16|up)#i',
                    $this->get('kernelArgs')
                )
            );
    }
    /**
     * Returns if the task needs the inits.
     *
     * @param bool $nums To return ids?
     *
     * @return bool|array
     */
    public function isInitNeededTasking($nums = false)
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        if ($nums) {
            return array_values(
                array_diff(
                    range(1, 24),
                    [
                        self::MEMTEST,
                        self::ALL_SNAPINS,
                        self::SINGLE_SNAPIN,
                        self::WAKE_UP
                    ]
                )
            );
        }

        return $this->isValid()
            && !in_array(
                $this->get($id),
                [
                    self::MEMTEST,
                    self::ALL_SNAPINS,
                    self::SINGLE_SNAPIN,
                    self::WAKE_UP
                ]
            );
    }
    /**
     * Returns if this is snapin only tasking.
     *
     * @param bool $nums To return ids?
     *
     * @return bool|array
     */
    public function isSnapinTasking($nums = false)
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        if ($nums) {
            return [
                self::ALL_SNAPINS,
                self::SINGLE_SNAPIN
            ];
        }

        return $this->isValid()
            && in_array(
                $this->get($id),
                [
                    self::ALL_SNAPINS,
                    self::SINGLE_SNAPIN
                ]
            );
    }
    /**
     * Returns if we need to task snapins too.
     *
     * @return bool
     */
    public function isSnapinTask()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return $this->isValid()
            && (
                $this->isDeploy()
                && $this->get($id) != self::DEPLOY_NO_SNAPINS
            )
            || in_array(
                $this->get($id),
                [
                    self::ALL_SNAPINS,
                    self::SINGLE_SNAPIN
                ]
            );
    }
    /**
     * Returns if this is a deploy tasking.
     *
     * @param bool $nums To return ids?
     *
     * @return bool|array
     */
    public function isDeploy($nums = false)
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        if ($nums) {
            return [
                self::DEPLOY,
                self::MULTICAST,
                self::DEPLOY_DEBUG,
                self::DEPLOY_NO_SNAPINS
            ];
        }

        return $this->isValid()
            && (
                in_array(
                    $this->get($id),
                    [
                        self::DEPLOY,
                        self::MULTICAST,
                        self::DEPLOY_DEBUG,
                        self::DEPLOY_NO_SNAPINS
                    ]
                )
                || preg_match(
                    '#type=(1|8|15|17|24|down)#i',
                    $this->get('kernelArgs')
                )
            );
    }
    /**
     * Returns if this is a multicast tasking.
     *
     * @param bool $nums To return ids?
     *
     * @return bool|array
     */
    public function isMulticast($nums = false)
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        if ($nums) {
            return [
                self::MULTICAST
            ];
        }

        return
            $this->isValid()
            && (
                $this->get($id) == self::MULTICAST
                || preg_match(
                    '#(type=8|mc=yes)#i',
                    $this->get('kernelArgs')
                )
            );
    }
    /**
     * Returns if this is a debug tasking.
     *
     * @return bool
     */
    public function isDebug()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return
            $this->isValid()
            && (
                in_array(
                    $this->get($id),
                    [
                        self::DEPLOY_DEBUG,
                        self::CAPTURE_DEBUG
                    ]
                )
                || preg_match('#mode=debug#i', $this->get('kernelArgs'))
                || preg_match('#mode=onlydebug#i', $this->get('kernelArgs'))
            );
    }
}
