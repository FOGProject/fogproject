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
        if (empty($level)) {
            $level = 0;
        }
        if (!in_array($level, array_keys(self::$_modes))) {
            $level = 0;
        }
        $allPrinters = Route::getIds(
            'printer',
            [],
            'name'
        );
        @natcasesort($allPrinters);
        $printerIDs = self::$Host->get('printers');
        $printerCount = count($printerIDs ?: []);
        if ($printerCount < 1) {
            return [
                'error' => 'np',
                'mode' => self::$_modes[$level],
                'allPrinters' => $allPrinters,
                'default' => '',
                'printers' => [],
            ];
        }
        $find = [
            'hostID' => self::$Host->get('id'),
            'isDefault' => 1
        ];
        $defaultID = Route::getIds(
            'printerassociation',
            $find,
            'printerID'
        );
        $find = ['id' => $defaultID];
        $defaultName = Route::getIds(
            'printer',
            $find,
            'name'
        );
        if (count($defaultName ?: []) != 1) {
            $default = '';
        } else {
            $default = array_shift($defaultName);
        }
        Route::listem('printer');
        $Printers = json_decode(
            Route::getData()
        );
        $printers = [];
        foreach ((array)$Printers->data as $Printer) {
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
        return [
            'mode' => self::$_modes[$level],
            'allPrinters' => $allPrinters,
            'default' => $default,
            'printers' => $printers,
        ];
    }
}
