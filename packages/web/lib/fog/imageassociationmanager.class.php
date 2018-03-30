<?php
/**
 * Image association manager class
 *
 * PHP version 5
 *
 * @category ImageAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Image association manager class
 *
 * @category ImageAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'imageGroupAssoc';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            [
                'igaID',
                'igaImageID',
                'igaStorageGroupID',
                'igaPrimary'
            ],
            [
                'INTEGER',
                'INTEGER',
                'INTEGER',
                "ENUM('0', '1')"
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                'igaID',
                [
                    'igaImageID',
                    'igaStorageGroupID'
                ]
            ],
            'MyISAM',
            'utf8',
            'igaID',
            'igaID'
        );
        return self::$DB->query($sql);
    }
}
