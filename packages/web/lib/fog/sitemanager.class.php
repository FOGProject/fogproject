<?php
/**
 * Site manager.
 *
 * PHP version 5
 *
 * @category SiteManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site manager.
 *
 * No schema()/install()/uninstall() here, unlike the plugin manager this
 * replaces. Core tables are created by commons/schema.php (step 331) and
 * migrated by step 332; a manager that could also create them would be a
 * second, divergent definition of the same tables.
 *
 * @category SiteManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'sites';
    /**
     * The catch-all site, or null when there is not one.
     *
     * Read by id from the flag rather than by name: the name is whatever
     * was free when schema step 333 ran ('Everything', or 'Everything (2)'
     * if that was taken), and an admin may rename it afterwards. The flag
     * is the identity.
     *
     * Direct SQL rather than Route::getIds(): `catchall` is not one of
     * Site's database fields (it cannot be -- see Site::$additionalFields),
     * so there is no column for a filter to name. It is also NULL-or-1, and
     * a filter builder that emits `= :val` has no way to ask for NOT NULL.
     *
     * @return Site|null
     */
    public function catchAll()
    {
        $row = self::$DB
            ->query(
                'SELECT `siteID` AS `id` FROM `sites` '
                . 'WHERE `siteCatchAll` IS NOT NULL LIMIT 1'
            )
            ->fetch(\PDO::FETCH_ASSOC)
            ->get();
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }
        return self::getClass('Site', (int)$row['id']);
    }
}
