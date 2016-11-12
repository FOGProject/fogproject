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
    protected $databaseFields = array(
        'id' => 'ttID',
        'name' => 'ttName',
        'description' => 'ttDescription',
        'icon' => 'ttIcon',
        'kernel' => 'ttKernel',
        'kernelArgs' => 'ttKernelArgs',
        'type' => 'ttType',
        'isAdvanced' => 'ttIsAdvanced',
        'access' => 'ttIsAccess',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'icon',
    );
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
            '../management/scss/_variables.scss',
            'rb'
        );
        if (!$fh) {
            return _('Icon File not found');
        }
        while (($line = fgets($fh)) !== false) {
            if (!preg_match('#^\$fa-var-#', $line)) {
                continue;
            }
            $match = preg_split(
                '#[:\s|:^\s]+#',
                trim(
                    preg_replace(
                        '#[\$\"\;\\\]|fa-var-#',
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
        echo '<select name="icon" class="fa">';
        foreach ((array) $icons as $name => &$unicode) {
            printf(
                '<option value="%s"%s> %s</option>',
                $name,
                $selected == $name ? ' selected' : '',
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
     * @return bool
     */
    public function isCapture()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return
            $this->isValid()
            && (
                in_array($this->get($id), array(2, 16))
                || preg_match(
                    '#type=(2|16|up)#i',
                    $this->get('kernelArgs')
                )
            )
            ;
    }
    /**
     * Returns if the task needs the inits.
     *
     * @return bool
     */
    public function isInitNeededTasking()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return (
            $this->isValid()
            && !in_array($this->get($id), array(4, 12, 13, 14))
        );
    }
    /**
     * Returns if this is snapin only tasking.
     *
     * @return bool
     */
    public function isSnapinTasking()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return (
            $this->isValid()
            && in_array($this->get($id), array(12, 13))
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

        return
            $this->isValid()
            && (
                (
                    $this->isDeploy()
                    && $this->get($id) != 17
                )
                || in_array($this->get($id), array(12, 13))
            )
            ;
    }
    /**
     * Returns if this is a deploy tasking.
     *
     * @return bool
     */
    public function isDeploy()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return
            $this->isValid()
            && (
                in_array($this->get($id), array(1, 8, 15, 17, 24))
                || preg_match(
                    '#type=(1|8|15|17|24|down)#i',
                    $this->get('kernelArgs')
                )
            )
            ;
    }
    /**
     * Returns if this is a multicast tasking.
     *
     * @return bool
     */
    public function isMulticast()
    {
        $id = (
            $this instanceof Task ?
            'typeID' :
            'id'
        );

        return
            $this->isValid()
            && (
                $this->get($id) == 8
                || preg_match(
                    '#(type=8|mc=yes)#i',
                    $this->get('kernelArgs')
                )
            )
            ;
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
                in_array($this->get($id), array(15, 16))
                || preg_match('#mode=debug#i', $this->get('kernelArgs'))
                || preg_match('#mode=onlydebug#i', $this->get('kernelArgs'))
            )
            ;
    }
}
