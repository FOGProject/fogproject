<?php
/**
 * Fileintegrity class handling file integrity.
 *
 * PHP version 5
 *
 * @category FileIntegrity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Fileintegrity class handling file integrity.
 *
 * @category FileIntegrity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileIntegrity extends FOGController
{
    /**
     * The database table.
     *
     * @var string
     */
    protected $databaseTable = 'fileChecksums';
    /**
     * The database fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'fcsID',
        'storagenodeID' => 'fcsStorageNodeID',
        'modtime' => 'fcsFileModTime',
        'checksum' => 'fcsFileChecksum',
        'path' => 'fcsFilePath',
        'status' => 'fcsStatus',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'storagenodeID',
        'path',
    );
    /**
     * The additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'storagenode',
    );
    /**
     * The image paths
     *
     * @var array
     */
    private $_imagePaths = array();
    /**
     * The snapin files
     *
     * @var array
     */
    private $_snapinFiles = array();
    /**
     * Gets the current running node
     *
     * @return void
     */
    public function getThisNode()
    {
        $this->set('storagenode', self::getClass('StorageNode'));
        self::getIPAddress();
        foreach ((array)self::getClass('StorageNodeManager')
            ->find(array('isEnabled' => 1)) as &$StorageNode
        ) {
            $ip = self::resolveHostname($StorageNode->get('ip'));
            if (!in_array($ip, self::$ips)) {
                continue;
            }
            $this->set('storagenode', $StorageNode);
            break;
        }
        if (!$this->get('storagenode')->isValid()) {
            throw new Exception(
                _('No node associated with any addresses of this system')
            );
        }
    }
    /**
     * Get hash of item.
     *
     * @param string $item the item to get hash of
     *
     * @return string
     */
    private function _getHash($item)
    {
        if (!is_dir($item)) {
            return hash_file(
                'sha512',
                $item
            );
        }
        $files = array();
        $dir = dir($item);
        while (false !== ($file = $dir->read())) {
            if (in_array($file, array('.', '..'))) {
                continue;
            }
            $files[] = $this->_getHash(
                sprintf(
                    '%s%s%s',
                    $item,
                    DS,
                    $file
                )
            );
        }
        $dir->close();
        return hash(
            'sha512',
            implode('', $files)
        );
    }
    /**
     * Gets the last modification time.
     *
     * @param string $item the item to get mod time of.
     *
     * @return string
     */
    private function _getModTime($item)
    {
        $stat = stat($item);
        return self::formatTime($stat['mtime'], 'Y-m-d H:i:s');
    }
    /**
     * Gets image paths
     *
     * @return void
     */
    public function getImagePaths()
    {
        $imagePaths = self::getSubObjectIDs(
            'Image',
            array('id' => $this->get('storagenode')->get('images')),
            'path'
        );
        $str = sprintf(
            '%s%s%s',
            $this->get('storagenode')->get('path'),
            DS,
            '%s'
        );
        foreach ((array)$imagePaths as &$path) {
            self::$imagePaths[] = sprintf(
                $str,
                $path
            );
            unset($path);
        }
    }
    /**
     * Gets snapin files
     *
     * @return void
     */
    public function getSnapinFiles()
    {
        self::$snapinFiles = $this
            ->get('storagenode')
            ->get('snapinfiles');
    }
    /**
     * Stores the path files
     *
     * @return void
     */
    public function processPathFiles()
    {
        $files = self::fastmerge(
            (array)self::$imagePaths,
            (array)self::$snapinFiles
        );
        foreach ((array)$files as &$file) {
            $this
                ->set(
                    'storagenodeID',
                    $this->get('storagenode')->get('id')
                )->set(
                    'modtime',
                    $this->_getModTime($file)
                )->set(
                    'checksum',
                    $this->_getHash($file)
                )->set(
                    'path',
                    $file
                )->save();
            unset($file);
        }
    }
}
