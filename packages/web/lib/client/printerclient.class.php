<?php
/**
 * Sends the printer information for the FOG Client
 *
 * PHP version 5
 *
 * @category PrinterClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sends the printer information for the FOG Client
 *
 * @category PrinterClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterClient extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'printermanager';
    /**
     * The available modes
     * 0 = no management
     * a = FOG Managed only
     * ar = FOG Handles all printers
     *
     * @var array
     */
    private static $_modes = [
        0,
        'a',
        'ar'
    ];
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        $level = self::$Host->get('printerLevel');
        if ($level === 0 || empty($level)) {
            $level = 0;
        }
        if (!in_array($level, array_keys(self::$_modes))) {
            $level = 0;
        }
        $allPrinters = self::getSubObjectIDs(
            'Printer',
            '',
            'name'
        );
        $printerIDs = self::$Host->get('printers');
        $printerCount = count($printerIDs);
        if ($printerCount < 1) {
            $data = [
                'error' => 'np',
                'mode' => self::$_modes[$level],
                'allPrinters' => $allPrinters,
                'default' => '',
                'printers' => [],
            ];
            return $data;
        }
        $defaultID = self::getSubObjectIDs(
            'PrinterAssociation',
            [
                'hostID' => self::$Host->get('id'),
                'isDefault' => 1,
            ],
            'printerID'
        );
        $defaultName = self::getSubObjectIDs(
            'Printer',
            ['id' => $defaultID],
            'name'
        );
        if (count($defaultName) < 1) {
            $default = '';
        } else {
            $default = array_shift($defaultName);
        }
        Route::listem('printer');
        $Printers = json_decode(
            Route::getData()
        );
        $Printers = $Printers->data;
        foreach ((array)$Printers as &$Printer) {
            if (!in_array($Printer->id, $printerIDs)) {
                continue;
            }
            $printers[] = [
                'type' => $Printer->config,
                'port' => $Printer->port,
                'file' => $Printer->file,
                'model' => $Printer->model,
                'name' => $Printer->name,
                'ip' => $Printer->ip,
                'configFile' => $Printer->configFile,
            ];
            unset($Printer);
        }
        unset($Printers);
        $data = [
            'mode' => self::$_modes[$level],
            'allPrinters' => $allPrinters,
            'default' => $default,
            'printers' => $printers,
        ];
        return $data;
    }
    /**
     * Sets the string for us
     *
     * @param string $stringsend the string to return
     * @param object $Printer    the printer information
     *
     * @return string
     */
    private function _getString($stringsend, &$Printer)
    {
        return sprintf(
            $stringsend,
            $Printer->port,
            $Printer->file,
            $Printer->model,
            $Printer->name,
            $Printer->ip,
            self::$Host->getDefault($Printer->id),
            $Printer->configFile
        );
    }
}
