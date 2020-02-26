<?php
/**
 * Slack manager mass management class
 *
 * PHP version 5
 *
 * @category SlackManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Slack manager mass management class
 *
 * @category SlackManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SlackManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'slack';
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
                'sID',
                'sToken',
                'sUsername'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'VARCHAR(255)'
            ],
            [
                false,
                false,
                false
            ],
            [
                false,
                false,
                false
            ],
            [
                'sID',
                [
                    'sToken'
                ]
            ],
            'InnoDB',
            'utf8',
            'sID',
            'sID'
        );
        return self::$DB->query($sql);
    }
}
