<?php
/**
 * FastRoute's route parser, with optional segments tried longest-first.
 *
 * PHP version 7.4+
 *
 * @category LongestFirstRouteParser
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.com/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */

namespace FOG\Router;

use FastRoute\RouteParser\Std;

/**
 * FastRoute's route parser, with optional segments tried longest-first.
 *
 * FastRoute expands a route with optional segments into one route per
 * prefix -- `/a/{b}[/{c}]` becomes `/a/{b}` and `/a/{b}/{c}` -- and Std
 * returns them SHORTEST first, which is the order they are then matched
 * in. AltoRouter, which this router replaced, matched the whole route as a
 * single regex whose optional group is greedy, so the longer form was
 * always attempted before it was given up.
 *
 * The two orders agree until a capture that can contain a slash precedes
 * an optional segment. `/[search|unisearch]/[*:item]/[i:limit]?` is that
 * route: `/unisearch/a/5` under shortest-first matches `/{_}/{item:.+?}`
 * with item=`a/5` and no limit at all, because a lazy quantifier still
 * runs to the end of the path when nothing after it needs the characters.
 * The search box searched for "a/5" and found nothing, for every term.
 *
 * Reversing the expansion restores the greedy-optional semantics every
 * route in the table was written against, and costs nothing for routes
 * where the two orders agree: a longer form that does not match falls
 * through to the shorter one exactly as before.
 *
 * @category LongestFirstRouteParser
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.com/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
class LongestFirstRouteParser extends Std
{
    /**
     * Parses a route string into its optional-segment variants, longest
     * first.
     *
     * @param string $route The route string.
     *
     * @return array
     */
    public function parse($route)
    {
        return array_reverse(parent::parse($route));
    }
}
