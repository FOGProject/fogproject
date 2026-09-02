<?php
/**
 * The group power-management grant class.
 *
 * PHP version 7.4+
 *
 * @category GroupPowerManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Assign\Resolver;
use FOG\Base\FOGController;
use FOG\Util\Timer;

/**
 * The group power-management grant class.
 *
 * A row here says "this group runs this action on this schedule", and every
 * member gets it -- including hosts added afterward. Nothing is written onto
 * a host; what a given machine actually runs is computed at read time by
 * Assign\Resolver::resolvePowerManagement() (ADR 0038).
 *
 * THERE IS NO `onDemand` FIELD, and its absence is deliberate. The host-level
 * PowerManagement class has one because an immediate shutdown, reboot or wake
 * is a row the client consumes and deletes on its next check-in. That is a
 * TASK -- it acts on the membership at the moment you start it -- and a grant
 * of "shut down immediately" would fire again for every host that joined the
 * group later. Only the SCHEDULE, which is a standing statement about the
 * group, lives here.
 *
 * @category GroupPowerManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupPowerManagement extends FOGController
{
    /**
     * The group power-management grant table.
     *
     * @var string
     */
    protected $databaseTable = 'groupPowerManagement';
    /**
     * The group power-management grant fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'gpmID',
        'groupID' => 'gpmGroupID',
        'min' => 'gpmMin',
        'hour' => 'gpmHour',
        'dom' => 'gpmDom',
        'month' => 'gpmMonth',
        'dow' => 'gpmDow',
        'action' => 'gpmAction'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'groupID',
        'action'
    ];
    /**
     * Get's the group object.
     *
     * @return object
     */
    public function getGroup()
    {
        return new Group($this->get('groupID'));
    }
    /**
     * The timer this grant fires on.
     *
     * Mirrors PowerManagement::getTimer(), RAW -- the `-1` weekday is NOT
     * normalized to `7` here, and that asymmetry with getCron() is deliberate.
     * Timer is FOG's own cron evaluator and reads -1 the way FOG's cron picker
     * writes it; the 7 exists only for Quartz, which is what the FOG client
     * schedules against. Normalizing for the server-side evaluator would move
     * Sunday.
     *
     * @return object
     */
    public function getTimer()
    {
        return new Timer(
            trim((string)$this->get('min')),
            trim((string)$this->get('hour')),
            trim((string)$this->get('dom')),
            trim((string)$this->get('month')),
            trim((string)$this->get('dow'))
        );
    }
    /**
     * Wakes every host this grant reaches.
     *
     * Membership is read at FIRE time, which is the whole point of the grant:
     * a host added to the group after the schedule was set is woken by it, and
     * a host removed is not. The old fan-out could do neither -- it wrote one
     * row per host at the moment of the press and nothing afterward changed
     * what it reached.
     *
     * @return void
     */
    public function wakeOnLAN()
    {
        $this->getGroup()->wakeOnLAN();
    }
    /**
     * The cron expression this grant fires on.
     *
     * Delegates rather than joining the fields itself. The resolver's
     * deduplication key IS the formatted string, so the expression the group
     * page shows an admin and the expression the client is handed have to come
     * out of one piece of code -- see Assign\Resolver::cronExpression().
     *
     * @return string the five-field cron expression
     */
    public function getCron()
    {
        return Resolver::cronExpression(
            $this->get('min'),
            $this->get('hour'),
            $this->get('dom'),
            $this->get('month'),
            $this->get('dow')
        );
    }
}
