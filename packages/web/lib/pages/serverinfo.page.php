<?php
/**
 * Presents server information when clicked.
 *
 * PHP version 5
 *
 * @category ServerInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        $this->name = 'Hardware Information';
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
            echo '<div class="box box-warning">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="box-tools pull-right">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="box-body">';
            echo _('Invalid Server Information!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $url = sprintf(
            '%s://%s/fog/status/hw.php',
            self::$httpproto,
            $this->obj->get('ip')
        );
        if (!$this->obj->get('online')) {
            echo '<div class="col-md-12">';
            echo '<div class="box box-warning">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="box-tools pull-right">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="box-body">';
            echo _('Server appears to be offline or unavailable!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $ret = self::$FOGURLRequests->process($url);
        if (!$ret) {
            echo '<div class="col-md-12">';
            echo '<div class="box box-warning">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo $this->title;
            echo '</h4>';
            echo '<div class="box-tools pull-right">';
            echo self::$FOGCollapseBox;
            echo self::$FOGCloseBox;
            echo '</div>';
            echo '</div>';
            echo '<div class="box-body">';
            echo _('Server appears to be offline or unavailable!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $ret = json_decode($ret[0]);
        $section = 0;
        foreach ((array)$ret->nic as $nicname => $values) {
            $nicparts = explode("$$", $values);
            if (count($nicparts) == 5) {
                $NICTransSized[$nicname] = self::formatByteSize($nicparts[2]);
                $NICRecSized[$nicname] = self::formatByteSize($nicparts[1]);
                $NICErrInfo[$nicname] = $nicparts[3];
                $NICDropInfo[$nicname] = $nicparts[4];
                $NICTrans[$nicname] = sprintf('%s %s', $nicparts[0], _('TX'));
                $NICRec[$nicname] = sprintf('%s %s', $nicparts[0], _('RX'));
                $NICErr[$nicname] =    sprintf('%s %s', $nicparts[0], _('Errors'));
                $NICDro[$nicname] = sprintf('%s %s', $nicparts[0], _('Dropped'));
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
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('FOG Version');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $fogversion;
        echo '</div>';
        echo '</div>';
        unset($fogversion);
        // General Info
        ob_start();
        foreach ($fields as $field => &$input) {
            echo '<div class="col-md-4 pull-left">';
            echo $field;
            echo '</div>';
            echo '<div class="col-md-8 pull-right">';
            echo $input;
            echo '</div>';
            unset($field, $input);
        }
        $rendered = ob_get_clean();
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('General Information');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
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
            echo '<div class="col-md-4 pull-left">';
            echo $field;
            echo '</div>';
            echo '<div class="col-md-8 pull-right">';
            echo $input;
            echo '</div>';
            unset($field, $input);
        }
        $rendered = ob_get_clean();
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('File System Information');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $rendered,
            $this->data
        );
        // Network Information.
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Network Information');
        echo '</h4>';
        echo '<div class="box-tools pull-right">';
        echo self::$FOGCollapseBox;
        echo self::$FOGCloseBox;
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div class="box-group" id="accordion">';
        foreach ((array)$NICTrans as $nicname => $txtran) {
            unset(
                $fields,
                $this->data
            );
            $ethName = $nicname;
            $fields = [
                $NICTrans[$nicname] => $NICTransSized[$nicname],
                $NICRec[$nicname] => $NICRecSized[$nicname],
                $NICErr[$nicname] => $NICErrInfo[$nicname],
                $NICDro[$nicname] => $NICDropInfo[$nicname]
            ];
            ob_start();
            foreach ($fields as $field => &$input) {
                echo '<div class="col-md-3 pull-left">';
                echo $field;
                echo '</div>';
                echo '<div class="col-md-9 pull-right">';
                echo $input;
                echo '</div>';
                unset($field, $input);
            }
            $rendered = ob_get_clean();
            echo '<div class="panel box box-primary">';
            echo '<div class="box-header with-border">';
            echo '<h4 class="box-title">';
            echo '<a data-toggle="collapse" data-parent="#accordion" href="#'
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
                . '" class="panel-collapse collapse">';
            echo '<div class="box-body">';
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
