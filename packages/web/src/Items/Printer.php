<?php
/**
 * The printer class
 *
 * PHP version 7.4+
 *
 * @category Printer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Router\Route;

/**
 * The printer class
 *
 * @category Printer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Printer extends FOGController
{
    /**
     * The printer table
     *
     * @var string
     */
    protected $databaseTable = 'printers';
    /**
     * The Printer fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'pID',
        'name' => 'pAlias',
        'description' => 'pDesc',
        'port' => 'pPort',
        'file' => 'pDefFile',
        'model' => 'pModel',
        'config' => 'pConfig',
        'configFile' => 'pConfigFile',
        'ip' => 'pIP',
        'pAnon2' => 'pAnon2',
        'pAnon3' => 'pAnon3',
        'pAnon4' => 'pAnon4',
        'pAnon5' => 'pAnon5'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * The additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts'
    ];
    /**
     * Stores/updates the printer
     *
     * @return object
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        return $this
            ->assocSetter('Printer', 'host')
            ->load();
    }
    /**
     * Adds the host to the printer.
     *
     * @param array $addArray the hosts to add.
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'hosts',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes hosts from the printer.
     *
     * @param array $removeArray the hosts to remove.
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'hosts',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Loads the hosts assigned
     *
     * @return void
     */
    protected function loadHosts()
    {
        $this->_loadHostIds(
            'printerassociation',
            ['printerID' => $this->get('id')],
            'hostID'
        );
    }
    /**
     * Update the default printer for the host.
     *
     * @param int  $hostid the host id to update for.
     * @param bool $onoff  if the printer is on or off.
     *
     * @return object
     */
    public function updateDefault($hostid, $onoff)
    {
        $find = ['printerID' => $this->get('id')];
        $AllHostsPrinter = Route::getIds(
            'printerassociation',
            $find
        );
        self::getClass('PrinterAssociationManager')
            ->update(
                [
                    'id' => $AllHostsPrinter,
                    'isDefault' => 0
                ]
            );
        self::getClass('PrinterAssociationManager')
            ->update(
                [
                    'hostID' => $onoff,
                    'printerID' => $this->get('id')
                ],
                '',
                ['isDefault' => 1]
            );
        return $this;
    }
    /**
     * Returns if the printer is valid
     *
     * @return bool
     */
    public function isValid()
    {
        $validTypes = [
            'iprint',
            'network',
            'local',
            'cups'
        ];
        $curtype = $this->get('config');
        $curtype = trim($this->get('config'));
        $curtype = strtolower($curtype);
        if (!in_array($curtype, $validTypes)) {
            return false;
        }
        return parent::isValid();
    }
    /**
     * Builds the printer type selector
     *
     * @return string
     */
    public static function buildPrinterTypeSelector()
    {
        $printerTypes = [
            'Local' => _('TCP/IP Port Printer'),
            'iPrint' => _('iPrint Printer'),
            'Network' => _('Network Printer'),
            'Cups' => _('CUPS Printer'),
        ];
        ob_start();
        foreach ((array)$printerTypes as $short => &$long) {
            printf(
                '<option value="%s"%s>%s</option>',
                \Initiator::e($short),
                (
                    filter_input(INPUT_POST, 'printertype') === $short ?
                    ' selected' :
                    ''
                ),
                \Initiator::e($long)
            );
            unset($short, $long);
        }
        $optionPrinter = '<select class="form-control" name="printertype" '
            . 'id="printertype">'
            . '<option value="">- '
            . self::$foglang['PleaseSelect']
            . ' -</option>'
            . ob_get_clean()
            . '</select>';
        self::$HookManager->processEvent(
            'PRINTER_TYPE_SELECTOR',
            ['optionPrinter' => &$optionPrinter]
        );
        return $optionPrinter;
    }
    /**
     * Destroy this particular object.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        // Funnel cleanup through the single cascade authority (the printer case in
        // Route::deletemass removes printerassociation rows and fires
        // DELETEMASS_API for plugins). deletemass also deletes the printer row; the
        // trailing parent::destroy() is a harmless no-op preserving the history.
        Route::deletemass('printer', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}
