<?php
/**
 * This is only used for capone plugin.
 *
 * PHP version 5
 *
 * @category CaponeTasking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * This is only used for capone plugin.
 *
 * @category CaponeTasking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class CaponeTasking extends FOGBase
{
    /**
     * The actions supported fog capone.
     *
     * @var array
     */
    protected $actions = array(
        'dmi',
        'imagelookup'
    );
    /**
     * The image types so capone follows.
     *
     * @var array
     */
    protected $imgTypes = array(
        1 => 'n',
        2 => 'mps',
        3 => 'mpa',
        4 => 'dd'
    );
    /**
     * Initializes the Capone tasking class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        switch (strtolower($_REQUEST['action'])) {
        case 'dmi':
            echo self::getSetting('FOG_PLUGIN_CAPONE_DMI');
            break;
        case 'imagelookup':
            if (!isset($_REQUEST['key'])
                || empty($_REQUEST['key'])
            ) {
                break;
            }
            try {
                $strSetup = "%s|%s|%s|%s|%s|%s|%s";
                ob_start();
                foreach ((array)self::getClass('CaponeManager')
                    ->find(
                        array(
                            'key' => trim(base64_decode($_REQUEST['key']))
                        )
                    ) as &$Capone
                ) {
                    $Image = $Capone->getImage();
                    if (!$Image->isValid()) {
                        continue;
                    }
                    $OS = $Image->getOS();
                    if (!$OS->isValid()) {
                        continue;
                    }
                    $StorageNode = $Image
                        ->getStorageGroup()
                        ->getOptimalStorageNode();
                    if (!$StorageNode->isValid()) {
                        continue;
                    }
                    $Image = $Capone->getImage();
                    $path = $Image->get('path');
                    $osid = $Image->get('osID');
                    $itid = $Image->get('imageTypeID');
                    $ptid = $Image->getPartitionType();
                    $format = $Image->get('format');
                    printf(
                        "%s\n",
                        base64_encode(
                            sprintf(
                                $strSetup,
                                $path,
                                $osid,
                                $this->imgTypes[$itid],
                                $ptid,
                                (int)$format,
                                sprintf(
                                    '%s:%s',
                                    $StorageNode->get('ip'),
                                    $StorageNode->get('path')
                                ),
                                $StorageNode->get('ip')
                            )
                        )
                    );
                    unset($Capone);
                }
                throw new Exception(
                    (
                        ob_get_contents() ?
                        ob_get_clean() :
                        base64_encode(null)
                    )
                );
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            break;
        }
    }
}
