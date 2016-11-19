<?php
/**
 * Location association manager class.
 *
 * PHP version 5
 *
 * @category LocationAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location association manager class.
 *
 * @category LocationAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationAssociationManager extends FOGManagerController
{
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $sql = Schema::createTable(
            'locationAssoc',
            true,
            array(
                'laID',
                'laLocationID',
                'laHostID'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'INTEGER'
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
                '',
                '',
                'laHostID'
            ),
            'MyISAM',
            'utf8',
            'laID',
            'laID'
        );
        return self::$DB->query($sql);
    }
    /**
     * Uninstalls the assoc table.
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = Schema::dropTable('locationAssoc');
        return self::$DB->query($sql);
    }
}
