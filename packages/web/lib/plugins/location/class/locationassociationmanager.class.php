<?php
/**
 * Location association manager class.
 *
 * PHP version 7.4+
 *
 * @category LocationAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location association manager class.
 *
 * @category LocationAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'locationAssoc';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = $this->createTableSql(
            $this->tablename,
            true,
            array(
                'laID',
                'laLocationID',
                'laHostID'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'INTEGER'
            ),
            array(
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false
            ),
            array(
                'laID',
                'laHostID',
            ),
            'InnoDB',
            'utf8',
            'laID',
            'laID'
        );
        return self::$DB->query($sql);
    }
}
