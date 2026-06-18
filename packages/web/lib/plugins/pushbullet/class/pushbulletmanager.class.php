<?php
/**
 * Manager class for pushbullet
 *
 * PHP Version 5
 *
 * @category PushbulletManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for pushbullet
 *
 * @category PushbulletManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PushbulletManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'pushbullet';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in schema().
     *
     * @return string
     */
    public function createSql()
    {
        $fields = [
            'pID',
            'pToken',
            'pName',
            'pEmail'
        ];
        $types = [
            'INTEGER',
            'VARCHAR(255)',
            'VARCHAR(255)',
            'VARCHAR(255)'
        ];
        $notnulls = [
            false,
            false,
            false,
            false
        ];
        $defaults = [
            false,
            false,
            false,
            false
        ];
        $keys = [
            'pID',
            'pToken'
        ];
        return Schema::createTable(
            $this->tablename,
            true,
            $fields,
            $types,
            $notnulls,
            $defaults,
            $keys,
            'InnoDB',
            'utf8',
            'pID',
            'pID'
        );
    }
    /**
     * The plugin's ordered, append-only schema migration list. Append new
     * steps (e.g. "ALTER TABLE `pushbullet` ADD COLUMN ...") to the END.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0
            $this->createSql(),
        ];
    }
    /**
     * Installs the database non-destructively (create-if-absent + apply any
     * pending additive steps). Does not drop existing data.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
}
