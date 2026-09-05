<?php
/**
 * Enrollment tokens: the credential that lets a machine enroll without an
 * admin clicking, from fog-agent design 0001 (agent-based registration).
 *
 * PHP version 7.4+
 *
 * @category Token
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\AgentEnrollToken;
use FOG\Router\Route;

/**
 * Mints, lists and revokes enrollment tokens.
 *
 * Only the sha256 of a token is stored, so the token itself is shown
 * exactly once, in the answer to the mint. An expiry is required: a token
 * that never lapses is a standing credential sitting in an image or a
 * runbook, and the design's own golden-image case (0001 section 4.2) is
 * served by a token that outlives the rollout and not the year. Uses
 * count down to zero; -1 is unlimited until the expiry.
 *
 * Consumption lives in Enrollment::_consumeToken(), which is the only
 * reader of the hash; this class is the admin's side.
 *
 * @category Token
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Token extends FOGBase
{
    const UNLIMITED = -1;

    /**
     * Mints a token and returns it, once.
     *
     * @param string $name    what the admin calls it (a rollout, a site)
     * @param int    $uses    how many enrollments it approves; -1 unlimited
     * @param string $expires 'Y-m-d H:i:s', must be in the future
     * @param string $by      the minting user's name
     *
     * @throws \RuntimeException 400 on a bad field
     *
     * @return array ['token' => the secret, 'row' => AgentEnrollToken]
     */
    public static function mint($name, $uses, $expires, $by)
    {
        $name = trim((string)$name);
        if ('' === $name || strlen($name) > 191) {
            throw new \RuntimeException('name is required, at most 191 characters', 400);
        }
        $uses = (int)$uses;
        if ($uses < 1 && self::UNLIMITED !== $uses) {
            throw new \RuntimeException('uses must be at least 1, or -1 for unlimited', 400);
        }
        $expires = trim((string)$expires);
        if (!self::validDate($expires) || strtotime($expires) <= time()) {
            throw new \RuntimeException('expires must be a date and time in the future', 400);
        }
        $expires = date('Y-m-d H:i:s', strtotime($expires));
        // 24 random bytes as hex: 48 characters that survive every shell,
        // every clipboard and every unattended-install file unquoted.
        $secret = bin2hex(random_bytes(24));
        $Row = new AgentEnrollToken();
        $Row->set('name', $name)
            ->set('hash', hash('sha256', $secret))
            ->set('uses', $uses)
            ->set('expires', $expires)
            ->set('createdBy', (string)$by)
            ->set('created', self::niceDate()->format('Y-m-d H:i:s'));
        if (!$Row->save()) {
            throw new \RuntimeException('could not store the token', 500);
        }
        Audit::record(
            [
                'type' => 'agent.token',
                'subjectType' => 'agentenrolltoken',
                'subjectID' => (int)$Row->get('id'),
                'subjectLabel' => $name,
                'renderable' => 1,
                'text' => sprintf(
                    'minted, %s, expires %s',
                    self::UNLIMITED === $uses ? 'unlimited uses' : $uses . ' use(s)',
                    $expires
                )
            ]
        );
        return ['token' => $secret, 'row' => $Row];
    }

    /**
     * Revokes a token: the row goes, so the hash can never match again.
     *
     * @param int    $id the token row
     * @param string $by the revoking user's name
     *
     * @throws \RuntimeException 404 when there is no such token
     *
     * @return void
     */
    public static function revoke($id, $by)
    {
        $Row = new AgentEnrollToken((int)$id);
        if (!$Row->isValid()) {
            throw new \RuntimeException('no such token', 404);
        }
        $name = (string)$Row->get('name');
        $Row->destroy();
        Audit::record(
            [
                'type' => 'agent.token',
                'subjectType' => 'agentenrolltoken',
                'subjectID' => (int)$id,
                'subjectLabel' => $name,
                'renderable' => 1,
                'text' => 'revoked by ' . $by
            ]
        );
    }

    /**
     * Every token, for the admin's list. Never the hash.
     *
     * @return array
     */
    public static function rows()
    {
        $now = time();
        $rows = [];
        foreach ((array)Route::getList('agentenrolltoken', [], 'AND', 'id') as $row) {
            $row = (array)$row;
            $uses = (int)($row['uses'] ?? 0);
            $expires = (string)($row['expires'] ?? '');
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'uses' => $uses,
                'expires' => $expires,
                'createdBy' => (string)($row['createdBy'] ?? ''),
                'created' => (string)($row['created'] ?? ''),
                // What the list shows at a glance: a token that can no
                // longer approve anything, and why.
                'state' => 0 === $uses ? 'spent'
                    : (self::validDate($expires) && strtotime($expires) < $now ? 'expired' : 'active')
            ];
        }
        return $rows;
    }
}
