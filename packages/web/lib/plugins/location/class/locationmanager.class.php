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
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive: it only creates the table when absent and is safe to
     * re-run. Used as the first step in schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
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
    }
    /**
     * The plugin's ordered, append-only schema migration list.
     *
     * One flat list covering every table this plugin owns. New schema
     * changes are appended to the END (e.g. "ALTER TABLE `location` ADD
     * COLUMN ...") and are applied incrementally and non-destructively by
     * Schema::applyUpdates(), tracked via the plugins.pSchema counter.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0
            $this->createSql(),
            // 1
            self::getClass('LocationAssociationManager')->createSql(),
        ];
    }
    /**
     * Install our database non-destructively (create-if-absent + apply any
     * pending additive steps). Does not drop existing data.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
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
                Initiator::e($short),
                ($preselection === $short ? ' selected' : ''),
                Initiator::e($long)
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
