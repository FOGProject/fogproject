<?php
class FileIntegrityManagementPage extends FOGPage {
    public $node = 'fileintegrity';
    public function __construct($name = '') {
        $this->name = 'File Integrity Management';
        self::$foglang['ExportFileintegrity'] = _('Export Checksums');
        parent::__construct($this->name);
        $this->menu['list'] = sprintf(self::$foglang['ListAll'],_('Checksums'));
        unset($this->menu['add']);
        if ($_REQUEST['id']) {
            $this->subMenu = array(
                $this->delformat => self::$foglang['Delete'],
            );
            $this->notes = array(
                _('Name')=>$this->obj->get('name'),
                _('Icon')=>sprintf('<i class="fa fa-%s fa-fw fa-2x"></i>',$this->obj->get('icon')),
            );
        }
        $this->headerData = array(
            _('Checksum'),
            _('Last Updated Time'),
            _('Storage Node'),
            _('Conflicting path/file'),
        );
        $this->templates = array(
            '${checksum}',
            '${modtime}',
            '<a href="?node=storage&sub=edit&id=${storageNodeID}" title="Edit: ${storage_name}" id="node-${storage_name}">${storage_name}</a>',
            '${file_path}',
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array(),
        );
        self::$returnData = function(&$FileIntegrity) {
            if (!$FileIntegrity->isValid()) return;
            $FileIntegrity->load();
            $this->data[] = array(
                'checksum'=>$FileIntegrity->get('checksum'),
                'modtime'=>$FileIntegrity->get('modtime'),
                'storageNodeID'=>$FileIntegrity->get('storageNode')->get('id'),
                'storage_name'=>$FileIntegrity->get('storageNode')->get('name'),
                'file_path'=>$FileIntegrity->get('path'),
            );
            unset($FileIntegrity);
        };
    }
    public function index() {
        $this->title = _('All Recorded Integrities');
        $dataRet = self::getSetting('FOG_DATA_RETURNED');
        if ($dataRet > 0 && self::getClass($this->childClass)->getManager()->count() > $dataRet && $_REQUEST['sub'] != 'list') $this->redirect(sprintf('?node=%s&sub=search',$this->node));
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->find());
        self::$HookManager->processEvent('FILE_INTEGRITY_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
    public function search_post() {
        $this->data = array();
        array_map(self::$returnData,self::getClass($this->childClass)->getManager()->search('',true));
        self::$HookManager->processEvent('FILE_INTEGRITY_DATA',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
}
