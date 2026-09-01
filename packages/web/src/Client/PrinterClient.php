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
            // BOTH SPELLINGS, on purpose. ADR 0038 decision 9 turns "this
            // host has no printers" into an ordinary success carrying an
            // explicit flag instead of an error, and its shipping order is
            // to send `np` ALONGSIDE the new flag for a release before
            // dropping it.
            //
            // The two halves need different evidence, which is why they are
            // separated rather than done together:
            //
            //   - ADDING `noPrinters` needs none. The shipped client's
            //     printer contract (fog-client 0.13.0,
            //     Modules/DataContracts/PrinterManager.cs) declares Mode,
            //     Printers and AllPrinters and nothing else, yet this
            //     endpoint has always sent `default` too -- which that
            //     contract has no member for and which the separate
            //     DefaultPrinterManager module reads from its own endpoint.
            //     So an unknown top-level key is not merely assumed safe;
            //     the endpoint has been proving it every poll for years.
            //
            //   - REMOVING `np` needs the observation nobody has made yet
            //     (UNKNOWN-4). `np`, matched case-insensitively against
            //     ReturnCode, is the single string that triggers
            //     removal-on-empty in the client, so dropping it changes
            //     what an old client does with this response. It stays
            //     until a mode-`ar` host with steady printers has been
            //     watched through a poll cycle.
            //
            // A client can only USE the flag if it is present in both
            // branches -- an absent key would be indistinguishable from an
            // older server -- so the success return below carries it too.
            return [
                'error' => 'np',
                'noPrinters' => true,
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
            // Always present, never only on the empty branch: a client
            // testing `noPrinters` has to be able to tell "this server says
            // no" from "this server is too old to say", and a key that
            // appears only in one case cannot express that.
            'noPrinters' => false,
            'mode' => self::$_modes[$level],
            'allPrinters' => $allPrinters,
            'default' => $default,
            'printers' => $printers,
        ];
    }
}
