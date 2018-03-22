<?php
/**
 * Manager class for pushbullet
 *
 * PHP Version 5
 *
 * @category PushbulletManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for pushbullet
 *
 * @category PushbulletManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PushbulletManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'pushbullet';
    /**
     * Perform the database and plugin installation
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $fields = [
            'pID',
            'pToken',
            'pName',
            'pEmail'
        ];
        $types = [
            'INTEGER',
            'VARCHAR(255)',
            'VARCHAR(255)',
            'VARCHAR(255)'
        ];
        $notnulls = [
            false,
            false,
            false,
            false
        ];
        $defaults = [
            false,
            false,
            false,
            false
        ];
        $keys = [
            'pID',
            'pToken'
        ];
        $sql = Schema::createTable(
            $this->tablename,
            true,
            $fields,
            $types,
            $notnulls,
            $defaults,
            $keys,
            'MyISAM',
            'utf8',
            'pID',
            'pID'
        );
        return self::$DB->query($sql);
    }
}
