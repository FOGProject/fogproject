<?php
/**
 * Windows Key manager mass management class
 *
 * PHP version 5
 *
 * @category WindowsKeyManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Windows Key manager mass management class
 *
 * @category WindowsKeyManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'windowsKeys';
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
                'wkID',
                'wkName',
                'wkDesc',
                'wkCreatedBy',
                'wkCreatedTime',
                'wkKey'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(200)'
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
                'wkKey',
                'wkName'
            ],
            'MyISAM',
            'utf8',
            'wkID',
            'wkID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return self::getClass('WindowsKeyAssociationManager')
            ->install();
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        self::getClass('WindowsKeyAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
