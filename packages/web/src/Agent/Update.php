<?php
/**
 * The version an agent should be running, and where to find out what it is.
 *
 * PHP version 7.4+
 *
 * @category Update
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;

/**
 * Self-update, design 0015.
 *
 * This server names a version and nothing else. What that version IS comes
 * from a release manifest signed by FOG Project, which the agent verifies
 * against a root compiled into its own binary before it will run a byte of
 * it. So a compromised or simply mistaken FOG server can choose which
 * PUBLISHED version a fleet runs, and cannot publish one -- which is the
 * boundary that makes it safe for an update channel to reach every managed
 * machine automatically with no human in the loop.
 *
 * That is also why `manifest_url` can be sent from here at all. A hostile
 * server pointing an agent at its own mirror achieves one of three things:
 * the mirror serves nothing, it serves an older signed manifest (refused by
 * the agent's sequence floor), or it serves the real one. There is no
 * fourth option, so the URL needs no trust and no permission of its own.
 *
 * There is no legacy module behind this capability, so unlike every other
 * one it is not gated on `moduleStatusByHost`. It is gated on an admin
 * having set a version, and the shipped default is empty: no host begins
 * updating itself because somebody upgraded their server.
 *
 * @category Update
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Update extends FOGBase
{
    /**
     * The capability name on the wire.
     */
    const CAPABILITY = 'update';

    /**
     * The version this host should be running, or '' for none.
     *
     * The host's own value is an OVERRIDE, not a floor: it wins even when
     * it is lower than the global. That is deliberate and it is the whole
     * recovery story. The likeliest shape of a bad release is one that
     * installs, starts and polls perfectly well and then behaves badly --
     * local rollback cannot catch that, because by every local measure the
     * agent is healthy. The only fix is the server naming an older
     * version, and a max() of host and global could not express it.
     *
     * The cost is that an override is sticky, and a forgotten one holds a
     * machine back silently. That is paid for by showing it: the host list
     * says which hosts carry one, so "everything not following the fleet"
     * is a filter and clearing them is one mass edit.
     *
     * @param Host $Host the host asking
     *
     * @return string
     */
    public static function version(Host $Host)
    {
        $own = trim((string)$Host->get('agentDesiredVersion'));
        if ('' !== $own) {
            return $own;
        }
        return trim((string)self::getSetting('FOG_AGENT_DESIRED_VERSION'));
    }

    /**
     * The update block of the desired state, or null when this host has no
     * desired version and the capability is not offered at all.
     *
     * @param Host $Host the host asking
     *
     * @return array|null
     */
    public static function desired(Host $Host)
    {
        $version = self::version($Host);
        if ('' === $version) {
            return null;
        }
        $block = ['desired' => $version];
        // Sent only when a mirror is configured. An absent key means "the
        // location built into the agent", which is a different statement
        // from an empty one and the agent reads it as such.
        $url = trim((string)self::getSetting('FOG_AGENT_UPDATE_MANIFEST_URL'));
        if ('' !== $url) {
            $block['manifest_url'] = $url;
        }
        return $block;
    }
}
