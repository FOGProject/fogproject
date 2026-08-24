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
                . '<br/>('
                . _('legacy fog-user-token header and HTTP Basic; not '
                    . 'needed for fog_ bearer tokens')
                . ')'
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
             * Ticking this hides the password fields above (fog.user.add.js)
             * and addPost() generates an unusable random one instead. It is
             * NOT left blank: uPass is NOT NULL, and User::set() bcrypts
             * whatever it is handed -- so an empty string becomes a VALID
             * hash OF the empty string, which password_verify('', ...)
             * accepts. Storing that would leave the account one unticked box
             * away from a blank-password login. The flag is the policy; the
             * random password is the backstop behind it.
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
            // Revealed by fog.user.add.js when the box is ticked, in place
            // of the password rows it hides. Says where the credential
            // actually comes from, because the answer is not on this form:
            // tokens are issued from the user's API tab or FOG
            // Configuration -> API Tokens, after the account exists.
            . '<div class="form-text d-none" id="apionly-password-note">'
            . _('No password is needed. Issue this account a token from its '
                . 'API tab, or from FOG Configuration &rarr; API Tokens, '
                . 'once it has been created.')
            . '</div>'
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
        // Cast before trim(). The API-only toggle DISABLES this field, and a
        // disabled input is not submitted at all -- so filter_input() answers
        // null here, which is a PHP 8.1 deprecation straight into trim() (the
        // null-to-internal-param class), invisible on a distro php.ini that
        // hides deprecations.
        $password = trim(
            (string)filter_input(INPUT_POST, 'password')
        );
        $friendly = trim(
            filter_input(INPUT_POST, 'display')
        );
        $apien = (int)isset($_POST['apienabled']);
        $apionly = (int)isset($_POST['apionly']);
        $token = self::createSecToken();

        /*
         * An API-only account is not asked for a password -- the form hides
         * the fields -- so one is generated here and never shown to anybody.
         *
         * It is NOT left empty. User::set() bcrypts whatever it is given, so
         * ''; would be stored as a perfectly valid hash of the empty string
         * and password_verify('', $hash) would return true. isAPIOnly()
         * refuses the sign-in either way, but that leaves the account one
         * unticked box away from a blank-password login by somebody who
         * later decides the service account should be interactive after all.
         * 256 bits of CSPRNG output cannot be typed back in.
         */
        if ($apionly && '' === $password) {
            $password = bin2hex(random_bytes(32));
        }

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
                . '<br/>('
                . _('legacy fog-user-token header and HTTP Basic; not '
                    . 'needed for fog_ bearer tokens')
                . ')'
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

        // A DATATABLE, like every other list in FOG, and like the central
        // pane. This was a hand-built <table> of checkboxes posted through
        // the tab form: no sorting, no search, and a shape that exists
        // nowhere else in the UI. An account with thirty integrations was
        // unreadable and there was no way to act on a subset.
        //
        // The whole card is now AJAX rather than a form. That removes the
        // hidden tokenaction=manage discriminator and _userBearerTokensPost()
        // with it -- both existed only because this card shared a POST
        // target with the legacy uAPIToken card above, which is exactly the
        // arrangement that made an absent checkbox read as "unticked" and
        // risked wiping uAPIToken as a side effect of touching a Bearer
        // token (the GH-987 defect class). Nothing shares a target now.
        //
        // Its own table id, not 'dataTable': the user edit page already
        // carries association grids on other tabs, and registerTable()
        // passes `retrieve: true` to DataTables -- a second init on a
        // duplicate id would silently hand back the FIRST table's instance.
        $body = '<p>'
            . _('Sent as an Authorization: Bearer header, on its own '
                . '&mdash; no fog-api-token header is needed alongside it. '
                . 'Each token acts with this user\'s roles.')
            . '</p>';

        $buttons = '';
        if ($mayDelete) {
            $buttons .= self::makeButton(
                'apitoken-delete-selected',
                _('Delete selected'),
                'btn btn-danger float-start'
            );
        }
        $buttons .= '<div class="btn-group float-end">';
        if ($mayEdit) {
            $buttons .= self::makeButton(
                'apitoken-disable-selected',
                _('Disable selected'),
                'btn btn-secondary'
            );
            $buttons .= self::makeButton(
                'apitoken-enable-selected',
                _('Enable selected'),
                'btn btn-secondary'
            );
        }
        if ($mayCreate) {
            $buttons .= self::makeButton(
                'issuetoken',
                _('Issue Token'),
                'btn btn-primary'
            );
        }
        $buttons .= '</div>';

        $table = '<table id="user-apitoken-table" '
            . 'class="display table table-bordered table-striped">';
        $table .= '<thead><tr class="header">'
            . '<th>' . _('Name') . '</th>'
            . '<th>' . _('Created') . '</th>'
            . '<th>' . _('Created By') . '</th>'
            . '<th>' . _('Last Used') . '</th>'
            . '<th width="22">' . _('Enabled') . '</th>'
            . '</tr></thead><tbody></tbody></table>';

        $modals = '';
        if ($mayCreate) {
            $issueBody = '<div class="row mb-3">';
            $issueBody .= self::makeLabel(
                'col-sm-3 col-form-label',
                'newtokenname',
                _('Name')
                . '<br/>('
                . _('what this token is for')
                . ')'
            );
            $issueBody .= '<div class="col-sm-9">'
                . self::makeInput(
                    'form-control',
                    'newtokenname',
                    _('e.g. nightly inventory script'),
                    'text',
                    'newtokenname',
                    '',
                    true,
                    false,
                    1,
                    255
                )
                . '</div>';
            $issueBody .= '</div>';
            $issueBody .= '<p class="form-text mb-0">'
                . _('Required, and unique for this account &mdash; it is the '
                    . 'only thing that tells one token from another when it '
                    . 'comes time to revoke one.')
                . '</p>';

            $modals .= self::makeModal(
                'userIssueTokenModal',
                _('Issue a Bearer token'),
                $issueBody,
                self::makeButton(
                    'closeUserIssueModal',
                    _('Cancel'),
                    'btn btn-outline-secondary float-start',
                    'data-bs-dismiss="modal"'
                )
                . self::makeButton(
                    'confirmUserIssueToken',
                    _('Issue Token'),
                    'btn btn-primary float-end'
                ),
                '',
                'primary'
            );

            // The plaintext reaches the browser once, in the reply to the
            // click that created it. It is not carried in the session and
            // not re-rendered on any later page load, so a back button, a
            // refresh, or a second admin opening the same user cannot
            // surface it. Its own modal rather than an inline alert because
            // it has to be DISMISSED: closing it is the moment the grid
            // reloads, and a banner nobody closes leaves a credential on
            // screen behind whatever happens next.
            $freshBody = '<p>'
                . _('This is the only time it will be shown. FOG stores only '
                    . 'a hash of it and cannot show it again. If you lose it, '
                    . 'delete this token and issue another.')
                . '</p>'
                . '<input type="text" class="form-control" readonly '
                . 'onclick="this.select();" id="apitoken-fresh-value"/>'
                . '<p class="mt-2 mb-0"><code>Authorization: Bearer '
                . '<span id="apitoken-fresh-header"></span></code></p>';
            $modals .= self::makeModal(
                'userFreshTokenModal',
                _('Copy this token now'),
                $freshBody,
                self::makeButton(
                    'closeUserFreshToken',
                    _('Done'),
                    'btn btn-primary float-end',
                    'data-bs-dismiss="modal"'
                ),
                '',
                'success'
            );
        }
        if ($mayDelete) {
            // The re-auth prompt $.deleteSelected drives when
            // FOG_DELETE_REAUTH is on. Without it $.reAuth calls
            // modal('show') on an empty jQuery set: nothing opens, nothing
            // is deleted, nothing is logged.
            $modals .= self::makeModal(
                'deleteModal',
                _('Confirm password'),
                '<div class="input-group">'
                . self::makeInput(
                    'form-control',
                    'deletePW',
                    _('Password'),
                    'password',
                    'deletePassword'
                )
                . '</div>',
                self::makeButton(
                    'closeDeleteModal',
                    _('Cancel'),
                    'btn btn-outline-secondary float-start',
                    'data-bs-dismiss="modal"'
                )
                . self::makeButton(
                    'confirmDeleteModal',
                    _('Delete') . ' {0} ' . _('{node}'),
                    'btn btn-outline-secondary float-end'
                ),
                '',
                'danger'
            );
        }

        echo '<div class="card mt-3">';
        echo '<div class="card-header">' . _('Bearer API Tokens') . '</div>';
        echo '<div class="card-body">';
        echo $body;
        echo $table;
        echo '<div class="btn-actionbox">' . $buttons . '</div>';
        echo '</div>';
        echo '</div>';
        echo $modals;
    }
    /**
     * This user's Bearer tokens, as DataTables JSON.
     *
     * Not Route::listem(). APIToken is deliberately absent from
     * Route::$validClasses -- a token-management REST surface would let one
     * API credential mint another -- so the REST layer cannot answer this
     * and should not be taught to.
     *
     * @return void
     */
    public function userAPITokenList()
    {
        $this->userAPITokenListPost();
    }
    /**
     * @return void
     */
    public function userAPITokenListPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        if (!Authorization::can('apitoken.view')) {
            self::jsonSend(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                json_encode(
                    [
                        'error' => _('You do not have permission to view API '
                            . 'tokens.'),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }

        $uid = (int)$this->obj->get('id');
        $rows = [];
        // visibleTo() applies the acting user's object scope; the userID
        // filter here narrows it to the account being edited. Both, not
        // either: scope decides what this administrator may see at all, and
        // the id decides which of that belongs on this page.
        foreach (
            self::getClass('APITokenManager')
                ->visibleTo((int)self::$FOGUser->get('id')) as $token
        ) {
            if ($token['userID'] !== $uid) {
                continue;
            }
            $rows[] = [
                'id' => $token['id'],
                'name' => $token['name'],
                'createdTime' => $token['createdTime'],
                'createdBy' => $token['createdBy'],
                // "Never" is a different fact from "used at the epoch", and
                // the column exists precisely to tell them apart.
                'lastUsed' => '' === $token['lastUsed']
                    ? _('Never')
                    : $token['lastUsed'],
                'enabled' => $token['enabled'] ? 1 : 0
            ];
        }

        self::jsonSend(
            HTTPResponseCodes::HTTP_SUCCESS,
            json_encode(
                [
                    'draw' => (int)filter_input(INPUT_POST, 'draw') ?: 0,
                    'recordsTotal' => count($rows),
                    'recordsFiltered' => count($rows),
                    'data' => $rows
                ]
            )
        );
    }
    /**
     * Revokes the selected tokens. See _postOnly() in the config page for
     * why a base method has to exist at all.
     *
     * @return void
     */
    public function userAPITokenDelete()
    {
        $this->_tokenPostOnly();
    }
    /**
     * @return void
     */
    public function userAPITokenDeletePost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        if (!Authorization::can('apitoken.delete')) {
            self::jsonSend(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                json_encode(
                    [
                        'error' => _('You do not have permission to revoke '
                            . 'API tokens.'),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }

        // The owner id is passed, so this card can only ever act on the
        // account it is displayed under. Without it anyone who may edit one
        // user could revoke any token on the server by posting its number.
        $revoked = self::getClass('APITokenManager')->revokeMany(
            array_map('intval', (array)($_POST['remitems'] ?? [])),
            (int)self::$FOGUser->get('id'),
            (int)$this->obj->get('id')
        );

        self::jsonSend(
            HTTPResponseCodes::HTTP_SUCCESS,
            json_encode(
                [
                    'msg' => sprintf(_('%d token(s) revoked.'), $revoked),
                    'title' => _('API Tokens Revoked')
                ]
            )
        );
    }
    /**
     * Enables or disables the selected tokens.
     *
     * @return void
     */
    public function userAPITokenEnable()
    {
        $this->_tokenPostOnly();
    }
    /**
     * @return void
     */
    public function userAPITokenEnablePost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');

        if (!Authorization::can('apitoken.edit')) {
            self::jsonSend(
                HTTPResponseCodes::HTTP_FORBIDDEN,
                json_encode(
                    [
                        'error' => _('You do not have permission to change '
                            . 'API tokens.'),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }

        $enabled = (int)filter_input(INPUT_POST, 'enabled') === 1;
        $changed = self::getClass('APITokenManager')->setEnabledMany(
            array_map('intval', (array)($_POST['remitems'] ?? [])),
            $enabled,
            (int)self::$FOGUser->get('id'),
            (int)$this->obj->get('id')
        );

        self::jsonSend(
            HTTPResponseCodes::HTTP_SUCCESS,
            json_encode(
                [
                    'msg' => sprintf(
                        $enabled
                            ? _('%d token(s) enabled.')
                            : _('%d token(s) disabled.'),
                        $changed
                    ),
                    'title' => _('API Tokens Updated')
                ]
            )
        );
    }
    /**
     * Refuses a GET on an endpoint that only acts on POST.
     *
     * These base methods exist because of how FOGPageManager::render()
     * dispatches: it looks the BASE method up FIRST and rewrites $method to
     * 'index' when it is missing, so a sub implemented only as fooPost()
     * never reaches the .Post test and quietly renders the node's default
     * page instead -- HTTP 200, wrong body, nothing logged.
     *
     * @return void
     */
    private function _tokenPostOnly()
    {
        header('Content-type: application/json');
        self::jsonSend(
            HTTPResponseCodes::HTTP_METHOD_NOT_ALLOWED,
            json_encode(
                [
                    'error' => _('This endpoint only accepts POST.'),
                    'title' => _('API Token Failed')
                ]
            )
        );
    }
    /**
     * User Change API Post
     *
     * @return void
     */
    public function userAPIPost()
    {
        self::checkAuthAndCSRF();
        // The Bearer card used to post to this same tab URL and had to be
        // routed off first, or its submits -- carrying none of the legacy
        // card's fields -- would read an absent apienabled checkbox as
        // "unticked" and an absent apitoken as empty, silently disabling
        // fog-user-token and wiping uAPIToken as a side effect of touching
        // a Bearer token (the GH-987 defect class exactly). That card is
        // now a DataTable driven by its own endpoints and shares no POST
        // target with this one, so the discriminator is gone with it.
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

        // Required, and required here rather than only by the form's
        // required attribute -- this endpoint is reachable without the
        // form. A nameless token is the one nobody can ever revoke with
        // confidence: the whole point of the last-used column is deciding
        // what to delete, and "(no name), never used" is not a decision
        // anybody will act on.
        if ('' === $name) {
            self::jsonSend(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                json_encode(
                    [
                        'error' => _('Give the token a name saying what it is for.'),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }

        $token = $uid > 0 ? APIToken::generate($uid, $name) : false;
        if (APIToken::DUPLICATE_NAME === $token) {
            // A user's token names are unique so the list stays readable
            // when it comes time to revoke one. Refused rather than
            // silently made unique, because a name the administrator did
            // not choose is no more identifying than no name at all.
            self::jsonSend(
                HTTPResponseCodes::HTTP_BAD_REQUEST,
                json_encode(
                    [
                        'error' => sprintf(
                            _('This account already has a token called '
                                . '"%s". Pick a different name.'),
                            $name
                        ),
                        'title' => _('API Token Failed')
                    ]
                )
            );
        }
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
