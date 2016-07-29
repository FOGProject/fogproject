<?php
class FileIntegrity extends FOGController {
    protected $databaseTable = 'fileChecksums';
    protected $databaseFields = array(
        'id' => 'fcsID',
        'storageNodeID' => 'fcsStorageNodeID',
        'modtime' => 'fcsFileModTime',
        'checksum' => 'fcsFileChecksum',
        'path' => 'fcsFilePath',
        'status' => 'fcsStatus',
    );
    protected $databaseFieldsRequired = array(
        'storageNodeID',
        'path',
    );
    protected $additionalFields = array(
        'storageNode',
    );
    private static $imagePaths = array();
    private static $snapinFiles = array();
    public function getThisNode() {
        $this->set('storageNode',self::getClass('StorageNode'));
        self::getIPAddress();
        foreach ((array)self::getClass('StorageNodeManager')->find(array('isEnabled'=>1)) AS &$StorageNode) {
            if ($StorageNode->isValid() && in_array(self::$FOGCore->resolveHostname($StorageNode->get('ip')),self::$ips)) {
                $this->set('storageNode',$StorageNode->load());
                break;
            }
            unset($StorageNode);
        }
        if (!$this->get('storageNode')->isValid()) die(_('No node associated with any addresses of this system'));
    }
    private function getHash($item) {
        if (!is_dir($item)) return hash_file('sha512',$item);
        $files = array();
        $dir = dir($item);
        while (false !== ($file = $dir->read())) {
            if ($file == '.' || $file == '..') continue;
            $files[] = $this->getHash(sprintf('%s%s%s',$item,DIRECTORY_SEPARATOR,$file));
        }
        $dir->close();
        return hash('sha512',implode('',$files));
    }
    private function getModTime($item) {
        $stat = stat($item);
        return $this->formatTime($stat['mtime'],'Y-m-d H:i:s');
    }
    public function getImagePaths() {
        self::$imagePaths = array_map(function(&$path) {
            return sprintf('%s%s%s',$this->get('storageNode')->get('path'),DIRECTORY_SEPARATOR,$path);
        },self::getSubObjectIDs('Image',array('id'=>self::$this->get('storageNode')->get('images')),'path'));
    }
    public function getSnapinFiles() {
        self::$snapinFiles = $this->get('storageNode')->get('snapinfiles');
    }
    public function processPathFiles() {
        array_map(function(&$file) {
            self::getClass(self)
                ->set('storageNodeID',$this->get('storageNode')->get('id'))
                ->set('modtime',$this->getModTime($file))
                ->set('checksum',$this->getHash($file))
                ->set('path',$file)
                ->save();
        },array_merge((array)self::$imagePaths,(array)self::$snapinFiles));
    }
}
