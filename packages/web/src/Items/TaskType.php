<?php
/**
 * Task type class.
 *
 * PHP version 7.4+
 *
 * @category TaskType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

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
    const ENROLL_SECUREBOOT = 25;
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
     * The icon list, memoised for the request.
     *
     * @var array|null name => codepoint, or null before the first read
     */
    private static $_faIcons = null;
    /**
     * Every icon name this install can actually render, and its codepoint.
     *
     * Read from the shipped Font Awesome stylesheet, which is the same file
     * the browser loads, so the picker cannot offer a name the page will not
     * draw. It used to be read from management/other/_variables.scss -- a
     * Font Awesome *4.7.0* variables file that no stylesheet imported and
     * nothing regenerated. The Font Awesome 7 migration did not touch it, so
     * the picker went on offering 786 v4 names of which 148 no longer exist,
     * including every one of the seven this branch had just repaired in the
     * database (schema steps 361-367). Picking one wrote the broken state
     * straight back. A second copy of the icon list is the whole defect;
     * deriving it from the stylesheet means the next version bump cannot
     * reintroduce it.
     *
     * BRANDS ARE EXCLUDED. FOG renders a stored name as `fas fa-<name>`, and
     * the brand icons live only in the Brands font, so `fas fa-github` draws
     * nothing. The stylesheet does not label an icon with its font, but it is
     * a concatenation and the brand declarations all follow the brands
     * font-family rule, so that rule's position is the boundary. Matched on
     * `Font Awesome <n> Brands` rather than a fixed 7 so a version bump does
     * not silently turn the filter off.
     *
     * Regular needs no such filter: in Font Awesome Free every regular icon
     * name also exists in solid.
     *
     * @return array name => codepoint, lowercase hex, sorted by name
     */
    private static function _faIcons()
    {
        if (null !== self::$_faIcons) {
            return self::$_faIcons;
        }
        self::$_faIcons = [];
        $path = BASEPATH . 'management/css/font-awesome.min.css';
        if (!is_readable($path)) {
            return self::$_faIcons;
        }
        $css = (string)file_get_contents($path);
        $boundary = preg_match(
            '/font-family:"Font Awesome \d+ Brands"/',
            $css,
            $brands,
            PREG_OFFSET_CAPTURE
        ) ? $brands[0][1] : strlen($css);
        // Each icon is `<selectors>{--fa:"\fXXX"}` and the selector list is
        // where the ALIASES are: `.fa-ban,.fa-cancel{--fa:"\f05e"}` declares
        // two usable names in one rule. Reading only the first class of each
        // rule drops every alias.
        preg_match_all(
            '/([^{}]+)\{--fa:\s*"\\\\([0-9a-f]+)"/i',
            $css,
            $rules,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($rules as $rule) {
            if ($rule[0][1] >= $boundary) {
                continue;
            }
            if (!preg_match_all(
                '/\.fa-([a-z0-9-]+)(?=[,{:\s]|$)/',
                $rule[1][0],
                $names
            )) {
                continue;
            }
            foreach ($names[1] as $name) {
                self::$_faIcons[$name] = strtolower($rule[2][0]);
            }
        }
        ksort(self::$_faIcons);

        return self::$_faIcons;
    }
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
        $icons = self::_faIcons();
        if (!count($icons)) {
            return _('No icons found');
        }
        ob_start();
        echo '<select class="form-control fa" id="icon" name="icon">';
        foreach ($icons as $name => $codepoint) {
            // The character itself, not an `&#x...` entity. Initiator::e()
            // html-escapes what it is given, and the old code passed it an
            // entity with no trailing semicolon -- which htmlspecialchars
            // cannot recognise as one even with double_encode off, so every
            // option rendered the literal text `&#xf02b` rather than a glyph.
            printf(
                '<option value="%s"%s>%s %s</option>',
                \Initiator::e($name),
                $selected == $name ? ' selected' : '',
                \Initiator::e(mb_chr(hexdec($codepoint), 'UTF-8')),
                \Initiator::e($name)
            );
        }

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
                    range(1, 25),
                    [
                        self::MEMTEST,
                        self::ALL_SNAPINS,
                        self::SINGLE_SNAPIN,
                        self::WAKE_UP,
                        self::ENROLL_SECUREBOOT
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
                    self::WAKE_UP,
                    self::ENROLL_SECUREBOOT
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
