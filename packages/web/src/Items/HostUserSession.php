<?php
/**
 * One user logon on a host: a session with two ends (design 0008).
 *
 * PHP version 7.4+
 *
 * @category UserTracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * One user logon on a host: a session with two ends (design 0008).
 *
 * The contrast to draw is with UserTracking next door, which this does not
 * replace: that is a log of login and logout EVENTS, two rows that have to be
 * paired by hand and often cannot be, because a logout event needs the
 * machine to still be alive to send it. Here `endedAt IS NULL` is open, and
 * a duration is a subtraction rather than a heuristic.
 *
 * @category UserTracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostUserSession extends FOGController
{
    /**
     * The hostUserSession table.
     *
     * @var string
     */
    protected $databaseTable = 'hostUserSession';
    /**
     * The hostUserSession fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'husID',
        'hostID' => 'husHostID',
        'sessionKey' => 'husSessionKey',
        'username' => 'husUserName',
        'domain' => 'husDomain',
        'sid' => 'husUserSID',
        'type' => 'husType',
        'state' => 'husState',
        'remoteHost' => 'husRemoteHost',
        'startedAt' => 'husStartedAt',
        'endedAt' => 'husEndedAt',
        'endReason' => 'husEndReason',
        'lastSeen' => 'husLastSeen'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'sessionKey',
        'username',
        'startedAt'
    ];
    /**
     * Ends in "id" but is not one: husUserSID is a varchar holding a Windows
     * SID or a Linux uid. Without this, save() would read the name as a
     * foreign key and rewrite every SID it stored to 0 -- the same trap the
     * Inventory class documents for iSystemUUID.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = [
        'sid'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'host'
    ];
    /**
     * Return the associated host object.
     *
     * @return object
     */
    public function getHost()
    {
        if (!array_key_exists('host', $this->data)) {
            $this->set('host', new Host($this->get('hostID')));
        }
        return $this->get('host');
    }
    /**
     * Whether this session is still open.
     *
     * @return bool
     */
    public function isOpen()
    {
        // validDate() is the one definition of "no date" -- falsy for null,
        // empty, unparseable and both spellings of the zero date. Spelling
        // the zero date out here instead is what
        // tests/date-columns-nullable.test.php exists to catch.
        return !self::validDate($this->get('endedAt'));
    }
    /**
     * How long the session lasted, in seconds.
     *
     * Null while it is still open, and null when the start is unusable --
     * never zero, which would read as a real session of no length.
     *
     * @return int|null
     */
    public function duration()
    {
        if ($this->isOpen()) {
            return null;
        }
        $start = strtotime((string)$this->get('startedAt'));
        $end = strtotime((string)$this->get('endedAt'));
        if (false === $start || false === $end || $end < $start) {
            return null;
        }
        return $end - $start;
    }
    /**
     * Whether the end was witnessed or assumed.
     *
     * A caller reporting a duration should say which: an inferred close is a
     * lower bound taken from the last time the session was seen, not a
     * measurement. The legacy table could not express the difference, which
     * is the defect design 0008 exists to fix.
     *
     * @return bool
     */
    public function endInferred()
    {
        return 'inferred' === $this->get('endReason');
    }
}
