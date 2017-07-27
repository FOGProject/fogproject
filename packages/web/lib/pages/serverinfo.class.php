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
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->templates,
            $this->attributes
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Server information');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if (!$this->obj->isValid()) {
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
        $ret = self::$FOGURLRequests->process($url);
        $ret = trim($ret[0]);
        if (!$ret) {
            echo _('Unable to get server infromation!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
        }
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8'),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
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
        if (count($arGeneral) < 1) {
            echo _('Unable to find basic information!');
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return;
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
        $this->title = _('General Information');
        $fields = array(
            _('Storage Node') => $this->obj->get('name'),
            _('IP') => self::resolveHostname(
                $this->obj->get('ip')
            ),
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
            _('Free Memory') => $arGeneral[10]
        );
        array_walk($fields, $this->fieldsToData);
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $this->form,
            $this->data
        );
        $this->title = _('File System Information');
        $fields = array(
            _('Total Disk Space') => $arFS[0],
            _('Used Disk Space') => $arFS[1],
            _('Free Disk Space') => $arFS[2]
        );
        array_walk($fields, $this->fieldsToData);
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render(12);
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $this->data
        );
        $this->title = _('Network Information');
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        foreach ((array)$NICTrans as $index => &$txtran) {
            unset(
                $fields,
                $this->data
            );
            $ethName = explode(' ', $txtran);
            $fields = array(
                $NICTrans[$index] => $NICTransSized[$index],
                $NICRec[$index] => $NICRecSized[$index],
                $NICErr[$index] => $NICErrInfo[$index],
                $NICDro[$index] => $NICDropInfo[$index]
            );
            array_walk($fields, $this->fieldsToData);
            echo '<div class="panel panel-info">';
            echo '<div class="panel-heading text-center">';
            echo '<h4 class="title">';
            echo $ethName[0];
            echo ' ';
            echo _('Information');
            echo '</h4>';
            echo '</div>';
            echo '<div class="panel-body">';
            $this->render(12);
            echo '</div>';
            echo '</div>';
            unset($txtran);
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
            $NICDro,
            $this->data,
            $fields,
            $this->attributes,
            $this->templates
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
