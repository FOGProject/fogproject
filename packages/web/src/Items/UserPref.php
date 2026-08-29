<?php
/**
 * One stored preference belonging to one user
 *
 * PHP version 7.4+
 *
 * @category UserPref
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * One stored preference belonging to one user.
 *
 * The value is OPAQUE. Nothing here parses it, and nothing server-side may
 * decide anything on the strength of what is inside it -- the first consumer
 * is DataTables' own saved state, whose shape belongs to DataTables and
 * changes between its major versions. Keeping the value uninterpreted is what
 * stops that becoming a schema migration every time the library moves.
 *
 * @category UserPref
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserPref extends FOGController
{
    /**
     * The largest value that will be stored, in bytes.
     *
     * A saved DataTables state for a wide grid runs to a couple of kilobytes;
     * the column is a longtext and would take megabytes. The cap is here
     * because the value arrives from a browser and is never read by anything
     * that would notice it growing -- an unbounded per-user write is a way to
     * fill a disk quietly, not a feature anybody asked for.
     *
     * @var int
     */
    const MAX_VALUE_BYTES = 65536;
    /**
     * The longest key that will be stored, in bytes.
     *
     * Matches the varchar(190) column. Two keys truncated to the same 190
     * bytes would collide on the UNIQUE index and overwrite each other, so
     * an over-long key is refused rather than cut down.
     *
     * @var int
     */
    const MAX_KEY_BYTES = 190;
    /**
     * The user preferences table
     *
     * @var string
     */
    protected $databaseTable = 'userPrefs';
    /**
     * The table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'upID',
        'userID' => 'upUserID',
        'key' => 'upKey',
        'value' => 'upValue',
        'createdTime' => 'upCreatedTime',
        'modifiedTime' => 'upModifiedTime'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'userID',
        'key'
    ];
}
