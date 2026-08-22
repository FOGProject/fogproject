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

namespace FOG;

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
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\History', 'History');
