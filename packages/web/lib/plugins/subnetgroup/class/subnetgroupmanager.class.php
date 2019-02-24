<?php
/**
 * SubnetGroup plugin
 *
 * PHP version 5
 *
 * @category SubnetGroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SubnetGroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetGroupManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'subnetgroup';
    /**
     * Installs the database for the plugin.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'sgID',
                'sgName',
                'sgGroupID',
                'sgSubnets'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'INTEGER',
                'TEXT'
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                'sgID'
            ],
            'MyISAM',
            'utf8',
            'sgID',
            'sgID'
        );
        return self::$DB->query($sql);
    }
}
