<?php
/**
 * The printer association class.
 *
 * PHP version 5
 *
 * @category PrinterAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * The printer association class.
 *
 * @category PrinterAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterAssociation extends FOGController
{
    /**
     * The printer assoc table.
     *
     * @var string
     */
    protected $databaseTable = 'printerAssoc';
    /**
     * The printer assoc fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'paID',
        'hostID' => 'paHostID',
        'printerID' => 'paPrinterID',
        'isDefault' => 'paIsDefault',
        'anon1' => 'paAnon1',
        'anon2' => 'paAnon2',
        'anon3' => 'paAnon3',
        'anon4' => 'paAnon4',
        'anon5' => 'paAnon5'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'printerID'
    ];
    /**
     * Return the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * Return the printer object.
     *
     * @return object
     */
    public function getPrinter()
    {
        return new Printer($this->get('printerID'));
    }
    /**
     * Returns if the printer is default or not.
     *
     * @return bool
     */
    public function isDefault()
    {
        return (bool)($this->get('isDefault') === 1);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\PrinterAssociation', 'PrinterAssociation');
