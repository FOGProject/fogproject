<?php
/**
 * One named, saved grid filter
 *
 * PHP version 7.4+
 *
 * @category SavedFilter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * One named, saved grid filter.
 *
 * The value is OPAQUE, the same stance as UserPref: it holds DataTables'
 * searchBuilder state, whose shape belongs to DataTables and changes between
 * its major versions. Nothing server-side decides anything on the strength of
 * what is inside it.
 *
 * OWNERSHIP: a null userID means the filter is GLOBAL -- offered to everyone
 * on that grid. Any other value makes it private to that user. creatorID
 * records who wrote it and survives for globals, so a shared filter still has
 * an author to ask about after its owner has moved on.
 *
 * @category SavedFilter
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SavedFilter extends FOGController
{
    /**
     * The largest filter that will be stored, in bytes.
     *
     * A SearchBuilder state with a dozen rules runs to a couple of kilobytes.
     * The cap is here because the value arrives from a browser: an unbounded
     * write is a way to fill a disk quietly, not a feature anybody asked for.
     * Matches UserPref::MAX_VALUE_BYTES for the same reason.
     *
     * @var int
     */
    const MAX_VALUE_BYTES = 65536;
    /**
     * The longest name that will be stored, in bytes.
     *
     * Matches the varchar(64) column, which is sized by the UNIQUE index it
     * sits in rather than by taste -- see schema step 393. A longer name is
     * refused rather than truncated, because two names cut to the same 64
     * bytes would collide on that index and overwrite each other.
     *
     * @var int
     */
    const MAX_NAME_BYTES = 64;
    /**
     * The longest grid key that will be stored, in bytes.
     *
     * Matches the varchar(128) column, same index and same reasoning.
     *
     * @var int
     */
    const MAX_TABLE_BYTES = 128;
    /**
     * The saved filters table
     *
     * @var string
     */
    protected $databaseTable = 'savedFilters';
    /**
     * The table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sfID',
        'userID' => 'sfUserID',
        'creatorID' => 'sfCreatorID',
        'table' => 'sfTable',
        'name' => 'sfName',
        'value' => 'sfValue',
        'createdTime' => 'sfCreatedTime',
        'modifiedTime' => 'sfModifiedTime'
    ];
    /**
     * The required fields
     *
     * userID is NOT required: a null means the filter is global, and that is
     * a state the table is designed to hold rather than a missing value.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'table',
        'name'
    ];
}
