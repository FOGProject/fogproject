<?php
/**
 * Hello World example plugin (collection manager + schema).
 *
 * A manager extends FOGManagerController and owns table-level concerns:
 * querying collections and creating/migrating the table.
 *
 * Database migrations use the NON-DESTRUCTIVE schema() contract: an ordered,
 * APPEND-ONLY list of steps. On install/upgrade the framework
 * (Plugin::installdb()) calls Schema::applyUpdates(schema(), $applied) and
 * records how many steps have run in the plugin's `pSchema` column, so only
 * pending steps are applied and existing data is never dropped.
 *
 * PHP version 5
 *
 * @category HelloWorldManager
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hello World example plugin (manager).
 *
 * @category HelloWorldManager
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HelloWorldManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'helloWorld';
    /**
     * The CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run; used as step 0 of schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'hwID',
                'hwName',
                'hwDesc',
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
            ],
            [
                false,
                false,
                false,
            ],
            [
                false,
                false,
                false,
            ],
            [
                'hwID',
            ],
            'InnoDB',
            'utf8',
            'hwID',
            'hwID'
        );
    }
    /**
     * The ordered, APPEND-ONLY schema migration list.
     *
     * Each step is a SQL string (or a closure returning a SQL string). To
     * evolve the schema, append a new step to the END of this array and never
     * reorder or remove existing entries -- the applied count in `pSchema`
     * tracks position, so reordering would re-run or skip the wrong step.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0 - create the table.
            $this->createSql(),
            //
            // Example of adding a column LATER (append, do not edit step 0):
            //
            // 1 - add an optional colour column.
            // "ALTER TABLE `helloWorld` ADD COLUMN `hwColor` VARCHAR(255) NULL",
            //
        ];
    }
    /**
     * Installs/upgrades the database non-destructively.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
}
