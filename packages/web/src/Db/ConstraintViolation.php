<?php
/**
 * Turns a database's foreign key refusal into a sentence an admin can act on.
 *
 * PHP version 7.4+
 *
 * @category ConstraintViolation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Db;

use FOG\Base\FOGBase;

/**
 * Turns a database's foreign key refusal into a sentence an admin can act on.
 *
 * ADR 0031 declared referential integrity in the database, which made some
 * deletes refusable for the first time. The refusal arrives as MariaDB's own
 * text:
 *
 *     Cannot delete or update a parent row: a foreign key constraint fails
 *     (`fog`.`location`, CONSTRAINT `fk_location_lStorageGroupID` FOREIGN
 *     KEY (`lStorageGroupID`) REFERENCES `nfsGroups` (`ngID`))
 *
 * Everything needed is in there, but it is written for whoever wrote the
 * schema. An admin deleting a storage group needs to be told that a location
 * still uses it.
 *
 * The pairing is read out of commons/schema-constraints.php rather than
 * parsed out of the message, so it cannot drift from the declaration: the
 * constraint name is the lookup key, and the map is what created the
 * constraint in the first place.
 *
 * @category ConstraintViolation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ConstraintViolation extends FOGBase
{
    /**
     * What each table is called in a sentence.
     *
     * Bounded, not a parallel copy of the schema: only a RESTRICT
     * relationship can refuse a delete -- CASCADE and SET NULL both succeed
     * -- so only the fifteen tables on either side of one can ever appear
     * here. A table missing from the list degrades to its own name, which is
     * ugly but never wrong.
     *
     * Singular only, and the messages below put an article in front of it.
     * "a location still refers to it" reads correctly whether one location
     * does or five do, so the plural is not needed and neither is a count --
     * which would otherwise mean a second query on an error path, and the id
     * plumbed into it from two callers that do not agree on how many ids
     * they hold.
     *
     * @var array<string, string>
     */
    private static $_labels = [
        // Tables that block a delete.
        'nfsGroupMembers' => 'storage node',
        'location' => 'location',
        'fileDeleteQueue' => 'queued file deletion',
        'images' => 'image',
        'tasks' => 'task',
        'scheduledTasks' => 'scheduled task',
        'snapinJobs' => 'snapin job',
        'snapinTasks' => 'snapin task',
        'multicastSessions' => 'multicast session',
        // Tables whose rows get blocked.
        'nfsGroups' => 'storage group',
        'taskStates' => 'task state',
        'taskTypes' => 'task type',
        'imageTypes' => 'image type',
        'imagePartitionTypes' => 'image partition type',
        'os' => 'operating system',
    ];

    /**
     * Whether a PDODB error string is a foreign key refusal.
     *
     * Matched on the server's own wording rather than on the errno, because
     * PDODB's message carries the text but exposes the errno separately --
     * and every caller here has the message in hand while only some have
     * the code.
     *
     * 1451 only. A 1452 is the opposite direction (a row pointing at a
     * parent that does not exist) and never comes from a delete, so a
     * caller seeing one has a different problem and should not be handed a
     * sentence about deleting.
     *
     * @param string $error the error text as PDODB recorded it
     *
     * @return bool
     */
    public static function isRefusal($error)
    {
        return false !== strpos(
            (string)$error,
            'Cannot delete or update a parent row'
        );
    }

    /**
     * A sentence naming what is still using the record.
     *
     * Returns null when the error is not a foreign key refusal, or when the
     * constraint is one the map does not describe -- a hand-made constraint,
     * or one left by an older release. The caller then keeps the raw text,
     * which is worse to read but is never wrong.
     *
     * @param string $error the error text as PDODB recorded it
     * @param string $noun  what the caller was deleting, if it knows -- e.g.
     *                      "storage group". Blank produces "this record".
     *
     * @return string|null
     */
    public static function explain($error, $noun = '')
    {
        if (!self::isRefusal($error)) {
            return null;
        }
        if (!preg_match('/CONSTRAINT `([^`]+)`/', (string)$error, $m)) {
            return null;
        }
        $rel = self::relationship($m[1]);
        if (null === $rel) {
            return null;
        }
        $subject = '' === (string)$noun
            ? _('this record')
            : sprintf(_('this %s'), (string)$noun);

        return sprintf(
            /* translators: %1$s what is being deleted (e.g. "this storage
               group"), %2$s one of the things still referring to it (e.g.
               "location"). The English reads correctly whether one or many
               refer to it. */
            _('Cannot delete %1$s because a %2$s still refers to it. '
            . 'Reassign or remove it first.'),
            $subject,
            self::label($rel['child'])
        );
    }

    /**
     * The map entry a constraint name came from.
     *
     * @param string $name the constraint name, e.g. fk_location_lStorageGroupID
     *
     * @return array|null
     */
    public static function relationship($name)
    {
        $name = strtolower((string)$name);
        foreach (SchemaReconciler::constraints() as $rel) {
            if (empty($rel['child']) || empty($rel['column'])) {
                continue;
            }
            if (strtolower(SchemaReconciler::constraintName($rel)) === $name) {
                return $rel;
            }
        }

        return null;
    }

    /**
     * A table's human name.
     *
     * @param string $table the table name
     *
     * @return string the human name, or the table name if there is none
     */
    public static function label($table)
    {
        $label = self::$_labels[$table] ?? null;

        return null === $label ? (string)$table : _($label);
    }
}
