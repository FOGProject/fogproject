<?php
/**
 * The service/global settings class.
 *
 * PHP version 5
 *
 * @category Service
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The service/global settings class.
 *
 * @category Service
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Service extends FOGController
{
    /**
     * The service table name.
     *
     * @var string
     */
    protected $databaseTable = 'globalSettings';
    /**
     * The service fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'settingID',
        'name' => 'settingKey',
        'description' => 'settingDesc',
        'value' => 'settingValue',
        'category' => 'settingCategory',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
    );
    /**
     * Adds a directory for directory cleaner.
     *
     * @param string $dir The directory to add.
     *
     * @return void
     */
    public function addDir($dir)
    {
        $dircount = self::getClass('DirCleanerManager')
            ->count(array('path' => $dir));
        if ($dircount > 0) {
            throw new Exception(self::$foglang['n/a']);
        }
        self::getClass('DirCleaner')
            ->set('path', $dir)
            ->save();
    }
    /**
     * Removes a directory for directory cleaner.
     *
     * @param int $dir The directory to remove.
     *
     * @return void
     */
    public function remDir($dir)
    {
        self::getClass('DirCleanerManager')
            ->destroy(array('id' => $dir));
    }
    /**
     * Set the display settings.
     *
     * @param int $x The width of the screen.
     * @param int $y The height of the screen.
     * @param int $r The refresh rate.
     *
     * @return void
     */
    public function setDisplay(
        $x,
        $y,
        $r
    ) {
        $keySettings = array(
            'FOG_CLIENT_DISPLAYMANAGER_X' => $x,
            'FOG_CLIENT_DISPLAYMANAGER_Y' => $y,
            'FOG_CLIENT_DISPLAYMANAGER_R' => $r,
        );
        foreach ($keySettings as $name => &$value) {
            self::setSetting($name, $value);
            unset($value);
        }
    }
    /**
     * Sets the green fog.
     *
     * @param int    $h The hour to run 0 - 23
     * @param int    $m The minute to run 0-59
     * @param string $t The type shutdown/reboot.
     *
     * @return void
     */
    public function setGreenFog($h, $m, $t)
    {
        $gfcount = self::getClass('GreenFogManager')
            ->count(
                array(
                    'hour' => $h,
                    'min' => $m
                )
            );
        if ($gfcount > 0) {
            throw new Exception(self::$foglang['TimeExists']);
        } else {
            self::getClass('GreenFog')
                ->set('hour', $h)
                ->set('min', $m)
                ->set('action', $t)
                ->save();
        }
    }
    /**
     * Removes green fog.
     *
     * @param int $gf The green fog to remove
     *
     * @return void
     */
    public function remGF($gf)
    {
        self::getClass('GreenFogManager')
            ->destroy(
                array(
                    'id' => $gf
                )
            );
    }
    /**
     * Add a user to prevent cleanup.
     *
     * @param string $user The user to add.
     *
     * @return object
     */
    public function addUser($user)
    {
        $usercount = self::getClass('UserCleanupManager')
            ->count(
                array(
                    'name' => $user
                )
            );
        if ($usercount > 0) {
            throw new Exception(self::$foglang['UserExists']);
        }
        foreach ((array)$user as &$name) {
            self::getClass('UserCleanup')
                ->set('name', $name)
                ->load('name')
                ->save();
            unset($name);
        }
        return $this;
    }
    /**
     * Remove a user.
     *
     * @param int $id The user cleanup id to remove.
     *
     * @return void
     */
    public function remUser($id)
    {
        self::getClass('UserCleanupManager')->destroy(
            array('id' => $id)
        );
    }
    /**
     * Builds the exit type selectors for us.
     *
     * @param string $name      What to call the form selector (name=)
     * @param string $selected  Which is the selected item.
     * @param bool   $nullField Is there going to be a null starter.
     * @param string $id        ID name to give.
     *
     * @return string
     */
    public static function buildExitSelector(
        $name = '',
        $selected = '',
        $nullField = false,
        $id = ''
    ) {
        if (empty($name)) {
            $name = $this->get('name');
        }
        $types = array(
            'sanboot',
            'grub',
            'grub_first_hdd',
            'grub_first_cdrom',
            'grub_first_found_windows',
            'refind_efi',
            'exit',
        );
        if ($nullField) {
            array_unshift(
                $types,
                sprintf(
                    ' - %s -',
                    _('Please Select an option')
                )
            );
        }
        $options = sprintf(
            '<select name="%s" autocomplete="off" class="form-control"%s>',
            $name,
            (
                $id ? ' id="'
                . $id
                . '"' :
                ''
            )
        );
        foreach ($types as $i => &$viewop) {
            $show = strtoupper($viewop);
            $value = $viewop;
            if ($nullField
                && $i == 0
            ) {
                $show = $viewop;
                $value = '';
            }
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $value,
                (
                    strtolower($selected) == $value ?
                    ' selected' :
                    ''
                ),
                $show
            );
            unset($viewop);
        }
        unset($viewop);
        return $options.'</select>';
    }
}
