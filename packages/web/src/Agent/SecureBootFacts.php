<?php
/**
 * Secure Boot posture an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category SecureBoot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Boot\SecureBootState;
use FOG\Items\Host;
use FOG\Managers\HostManager;

/**
 * Writes a reported Secure Boot posture onto hosts.hostSbState (design
 * 0012).
 *
 * A fact report like InventoryFacts, and registered the same way: an entry
 * in State::FACT_REPORTS and a block in the poll, never a route of its own
 * (the route rule, protocol-v1.md).
 *
 * WHY A SECOND REPORTER AT ALL. hostSbState is written by iPXE on every PXE
 * boot, which is the right place for it: iPXE runs whenever the machine
 * netboots, where FOS runs only when someone schedules a task. But a machine
 * that boots from its own disk never netboots, so for that machine the value
 * is frozen at whatever it said the last time anybody imaged it -- and the
 * staleness runs in the dangerous direction. `disabled` is the value that
 * makes a host look like a valid enrollment target, and it is exactly what a
 * machine leaves behind on the last netboot before it starts enforcing.
 *
 * OBSERVATIONS, NOT A VERDICT. The agent sends the same three raw values
 * iPXE sends -- platform, the SecureBoot byte, the SetupMode byte -- and
 * this class maps them with SecureBootState::fromBootRequest(), the very
 * call the boot path uses. The six state names were copied verbatim from
 * FOS's own sbState() so the reporters could not drift into two
 * vocabularies for one fact; letting the agent send a computed name would
 * reintroduce that drift with a third implementation, in Go.
 *
 * STILL ADVISORY (ADR 0029). An agent report arrives over an enrolled mTLS
 * channel, so unlike an anonymous boot request the server knows whose
 * certificate asserted it. That is attribution, not trust: a compromised
 * operating system can lie about its own firmware. Nothing may read this
 * column as a security control -- it is for targeting, filtering and
 * display, exactly as before.
 *
 * @category SecureBoot
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SecureBootFacts extends FOGBase
{
    /**
     * Records a reported Secure Boot posture on the host row.
     *
     * Reached only when the server's hash for the block moved, so every
     * call here is a real change in what the machine says about itself.
     *
     * @param Host  $Host  the host the certificate bound
     * @param array $block the reported observations
     *
     * @return void
     */
    public static function report(Host $Host, array $block)
    {
        // null and '' are DIFFERENT inputs to fromBootRequest(): '' means
        // the machine looked and found nothing readable, null means it did
        // not answer at all. Casting a missing key to '' would turn a
        // malformed block into "UEFI, state unreadable", which asserts an
        // observation about a machine that made none.
        $state = SecureBootState::fromBootRequest(
            (string)($block['platform'] ?? ''),
            array_key_exists('secure_boot', $block)
                ? (string)$block['secure_boot'] : null,
            array_key_exists('setup_mode', $block)
                ? (string)$block['setup_mode'] : null
        );
        if (!SecureBootState::isKnown($state)
            || SecureBootState::UNKNOWN === $state
        ) {
            // fromBootRequest() answers UNKNOWN when it was given nothing
            // at all. Writing that would erase a real observation and
            // replace it with "nobody has ever said", which is worse than
            // the stale value it overwrote -- and the agent does not send
            // the block at all when it has nothing to say, so arriving
            // here means a malformed report rather than an honest silence.
            return;
        }

        $hostID = (int)$Host->get('id');
        $previous = (string)$Host->get('sbstate');

        // The manager rather than Host::save(), as agentPoll and
        // Enrollment::renew do: a save rewrites the MAC association, and a
        // fact report is a routine call on every poll where the posture
        // moved.
        //
        // The time is the SERVER's, on storageTimeZone() like every other
        // datetime FOG writes. A client-supplied timestamp on a
        // client-supplied observation is two lies for the price of one, and
        // the question the column answers -- how stale is this -- is only
        // meaningful in the server's own time base.
        (new HostManager())->update(
            ['id' => $hostID],
            '',
            [
                // PROPERTY names, not column names: update() looks each one
                // up in Host::$databaseFields, so 'hostSbState' resolves to
                // nothing and builds an UPDATE with an empty column.
                'sbstate' => $state,
                'sbstatetime' => self::niceDate()
                    ->setTimezone(self::storageTimeZone())
                    ->format('Y-m-d H:i:s')
            ]
        );

        // Renderable, and naming both ends of the move: "enforcing" alone
        // does not tell an admin that this host just stopped being a valid
        // enrollment target, which is the thing worth seeing in a list.
        Audit::record(
            [
                'type' => 'agent.secureboot',
                'subjectType' => 'host',
                'subjectID' => $hostID,
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => 1,
                'text' => substr(
                    // No "Secure Boot" prefix: label() already spells it
                    // where it belongs ("Secure Boot ON"), and prepending
                    // produced "agent reported Secure Boot Secure Boot ON"
                    // on the first real report. It reads correctly for the
                    // labels that do NOT carry the words, too -- "agent
                    // reported UEFI, state unreadable".
                    sprintf(
                        'agent reported %s%s',
                        SecureBootState::label($state),
                        '' === $previous || $previous === $state
                            ? ''
                            : ' (was ' . SecureBootState::label($previous) . ')'
                    ),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }
}
