<?php
/**
 * OU manager mass management class
 *
 * PHP version 5
 *
 * @category OUManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * OU manager mass management class
 *
 * @category OUManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OUManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'ou';
    /**
     * Install our database
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
                'ouID',
                'ouName',
                'ouDesc',
                'ouCreatedBy',
                'ouCreatedTime',
                'ouDN'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(255)'
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                false
            ],
            [
                'ouID',
                'ouName'
            ],
            'InnoDB',
            'utf8',
            'ouID',
            'ouID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return self::getClass('OUAssociationManager')
            ->install();
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        $res = true;
        self::getClass('OUAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
