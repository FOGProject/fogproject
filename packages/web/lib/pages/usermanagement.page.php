<?php
/**
 * User management page.
 *
 * PHP version 7.4+
 *
 * @category UserManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * User management page.
 *
 * @category UserManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserManagement extends FOGPage
{
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'user';
    /**
     * Initializes the user class.
     *
     * @param string $name The name to load this as.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('User Management');
        parent::__construct($this->name);
        $this->headerData = [
            _('Username'),
            _('Friendly Name'),
            _('API?')
        ];
        $this->attributes = [
            [],
            [],
            ['width' => 22]
        ];
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        if ($this->obj instanceof User
            && $this->obj->isValid()
            && !$this->obj->get('token')
        ) {
            $this->obj
                ->set('token', self::createSecToken())
                ->save();
        }
        return $this;
    }
    /**
     * Builds the create-form fields (shared by add() and addModal()).
     *
     * @return array
     */
    protected function _addFields()
    {
        $user = filter_input(INPUT_POST, 'user');
        $display = filter_input(INPUT_POST, 'display');

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'user',
                _('User Name')
            ) => self::makeInput(
                'form-control username-input',
                'user',
                _('User Name'),
                'text',
                'user',
                $user,
                true,
                false,
                3,
                50,
                'beRegexTo="'
                . '(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$"'
                . ' requirements="'
                . _('Username must begin with 2 numbers or letters.')
                . ' '
                . _('Username must end with a number or letter.')
                . ' '
                . _('You may use _, ., -, or a space between.')
                . ' '
                . _('It must be between 3 and 50 characters.')
                . '"'
            ),
            self::makeLabel(
                $labelClass,
                'display',
                _('Friendly Name')
            ) => self::makeInput(
                'form-control userdisplay-input',
                'display',
                _('Friendly Name'),
                'text',
                'display',
                $display,
                false,
                false
            ),
            self::makeLabel(
                $labelClass,
                'password',
                _('User Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password1-input',
                'password',
                _('User Password'),
                'password',
                'password',
                '',
                true,
                false,
                (int)self::getSetting('FOG_USER_MINPASSLENGTH'),
                -1,
                'beRegexTo="'
                . self::getSetting('FOG_USER_VALIDPASSCHARS')
                . '" requirements="'
                . _(self::getSetting('FOG_USER_VALIDPASSHELPMSG'))
                . '"'
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'password_name',
                _('User Password')
                . '<br/>('
                . _('confirm')
                . ')'
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password2-input',
                'password_name',
                _('User Password'),
                'password',
                'password_name',
                '',
                true,
                false,
                -1,
                -1,
                'beEqualTo="password"'
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'apienabled',
                _('User API Enable')
            ) => self::makeInput(
                'apienabled-input',
                'apienabled',
                '',
                'checkbox',
                'apienabled',
                '',
                false,
                false,
                -1,
                -1,
                (isset($_POST['apienabled']) ? 'checked' : '')
            )
        ];

        // A user created into no site at all sees nothing, so this one
        // matters more than the group/usergroup equivalents.
        return self::fastmerge($fields, self::siteAddField($labelClass));
    }
    /**
     * Page to enable creating a new user.
     *
     * @return void
     */
    public function add()
    {
        $this->renderAddForm(
            'user',
            _('Create New User'),
            'USER_ADD_FIELDS',
            'User'
        );
    }
    /**
     * Page to enable creating a new user.
     *
     * @return void
     */
    public function addModal()
    {
        $this->renderAddModalForm(
            'user',
            'USER_ADD_FIELDS',
            'User'
        );
    }
    /**
     * Actually create the new user.
     *
     * @return void
     */
    public function addPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent('USER_ADD_POST');
        $userPat = "/(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$/";
        $userErr =  _('Username must begin with 2 numbers or letters.')
            . ' '
            . _('Username must end with a number or letter.')
            . ' '
            . _('You may use _, ., -, or a space between.')
            . ' '
            . _('It must be between 3 and 50 characters.');
        $user = strtolower(
            trim(
                filter_input(INPUT_POST, 'user')
            )
        );
        $password = trim(
            filter_input(INPUT_POST, 'password')
        );
        $friendly = trim(
            filter_input(INPUT_POST, 'display')
        );
        $apien = (int)isset($_POST['apienabled']);
        $token = self::createSecToken();

        $serverFault = false;
        try {
            if (!preg_match($userPat, $user)) {
                throw new \Exception($userErr);
            }
            $exists = self::getClass('UserManager')
                ->exists($user);
            if ($exists) {
                throw new \Exception(
                    _('A username already exists with this name!')
                );
            }
            $User = self::getClass('User')
                ->set('name', $user)
                ->set('password', $password)
                ->set('display', $friendly)
                ->set('api', $apien)
                ->set('type', 0)
                ->set('token', $token);
            if (!$User->save()) {
                $serverFault = true;
                throw new \Exception(_('Add user failed!'));
            }
            $this->siteAddPost('user', $User);
            $code = HTTPResponseCodes::HTTP_CREATED;
            $hook = 'USER_ADD_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('User added!'),
                    'title' => _('User Create Success'),
                    'id' => $User->get('id')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'USER_ADD_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('User Create Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'User' => &$User,
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * User general div element.
     *
     * @return void
     */
    public function userGeneral()
    {
        $user = (
            filter_input(INPUT_POST, 'user') ?:
            $this->obj->get('name')
        );

        $display = (
            filter_input(INPUT_POST, 'display') ?:
            $this->obj->get('display')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'user',
                _('User Name')
            ) => self::makeInput(
                'form-control username-input',
                'user',
                _('User Name'),
                'text',
                'user',
                $user,
                true,
                false,
                3,
                50,
                'beRegexTo="'
                . '(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$"'
                . ' requirements="'
                . _('Username must begin with 2 numbers or letters.')
                . ' '
                . _('Username must end with a number or letter.')
                . ' '
                . _('You may use _, ., -, or a space between.')
                . ' '
                . _('It must be between 3 and 50 characters.')
                . '"'
            ),
            self::makeLabel(
                $labelClass,
                'display',
                _('Friendly Name')
            ) => self::makeInput(
                'form-control userdisplay-input',
                'display',
                _('Friendly Name'),
                'text',
                'display',
                $display,
                false,
                false
            )
        ];

        /*
         * Where this account authenticates, read-only.
         *
         * Shown because of what it takes AWAY: an account with an auth
         * source has no Password tab (see edit()), and without this field
         * the tab is simply missing with nothing on the page to explain
         * why. Read-only rather than editable -- clearing it hands the
         * account back to local password login, which is an auth decision
         * and not a text box.
         */
        $authSource = trim((string)$this->obj->get('authsource'));
        if ('' !== $authSource) {
            $fields[self::makeLabel(
                $labelClass,
                'authsource',
                _('Signs In With')
            )] = self::makeInput(
                'form-control',
                'authsource',
                '',
                'text',
                'authsource',
                $authSource,
                false,
                false,
                -1,
                -1,
                '',
                true
            );
            /*
             * Hand the account back to FOG's own password.
             *
             * Until this existed the only way to do it was a REST call or
             * a hand-written UPDATE, which is a bad place to be standing
             * when the reason you want it is that the directory is down.
             *
             * A checkbox that takes effect on Update, rather than a button
             * of its own: it is two deliberate acts, it reuses the form's
             * CSRF and its existing handler, and it is undone by not
             * pressing Update. User::save() permits the clear -- it is the
             * recovery direction the break-glass guard exists to keep open
             * -- so nothing here can reduce the number of administrators
             * able to sign in without a directory.
             *
             * The warning is not boilerplate. Whether the account can
             * still sign in through its provider afterwards depends on how
             * that provider works, and core cannot tell which kind this is:
             * a provider that vouches through USER_LOGGING_IN (LDAP) is
             * refused once the stamp is gone, because passwordValidate()
             * honours that vouching ONLY for stamped accounts -- that
             * restriction is what stops a plugin authenticating a local
             * account. A provider that establishes the session itself
             * (OIDC) never reaches passwordValidate() and is unaffected.
             * So the safe instruction is the same either way: set a
             * password immediately.
             */
            $fields[self::makeLabel(
                $labelClass,
                'returnlocal',
                _('Return To Local Login')
                . '<br/>('
                . sprintf(
                    _('lets this account use a FOG password again; sign-in through %s may stop working, so set a password straight afterwards'),
                    \Initiator::e($authSource)
                )
                . ')'
            )] = self::makeInput(
                'returnlocal-input',
                'returnlocal',
                '',
                'checkbox',
                'returnlocal',
                '',
                false,
                false,
                -1,
                -1,
                isset($_POST['returnlocal']) ? ' checked' : ''
            );
        }

        $buttons = self::makeButton(
            'general-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        $buttons .= self::makeButton(
            'general-delete',
            _('Delete'),
            'btn btn-danger float-start'
        );

        self::$HookManager->processEvent(
            'USER_GENERAL_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        $this->renderGeneralForm('user', $rendered, $buttons);
    }
    /**
     * User General Post
     *
     * @return void
     */
    public function userGeneralPost()
    {
        self::checkAuthAndCSRF();
        $userPat = "/(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[A-Za-z\d][\w\s\-\.]*[A-Za-z\d]$/";
        $userErr =  _('Username must begin with 2 numbers or letters.')
            . ' '
            . _('Username must end with a number or letter.')
            . ' '
            . _('You may use _, ., -, or a space between.')
            . ' '
            . _('It must be between 3 and 50 characters.');
        $user = strtolower(
            trim(
                filter_input(INPUT_POST, 'user')
            )
        );
        $display = trim(
            filter_input(INPUT_POST, 'display')
        );
        if (!preg_match($userPat, $user)) {
            throw new \Exception($userErr);
        }
        $exists = self::getClass('UserManager')
            ->exists($user);
        if ($user != $this->obj->get('name')
            && $exists
        ) {
            throw new \Exception(
                _('A user already exists with this name')
            );
        }
        $this->obj
            ->set('name', $user)
            ->set('display', $display);

        /*
         * Return the account to FOG's own password, if asked.
         *
         * Only ever clears. There is no path here that SETS an auth source
         * -- writing one takes local password login away from an account,
         * which is the direction that can lock an install out of itself,
         * and it is not something a general-details form should be able to
         * do as a side effect. Clearing is the recovery direction and
         * User::save() lets it through unconditionally.
         *
         * Guarded on there being one to clear so an unrelated Update on a
         * local account cannot mark the field dirty.
         */
        if (isset($_POST['returnlocal'])
            && '' !== trim((string)$this->obj->get('authsource'))
        ) {
            $this->obj->set('authsource', '');
        }
    }
    /**
     * Change password div element.
     *
     * @return void
     */
    public function userChangePW()
    {
        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'password',
                _('User Password')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password1-input',
                'password',
                _('User Password'),
                'password',
                'password',
                '',
                true,
                false,
                (int)self::getSetting('FOG_USER_MINPASSLENGTH'),
                -1,
                'beRegexTo="'
                . self::getSetting('FOG_USER_VALIDPASSCHARS')
                . '" requirements="'
                . _(self::getSetting('FOG_USER_VALIDPASSHELPMSG'))
                . '"'
            )
            . '</div>',
            self::makeLabel(
                $labelClass,
                'password_name',
                _('User Password')
                . '<br/>('
                . _('confirm')
                . ')'
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control password2-input',
                'password_name',
                _('User Password'),
                'password',
                'password_name',
                '',
                true,
                false,
                -1,
                -1,
                'beEqualTo="password"'
            )
            . '</div>'
        ];

        $buttons = self::makeButton(
            'changepw-send',
            _('Update'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'USER_CHANGEPW_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'user-changepw-form',
            self::makeTabUpdateURL(
                'user-changepw',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * User change password post.
     *
     * @return void
     */
    public function userChangePWPost()
    {
        self::checkAuthAndCSRF();
        /*
         * The tab is not rendered for this account (see edit()), so this
         * refusal is what makes that removal mean something rather than
         * merely look like it: the tab-update URL is guessable and a stale
         * page left open still holds the form.
         *
         * It is not a security boundary -- a password stored here would be
         * inert either way, because passwordValidate() refuses a local
         * credential for an account with an auth source. It is an honesty
         * one. Silently accepting the write is how an admin comes to
         * believe a directory account has a working local password.
         */
        $authSource = trim((string)$this->obj->get('authsource'));
        if ('' !== $authSource) {
            throw new \Exception(
                sprintf(
                    _('%s signs in through %s, so a password stored here would never be accepted. Clear the authentication source first.'),
                    $this->obj->get('name'),
                    $authSource
                )
            );
        }
        $password = trim(
            filter_input(INPUT_POST, 'password')
        );
        $this->obj
            ->set('password', $password);
    }
    /**
     * API div element.
     *
     * @return void
     */
    public function userAPI()
    {
        $apienabled = (
            isset($_POST['apienabled']) ?
            ' checked' :
            (
                $this->obj->get('api') ?
                ' checked' :
                ''
            )
        );
        $token = base64_encode(
            $this->obj->get('token')
        );

        $labelClass = 'col-sm-3 col-form-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'apienabled',
                _('User API Enable')
            ) => self::makeInput(
                'apienabled-input',
                'apienabled',
                '',
                'checkbox',
                'apienabled',
                '',
                false,
                false,
                -1,
                -1,
                $apienabled
            ),
            self::makeLabel(
                $labelClass,
                'apitoken',
                _('User API Token')
            ) => '<div class="input-group">'
            . self::makeInput(
                'form-control token',
                'apitoken',
                _('User API Token'),
                'text',
                'apitoken',
                $token,
                false,
                false,
                -1,
                -1,
                '',
                true,
                false
            )
            . self::makeButton(
                'resettoken',
                _('Reset Token'),
                'btn btn-warning resettoken'
            )
            . '</div>'
        ];

        $buttons = self::makeButton(
            'api-send',
            _('Update'),
            'btn btn-primary float-end'
        );

        self::$HookManager->processEvent(
            'USER_API_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons,
                'User' => &$this->obj
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            '',
            'user-api-form',
            self::makeTabUpdateURL(
                'user-api',
                $this->obj->get('id')
            ),
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="card">';
        echo '<div class="card-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
        $this->_userBearerTokens();
    }
    /**
     * The Bearer token card: issue, list, disable and delete APITokens.
     *
     * A SEPARATE card from the one above, deliberately. The field above is
     * users.uAPIToken -- plaintext, permanently re-readable, sent as
     * fog-user-token beside fog-api-token. These are ADR 0027 tokens: hashed
     * at rest, shown once, and the only thing Authorization: Bearer accepts.
     * Two credentials with different properties should not share a card and
     * read as one setting.
     *
     * @return void
     */
    private function _userBearerTokens()
    {
        $uid = (int)$this->obj->get('id');
        // Shown exactly once, on the render immediately after creation. The
        // plaintext exists nowhere else -- not in the row, not in a log --
        // so this is the user's only chance to copy it, and the session key
        // is cleared as it is read rather than on any later event that might
        // not happen.
        $fresh = (string)($_SESSION['fog_new_api_token'] ?? '');
        unset($_SESSION['fog_new_api_token']);

        echo '<form class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('user-api', $uid)
            . '" id="user-apitoken-form">';
        echo '<div class="card mt-3">';
        echo '<div class="card-header">' . _('Bearer API Tokens') . '</div>';
        echo '<div class="card-body">';

        if ('' !== $fresh) {
            echo '<div class="alert alert-success">';
            echo '<h5>' . _('Copy this token now') . '</h5>';
            echo '<p>'
                . _('This is the only time it will be shown. FOG stores only '
                    . 'a hash of it and cannot show it again. If you lose it, '
                    . 'delete this token and issue another.')
                . '</p>';
            echo '<input type="text" class="form-control" readonly '
                . 'onclick="this.select();" value="'
                . \Initiator::e($fresh) . '"/>';
            echo '<p class="mt-2 mb-0"><code>Authorization: Bearer '
                . \Initiator::e($fresh) . '</code></p>';
            echo '</div>';
        }

        echo '<p>'
            . _('Sent as an Authorization: Bearer header, on its own -- no '
                . 'fog-api-token header is needed alongside it. Each token '
                . 'acts with this user\'s roles.')
            . '</p>';

        $tokens = self::getClass('APITokenManager')
            ->find(['userID' => $uid]);
        echo '<table class="table table-sm">';
        echo '<thead><tr>'
            . '<th>' . _('Name') . '</th>'
            . '<th>' . _('Created') . '</th>'
            . '<th>' . _('Last Used') . '</th>'
            . '<th>' . _('Enabled') . '</th>'
            . '<th>' . _('Delete') . '</th>'
            . '</tr></thead><tbody>';
        if (count($tokens) < 1) {
            echo '<tr><td colspan="5">' . _('No tokens issued.') . '</td></tr>';
        }
        foreach ((array)$tokens as &$token) {
            $tid = (int)$token->get('id');
            $last = trim((string)$token->get('lastUsed'));
            echo '<tr>';
            echo '<td>' . \Initiator::e($token->get('name')) . '</td>';
            echo '<td>' . \Initiator::e($token->get('createdTime')) . '</td>';
            // A token that has never been used reads as such rather than as
            // a date, so "issued and forgotten" is visible at a glance --
            // that is the whole reason the column is recorded.
            echo '<td>'
                . ('' === $last ? _('Never') : \Initiator::e($last))
                . '</td>';
            echo '<td><input type="checkbox" name="tokenenabled[]" value="'
                . $tid . '"'
                . ('1' === (string)$token->get('enabled') ? ' checked' : '')
                . '/></td>';
            echo '<td><input type="checkbox" name="tokendelete[]" value="'
                . $tid . '"/></td>';
            echo '</tr>';
            unset($token);
        }
        echo '</tbody></table>';

        echo '<div class="input-group">';
        echo '<input type="text" class="form-control" name="newtokenname" '
            . 'placeholder="' . _('Name for a new token') . '"/>';
        echo '<button type="submit" name="createtoken" value="1" '
            . 'class="btn btn-secondary">' . _('Issue Token') . '</button>';
        echo '</div>';

        echo '</div>';
        echo '<div class="card-footer">';
        echo self::makeButton(
            'apitoken-send',
            _('Update'),
            'btn btn-primary float-end'
        );
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * User Change API Post
     *
     * @return void
     */
    public function userAPIPost()
    {
        self::checkAuthAndCSRF();
        // The Bearer card posts to this same tab URL, so it lands here too.
        // Routed first and returned from: its submits carry none of the
        // legacy card's fields, and falling through would read an absent
        // apienabled checkbox as "unticked" and an absent apitoken as empty
        // -- silently disabling fog-user-token for the account and wiping
        // uAPIToken as a side effect of issuing a Bearer token. That is the
        // control-type/hand-built-form defect class (GH-987) exactly.
        if ($this->_userBearerTokensPost()) {
            return;
        }
        $apien = (int)isset($_POST['apienabled']);
        $apitoken = base64_decode(
            filter_input(INPUT_POST, 'apitoken')
        );
        $this->obj
            ->set('api', $apien)
            ->set('token', $apitoken);
    }
    /**
     * Handles a submit from the Bearer token card.
     *
     * @return bool whether this request came from that card.
     */
    private function _userBearerTokensPost()
    {
        $isCreate = null !== filter_input(INPUT_POST, 'createtoken');
        $isManage = null !== filter_input(INPUT_POST, 'apitoken-send');
        if (!$isCreate && !$isManage) {
            return false;
        }
        $uid = (int)$this->obj->get('id');
        if ($isCreate) {
            $token = APIToken::generate(
                $uid,
                (string)filter_input(INPUT_POST, 'newtokenname')
            );
            if (false === $token) {
                throw new \Exception(_('Could not issue the token!'));
            }
            // Carried to the next render and shown once. Not returned in
            // this response body and not logged: the plaintext should touch
            // as few places as possible on its way to the screen.
            $_SESSION['fog_new_api_token'] = $token;
            return true;
        }
        $keepEnabled = array_map(
            'intval',
            (array)($_POST['tokenenabled'] ?? [])
        );
        $toDelete = array_map(
            'intval',
            (array)($_POST['tokendelete'] ?? [])
        );
        // Scoped to THIS user's tokens. The ids arrive from a form and a
        // form is an untrusted list, so acting on them without the userID
        // filter would let anyone who can edit one user disable or delete
        // any token on the server by posting its id.
        $tokens = self::getClass('APITokenManager')
            ->find(['userID' => $uid]);
        foreach ((array)$tokens as &$token) {
            $tid = (int)$token->get('id');
            if (in_array($tid, $toDelete, true)) {
                $token->destroy();
                unset($token);
                continue;
            }
            $want = in_array($tid, $keepEnabled, true) ? '1' : '0';
            if ($want !== (string)$token->get('enabled')) {
                $token->set('enabled', $want)->save();
            }
            unset($token);
        }
        return true;
    }
    /**
     * Present the roles tab.
     *
     * @return void
     */
    public function userRole()
    {
        $this->renderAssocTab(
            'user-role',
            _('Role Associations'),
            _('Role Name'),
            'role'
        );
    }
    /**
     * Update the user's role associations.
     *
     * @return void
     */
    public function userRolePost()
    {
        $this->assocPost('addRole', 'removeRole');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'userRoles' => [
                    (int)$this->obj->get('id') => (array)$this->obj->get('roles')
                ]
            ]
        );
        if (!$adminRemains) {
            throw new \Exception(
                _('This change would leave no user with administrator access.')
            );
        }
    }
    /**
     * Present the user groups tab.
     *
     * @return void
     */
    public function userGroup()
    {
        $this->renderAssocTab(
            'user-group',
            _('User Group Associations'),
            _('Group Name'),
            'usergroup'
        );
    }
    /**
     * Update the user's group associations.
     *
     * @return void
     */
    public function userGroupPost()
    {
        $this->assocPost('addGroup', 'removeGroup');
        // assocPost only mutates the in-memory list; the save happens in
        // editPost after this returns, so throwing here aborts the change.
        $adminRemains = Authorization::adminExistsGiven(
            [
                'userGroups' => [
                    (int)$this->obj->get('id') => (array)$this->obj->get('usergroups')
                ]
            ]
        );
        if (!$adminRemains) {
            throw new \Exception(
                _('This change would leave no user with administrator access.')
            );
        }
    }
    /**
     * Enable user to edit a user.
     *
     * @return void
     */
    public function edit()
    {
        $tabData = [];

        // General
        $tabData[] = [
            'name' => _('General'),
            'id' => 'user-general',
            'generator' => function () {
                $this->userGeneral();
            }
        ];

        /*
         * Password Changing -- only for an account that authenticates here.
         *
         * User::passwordValidate() refuses a local credential outright for
         * an account carrying uAuthSource, so on a directory-owned account
         * this tab could only ever store a password that nothing would
         * accept. That is worse than useless: it reads as "I have set them
         * a password", which is exactly the thing an admin would rely on in
         * an outage and find does not work.
         *
         * To give such an account a local password, clear its auth source
         * first -- that is the supported recovery direction, and
         * User::save() deliberately permits it (see
         * _assertAuthSourceKeepsBreakGlass).
         *
         * The General tab shows the auth source, so the missing tab has an
         * explanation on the same page.
         */
        if ('' === trim((string)$this->obj->get('authsource'))) {
            $tabData[] = [
                'name' => _('Password'),
                'id' => 'user-changepw',
                'generator' => function () {
                    $this->userChangePW();
                }
            ];
        }

        // API Updating
        $tabData[] = [
            'name' => _('API'),
            'id' => 'user-api',
            'generator' => function () {
                $this->userAPI();
            }
        ];

        // Roles
        $tabData[] = [
            'name' => _('Roles'),
            'id' => 'user-role',
            'generator' => function () {
                $this->userRole();
            }
        ];

        // User Groups
        $tabData[] = [
            'name' => _('Groups'),
            'id' => 'user-group',
            'generator' => function () {
                $this->userGroup();
            }
        ];
        // Site
        $tabData[] = [
            'name' => _('Site'),
            'id' => 'user-site',
            'generator' => function () {
                $this->userSite();
            }
        ];

        $this->renderEditTabs($tabData, $this->obj);
    }
    /**
     * Actually save the edits.
     *
     * @return void
     */
    public function editPost()
    {
        $this->handleEditPost(
            'User',
            'USER_EDIT',
            _('User updated!'),
            _('User Update Success'),
            _('User Update Fail'),
            function (&$serverFault) {
                global $tab;
                switch ($tab) {
                    case 'user-site':
                        $this->userSitePost();
                        break;
                    case 'user-general':
                        $this->userGeneralPost();
                        break;
                    case 'user-changepw':
                        $this->userChangePWPost();
                        break;
                    case 'user-api':
                        $this->userAPIPost();
                        break;
                    case 'user-role':
                        $this->userRolePost();
                        break;
                    case 'user-group':
                        $this->userGroupPost();
                }
                if (!$this->obj->save()) {
                    $serverFault = true;
                    throw new \Exception(_('User update failed!'));
                }
                if ('user-role' === $tab || 'user-group' === $tab) {
                    Authorization::resetCache();
                }
            }
        );
    }
    /**
     * Gets the role list for the roles association tab.
     *
     * @return void
     */
    public function getRolesList()
    {
        return $this->assocItemsList(
            'role',
            'roleuserassociation',
            'roleUserAssoc',
            '`roles`.`rID`',
            '`roleUserAssoc`.`ruaRoleID`',
            '`roleUserAssoc`.`ruaUserID`',
            [
                [
                    'db' => 'userAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }
    /**
     * Gets the user group list for the groups association tab.
     *
     * @return void
     */
    public function getGroupsList()
    {
        return $this->assocItemsList(
            'usergroup',
            'usergroupmember',
            'userGroupMembers',
            '`userGroups`.`ugID`',
            '`userGroupMembers`.`ugmGroupID`',
            '`userGroupMembers`.`ugmUserID`',
            [
                [
                    'db' => 'userAssoc',
                    'dt' => 'association',
                    'removeFromQuery' => true
                ]
            ]
        );
    }

    /**
     * Presents the site tab.
     *
     * @return void
     */
    public function userSite()
    {
        $this->renderSiteTab('user', $this->obj);
    }
    /**
     * Updates the site.
     *
     * @return void
     */
    public function userSitePost()
    {
        $this->siteTabPost('user', $this->obj);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\UserManagement', 'UserManagement');
