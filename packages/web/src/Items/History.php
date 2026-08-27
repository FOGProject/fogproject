<?php
/**
 * Stores any actions to the database.
 *
 * PHP version 7.4+
 *
 * @category History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Stores any actions to the database.
 *
 * @category History
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class History extends FOGController
{
    /**
     * History table name.
     *
     * @var string
     */
    protected $databaseTable = 'history';
    /**
     * History field and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hID',
        'info' => 'hText',
        'createdBy' => 'hUser',
        'createdTime' => 'hTime',
        'ip' => 'hIP',
        // ADR 0020 phase 3. Added to the table by schema 350 and filled by
        // FOGBase::logHistory() from here on.
        //
        // `history` is the one event table whose subject is not always the
        // same class, so it is the one that carries subjectType. taskLog
        // and userTracking are always about a Host and say so once, in the
        // model, rather than on every row.
        //
        // Old rows keep an empty type and no subject. They are not
        // backfilled -- the entity a pre-phase-3 row is about exists only
        // inside the prose, in the locale of whoever triggered it, so
        // there is nothing to recover it from. Readers switch in phase 4
        // and fall back to the prose when `type` is empty.
        'type' => 'hType',
        'subjectType' => 'hSubjectType',
        'subjectID' => 'hSubjectID',
        'subjectLabel' => 'hSubjectLabel'
    ];
    /**
     * The text is the one field a row cannot be written without.
     *
     * Declared in ADR 0020 phase 5, when schema 355 returned `hText` to
     * TEXT. MySQL and MariaDB both refuse a literal DEFAULT on a TEXT
     * column, so the column that used to be `varchar(255) NOT NULL DEFAULT
     * ''` is now `TEXT NOT NULL` with no default at all -- and an INSERT
     * omitting it is error 1364 on a strict server rather than a silent ''.
     *
     * Declaring it required rather than making the column nullable,
     * because it is required in fact: FOGBase::logHistory() returns without
     * writing when the text is empty, so a textless row has never been
     * something this table stores. tests/optional-columns-carry-defaults
     * is what caught the disagreement.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'info'
    ];
    /**
     * A row recording that an object was written.
     *
     * Not split into created/updated: save() is one INSERT ... ON DUPLICATE
     * KEY UPDATE, so it genuinely does not know which of the two happened,
     * and the prose it has written for years does not distinguish either.
     * Encoding a distinction the writer cannot see would be inventing data.
     *
     * @var string
     */
    const TYPE_UPDATE = 'update';
    /**
     * A row recording that a write was attempted and failed.
     *
     * @var string
     */
    const TYPE_UPDATE_FAILED = 'update_failed';
    /**
     * A row recording that an object was destroyed.
     *
     * @var string
     */
    const TYPE_DELETE = 'delete';
    /**
     * A row recording that a destroy was attempted and failed.
     *
     * @var string
     */
    const TYPE_DELETE_FAILED = 'delete_failed';
    /**
     * A row written by the general debug logger rather than by a model.
     *
     * These carry no subject: FOGBase::log() takes a string and has no
     * object in hand. They are also the volume risk the table's UNIQUE
     * index was built to suppress -- see ADR 0020 decision 6, which
     * replaces that index with a bound on this writer.
     *
     * @var string
     */
    const TYPE_LOG = 'log';
    /**
     * One history row as a sentence, in the READER's language.
     *
     * ADR 0020 phase 4, moved here from Route in decision 5's writer
     * half once the dashboard card became a second reader. Route still
     * has a thin `_historySummary()` wrapper that calls this.
     *
     * `history` stored its prose pre-translated at write
     * time -- `sprintf('%s %s: %s ...', _('ID'), ...)` -- so a row written
     * by a German-speaking operator read as German to everyone afterwards,
     * and the same field label had two spellings in one English install
     * because two of the four writers used `_('NAME')` and two `_('Name')`.
     * Phase 3 gave the row a machine-readable type and subject; this turns
     * those back into a sentence at the moment somebody reads it.
     *
     * Falls back to the stored prose whenever the frame cannot answer:
     *
     *   - a row written before phase 3, which has no type. Those are NOT
     *     backfilled -- the prose is only parseable in the locale it was
     *     written in, so a parser would produce a table that is complete on
     *     English installs and partial elsewhere. The ADR takes the clean
     *     boundary instead.
     *   - a TYPE_LOG row, which has no subject: FOGBase::log() takes a
     *     string and has no object in hand.
     *   - a type this does not recognise, which is what a plugin writing
     *     its own code looks like.
     *
     * The failure types deliberately do not try to carry the error text.
     * That detail exists only inside the prose, so the sentence says what
     * happened and `info` -- still returned beside this -- says why.
     *
     * The subject's class is NOT translated. It is an identifier, it is
     * what the prose has always shown, and it is not in any message
     * catalogue.
     *
     * @param array $row The raw database row.
     *
     * @return string
     */
    public static function summary($row)
    {
        $type = isset($row['hType']) ? (string)$row['hType'] : '';
        $label = isset($row['hSubjectLabel']) ? (string)$row['hSubjectLabel'] : '';
        $id = isset($row['hSubjectID']) ? $row['hSubjectID'] : null;
        $class = isset($row['hSubjectType']) ? (string)$row['hSubjectType'] : '';
        $text = isset($row['hText']) ? (string)$row['hText'] : '';
        if ('' === $type || '' === $class || null === $id) {
            return $text;
        }
        $class = ucfirst($class);
        // Each arm spells its whole msgid out. A format string built from a
        // variable never reaches the catalogue -- xgettext reads source, not
        // runtime -- so the sentence has to be a literal per case.
        if ('' !== $label) {
            switch ($type) {
                case self::TYPE_UPDATE:
                    return sprintf(_('%1$s "%2$s" (ID %3$s) was saved'), $class, $label, $id);
                case self::TYPE_UPDATE_FAILED:
                    return sprintf(_('%1$s "%2$s" (ID %3$s) failed to save'), $class, $label, $id);
                case self::TYPE_DELETE:
                    return sprintf(_('%1$s "%2$s" (ID %3$s) was deleted'), $class, $label, $id);
                case self::TYPE_DELETE_FAILED:
                    return sprintf(_('%1$s "%2$s" (ID %3$s) failed to delete'), $class, $label, $id);
            }
            return $text;
        }
        // Plenty of objects have no name -- an association row, a task log.
        // The prose has always dropped the name clause for those rather
        // than printing an empty one.
        switch ($type) {
            case self::TYPE_UPDATE:
                return sprintf(_('%1$s (ID %2$s) was saved'), $class, $id);
            case self::TYPE_UPDATE_FAILED:
                return sprintf(_('%1$s (ID %2$s) failed to save'), $class, $id);
            case self::TYPE_DELETE:
                return sprintf(_('%1$s (ID %2$s) was deleted'), $class, $id);
            case self::TYPE_DELETE_FAILED:
                return sprintf(_('%1$s (ID %2$s) failed to delete'), $class, $id);
        }
        return $text;
    }

}
