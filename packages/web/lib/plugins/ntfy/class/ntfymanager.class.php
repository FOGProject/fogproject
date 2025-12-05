<?php
/**
 * Manager class for ntfy
 *
 * PHP version 5
 *
 * @category NtfyManager
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager class for ntfy
 *
 * @category NtfyManager
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
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
     * Installs the database for the plugin.
     *
     * @param string $name the name of the plugin.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();

        $fields = array(
            'nID',
            'nServerURL',
            'nTopicEndpoint',
            'nCredentials'
        );
        $types = array(
            'INTEGER',
            'VARCHAR(255)',
            'VARCHAR(255)',
            'VARCHAR(255)'
        );
        $notnulls = array(
            false,
            false,
            false,
            false
        );
        $defaults = array(
            false,
            false,
            false,
            false
        );
        $keys = array(
            'nID'
        );
        $sql = Schema::createTable(
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

        return self::$DB->query($sql);
    }
    // /**
    //  * Uninstalls.
    //  *
    //  * @return bool
    //  */
    // public function uninstall()
    // {
    //     return true;
    // }
}
