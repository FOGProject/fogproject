<?php
/**
 * Starting and ending an impersonation span.
 *
 * PHP version 7.4+
 *
 * @category ImpersonateManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Auth\Authorization;
use FOG\Auth\Identity;
use FOG\Base\FOGPage;
use FOG\Items\User;

/**
 * Starting and ending an impersonation span.
 *
 * The node is in Authorization::EXEMPT_NODES, so nothing upstream
 * permission-checks this page. That is not an oversight and it is not a hole:
 *
 *  - ENDING must never be checked, or impersonating a user who holds no
 *    roles traps the administrator inside them with no way back.
 *  - STARTING is checked HERE, against Identity::PERMISSION plus the two
 *    subset tests, because "may this person become that person" is a
 *    question about a PAIR of users and no single permission string can
 *    express it.
 *
 * ADR 0033.
 *
 * @category ImpersonateManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImpersonateManagement extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'impersonate';
    /**
     * Initializes the page.
     *
     * @param string $name The name this initializes with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Impersonate');
        parent::__construct($this->name);
    }
    /**
     * The picker: everybody this administrator may become.
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->title = _('Impersonate a user');
        if (!Authorization::can(Identity::PERMISSION)) {
            // Gated here rather than upstream because the node is exempt.
            // Same shape as the refusal requirePagePermission() would have
            // produced, so a denial looks the same wherever it came from.
            self::setMessage(
                _('You do not have permission to impersonate users.'),
                _('Permission denied'),
                'warning'
            );
            self::redirect('?node=home');

            return;
        }
        echo '<div class="col-md-12">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">' . \Initiator::e($this->title) . '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<p>';
        echo \Initiator::e(
            _('You will see FOG exactly as the person you choose sees it -- '
            . 'their permissions, their sites, their timezone and theme. '
            . 'Impersonation is read-only apart from their own preferences, '
            . 'and every action you take is recorded against YOUR name.')
        );
        echo '</p>';
        $candidates = $this->_candidates();
        if (count($candidates) < 1) {
            echo '<p class="text-body-secondary">';
            echo \Initiator::e(
                _('There is nobody you may impersonate. A user must hold no '
                . 'permission and reach no site that you do not.')
            );
            echo '</p>';
        } else {
            echo self::makeFormTag(
                '',
                'impersonate-start-form',
                '?node=impersonate&sub=start',
                'post'
            );
            echo '<div class="mb-3">';
            echo '<select class="form-select" name="targetid" required>';
            echo '<option value="">' . \Initiator::e(_('Choose a user'))
                . '</option>';
            foreach ($candidates as $id => $label) {
                printf(
                    '<option value="%d">%s</option>',
                    $id,
                    \Initiator::e($label)
                );
            }
            echo '</select>';
            echo '</div>';
            echo '<button type="submit" class="btn btn-primary float-end">';
            echo \Initiator::e(_('Impersonate'));
            echo '</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * The users this administrator may become.
     *
     * Reads the users table directly rather than through Route::getIds(),
     * for the reason Authorization::rolesHolding() and SiteScope both state:
     * a routed read bolts the CALLER'S object scope onto the query, and the
     * caller here is choosing somebody to impersonate -- a question about
     * users they can already reach by definition, since the site subset test
     * below refuses anybody they cannot.
     *
     * Filtered by asking Identity the same question the POST will ask, so
     * the picker can never offer a name that start() then refuses.
     *
     * @return array user id => display label
     */
    private function _candidates()
    {
        $realID = Identity::realUserID();
        $out = [];
        $rows = self::$DB
            ->query('SELECT `uID`, `uName` FROM `users` ORDER BY `uName`')
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $id = (int)$row['uID'];
            if ('' !== Identity::refusalReason($realID, $id)) {
                continue;
            }
            $out[$id] = (string)$row['uName'];
        }

        return $out;
    }
    /**
     * The picker again, under the sub the form posts to.
     *
     * Required, not decorative: FOGPageManager::render() only appends the
     * 'Post' suffix when the BASE method exists, so without a start() the
     * dispatcher falls back to index() and startPost() below is never
     * reached -- silently, with the form appearing to do nothing.
     *
     * @return void
     */
    public function start(...$args)
    {
        $this->index(...$args);
    }
    /**
     * Begin impersonating.
     *
     * @return void
     */
    public function startPost(...$args)
    {
        $targetID = (int)filter_input(
            INPUT_POST,
            'targetid',
            FILTER_VALIDATE_INT
        );
        try {
            // "Impersonate another" is END THEN START, never a swap, so the
            // audit never has to answer "acting as B, who was being acted as
            // by A". Ending first also means the second span's subset tests
            // are run against the real administrator rather than against
            // whoever they were already wearing.
            Identity::end('replaced by another impersonation');
            Identity::begin($targetID);
            $target = new User($targetID);
            self::setMessage(
                sprintf(
                    _('You are now viewing FOG as %s.'),
                    (string)$target->get('name')
                ),
                _('Impersonating'),
                'info'
            );
        } catch (\Exception $e) {
            self::setMessage(
                $e->getMessage(),
                _('Impersonation refused'),
                'warning'
            );
        }
        self::redirect('?node=home');
    }
    /**
     * Stop impersonating.
     *
     * Answers the same on GET and POST, and takes no argument. There is
     * nothing to authorize, nothing to choose and nothing to get wrong: the
     * only way out must work from any page, in any permission state, for a
     * mask holding no roles at all.
     *
     * @return void
     */
    public function end(...$args)
    {
        $was = Identity::impersonatedUserName();
        Identity::end();
        if ('' !== $was) {
            self::setMessage(
                sprintf(_('You are no longer viewing FOG as %s.'), $was),
                _('Impersonation ended'),
                'info'
            );
        }
        self::redirect('?node=home');
    }
    /**
     * POST lands on the same handler as GET.
     *
     * @return void
     */
    public function endPost(...$args)
    {
        $this->end(...$args);
    }
}
/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ImpersonateManagement', 'ImpersonateManagement');
