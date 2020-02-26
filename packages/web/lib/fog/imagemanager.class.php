<?php
/**
 * Image manager mass management class
 *
 * PHP version 5
 *
 * @category ImageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Image manager mass management class
 *
 * @category ImageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'images';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $compress = sprintf(
            "ENUM('%s')",
            implode("','", range(0, 9))
        );
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'imageID',
                'imageName',
                'imageDesc',
                'imagePath',
                'imageProtect',
                'imageMagnetUri',
                'imageDateTime',
                'imageCreateBy',
                'imageBuilding',
                'imageSize',
                'imageTypeID',
                'imagePartitionTypeID',
                'imageOSID',
                'imageFormat',
                'imageLastDeploy',
                'imageCompress',
                'imageEnabled',
                'imageReplicate'
            ],
            [
                'INTEGER',
                'VARCHAR(40)',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1')",
                'LONGTEXT',
                'TIMESTAMP',
                'VARCHAR(40)',
                'INTEGER',
                'VARCHAR(255)',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'VARCHAR(2)',
                'DATETIME',
                $compress,
                "ENUM('0', '1')",
                "ENUM('0', '1')"
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ],
            [
                'imageID',
                [
                    'imageName',
                    'imageTypeID'
                ]
            ],
            'InnoDB',
            'utf8',
            'imageID',
            'imageID'
        );
        return self::$DB->query($sql);
    }
}
