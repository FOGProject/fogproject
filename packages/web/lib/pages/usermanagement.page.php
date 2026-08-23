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
            _('API?'),
            // Separate from API? rather than folded into it: they are
            // independent flags and every combination is real. API? is
            // whether fog-user-token works for this account; this is
            // whether the account can sign in at all. An account can be
            // API-only with API? off -- a service account reachable only
            // through an issued Bearer token -- and collapsing the two into
            // one cell would make that state unreadable.
            _('API Only?')
        ];
        $this->attributes = [
            [],
            [],
            ['width' => 22],
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
            ),
            /*
             * Offered at creation, not only on edit, because the account
             * this exists for is created for the purpose: an unattended
             * integration that wants roles and a token and never a browser.
             * Creating it interactive and then turning the sign-in off is
             * the same end state reached through a window in which the
             * password chosen here is a working login nobody is watching.
             *
             * A password is still required by the form above and is
             * deliberately not made optional: uPass is NOT NULL, an empty
             * hash fails password_verify() by accident rather than by rule,
             * and the flag is what makes the credential unusable. Weakening
             * the field would put an account one unticked box away from a
             * blank-password login.
             */
            self::makeLabel(
                $labelClass,
                'apionly',
                _('API Only Account')
                . '<br/>('
                . _('a service account: it may hold API tokens and can '
                    . 'never sign in to this interface')
                . ')'
            ) => self::makeInput(
                'apionly-input',
                'apionly',
                '',
                'checkbox',
                'apionly',
                '',
                false,
                false,
                -1,
                -1,
                (isset($_POST['apionly']) ? 'checked' : '')
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
        $apionly = (int)isset($_POST['apionly']);
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
                ->set('apionly', $apionly)
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
         * Whether this account may sign in at all.
         *
         * Editable here rather than read-only like the auth source below,
         * because unlike an auth source this IS a plain two-state
         * administrative decision with no external system behind it, and
         * both directions are safe to expose: setting it is guarded by
         * User::save(), clearing it only ever gives a sign-in back.
         *
         * A password is not offered alongside it and the Password tab
         * disappears while it is set (see edit()), so there is no way to
         * arrive at "I set them a password" for an account that could never
         * use one.
         */
        $fields[self::makeLabel(
            $labelClass,
            'apionly',
            _('API Only Account')
            . '<br/>('
            . _('a service account: it may hold API tokens and can never '
                . 'sign in to this interface')
            . ')'
        )] = self::makeInput(
            'apionly-input',
            'apionly',
            '',
            'checkbox',
            'apionly',
            '',
            false,
            false,
            -1,
            -1,
            ($this->obj->isAPIOnly() ? ' checked' : '')
        );

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
            ->set('display', $display)
            /*
             * Set from presence, like every other checkbox on this form.
             * Both directions run on every Update, which is what makes the
             * box able to clear the flag as well as set it -- and the
             * refusal that protects the last interactive administrator
             * lives in User::save(), so it applies to the REST and CSV
             * paths identically rather than only to this one.
             */
            ->set('apionly', (int)isset($_POST['apionly']));

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

        // Gated on apitoken.*, NOT on the user.edit that got us to this
        // page. Otherwise the central pane's permissions are decorative:
        // anyone who could edit a user could delete their credentials from
        // here regardless of holding apitoken.delete.
        if (!Authorization::can('apitoken.view')) {
            return;
        }
        $mayEdit = Authorization::can('apitoken.edit');
        $mayCreate = Authorization::can('apitoken.create');
        $mayDelete = Authorization::can('apitoken.delete');

        echo '<form class="form-horizontal" method="post" action="'
            . self::makeTabUpdateURL('user-api', $uid)
            . '" id="user-apitoken-form">';
        echo '<div class="card mt-3">';
        echo '<div class="card-header">' . _('Bearer API Tokens') . '</div>';
        echo '<div class="card-body">';

        // Filled in by fog.user.edit.js from issueAPIToken()'s response and
        // never by PHP. The plaintext reaches the browser once, in the reply
        // to the click that created it, and is not carried in the session or
        // re-rendered on any later page load -- so a back button, a refresh
        // or a second admin opening the same user cannot surface it.
        echo '<div class="alert alert-success d-none" id="apitoken-fresh">';
        echo '<h5>' . _('Copy this token now') . '</h5>';
        echo '<p>'
            . _('This is the only time it will be shown. FOG stores only a '
                . 'hash of it and cannot show it again. If you lose it, '
                . 'delete this token and issue another.')
            . '</p>';
        echo '<input type="text" class="form-control" readonly '
            . 'onclick="this.select();" id="apitoken-fresh-value"/>';
        echo '<p class="mt-2 mb-0"><code>Authorization: Bearer '
            . '<span id="apitoken-fresh-header"></span></code></p>';
        echo '</div>';

        echo '<p>'
            . _('Sent as an Authorization: Bearer header, on its own &mdash; no '
                . 'fog-api-token header is needed alongside it. Each token '
                . 'acts with this user\'s roles.')
            . '</p>';

        $tokens = self::getClass('APITokenManager')
            ->forUser($uid);
        echo '<table class="table table-sm">';
        echo '<thead><tr>'
            . '<th>' . _('Name') . '</th>'
            . '<th>' . _('Created') . '</th>'
            . '<th>' . _('Last Used') . '</th>'
            . '<th>' . _('Enabled') . '</th>'
            . ($mayDelete ? '<th>' . _('Delete') . '</th>' : '')
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
                . ($mayEdit ? '' : ' disabled')
                . '/></td>';
            if ($mayDelete) {
                echo '<td><input type="checkbox" name="tokendelete[]" '
                    . 'value="' . $tid . '"/></td>';
            }
            echo '</tr>';
            unset($token);
        }
        echo '</tbody></table>';

        // type=button, not submit. Creation does NOT ride this form: it is
        // its own AJAX call to issueAPIToken(), the same shape the Reset
        // Token control beside it already uses. Two reasons, and the first
        // is fatal on its own:
        //
        //  - processForm() posts `new FormData(form)`, and FormData omits
        //    submit buttons unless the submitter is passed to it. A
        //    name=createtoken submit button therefore never arrives, so the
        //    handler could not tell a create from a save however it was
        //    wired.
        //  - the plaintext is shown once. Routing it through the tab form
        //    means either putting it in the session and reloading -- which
        //    lands the user back on the General tab with the secret
        //    unseen -- or threading it through handleEditPost()'s shared
        //    fixed-shape response. The dedicated endpoint returns it to the
        //    click that asked for it and nothing else has to change.
        // Read by _userBearerTokensPost() to tell this card's save from the
        // legacy card's. See the comment there for why it cannot be the
        // button's own name.
        echo '<input type="hidden" name="tokenaction" value="manage"/>';

        if ($mayCreate) {
            echo '<div class="input-group">';
            echo '<input type="text" class="form-control" '
                . 'name="newtokenname" id="newtokenname" placeholder="'
                . _('Name for a new token') . '"/>';
            echo '<button type="button" id="issuetoken" '
                . 'class="btn btn-secondary">' . _('Issue Token')
                . '</button>';
            echo '</div>';
        }

        echo '</div>';
        echo '<div class="card-footer">';
        if ($mayEdit || $mayDelete) {
            echo self::makeButton(
                'apitoken-send',
                _('Update'),
                'btn btn-primary float-end'
            );
        }
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
     * Issues one API token and returns its plaintext, once.
     *
     * Its own endpoint rather than part of the tab form, for the reasons set
     * out where the Issue Token button is emitted. Modelled on the Reset
     * Token control beside it, which has always been a direct AJAX call.
     *
     * The plaintext is written to this response and nowhere else -- not the
     * session, not a log, not the row. If the caller loses it, the token is
     * unrecoverable by design and the answer is to delete it and issue
     * another.
     *
     * @return void
     */
    public function issueAPIToken()
    {
        // State-changing and it mints a credential, so it gets the same gate
        // as any other POST here. Ordinary role checks still apply: reaching
        // this page at all requires user.edit.
        self::checkAuthAndCSRF();
        if (!Authorization::can('apitoken.create')) {
            header('Content-type: application/json');
            self::jsonSend(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                json_encode(
                    [
                        'error' => _('You do not have permission to issue '
                            . 'API tokens.'),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }
        // Not optional. Without it jQuery reads the body as text, hands
        // $.notifyFromAPI a STRING, and every res.<key> lookup is undefined
        // -- so the caller sees no token and no error, just a notification
        // that says nothing. Every other JSON endpoint here sets it too.
        header('Content-type: application/json');

        $uid = (int)$this->obj->get('id');
        $name = trim((string)filter_input(INPUT_POST, 'newtokenname'));

        $token = $uid > 0 ? APIToken::generate($uid, $name) : false;
        if (false === $token) {
            // generate() returns false when the row did not store, so this
            // is "there is no token", not "there might be one you cannot
            // see". Reported as a server fault because nothing the user
            // typed can cause it.
            self::jsonSend(
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
                json_encode(
                    [
                        'error' => _('Could not issue the token!'),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }

        self::jsonSend(
            HTTPResponseCodes::HTTP_CREATED,
            json_encode(
                [
                    'msg' => _('API token issued!'),
                    'title' => _('API Token Created'),
                    'token' => $token,
                    'name' => $name,
                    'created' => self::formatTime('now', 'Y-m-d H:i:s')
                ]
            )
        );
    }
    /**
     * Handles a submit from the Bearer token card.
     *
     * @return bool whether this request came from that card.
     */
    private function _userBearerTokensPost()
    {
        // A hidden field, not the button's name. processForm() posts
        // `new FormData(form)` and FormData omits submit buttons unless the
        // submitter is passed to it, so a name= on the button never arrives
        // -- the handler would see every save as "not mine" and fall
        // through to the legacy card. The JS sets this before posting.
        if ('manage' !== (string)filter_input(INPUT_POST, 'tokenaction')) {
            return false;
        }
        // Returns true either way: the request WAS this card's, so it must
        // not fall through to the legacy card's handler even when the user
        // may not act on it. Falling through would read the legacy card's
        // absent fields as empty and wipe uAPIToken -- turning a permission
        // denial into data loss on a different credential.
        if (!Authorization::can('apitoken.view')) {
            return true;
        }
        $mayEdit = Authorization::can('apitoken.edit');
        $mayDelete = Authorization::can('apitoken.delete');
        $uid = (int)$this->obj->get('id');
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
            ->forUser($uid);
        foreach ((array)$tokens as &$token) {
            $tid = (int)$token->get('id');
            if ($mayDelete && in_array($tid, $toDelete, true)) {
                // revoke(), not destroy(): it writes the audit row first,
                // while the owner and name can still be read off the row.
                $token->revoke();
                unset($token);
                continue;
            }
            if ($mayEdit) {
                $token->setEnabled(in_array($tid, $keepEnabled, true));
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
         * An API-only account is refused by the same method for its own
         * reason, so it is in exactly the same position and the tab is
         * hidden for it too. To give such an account a usable password,
         * untick API Only first.
         *
         * The General tab shows both the auth source and the API-only box,
         * so the missing tab has an explanation on the same page.
         */
        if ('' === trim((string)$this->obj->get('authsource'))
            && !$this->obj->isAPIOnly()
        ) {
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
