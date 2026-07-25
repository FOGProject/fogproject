<?php
/**
 * Filters REST list/search payloads to the user's Sites.
 *
 * PHP version 5
 *
 * @category FilterSiteMassData
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Filters REST list/search payloads to the user's Sites.
 *
 * The web AJAX list is filtered by ListSiteHosts on AJAX_DATA_DISPLAY_CHANGE,
 * but that event only fires from the management index. A REST call
 * (/api/host, /api/user, ...) goes straight through Route::listem/search and
 * would otherwise return every object regardless of site scope. This listener
 * closes that leak by trimming the already-built payload to the acting user's
 * site scope, mirroring the single-object boundary of SiteScopeCheck.
 *
 * @category FilterSiteMassData
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FilterSiteMassData extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'FilterSiteMassData';
    /**
     * The description.
     *
     * @var string
     */
    public $description = 'Restrict REST list/search results to the site scope.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node the hook works with.
     *
     * @var string
     */
    public $node = 'site';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['API_MASSDATA_MAPPING', 'filterMassData'],
        ]);
    }
    /**
     * Trim the REST list/search payload to the acting user's site scope.
     *
     * Gated on Route::$apiRequest so only genuine REST calls are touched;
     * the web AJAX list is handled elsewhere and self::$ajax is unreliable
     * (it is derived from the client-set X-Requested-With header). Unrestricted
     * users and non-scoped classes are left untouched. A restricted user with
     * no site sees an empty list (strict deny-all).
     *
     * The REST list/search path is unpaginated (FOGManagerController::limit
     * applies no LIMIT unless the caller sends DataTables start/length, which
     * only the web UI does), so data['data'] holds the whole result set and
     * the kept-row count is the true in-scope total.
     *
     * @param mixed $arguments The mass-data payload to modify.
     *
     * @return void
     */
    public function filterMassData($arguments)
    {
        if (empty(Route::$apiRequest)) {
            return;
        }
        $node = strtolower((string)$arguments['classname']);
        $scoped = ['host', 'user', 'group', 'usergroup'];
        if (!in_array($node, $scoped, true)) {
            return;
        }
        if (Authorization::isUnrestricted()) {
            return;
        }
        // Snapshot the envelope by value rather than working through a live
        // reference to Route::$data. Site::filterInScope() below issues its
        // own Route::getIds(), and getIds() finishes with `self::$data = ''`
        // -- so the payload this hook is filtering was being blanked out from
        // under it mid-method. Reading $data['data'] afterwards then hit
        // "Cannot access offset of type string on string" and 500'd the
        // request. Any restricted user listing a scoped class over REST hit
        // this; it stayed hidden because that needs an API user who holds a
        // non-'*' role, which nothing exercised until now.
        $payload = $arguments['data'];
        if (empty($payload['data']) || !is_array($payload['data'])) {
            return;
        }
        $rows = $payload['data'];
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)(
                is_array($row) ?
                ($row['id'] ?? 0) :
                ($row->id ?? 0)
            );
        }
        $userID = (int)self::$FOGUser->get('id');
        $allowed = array_flip(
            Site::filterInScope($node, $ids, $userID)
        );
        $kept = [];
        foreach ($rows as $ind => $row) {
            if (isset($allowed[$ids[$ind]])) {
                $kept[] = $row;
            }
        }
        if (count($kept) === count($rows)) {
            // Nothing filtered, but Route::$data may still have been clobbered
            // by the lookups above, so restore the snapshot before returning
            // instead of leaving the caller with an empty payload.
            $arguments['data'] = $payload;
            return;
        }
        $payload['data'] = array_values($kept);
        $payload['recordsFiltered'] = count($kept);
        $payload['recordsTotal'] = count($kept);
        // Single write-back through the reference the HookManager handed us.
        $arguments['data'] = $payload;
    }
}
