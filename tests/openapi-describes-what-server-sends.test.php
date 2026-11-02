<?php
/**
 * The OpenAPI document must describe what the API sends.
 *
 * GH-1953. An audit of a live server against system/openapi found places
 * where the document was wrong, not the server:
 *
 *   - Plugin classes were typed `string` throughout. _entitySchema() read
 *     column types from the core manifest only, which carries no plugin
 *     table. FOGBase::columnType() falls back to the server catalog.
 *   - createdBy and createdTime were dropped because they are not settable.
 *     They are returned, so they are documented readOnly.
 *   - ?expand was offered on every class. Route::relationMap() has entries
 *     for host only.
 *   - /availablekernels and /availableinitrds said "present on disk". They
 *     list FOS releases from GitHub.
 *   - DELETE, the cancel routes and POST /{class}/{id}/task answer 200 with
 *     a placeholder body. The document promised {msg} or a task envelope.
 *   - TaskRequest lacked snapinAbortOnFailure and did not require taskTypeID.
 *   - task.pct (zerofill) is a string; tasktype and multicastsession single
 *     GETs carry fields the schema did not name; list rows carry DT_RowId,
 *     mainlink and *Link keys; the envelope carries _unadjustednote.
 *   - PUT /system/userpref/{key}, POST /unisearch and DELETE /{class}/cancel
 *     were served and not described.
 *
 * Calls OpenAPI::document() for real, against the fake database, so each
 * check reads the generated document rather than the source text.
 *
 * Usage: php tests/openapi-describes-what-server-sends.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('openapi-sends');
$db = FogTestHarness::fakeDb();
$t = new FogChecks();

// A plugin-shaped model: its table is not in the core manifest, so only the
// server catalog can type it.
class Gh1953Widget extends \FOG\Base\FOGController
{
    protected $databaseTable = 'gh1953Widgets';
    protected $databaseFields = [
        'id' => 'gwID',
        'name' => 'gwName',
        'port' => 'gwPort',
        'mode' => 'gwMode',
        'note' => 'gwNote',
    ];
}

$db->responder = function ($sql, $params) {
    if (false === strpos($sql, 'information_schema')) {
        return null;
    }
    if (strtolower((string)($params[':table'] ?? '')) !== 'gh1953widgets') {
        return [];
    }
    // The shape _loadPluginColumnTypes() reads: COLUMN_TYPE has no NOT NULL
    // and no DEFAULT, IS_NULLABLE is separate.
    return [
        ['c' => 'gwID', 'ty' => 'int(11)', 'n' => 'NO'],
        ['c' => 'gwName', 'ty' => 'varchar(64)', 'n' => 'NO'],
        ['c' => 'gwPort', 'ty' => 'int(11)', 'n' => 'NO'],
        ['c' => 'gwMode', 'ty' => "enum('a','b')", 'n' => 'NO'],
        ['c' => 'gwNote', 'ty' => 'text', 'n' => 'YES'],
    ];
};
$valid = FogTestHarness::getStatic('Route', 'validClasses');
$valid[] = 'gh1953widget';
FogTestHarness::setStatic('Route', 'validClasses', $valid);

$doc = \FOG\Router\OpenAPI::document();
$schemas = $doc['components']['schemas'];
$paths = $doc['paths'];

// 1. Plugin columns are typed from the catalog.
$w = $schemas[\FOG\Router\OpenAPI::schemaName('gh1953widget')]['properties'] ?? [];
$t->check('plugin int column is integer', ($w['port']['type'] ?? '') === 'integer');
$t->check('plugin id is integer', ($w['id']['type'] ?? '') === 'integer');
$t->check('plugin enum keeps its values', ($w['mode']['enum'] ?? []) === ['a', 'b']);
$t->check('plugin varchar keeps its length', ($w['name']['maxLength'] ?? 0) === 64);
$t->check('plugin NOT NULL column is not nullable', empty($w['port']['nullable']));
$t->check('plugin nullable column is nullable', !empty($w['note']['nullable']));
$t->check(
    'plugin column no longer says "No type information"',
    false === strpos((string)($w['port']['description'] ?? ''), 'No type information')
);

// 2. createdBy / createdTime are documented readOnly, not omitted.
$h = $schemas['Host']['properties'];
$t->check('host createdBy present', isset($h['createdBy']));
$t->check('host createdBy readOnly', !empty($h['createdBy']['readOnly']));
$t->check('host createdTime readOnly', !empty($h['createdTime']['readOnly']));

// 3. expand only where relationMap() has entries.
$hasExpand = function ($op) {
    foreach ((array)($op['parameters'] ?? []) as $p) {
        if (($p['$ref'] ?? '') === '#/components/parameters/expand') {
            return true;
        }
    }
    return false;
};
$t->check('host list offers expand', $hasExpand($paths['/host']['get']));
$t->check('host get offers expand', $hasExpand($paths['/host/{id}']['get']));
$t->check('image list does not offer expand', !$hasExpand($paths['/image']['get']));
$t->check('image search does not offer expand', !$hasExpand($paths['/image/search/{item}']['get']));
$t->check(
    'host list names the tokens and the companion keys',
    false !== strpos(($paths['/host']['get']['description'] ?? ''), 'snapins')
    && false !== strpos(($paths['/host']['get']['description'] ?? ''), '<token>_total')
);

// 4. FOS releases, not files on disk.
foreach (['/availablekernels', '/availableinitrds'] as $p) {
    $op = $paths[$p]['get'];
    $t->check("$p does not say on disk", false === stripos($op['summary'], 'on disk'));
    $t->check("$p says GitHub", false !== strpos($op['description'] ?? '', 'GitHub'));
}

// 5. Success bodies are placeholders, not {msg} or an envelope.
$emptyOk = function ($op) {
    $r = $op['responses']['200'] ?? null;
    return null !== $r && !isset($r['content']);
};
$t->check('DELETE /host/{id} 200 has no body schema', $emptyOk($paths['/host/{id}']['delete']));
$t->check('DELETE /task/{id}/cancel 200 has no body schema', $emptyOk($paths['/task/{id}/cancel']['delete']));
$t->check('POST /host/{id}/task 200 has no body schema', $emptyOk($paths['/host/{id}/task']['post']));
$t->check('DELETE /task/cancel is not offered (needs an id)', !isset($paths['/task/cancel']));
$t->check('DELETE /host/cancel is not offered (needs an id)', !isset($paths['/host/cancel']));
$t->check('DELETE /group/cancel is not offered (needs an id)', !isset($paths['/group/cancel']));

// 6. TaskRequest.
$tr = $schemas['TaskRequest'];
$t->check('TaskRequest requires taskTypeID', ($tr['required'] ?? []) === ['taskTypeID']);
$t->check('TaskRequest has snapinAbortOnFailure', isset($tr['properties']['snapinAbortOnFailure']));

// 7. Field values.
$pct = $schemas['Task']['properties']['pct'];
$t->check('task.pct is a string', ($pct['type'] ?? '') === 'string');
$tt = $schemas['Tasktype']['properties'];
foreach (['isCapture', 'isDeploy', 'isInitNeeded', 'isMulticast', 'isDebug', 'isImagingTask'] as $f) {
    $t->check("tasktype documents $f", ($tt[$f]['type'] ?? '') === 'boolean');
}
$t->check('tasktype documents initIDs', ($tt['initIDs']['type'] ?? '') === 'array');
$ms = $schemas['Multicastsession']['properties'];
$t->check('multicastsession has imageID', ($ms['imageID']['type'] ?? '') === 'integer');
$t->check('multicastsession image may be the embedded object', isset($ms['image']['oneOf']));
$t->check('multicastsession does not claim imagename', !isset($ms['imagename']));

// 8. List rows and the envelope.
$t->check('host rows document DT_RowId', isset($h['DT_RowId']));
$t->check('host rows document mainlink', isset($h['mainlink']));
$t->check('host rows document imageLink', isset($h['imageLink']));
$t->check('envelope documents _unadjustednote', isset($schemas['ListEnvelope']['properties']['_unadjustednote']));
$t->check('page documents _unadjustednote', isset($schemas['HostPage']['properties']['_unadjustednote']));

// 9. Routes that were served and not described.
$t->check('PUT /system/userpref/{key}', isset($paths['/system/userpref/{key}']['put']));
$t->check('POST /unisearch', isset($paths['/unisearch']['post']));

// 10. The public feature header is a comma-separated token list. Its example
// is tied to the literal emitted by the Route handler; the JSON body
// advertises the same two directory features.
$enroll = $paths['/agent/v1/enroll']['get'];
$featureHeader = $enroll['responses']['200']['headers']['X-FOG-Agent-Features'] ?? [];
$featureHeaderSchema = $featureHeader['schema'] ?? [];
$featureHeaderDescription = (string)($featureHeader['description'] ?? '');
// The handler cannot run here (it sends headers and exits), so read its
// source: it must build the header from the two FEATURE constants joined by
// ', ', and the example must equal what that produces.
$featuresMethod = new \ReflectionMethod(\FOG\Router\Route::class, 'agentFeatures');
$featuresSource = implode('', array_slice(
    file((string)$featuresMethod->getFileName()),
    $featuresMethod->getStartLine() - 1,
    $featuresMethod->getEndLine() - $featuresMethod->getStartLine() + 1
));
$advertisedFeatureHeader = implode(', ', [
    \FOG\Agent\MachineIdentity::FEATURE,
    \FOG\Agent\DirectoryOperation::FEATURE
]);
$t->check('enrollment feature handler builds its header from both FEATURE constants',
    false !== strpos($featuresSource, 'MachineIdentity::FEATURE')
    && false !== strpos($featuresSource, 'DirectoryOperation::FEATURE')
    && false !== strpos($featuresSource, "header('X-FOG-Agent-Features: ' . implode(', ', \$features))"));
$t->check('enrollment feature header is a string without individual-token enum',
    ($featureHeaderSchema['type'] ?? '') === 'string' && !isset($featureHeaderSchema['enum']));
$t->check('enrollment feature header description lists both comma-separated tokens',
    false !== stripos($featureHeaderDescription, 'comma-separated')
    && false !== strpos($featureHeaderDescription, 'directory-join-target-v1')
    && false !== strpos($featureHeaderDescription, 'directory-task-operations-v1'));
$t->check('enrollment feature header example matches the advertised features',
    ($featureHeaderSchema['example'] ?? null) === $advertisedFeatureHeader);
$enrollBodyFeatures = $enroll['responses']['200']['content']['application/json']['schema']['properties']['features'] ?? [];
$t->check('enrollment JSON body advertises both directory features',
    ($enrollBodyFeatures['type'] ?? '') === 'array'
    && ($enrollBodyFeatures['items']['enum'] ?? []) === ['directory-join-target-v1', 'directory-task-operations-v1']);
$enrollPost = $paths['/agent/v1/enroll']['post'];
$t->check('POST /agent/v1/enroll documents the 409 refusal with a reason',
    isset($enrollPost['responses']['409']['content']['application/json']['schema']['properties']['reason']));
$t->check('POST /agent/v1/enroll accepts the features list',
    ($enrollPost['requestBody']['content']['application/json']['schema']['properties']['features']['items']['enum'] ?? [])
    === ['directory-join-target-v1']);
$identityCheck = $paths['/agent/v1/identity/check']['post'] ?? [];
$t->check('identity/check answers 200 and 409',
    isset($identityCheck['responses']['200'], $identityCheck['responses']['409']));

// 11. Task-driven directory operations retain the existing routes and bind
// the concrete operation and Task identity in their documented contracts.
$poll = $paths['/agent/v1/poll']['post'];
$pollResponse = $poll['responses']['200']['content']['application/json']['schema']['properties'] ?? [];
$directoryPlan = $pollResponse['directory_rejoin'] ?? [];
$directoryPlanProperties = $directoryPlan['properties'] ?? [];
$planAlternatives = $directoryPlan['anyOf'] ?? [];
$pollRequest = $poll['requestBody']['content']['application/json']['schema']['properties'] ?? [];
$pollFeatures = $pollRequest['features']['items']['enum'] ?? [];
$t->check('poll declares directory-task-operations-v1', in_array('directory-task-operations-v1', $pollFeatures, true));
$directoryAttempt = $pollRequest['directory_attempt'] ?? [];
$directoryAttemptProperties = $directoryAttempt['properties'] ?? [];
$t->check('directory_attempt is required for the target-operation feature',
    ($directoryAttempt['x-required-when-feature'] ?? '') === 'directory-task-operations-v1');
$t->check('directory_attempt requires marker summary fields',
    ($directoryAttempt['required'] ?? []) === ['phase', 'attempt_id', 'host_id', 'fingerprint']);
$t->check('directory_attempt documents phase vocabulary',
    ($directoryAttemptProperties['phase']['enum'] ?? []) === ['none', 'unreadable', 'reserved', 'may_have_run', 'unknown', 'failed', 'awaiting_reboot', 'verified']);
$t->check('directory_attempt documents marker summary types',
    ($directoryAttemptProperties['attempt_id']['type'] ?? '') === 'string'
    && ($directoryAttemptProperties['host_id']['type'] ?? '') === 'integer'
    && ($directoryAttemptProperties['fingerprint']['type'] ?? '') === 'string');
$t->check('poll documents directory_hold boolean', ($pollResponse['directory_hold']['type'] ?? '') === 'boolean');
$t->check('directory plan remains nullable with conditional shapes',
    !empty($directoryPlan['nullable']) && count($planAlternatives) === 2);
$blockedPlan = null;
$concretePlan = null;
foreach ($planAlternatives as $alternative) {
    $required = $alternative['required'] ?? [];
    if (in_array('phase', $required, true) && in_array('reason', $required, true)) {
        $blockedPlan = $alternative;
    }
    if (in_array('version', $required, true) && in_array('attempt_id', $required, true)
        && in_array('operation', $required, true) && in_array('task_id', $required, true)) {
        $concretePlan = $alternative;
    }
}
$t->check('blocked directory plan requires blocked phase and reason',
    in_array('blocked', $blockedPlan['properties']['phase']['enum'] ?? [], true)
    && ($blockedPlan['properties']['reason']['type'] ?? '') === 'string');
$t->check('concrete directory plan requires task_id', is_array($concretePlan));
$t->check('concrete directory plan phase excludes blocked',
    is_array($concretePlan)
    && !in_array('blocked', $concretePlan['properties']['phase']['enum'] ?? [], true));
$t->check('directory plan task_id is nonnegative integer',
    ($directoryPlanProperties['task_id']['type'] ?? '') === 'integer'
    && 0 === ($directoryPlanProperties['task_id']['minimum'] ?? null));
$t->check('directory plan documents Workgroup join operation and mode',
    in_array('join', $directoryPlanProperties['operation']['enum'] ?? [], true)
    && in_array('join', $directoryPlanProperties['mode']['enum'] ?? [], true));

$start = $paths['/agent/v1/directory/rejoin/start']['post'];
$startRequest = $start['requestBody']['content']['application/json']['schema'] ?? [];
$startProperties = $startRequest['properties'] ?? [];
$startTargetSchema = $start['responses']['200']['content']['application/json']['schema']['properties']
    ['directory']['properties']['rejoin']['properties']['target'] ?? [];
$startTargetDescription = strtolower((string)($startTargetSchema['description'] ?? ''));
$t->check('rejoin/start response Target remains nullable object',
    ($startTargetSchema['type'] ?? '') === 'object' && !empty($startTargetSchema['nullable']));
$t->check('rejoin/start Target describes Existing Rebind and Rename GUID/SID pinning',
    false !== strpos($startTargetDescription, 'existing rebind')
    && false !== strpos($startTargetDescription, 'ordinary rename')
    && false !== strpos($startTargetDescription, 'guid/sid')
    && false !== strpos($startTargetDescription, 'pin'));
$t->check('rejoin/start Target describes pre-creation Create and Workgroup Join null cases',
    false !== strpos($startTargetDescription, 'create')
    && false !== strpos($startTargetDescription, 'before')
    && false !== strpos($startTargetDescription, 'workgroup join')
    && false !== strpos($startTargetDescription, 'null'));
$t->check('rejoin/start requires operation and task_id',
    in_array('operation', $startRequest['required'] ?? [], true)
    && in_array('task_id', $startRequest['required'] ?? [], true));
$t->check('rejoin/start accepts join and nonnegative task_id',
    in_array('join', $startProperties['operation']['enum'] ?? [], true)
    && ($startProperties['task_id']['type'] ?? '') === 'integer'
    && 0 === ($startProperties['task_id']['minimum'] ?? null));

$resultItem = $paths['/agent/v1/result']['post']['requestBody']['content']['application/json']['schema']['properties']['item']['properties'] ?? [];
$t->check('result item documents task_id and netbios',
    ($resultItem['task_id']['type'] ?? '') === 'integer'
    && ($resultItem['netbios']['type'] ?? '') === 'string');

$t->finish();
