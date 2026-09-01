<?php
/**
 * Sends the printer information for the FOG Client
 *
 * PHP version 7.4+
 *
 * @category PrinterClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

use FOG\Assign\Resolver;
use FOG\Router\Route;

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
        // ADR 0038 decision 5: printers resolve LIVE, on each request. There
        // is no task to hang a snapshot on -- fog-client reconciles desired
        // state on a schedule and a removal has to reach the machine -- so
        // the list is computed every time rather than written down anywhere.
        //
        // The host's own printers plus whatever its groups GRANT, with a
        // group grant never displacing a printer the host holds directly and
        // a host-direct default always beating a group's.
        $hostID = (int)self::$Host->get('id');
        $resolved = Resolver::resolvePrinters([$hostID])[$hostID]
            ?? ['printers' => [], 'default' => null];
        $printerIDs = $resolved['printers'];
        $printerCount = count($printerIDs ?: []);
        if ($printerCount < 1) {
            // STILL SPELLED `np`, and that is deliberate rather than
            // leftover. ADR 0038 decision 9 makes this an ordinary success
            // carrying an explicit flag instead of an error, and traces
            // through fog-client to show it is safe -- but that trace has
            // not been WATCHED happen on a mode-`ar` host with steady
            // printers (UNKNOWN-4), and `np` is the single string that
            // triggers removal-on-empty in the client. Changing where the
            // list comes from and what the empty case means to the client
            // in one step would leave nothing to bisect if a fleet came
            // back with no printers. So this release changes only the
            // source of the list; the wire format moves separately.
            return [
                'error' => 'np',
                'mode' => self::$_modes[$level],
                'allPrinters' => $allPrinters,
                'default' => '',
                'printers' => [],
            ];
        }
        $default = '';
        if (null !== $resolved['default']) {
            $defaultName = Route::getIds(
                'printer',
                ['id' => [$resolved['default']]],
                'name'
            );
            if (count($defaultName ?: []) === 1) {
                $default = array_shift($defaultName);
            }
        }
        // Indexed by id, then emitted in RESOLVED order. The old loop walked
        // the printer catalog and kept the ones the host had, so the payload
        // came out in catalog order and the precedence the resolver just
        // worked out was thrown away on the wire. Nothing in the client
        // depends on the order, but a list whose order contradicts the rule
        // that produced it is a thing somebody eventually has to explain.
        $Printers = Route::getList('printer');
        $byID = [];
        foreach ($Printers as $Printer) {
            $byID[(int)$Printer->id] = $Printer;
        }
        unset($Printers);
        $printers = [];
        foreach ($printerIDs as $printerID) {
            $Printer = $byID[(int)$printerID] ?? null;
            if (null === $Printer) {
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
        return [
            'mode' => self::$_modes[$level],
            'allPrinters' => $allPrinters,
            'default' => $default,
            'printers' => $printers,
        ];
    }
}
