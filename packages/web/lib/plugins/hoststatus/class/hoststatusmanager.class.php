<?php
/**
 * HostStatus manager mass class.
 *
 * @category HostStatus
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@ehu.eus>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostStatusManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'hoststatus';
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
            array(
                'hsID',
                'hsName',
                'hsDesc'
            ),
            array(
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
            ),
            array(
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false
            ),
            array(
                'hsID',
                'hsName',
                array(
                )
            ),
            'MyISAM',
            'utf8',
            'hsID',
            'hsID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return true;
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall();
    }
}
