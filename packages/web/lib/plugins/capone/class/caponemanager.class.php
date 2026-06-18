<?php
/**
 * Manager class for Capone
 *
 * PHP version 5
 *
 * @category CaponeManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for Capone
 *
 * @category CaponeManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class CaponeManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'capone';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'cID',
                'cImageID',
                'cOSID',
                'cKey'
            ],
            [
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'VARCHAR(255)'
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
                'cID',
                'cKey'
            ],
            'InnoDB',
            'utf8',
            'cID',
            'cID'
        );
    }
    /**
     * Seeds this plugin's global settings, but only the ones that are
     * missing, so an admin's existing values are never overwritten on a
     * re-run/upgrade. Used as a step in schema().
     *
     * @return bool
     */
    public function seedSettings()
    {
        $category = 'Plugin: Capone';
        $fields = [
            'name',
            'description',
            'value',
            'category'
        ];
        $settings = [
            [
                'FOG_PLUGIN_CAPONE_DMI',
                'This setting is used for the capone '
                . 'module to set the DMI field used.',
                '',
                $category
            ],
            [
                'FOG_PLUGIN_CAPONE_REGEX',
                'This setting is used for the capone '
                . 'module to set the reg ex used.',
                '',
                $category
            ],
            [
                'FOG_PLUGIN_CAPONE_SHUTDOWN',
                'This setting is used for the capone '
                . 'module to set the shutdown after imaging.',
                '',
                $category
            ]
        ];
        $SettingManager = self::getClass('SettingManager');
        $toInsert = [];
        foreach ($settings as $setting) {
            if (!$SettingManager->exists($setting[0], '', 'name')) {
                $toInsert[] = $setting;
            }
        }
        if (count($toInsert)) {
            $SettingManager->insertBatch($fields, $toInsert);
        }
        return true;
    }
    /**
     * The plugin's ordered, append-only schema migration list. Append new
     * steps (e.g. "ALTER TABLE `capone` ADD COLUMN ...") to the END.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0
            $this->createSql(),
            // 1
            function () {
                return $this->seedSettings();
            },
        ];
    }
    /**
     * Installs the capone database non-destructively (create-if-absent +
     * seed any missing settings). Does not drop existing data or values.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
    /**
     * Removes the database items when plugin is removed.
     *
     * @return bool
     */
    public function uninstall()
    {
        Route::deletemass(
            'setting',
            ['name' => 'FOG_PLUGIN_CAPONE_%']
        );
        Route::deletemass(
            'pxemenuoptions',
            ['name' => 'fog.capone']
        );
        return parent::uninstall();
    }
}
