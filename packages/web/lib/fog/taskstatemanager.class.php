<?php
/**
 * The task state manager class.
 *
 * PHP version 5
 *
 * @category TaskStateManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The task state manager class.
 *
 * @category TaskStateManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskStateManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'taskStates';
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
                'tsID',
                'tsName',
                'tsDescription',
                'tsOrder',
                'tsIcon'
            ],
            [
                'INTEGER',
                'VARCHAR(50)',
                'LONGTEXT',
                'TINYINT(4)',
                'VARCHAR(255)'
            ],
            [
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
                false
            ],
            [
                'tsID',
                'tsName'
            ],
            'MyISAM',
            'utf8',
            'tsID',
            'tsID'
        );
        return self::$DB->query($sql);
    }
}
