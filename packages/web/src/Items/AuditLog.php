<?php
/**
 * One row per authorized action.
 *
 * PHP version 7.4+
 *
 * @category AuditLog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * One row per authorized action.
 *
 * The header half of ADR 0021's two-table audit trail. Written at the
 * authorization seam, where the actor, the permission and the outcome are all
 * known -- not at save(), where denials are unreachable by definition and one
 * UI action splinters into a dozen rows.
 *
 * DELIBERATELY ABSENT FROM Route::$validClasses. There is no create route, no
 * edit route and no delete route onto this table, and that is the strongest
 * thing FOG can honestly offer: the web tier holds GRANT ALL, so anyone with
 * the database credential can rewrite these rows and no application design
 * changes that. Append-only by construction of the application, not by
 * cryptographic guarantee -- see ADR 0021 Decision 8, and do not oversell it.
 *
 * @category AuditLog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AuditLog extends FOGController
{
    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'auditLog';
    /**
     * Friendly names to column names.
     *
     * createdTime, createdBy and ip are ADR 0020's frame, spelled exactly as
     * history and taskLog spell them, so one viewer can read all of them.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'alID',
        'createdTime' => 'alCreatedTime',
        'createdBy' => 'alCreatedBy',
        'ip' => 'alIP',
        'authSource' => 'alAuthSource',
        'type' => 'alType',
        'subjectType' => 'alSubjectType',
        'subjectID' => 'alSubjectID',
        'subjectLabel' => 'alSubjectLabel',
        'permission' => 'alPermission',
        'outcome' => 'alOutcome',
        'correlationID' => 'alCorrelationID',
        'affectedCount' => 'alAffectedCount',
        'renderable' => 'alRenderable',
        'text' => 'alText'
    ];
    /**
     * Keys ending in "id" that are NOT foreign keys.
     *
     * save() infers "this is a foreign key" from the name ending in "id",
     * and correlationID is 32 hex characters. Left out, filter_var would
     * fail on it and write 0 instead -- silently, with no error anywhere --
     * so every audit row would claim to belong to correlation 0 and the join
     * that makes one action one operation would be gone.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = [
        'correlationID'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\AuditLog', 'AuditLog');
