<?php
/**
 * Printer association manager mass management class.
 *
 * PHP version 5
 *
 * @category PrinterAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Printer association manager mass management class.
 *
 * @category PrinterAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'printerAssoc';
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
                'paID',
                'paHostID',
                'paPrinterID',
                'paIsDefault',
                'paAnon1',
                'paAnon2',
                'paAnon3',
                'paAnon4',
                'paAnon5'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'INTEGER',
                "ENUM('0', '1')",
                'VARCHAR(2)',
                'VARCHAR(2)',
                'VARCHAR(2)',
                'VARCHAR(2)',
                'VARCHAR(2)'
            ),
            array(
                false,
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
            ),
            array(
                'paID',
                array(
                    'paPrinterID',
                    'paHostID'
                )
            ),
            'MyISAM',
            'utf8',
            'paID',
            'paID'
        );
        return self::$DB->query($sql);
    }
}
