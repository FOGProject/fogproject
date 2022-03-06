<?php
/**
 * Group association manager class
 *
 * PHP version 5
 *
 * @category GroupAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Group association manager class
 *
 * @category GroupAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'groupMembers';
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
                'gmID',
                'gmHostID',
                'gmGroupID'
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
                'gmID',
                array(
                    'gmHostID',
                    'gmGroupID'
                )
            ),
            'InnoDB',
            'utf8',
            'gmID',
            'gmID'
        );
        return self::$DB->query($sql);
    }
}
