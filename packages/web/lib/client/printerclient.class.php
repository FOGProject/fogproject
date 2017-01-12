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
class PrinterClient extends FOGClient implements FOGClientSend
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
    private static $_modes = array(
        0,
        'a',
        'ar'
    );
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        $level = $this->Host->get('printerLevel');
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
        $printerIDs = $this->Host->get('printers');
        $printerCount = count($printerIDs);
        if ($printerCount < 1) {
            $data = array(
                'error' => 'np',
                'mode' => self::$_modes[$level],
                'allPrinters' => $allPrinters,
                'default' => '',
                'printers' => array(),
            );
            return $data;
        }
        $defaultID = self::getSubObjectIDs(
            'PrinterAssociation',
            array(
                'hostID' => $this->Host->get('id'),
                'isDefault' => 1,
            ),
            'printerID'
        );
        $defaultName = self::getSubObjectIDs(
            'Printer',
            array('id' => $defaultID),
            'name'
        );
        if (count($defaultName) < 1) {
            $default = '';
        } else {
            $default = array_shift($defaultName);
        }
        $printers = array();
        foreach ((array)self::getClass('PrinterManager')
            ->find(array('id' => $printerIDs)) as &$Printer
        ) {
            $printers[] = array(
                'type' => $Printer->get('config'),
                'port' => $Printer->get('port'),
                'file' => $Printer->get('file'),
                'model' => $Printer->get('model'),
                'name' => $Printer->get('name'),
                'ip' => $Printer->get('ip'),
                'configFile' => $Printer->get('configFile'),
            );
            unset($Printer);
        }
        $data = array(
            'mode' => self::$_modes[$level],
            'allPrinters' => $allPrinters,
            'default' => $default,
            'printers' => $printers,
        );
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
            $Printer->get('port'),
            $Printer->get('file'),
            $Printer->get('model'),
            $Printer->get('name'),
            $Printer->get('ip'),
            $this->Host->getDefault($Printer->get('id')),
            $Printer->get('configFile')
        );
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $level = $this->Host->get('printerLevel');
        if ($level === 0 || empty($level)) {
            $level = 0;
        }
        if (!in_array($level, array_keys(self::$_modes))) {
            $level = 0;
        }
        $printerIDs = $this->Host->get('printers');
        $printerCount = count($printerIDs);
        if ($printerCount < 1) {
            throw new Exception(
                sprintf(
                    "%s\n",
                    base64_encode("#!mg=$level")
                )
            );
        }
        $this->send = base64_encode(sprintf("#!mg=%s\n", self::$_modes[$level]));
        $this->send .= "\n";
        $strtosend = "%s|%s|%s|%s|%s|%s";
        foreach ((array)self::getClass('PrinterManager')
            ->find(array('id' => $printerIDs)) as &$Printer
        ) {
            $printerStr = $this->_getString($strtosend, $Printer);
            $printerStrEncoded = base64_encode($printerStr);
            $this->send .= sprintf(
                "%s\n",
                $printerStrEncoded
            );
            unset($Printer);
        }
    }
}
