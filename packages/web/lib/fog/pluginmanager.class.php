<?php
/**
 * Plugin manager class.
 *
 * PHP version 5
 *
 * @category PluginManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Plugin manager class.
 *
 * @category PluginManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PluginManager extends FOGManagerController
{
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
     * @param mixed  $not           Comparator but use not instead
     *
     * @return parent::destroy
     */
    public function destroy(
        $findWhere = [],
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false
    ) {
        /**
         * Find the items to be destroyed by their names.
         */
        Route::ids(
            'plugin',
            $findWhere,
            'name'
        );
        $names = json_decode(Route::getData(), true);
        /**
         * Call the item's and their manager's uninstall method.
         */
        foreach ($names as &$name) {
            self::getClass($name.'Manager')->uninstall();
            unset($name);
        }
        /*
         * Remove the main item
         */
        parent::destroy(
            $findWhere,
            $whereOperator,
            $orderBy,
            $sort,
            $compare,
            $groupBy,
            $not
        );
    }
}
