<?php
/**
 * Manager class for wolbroadcast
 *
 * PHP Version 5
 *
 * @category WolbroadcastManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for wolbroadcast
 *
 * @category WolbroadcastManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WolbroadcastManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'wolbroadcast';
    /**
     * Perform the database and plugin installation
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'wbID',
                'wbName',
                'wbDesc',
                'wbBroadcast'
            ),
            array(
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(16)'
            ),
            array(
                false,
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false,
                false
            ),
            array(
                'wbID'
            ),
            'InnoDB',
            'utf8',
            'wbID',
            'wbID'
        );
        return self::$DB->query($sql);
    }
}
