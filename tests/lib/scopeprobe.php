<?php
/**
 * A plugin listener standing in for the site plugin's AddSiteAPI::scopeIDs().
 *
 * Its own file, and required only after Initiator has run, because it extends
 * Hook -- a class the autoloader has to be registered before it can resolve.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * A plugin listener standing in for the site plugin's AddSiteAPI::scopeIDs().
 *
 * The real hook is exercised end to end by the HTTP probe in
 * scripts/background_scripts/; what this file needs is a listener whose answer
 * it can set, so that the THREE STATES are reachable without a site plugin, a
 * restricted user and a membership table. 'none' means the handler returns
 * without touching $arguments -- which is the no-plugin and the no-acting-user
 * case both, since that is exactly what AddSiteAPI does when FOGUser is
 * invalid.
 */
class ScopeProbe extends Hook
{
    public $name = 'ScopeProbe';
    public $description = 'Answers API_SCOPE_IDS with a fixed value.';
    public $active = true;

    /** @var mixed 'none', or the array to answer API_SCOPE_IDS with. */
    public static $answer = 'none';

    /**
     * What to answer API_SCOPE_WHERE with.
     *
     * 'none' means do not answer, which is the state every third-party plugin
     * written before the fragment event existed is permanently in. A callable
     * is handed the caller's own $idExpr, because a fragment that hardcoded a
     * column name would be testing a string rather than the seam.
     *
     * @var mixed
     */
    public static $whereAnswer = 'none';

    /**
     * @param mixed $arguments the event payload
     *
     * @return void
     */
    public function scope($arguments)
    {
        if ('none' === self::$answer) {
            return;
        }
        $arguments['ids'] = self::$answer;
    }

    /**
     * @param mixed $arguments the event payload
     *
     * @return void
     */
    public function scopeWhere($arguments)
    {
        if ('none' === self::$whereAnswer) {
            return;
        }
        $arguments['where'] = is_callable(self::$whereAnswer)
            ? call_user_func(self::$whereAnswer, $arguments['idExpr'])
            : self::$whereAnswer;
    }
}
