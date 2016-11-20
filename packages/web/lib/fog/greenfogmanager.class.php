<?php
/**
 * Green fog manager class.
 *
 * PHP version 5
 *
 * @category GreenFogManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Green fog manager class.
 *
 * @category GreenFogManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GreenFogManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'greenFog';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'gfID',
                'gfHostID',
                'gfHour',
                'gfMin',
                'gfAction',
                'gfDays'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'VARCHAR(2)',
                'VARCHAR(25)'
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                'gfID',
                array(
                    'gfHour',
                    'gfMin',
                    'gfAction'
                ),
            ),
            'MyISAM',
            'utf8',
            'gfID',
            'gfID'
        );
        return self::$DB->query($sql);
    }
}
