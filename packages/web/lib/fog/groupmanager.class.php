<?php
/**
 * Group manager mass management class
 *
 * PHP version 5
 *
 * @category GroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Group manager mass management class
 *
 * @category GroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupManager extends FOGManagerController
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
            [
                'gmID',
                'gmHostID',
                'gmGroupID'
            ],
            [
                'INTEGER',
                'INTEGER',
                'INTEGER'
            ],
            [
                false,
                false,
                false
            ],
            [
                false,
                false,
                false
            ],
            [
                'gmID',
                [
                    'gmHostID',
                    'gmGroupID'
                ]
            ],
            'InnoDB',
            'utf8',
            'gmID',
            'gmID'
        );
        return self::$DB->query($sql);
    }
}
