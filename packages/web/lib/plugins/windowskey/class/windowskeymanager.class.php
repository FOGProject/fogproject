<?php
/**
 * Windows Key manager mass management class
 *
 * PHP version 5
 *
 * @category WindowsKeyManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Windows Key manager mass management class
 *
 * @category WindowsKeyManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'windowsKeys';
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
                'wkID',
                'wkName',
                'wkDesc',
                'wkCreatedBy',
                'wkCreatedTime',
                'wkKey'
            ),
            array(
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(200)'
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                false
            ),
            array(
                'wkKey',
                'wkName'
            ),
            'MyISAM',
            'utf8',
            'wkID',
            'wkID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return self::getClass('WindowsKeyAssociationManager')
            ->install();
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        self::getClass('WindowsKeyAssociationManager')->uninstall();
        return parent::uninstall();
    }
    /**
     * Removes fields.
     *
     * Customized for hosts
     *
     * @param array  $findWhere     What to search for
     * @param string $whereOperator Join multiple where fields
     * @param string $orderBy       Order returned fields by
     * @param string $sort          How to sort, ascending, descending
     * @param string $compare       How to compare fields
     * @param mixed  $groupBy       How to group fields
     * @param mixed  $not           Comparator but use not instead.
     *
     * @return parent::destroy
     */
    public function destroy(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false
    ) {
        parent::destroy(
            $findWhere,
            $whereOperator,
            $orderBy,
            $sort,
            $compare,
            $groupBy,
            $not
        );
        if (isset($findWhere['id'])) {
            $findWhere = array('windowskeyID' => $findWhere['id']);
        }
        self::getClass('WindowsKeyAssociationManager')->destroy($findWhere);
        return true;
    }
}
