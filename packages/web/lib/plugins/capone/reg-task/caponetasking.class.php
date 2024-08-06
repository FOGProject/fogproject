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
    protected $actions = [
        'dmi',
        'imagelookup'
    ];
    /**
     * The image types so capone follows.
     *
     * @var array
     */
    protected $imgTypes = [
        1 => 'n',
        2 => 'mps',
        3 => 'mpa',
        4 => 'dd'
    ];
    /**
     * Initializes the Capone tasking class.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $action = filter_input(INPUT_POST, 'action');
        switch ($action) {
            case 'dmi':
                echo self::getSetting('FOG_PLUGIN_CAPONE_DMI');
                break;
            case 'imagelookup':
                $key = filter_input(INPUT_POST, 'key');
                if (!$key) {
                    break;
                }
                try {
                    $strSetup = "%s|%s|%s|%s|%s|%s|%s";
                    ob_start();
                    Route::listem(
                        'capone',
                        ['key' => $keys]
                    );
                    $capones = json_decode(
                        Route::getData()
                    );
                    foreach ($capones->data as &$Capone) {
                        $Image = new Image($Capone->imageID);
                        $OS = $Image->getOS();
                        $StorageNode = $Image
                            ->getStorageGroup()
                            ->getOptimalStorageNode();
                        if (!$Image->isValid()
                            || $OS->isValid()
                            || $StorageNode->isValid()
                        ) {
                            continue;
                        }
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
        }
    }
}
