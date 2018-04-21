<?php
/**
 * The printer class
 *
 * PHP version 5
 *
 * @category Printer
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
     * Removes the printer.
     *
     * @param string $key The key to match for removing.
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        self::getClass('PrinterAssociationManager')
            ->destroy(['printerID'=>$this->get('id')]);
        return parent::destroy($key);
    }
    /**
     * Stores/updates the printer
     *
     * @return object
     */
    public function save()
    {
        parent::save();
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
        $find = ['printerID' => $this->get('id')];
        Route::ids(
            'printerassociation',
            $find,
            'hostID'
        );
        $hosts = json_decode(Route::getData(), true);
        $this->set('hosts', (array)$hosts);
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
        Route::ids(
            'printerassociation',
            $find
        );
        $AllHostsPrinter = json_decode(Route::getData(), true);
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
                $short,
                (
                    filter_input(INPUT_POST, 'printertype') === $short ?
                    ' selected' :
                    ''
                ),
                $long
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
}
