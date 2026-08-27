<?php
/**
 * Presents server information when clicked.
 *
 * PHP version 7.4+
 *
 * @category ServerInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Base\FOGPage;
use FOG\Items\MACAddress;
use FOG\Items\StorageNode;

/**
 * Presents server information when clicked.
 *
 * @category ServerInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ServerInfo extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'hwinfo';
    /**
     * Initializes the server information.
     *
     * @param string $name The name this initializes with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Hardware Information');
        parent::__construct($this->name);
        global $id;
        $this->obj = new StorageNode($id);
    }
    /**
     * The index page.
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->title = _('Server Information');
        if (!$this->obj->isValid()) {
            echo '<div class="col-md-12">';
            echo '<div class="card card-warning card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="card-tools float-end">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="card-body">';
            echo _('Invalid Server Information!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $url = sprintf(
            '%s://%s/%s/status/hw.php',
            self::$httpproto,
            $this->obj->get('ip'),
            self::webrootPath($this->obj->get('webroot'))
        );
        if (!$this->obj->get('online')) {
            echo '<div class="col-md-12">';
            echo '<div class="card card-warning card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="card-tools float-end">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="card-body">';
            echo _('Server appears to be offline or unavailable!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $ret = self::$FOGURLRequests->process($url);
        if (!$ret) {
            echo '<div class="col-md-12">';
            echo '<div class="card card-warning card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="card-tools float-end">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="card-body">';
            echo _('Server appears to be offline or unavailable!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $ret = json_decode($ret[0]);
        if (!is_object($ret)) {
            // Node was reachable but returned non-JSON (e.g. an HTML error
            // page or partial body); json_decode yields null/scalar. Bail out
            // the same way as an offline node instead of reading properties off
            // a non-object, which spammed "Attempt to read property on null".
            echo '<div class="col-md-12">';
            echo '<div class="card card-warning card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="card-tools float-end">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="card-body">';
            echo _('Invalid Server Information!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $section = 0;
        foreach ((array)$ret->nic as $nicname => $values) {
            $nicparts = explode("$$", $values);
            if (count($nicparts) >= 5) {
                $NICTransSized[$nicname] = self::formatByteSize($nicparts[2]);
                $NICRecSized[$nicname] = self::formatByteSize($nicparts[1]);
                $NICErrInfo[$nicname] = $nicparts[3];
                $NICDropInfo[$nicname] = $nicparts[4];
                $NICTrans[$nicname] = sprintf('%s %s', $nicparts[0], _('TX'));
                $NICRec[$nicname] = sprintf('%s %s', $nicparts[0], _('RX'));
                $NICErr[$nicname] =    sprintf('%s %s', $nicparts[0], _('Errors'));
                $NICDro[$nicname] = sprintf('%s %s', $nicparts[0], _('Dropped'));
                // Older nodes (5 fields) report no MAC; resolve the vendor on
                // this (master) side, where the oui table is always present.
                $mac = isset($nicparts[5]) ? trim($nicparts[5]) : '';
                $NICMacInfo[$nicname] = $mac;
                $NICVendorInfo[$nicname] = (
                    $mac !== '' ? MACAddress::getVendor($mac) : ''
                );
                $NICMac[$nicname] = sprintf('%s %s', $nicparts[0], _('MAC'));
            }
        }
        $fields = [
            _('Storage Node') => $this->obj->get('name'),
            _('IP') => self::resolveHostname(
                $this->obj->get('ip')
            ),
            _('Kernel') => $ret->general->kernel,
            _('Hostname') => $ret->general->hostname,
            _('Uptime') => $ret->general->uptimeload,
            _('CPU Type') => $ret->general->cputype,
            _('CPU Count') => $ret->general->cpucount,
            _('CPU Model') => $ret->general->cpumodel,
            _('CPU Speed') => $ret->general->cpuspeed,
            _('CPU Cache') => $ret->general->cpucache,
            _('Total Memory') => $ret->general->totmem,
            _('Used Memory') => $ret->general->usedmem,
            _('Free Memory') => $ret->general->freemem
        ];
        $fogversion = $ret->general->fogversion;
        // Running FOG Version
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('FOG Version');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $fogversion;
        echo '</div>';
        echo '</div>';
        unset($fogversion);
        // General Info
        ob_start();
        foreach ($fields as $field => &$input) {
            echo '<div class="col-md-4 float-start">';
            echo $field;
            echo '</div>';
            echo '<div class="col-md-8 float-end">';
            echo $input;
            echo '</div>';
            unset($field, $input);
        }
        $rendered = ob_get_clean();
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('General Information');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $rendered
        );
        // File System Info
        $fields = [
            _('Total Disk Space') => $ret->filesys->totalspace,
            _('Used Disk Space') => $ret->filesys->usedspace,
            _('Free Disk Space') => $ret->filesys->freespace
        ];
        ob_start();
        foreach ($fields as $field => &$input) {
            echo '<div class="col-md-4 float-start">';
            echo $field;
            echo '</div>';
            echo '<div class="col-md-8 float-end">';
            echo $input;
            echo '</div>';
            unset($field, $input);
        }
        $rendered = ob_get_clean();
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('File System Information');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $rendered,
            $this->data
        );
        // Network Information.
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Network Information');
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<div id="accordion">';
        foreach ((array)$NICTrans as $nicname => $txtran) {
            unset(
                $fields,
                $this->data
            );
            $ethName = $nicname;
            $fields = [];
            $mac = isset($NICMacInfo[$nicname]) ? $NICMacInfo[$nicname] : '';
            if ($mac !== '') {
                $vendor = isset($NICVendorInfo[$nicname])
                    ? $NICVendorInfo[$nicname]
                    : '';
                $macDisplay = \Initiator::e($mac);
                if ($vendor !== '') {
                    // Mirror the OUI vendor icon used everywhere a MAC renders:
                    // an fa-info-circle whose tooltip carries the vendor name.
                    $macDisplay .= ' <i class="fas fa-circle-info text-muted '
                        . 'mac-vendor-icon" data-bs-toggle="tooltip" '
                        . 'data-bs-placement="right" data-container="body" title="'
                        . \Initiator::e($vendor)
                        . '"></i>';
                }
                $fields[$NICMac[$nicname]] = $macDisplay;
            }
            $fields += [
                $NICTrans[$nicname] => $NICTransSized[$nicname],
                $NICRec[$nicname] => $NICRecSized[$nicname],
                $NICErr[$nicname] => $NICErrInfo[$nicname],
                $NICDro[$nicname] => $NICDropInfo[$nicname]
            ];
            ob_start();
            foreach ($fields as $field => &$input) {
                echo '<div class="col-md-3 float-start">';
                echo $field;
                echo '</div>';
                echo '<div class="col-md-9 float-end">';
                echo $input;
                echo '</div>';
                unset($field, $input);
            }
            $rendered = ob_get_clean();
            echo '<div class="panel card card-primary card-outline">';
            echo '<div class="card-header">';
            echo '<h4 class="card-title">';
            echo '<a data-bs-toggle="collapse" data-bs-parent="#accordion" href="#'
                . $ethName
                . '">';
            echo $ethName;
            echo ' ';
            echo _('Information');
            echo '</a>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="'
                . $ethName
                . '" class="collapse">';
            echo '<div class="card-body">';
            echo $rendered;
            echo '</div>';
            echo '</div>';
            echo '</div>';
            unset($rendered);
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ServerInfo', 'ServerInfo');
