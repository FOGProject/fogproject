<?php
/**
 * Manager class for ntfy
 *
 * PHP version 5
 *
 * @category NtfyManager
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for ntfy
 *
 * @category NtfyManager
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NtfyManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'ntfy';
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
            'nID',
            'nServerURL',
            'nTopicEndpoint',
            'nCredentials'
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
            'nID'
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
            'nID',
            'nID'
        );
    }
    /**
     * The plugin's ordered, append-only schema migration list. Append new
     * steps (e.g. "ALTER TABLE `ntfy` ADD COLUMN ...") to the END.
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
