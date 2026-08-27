<?php
/**
 * One row per changed field.
 *
 * PHP version 7.4+
 *
 * @category AuditChange
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * One row per changed field.
 *
 * The detail half of ADR 0021's audit trail, joined to its header by auditID.
 *
 * Two invariants live in the writers rather than here, and both are HARD:
 * a credential's value is never stored (Redaction decides, and a redacted row
 * carries NULL in both value columns with redacted = 1), and a DELETE writes
 * no rows in this table at all -- a per-column inventory of a deleted host is
 * a full credential dump wearing an audit badge.
 *
 * subjectType/subjectID repeat the header's because one header can cover many
 * objects: an iterating path that saves forty hosts writes one header and
 * change rows for each host that landed.
 *
 * Like AuditLog, deliberately absent from Route::$validClasses.
 *
 * @category AuditChange
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AuditChange extends FOGController
{
    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'auditChange';
    /**
     * Friendly names to column names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'acID',
        'auditID' => 'acAuditID',
        'subjectType' => 'acSubjectType',
        'subjectID' => 'acSubjectID',
        'field' => 'acField',
        'oldValue' => 'acOldValue',
        'newValue' => 'acNewValue',
        'redacted' => 'acRedacted'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\AuditChange', 'AuditChange');
