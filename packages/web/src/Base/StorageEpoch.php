<?php
/**
 * The instant this install started storing dates in UTC, and what was true
 * before it.
 *
 * Nothing that was already stored is ever converted. Up to five different
 * clocks have written FOG's date columns -- PHP through FOG_TZ_INFO, MySQL
 * NOW() in hand-written statements, MySQL DEFAULT current_timestamp(), the
 * display-zone regression fixed in #1491, and the fog-client's own clock on
 * check-in -- and no sweep can know which one wrote any given row. A
 * conversion is also one-way: once 10:13:47 has become 15:13:47 there is
 * nothing left saying which it was. So the convention change is RECORDED
 * and every reader compares against it.
 *
 * Full reasoning, including the alternatives rejected and why:
 * docs/development/utc-storage-boundary.md.
 *
 * @category Base
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

/**
 * Reads the one row schema step 396 wrote, and answers the two questions
 * every date on the way to a screen has to ask of it.
 *
 * @category Base
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class StorageEpoch extends FOGBase
{
    /**
     * Hours either side of the boundary in which a value cannot be
     * classified, and is treated as pre-boundary.
     *
     * UTC-12 to UTC+14 is the full span of real offsets, so 26 hours is safe
     * on any install without having to trust a recorded zone. A narrower
     * band computed from seZone and seDbZone would be more precise and is
     * not worth it: the cost of being too wide is that a handful of values
     * written in the day around a one-time upgrade are labeled unadjusted
     * when they could have been labeled exactly, and the cost of being too
     * narrow is a timestamp silently an hour wrong and presented as right.
     * That asymmetry is the whole argument.
     *
     * @var int
     */
    const BAND_HOURS = 26;
    /**
     * The row, once read. False while it has not been looked for, null when
     * there is none.
     *
     * @var array|null|false
     */
    private static $_row = false;
    /**
     * The parsed boundary, memoized.
     *
     * @var \DateTime|null
     */
    private static $_boundary = null;
    /**
     * Reads the row, at most once per process.
     *
     * Every failure answers "no boundary": a database that is not up yet
     * (the installer runs this code path before the schema exists), a table
     * an older install does not have, a permissions problem. Answering "no
     * boundary" means behaving exactly as FOG did before this existed,
     * which is the only safe default -- the alternative is claiming values
     * are UTC on an install that never made them so.
     *
     * @return array|null
     */
    private static function _row()
    {
        if (false !== self::$_row) {
            return self::$_row;
        }
        self::$_row = null;
        if (!self::$DB) {
            return self::$_row;
        }
        try {
            $row = self::$DB
                ->query(
                    'SELECT `seBoundary`, `seZone`, `seDbZone`, `seSchema` '
                    . 'FROM `storageEpoch` ORDER BY `seID` LIMIT 1'
                )
                ->fetch()
                ->get();
        } catch (\Throwable $e) {
            return self::$_row;
        }
        if (!is_array($row) || empty($row['seBoundary'])) {
            return self::$_row;
        }
        self::$_row = $row;

        return self::$_row;
    }
    /**
     * Whether this install has crossed the boundary.
     *
     * This is the switch for the whole feature. Until the row exists,
     * storageTimeZone() keeps answering FOG_TZ_INFO and the database session
     * keeps its own zone, so a half-upgraded install behaves exactly as it
     * did before rather than in some third way.
     *
     * @return bool
     */
    public static function active()
    {
        return null !== self::_row();
    }
    /**
     * The boundary instant, in UTC.
     *
     * @return \DateTime|null
     */
    public static function boundary()
    {
        if (null !== self::$_boundary) {
            return self::$_boundary;
        }
        $row = self::_row();
        if (null === $row) {
            return null;
        }
        try {
            self::$_boundary = new \DateTime(
                (string)$row['seBoundary'],
                new \DateTimeZone('UTC')
            );
        } catch (\Exception $e) {
            return null;
        }

        return self::$_boundary;
    }
    /**
     * The zone pre-boundary values were written in, as far as anything can
     * say.
     *
     * seZone is a RECORD of what FOG_TZ_INFO was when the boundary was
     * stamped, not a live read of it. That is the point: after the boundary
     * FOG_TZ_INFO becomes a display setting an admin may change freely, and
     * changing it must not silently re-interpret every old row.
     *
     * @return \DateTimeZone
     */
    public static function priorZone()
    {
        $row = self::_row();
        $name = null === $row ? '' : trim((string)$row['seZone']);
        if ('' === $name) {
            return new \DateTimeZone('UTC');
        }
        try {
            return new \DateTimeZone($name);
        } catch (\Exception $e) {
            // A zone name this PHP no longer knows -- tzdata moved, or the
            // setting held something unusable even then.
            return new \DateTimeZone('UTC');
        }
    }
    /**
     * Whether a stored value predates the boundary, and so does NOT mean UTC.
     *
     * The column type decides more than the value does, and getting that
     * backwards is the one way this can make a correct timestamp wrong:
     *
     *  - A TIMESTAMP column has ALWAYS held a UTC instant. MySQL converts it
     *    on the way in and out using the session zone, so once the session
     *    runs at +00:00 the string handed back is UTC for every row in the
     *    table, however old. Those are never pre-boundary.
     *  - A DATETIME column is stored verbatim and means whatever the clock
     *    that wrote it meant. Only those can be pre-boundary.
     *
     * @param string $value      The stored value.
     * @param bool   $isDatetime Whether the column is DATETIME rather than
     *                           TIMESTAMP.
     *
     * @return bool
     */
    public static function isPreBoundary($value, $isDatetime = true)
    {
        if (!$isDatetime || !self::active()) {
            return false;
        }
        $boundary = self::boundary();
        if (null === $boundary) {
            return false;
        }
        $value = trim((string)$value);
        if ('' === $value || !self::validDate($value)) {
            return false;
        }
        try {
            // Read in UTC, which is what the value claims to be if it is
            // post-boundary. The band is what covers it being wrong by an
            // offset if it is not.
            $when = new \DateTime($value, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return false;
        }
        $cutoff = clone $boundary;
        $cutoff->modify('+' . self::BAND_HOURS . ' hours');

        return $when < $cutoff;
    }
}
