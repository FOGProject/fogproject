<?php
/**
 * The group power-management grant manager class.
 *
 * PHP version 7.4+
 *
 * @category GroupPowerManagementManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Items\GroupPowerManagement;

/**
 * The group power-management grant manager class.
 *
 * @category GroupPowerManagementManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupPowerManagementManager extends FOGManagerController
{
    /**
     * The wake grants, as objects the scheduler can time and fire.
     *
     * A DIRECT READ, for the reason Assign\Resolver's class docblock sets
     * out at length: FOGController::buildQuery() walks the field-relationship
     * declarations transitively, and any query whose class chain reaches Host
     * picks up `AND hostMAC.hmPrimary = '1'` in its WHERE rather than its ON.
     * That would silently drop grants whose groups contain a host with no
     * primary MAC -- and a wake grant is precisely the thing you set on
     * machines whose MACs you are least sure about.
     *
     * Only `wol`. A group-granted shutdown or reboot is run by the FOG client
     * on each member, which reads it through Client\PM; the server has
     * nothing to send and would only duplicate it. A wake is the one action a
     * sleeping machine cannot perform for itself.
     *
     * Ids first, then objects, rather than hydrating from the row: the id is
     * all that is needed to decide whether to instantiate, and the scheduler
     * runs this every tick against a table that is usually empty.
     *
     * @return array GroupPowerManagement objects, ascending by id
     * @throws \RuntimeException when the query failed
     */
    public function wakeGrants()
    {
        $res = self::$DB->query(
            "SELECT `gpmID` FROM `groupPowerManagement` "
            . "WHERE `gpmAction` = 'wol' ORDER BY `gpmID`"
        );
        if (false !== $res->error) {
            throw new \RuntimeException(
                sprintf(
                    'Group power-management read failed: %s',
                    (string)$res->error
                )
            );
        }
        $rows = (array)$res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $grants = [];
        foreach ($rows as $row) {
            $grants[] = new GroupPowerManagement($row['gpmID']);
        }

        return $grants;
    }
}
