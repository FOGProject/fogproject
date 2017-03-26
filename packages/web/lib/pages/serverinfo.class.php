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
        $this->menu = array(
            "?node=storage&sub=edit&id={$id}" => _('Edit Node')
        );
        $this->notes = array(
            sprintf(
                '%s %s',
                self::$foglang['Storage'],
                self::$foglang['Node']
            ) => $this->obj->get('name'),
                _('Hostname / IP') => $this->obj->get('ip'),
                self::$foglang['ImagePath'] => $this->obj->get('path'),
                self::$foglang['FTPPath'] => $this->obj->get('ftppath')
            );
    }
    /**
     * The index page.
     *
     * @return void
     */
    public function index()
    {
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        if (!$this->obj->isValid()) {
            printf(
                '<p>%s</p>',
                _('Invalid Server Information!')
            );
            return;
        }
        $url = sprintf(
            'http://%s/fog/status/hw.php',
            $this->obj->get('ip')
        );
        $ret = self::$FOGURLRequests->process($url);
        $ret = trim($ret[0]);
        if (empty($ret) || !$ret) {
            printf(
                '<p>%s</p>',
                _('Unable to pull server information!')
            );
            return;
        }
        $section = 0;
        $arGeneral = array();
        $arFS = array();
        $arNIC = array();
        $lines = explode("\n", $ret);
        foreach ((array)$lines as &$line) {
            $line = trim($line);
            switch ($line) {
            case '@@start':
            case '@@end':
                break;
            case '@@general':
                $section = 0;
                break;
            case '@@fs':
                $section = 1;
                break;
            case '@@nic':
                $section = 2;
                break;
            default:
                switch ($section) {
                case 0:
                    $arGeneral[] = $line;
                    break;
                case 1:
                    $arFS[] = $line;
                    break;
                case 2:
                    $arNIC[] = $line;
                    break;
                }
                break;
            }
            unset($line);
        }
        foreach ((array)$arNIC as &$nic) {
            $nicparts = explode("$$", $nic);
            if (count($nicparts) == 5) {
                $NICTransSized[] = self::formatByteSize($nicparts[2]);
                $NICRecSized[] = self::formatByteSize($nicparts[1]);
                $NICErrInfo[] = $nicparts[3];
                $NICDropInfo[] = $nicparts[4];
                $NICTrans[] = sprintf('%s %s', $nicparts[0], _('TX'));
                $NICRec[] = sprintf('%s %s', $nicparts[0], _('RX'));
                $NICErr[] =    sprintf('%s %s', $nicparts[0], _('Errors'));
                $NICDro[] = sprintf('%s %s', $nicparts[0], _('Dropped'));
            }
            unset($nic);
        }
        if (count($arGeneral) < 1) {
            printf(_('Unable to find basic information'));
            return;
        }
        $fields = array(
            sprintf('<b>%s</b>', _('General Information')) => '&nbsp;',
            _('Storage Node') => $this->obj->get('name'),
            _('IP') => self::resolveHostname($this->obj->get('ip')),
            _('Kernel') => $arGeneral[0],
            _('Hostname') => $arGeneral[1],
            _('Uptime') => $arGeneral[2],
            _('CPU Type') => $arGeneral[3],
            _('CPU Count') => $arGeneral[4],
            _('CPU Model') => $arGeneral[5],
            _('CPU Speed') => $arGeneral[6],
            _('CPU Cache') => $arGeneral[7],
            _('Total Memory') => $arGeneral[8],
            _('Used Memory') => $arGeneral[9],
            _('Free Memory') => $arGeneral[10],
            sprintf('<b>%s</b>', _('File System Information')) => '&nbsp;',
            _('Total Disk Space') => $arFS[0],
            _('Used Disk Space') => $arFS[1],
            _('Free Disk Space') => $arFS[2],
            sprintf('<b>%s</b>', _('Network Information')) => '&nbsp;',
        );
        foreach ((array)$NICTrans as $index => &$txtran) {
            $ethName = explode(' ', $txtran);
            $fields[
                sprintf(
                    '<b>%s %s</b>',
                    $ethName[0],
                    _('Information')
                )
            ] = '&nbsp;';
            $fields[$NICTrans[$index]] = $NICTransSized[$index];
            $fields[$NICRec[$index]] = $NICRecSized[$index];
            $fields[$NICErr[$index]] = $NICErrInfo[$index];
            $fields[$NICDro[$index]] = $NICDropInfo[$index];
            unset($txtran, $index);
        }
        unset(
            $arGeneral,
            $arNIC,
            $arFS,
            $NICTransSized,
            $NICRecSized,
            $NICErrInfo,
            $NICDropInfo,
            $NICTrans,
            $NICRec,
            $NICErr,
            $NICDro
        );
        $this->data = array();
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'SERVER_INFO_DISP',
                array(
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        $this->render();
    }
}
