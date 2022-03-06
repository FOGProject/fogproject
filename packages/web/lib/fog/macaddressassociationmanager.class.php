<?php
/**
 * MAC association manager mass management class.
 *
 * PHP version 5
 *
 * @category MACAddressAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * MAC association manager mass management class.
 *
 * @category MACAddressAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MACAddressAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'hostMAC';
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
                'hmID',
                'hmHostID',
                'hmMAC',
                'hmDesc',
                'hmPrimary',
                'hmPending',
                'hmIgnoreClient',
                'hmIgnoreImaging'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'VARCHAR(17)',
                'LONGTEXT',
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                "ENUM('0', '1')"
            ),
            array(
                false,
                false,
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
                '0',
                '0',
                '0'
            ),
            array(
                array(
                    'hmMAC',
                    'hmHostID'
                )
            ),
            'InnoDB',
            'utf8',
            'hmID',
            'hmID'
        );
    }
}
