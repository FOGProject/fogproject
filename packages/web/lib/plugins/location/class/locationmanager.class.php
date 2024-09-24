<?php
/**
 * Location manager mass management class
 *
 * PHP version 5
 *
 * @category LocationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location manager mass management class
 *
 * @category LocationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'location';
    /**
     * Install our database
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
                'lID',
                'lName',
                'lDesc',
                'lStorageGroupID',
                'lStorageNodeID',
                'lCreatedBy',
                'lCreatedTime',
                'lTftpEnabled',
                'lStorageNodeProto'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'INTEGER',
                'INTEGER',
                'VARCHAR(40)',
                'TIMESTAMP',
                "ENUM('0', '1')",
                "ENUM('http', 'https')"
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
                false
            ],
            [
                'lID',
                'lName',
                [
                    'lStorageGroupID',
                    'lStorageNodeID'
                ]
            ],
            'InnoDB',
            'utf8',
            'lID',
            'lID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return self::getClass('LocationAssociationManager')
            ->install();
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        $res = true;
        Route::deletemass(
            'setting',
            ['name' => 'FOG_SNAPIN_LOCATION_SEND_ENABLED']
        );
        self::getClass('LocationAssociationManager')->uninstall();
        return parent::uninstall();
    }
    /**
     * Build protocol select box
     *
     * @return string
     */
    public static function buildProtocolSelectBox($preselection)
    {
        $protocols = ['http' => 'HTTP', 'https' => 'HTTPS'];
        ob_start();
        foreach ($protocols as $short => $long) {
            printf(
                '<option value="%s"%s>%s</option>',
                $short,
                ($preselection === $short ? ' selected' : ''),
                $long
            );
        }
        return '<select class="form-control" name="storagenodeprotocol" '
            . 'id="storagenodeprotocol">'
            . '<option value="">- '
            . self::$foglang['PleaseSelect']
            .' -</option>'
            . ob_get_clean()
            . '</select>';
    }
}
