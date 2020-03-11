<?php
/**
 * Manager class for subnetgroup
 *
 * PHP Version 5
 *
 * @category SubnetgroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for subnetgroup
 *
 * @category SubnetgroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubnetgroupManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'subnetgroup';
    /**
     * Perform the database and plugin installation
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
                'sgID',
                'sgName',
                'sgGroupID',
                'sgSubnets'
            ),
            array(
                'INTEGER',
                'VARCHAR(255)',
                'INTEGER',
                'TEXT',
            ),
            array(
                false,
                false,
                false,
                false,
            ),
            array(
                false,
                false,
                false,
                false,
            ),
            array(
                'sgID'
            ),
            'MyISAM',
            'utf8',
            'sgID',
            'sgID'
        );
        return self::$DB->query($sql);
    }
}
