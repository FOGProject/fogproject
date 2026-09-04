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
use FOG\Managers\PrinterAssociationManager;
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
        'uri' => 'pURI'
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
     * @return bool|object false when the row itself could not be written
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
        (new PrinterAssociationManager())
            ->update(
                [
                    'id' => $AllHostsPrinter,
                    'isDefault' => 0
                ]
            );
        (new PrinterAssociationManager())
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
     * The device URI this printer is reached at (design 0010 section 2).
     *
     * Both print subsystems already describe a printer this way: CUPS takes
     * a device URI directly, and a Windows Standard TCP/IP port is the same
     * information written differently. One URI therefore serves both
     * platforms, where `pConfig` could serve only one -- it named a code
     * path, and three of its four values throw on whichever platform the
     * machine happens to be running.
     *
     * DERIVED ON READ when nothing was explicitly set, rather than
     * backfilled once on upgrade. `pPort` is a longtext that has held
     * whatever an admin typed for a decade, so a derivation WILL be wrong
     * for some rows -- and a wrong answer written into a column has to be
     * found and corrected by hand on every install, where a wrong answer
     * computed here is fixed for everybody by fixing this method. An admin
     * who sets `pURI` overrides it and is never second-guessed.
     *
     * Empty when nothing can be derived, which is an honest answer: a Local
     * printer with no address recorded has no URI, and inventing one would
     * send the agent at a machine nobody named.
     *
     * @return string
     */
    public function uri()
    {
        $explicit = trim((string)$this->get('uri'));
        if ('' !== $explicit) {
            return $explicit;
        }
        $ip = trim((string)$this->get('ip'));
        $port = trim((string)$this->get('port'));
        switch (strtolower(trim((string)$this->get('config')))) {
            case 'local':
                // A TCP/IP port printer. 9100 is the RAW default and what every
                // port monitor uses when no port number was recorded.
                return '' === $ip ? '' : 'socket://' . $ip . ':9100';
            case 'network':
                // pPort holds a UNC path: \\server\share.
                $unc = str_replace('\\', '/', $port);
                $unc = ltrim($unc, '/');
                return '' === $unc ? '' : 'smb://' . $unc;
            case 'cups':
                // The CUPS branch pointed lpadmin at lpd://<ip>/<queue>, with
                // the printer's own alias as the queue name.
                $name = trim((string)$this->get('name'));
                return '' === $ip ? '' : 'lpd://' . $ip . '/' . $name;
            case 'iprint':
                // Novell/Micro Focus iPrint, driven by iprntcmd.exe and Windows
                // only. Given a scheme of its own rather than forced into one of
                // the others, so a provider that cannot handle it can say so
                // (design 0010 section 2).
                return '' === $port ? '' : 'iprint://' . $port;
            default:
                return '';
        }
    }
    /**
     * The driver to print with, or empty for driverless.
     *
     * Empty is a VALUE here, not a missing field: modern CUPS and Windows
     * both support IPP Everywhere, where the printer describes its own
     * capabilities and no driver file is involved. FOG's model assumed a
     * driver always exists, which is why this needs saying out loud.
     *
     * @return string
     */
    public function driver()
    {
        $model = trim((string)$this->get('model'));
        if ('' !== $model) {
            return $model;
        }
        return trim((string)$this->get('file'));
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
