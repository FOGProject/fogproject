<?php
/**
 * Handles the display of schema and schema updating in general.
 *
 * PHP version 7.4+
 *
 * @category SchemaUpdaterPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGPage;
use FOG\Db\DatabaseManager;
use FOG\Db\SchemaReconciler;
use FOG\Items\Schema;
use FOG\Router\HTTPResponseCodes;

/**
 * Handles the display of schema and schema updating in general.
 *
 * @category SchemaUpdaterPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SchemaUpdaterPage extends FOGPage
{
    /**
     * The relavent calling node url
     *
     * @var string
     */
    public $node = 'schema';
    /**
     * The page initializer
     *
     * @param string $name The name to work from.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        parent::__construct($name);
        $schema = new Schema(1);
        // The row seed has to survive this gate. vValue >= FOG_SCHEMA is the
        // normal "nothing to do" state, but it is also exactly the state a
        // database with a carried-over 1.5 count sits in permanently -- and
        // that is the install most likely to be missing a seeded row. Bouncing
        // it to index.php made Schema::seedRequiredRows() unreachable for the
        // very case it was written for, including via the installer, which
        // POSTs its deploy to this same page.
        if ($schema->get('version') >= FOG_SCHEMA
            && !Schema::requiredRowsMissing()
        ) {
            /*
             * The installer POSTs its deploy here and reads nothing but the
             * HTTP status, so bouncing it to the dashboard made "the schema
             * is already current" indistinguishable from "the login page
             * happened to render" -- and once a plugin could send that page
             * to an identity provider instead, installfog.sh reported
             * "Updating Database...Failed!" on a healthy database.
             *
             * Only the header channel can reach this: validSchemaBootstrap()
             * accepts the URL token only while a deploy is outstanding, and
             * by definition none is here.
             */
            if (self::validSchemaBootstrap()) {
                echo _('Database schema is already up to date.');
                exit;
            }
            self::redirect('../management/index.php');
        }
        $this->name = _('Database Schema Installer / Updater');
    }
    /**
     * The first page displayed if on GUI
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->title = _('Database Schema Installer / Updater');
        $vals = [
            "\n",
        ];

        // Established install, no admin session: an admin login is the
        // credential here, so render the login form itself rather than an
        // update button that can only 403. It has to be THIS page: while the
        // schema is stale DatabaseManager::establish() redirects every other
        // request here, so there is no reachable login page to send them to.
        // page.class.php already loads fog.login.js for any invalid user, so
        // the form is wired up, and it reloads ?node=schema on success.
        //
        // validSchemaBootstrap() first, though: a caller holding the install
        // token must not be sent to a login form. Logging in reads the schema
        // this deploy is about to create, so on an upgrade that form cannot be
        // passed at all -- see GH-927. The token IS the credential in that
        // case, exactly as it is for the installer's own header.
        if (!self::validSchemaBootstrap()
            && self::hasFogUsers()
            && !self::isSchemaAdmin()
        ) {
            // A non-admin who is already signed in gets bounced here by the
            // same stale-schema redirect. They are shown the way out rather
            // than a login form, because the form does not work for them:
            // page.class.php only enqueues fog.login.js when the user is
            // INVALID, so for a signed-in user nothing intercepts the submit,
            // the browser posts natively, and loginPost() answers with
            // Content-type: application/json -- a raw JSON blob on screen
            // instead of a reloaded page.
            //
            // Sending them through logout is also the honest sequence: it
            // ends the session that cannot do this, and the login form they
            // land on afterward is the wired one. Logout survives the
            // stale-schema redirect for exactly this reason (see the
            // carve-out in DatabaseManager::establish()).
            if (self::$FOGUser && self::$FOGUser->isValid()) {
                printf(
                    // alert-link, not btn-primary: a .btn inside a .alert
                    // renders its label unreadably dark on the fill under the
                    // dark theme. .alert-link is Bootstrap's own mechanism for
                    // a link in this exact placement -- it takes its color
                    // from --bs-alert-link-color, which the alert variant
                    // defines per theme -- so it is legible in both without
                    // this page having to know anything about the palette.
                    '<div class="container-fluid pt-3">'
                    . '<div class="alert alert-warning" role="alert">'
                    . '<p class="mb-1"><strong>%s</strong> %s</p>'
                    . '<a href="../management/index.php?node=logout" '
                    . 'class="alert-link">%s</a>'
                    . '</div></div>',
                    \Initiator::e(
                        sprintf(
                            _('Signed in as %s.'),
                            self::$FOGUser->get('name')
                        )
                    ),
                    \Initiator::e(
                        _(
                            'Applying a database schema update requires an '
                            . 'administrator account.'
                        )
                    ),
                    \Initiator::e(_('Log out and sign in as an administrator'))
                );
                return;
            }
            ProcessLogin::mainLoginForm();
            return;
        }
        $buttons = self::makeButton(
            'schema-send',
            _('Install/Update now'),
            'btn btn-primary d-none runningdb float-end'
        );

        echo self::makeFormTag(
            '',
            'schema-update-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        // Carry the token into the POST so the deploy runs without a session.
        // Emitted solely to a caller who already proved possession, and only
        // while a deploy is outstanding -- so it is never disclosed, and it
        // stops working the moment this deploy brings the schema up to date.
        // Not gated on hasFogUsers(): an upgrade has users and still needs
        // this path, which is the whole of GH-927.
        if (self::installTokenParam()) {
            echo '<input type="hidden" name="fogtoken" value="'
                . \Initiator::e(FOG_SCHEMA_INSTALL_TOKEN)
                . '"/>';
        }
        echo '<div class="card" id="schema-modify">';
        echo '<div class="card-body">';
        echo '<!-- Schema Update -->';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Database Install | Update');
        echo '</h4>';
        echo '</div>';

        echo '<div class="card-body">';

        // DB Running
        echo '<div class="d-none runningdb" id="runningdb">';
        echo '<p class="form-text">';
        printf(
            '%s %s %s %s %s (%s->%s->%s), %s %s.',
            _('If you would like to backup your'),
            _('FOG database you can do so using'),
            _('MySQL Administrator or by running'),
            _('the following command in a terminal'),
            _('window'),
            _('Applications'),
            _('System Tools'),
            _('Terminal'),
            _('this will save the backup in your home'),
            _('directory')
        );
        echo '</p>';
        echo '<hr/>';
        echo '<p class="form-text">';
        printf(
            '%s, %s %s. %s, %s %s %s. %s, %s %s.',
            _('Your FOG database schema is not up to date'),
            _('either because you have updated'),
            _('or this is a new FOG installation'),
            _('If this is an upgrade'),
            _('there will be a database backup stored on your'),
            _('FOG server defaulting under the folder'),
            '/home/fogDBbackups',
            _('Should anything go wrong'),
            _('this backup will enable you to return to the'),
            _('previous install if needed')
        );
        echo '</p>';
        echo '<pre>';
        echo 'mysqldump --allow-keywords -x -v fog > fogbackup.sql';
        echo '</pre>';
        echo '</div>';

        // Completed Update.
        echo '<div class="d-none" id="completed">';
        echo '<p class="form-text">';
        echo _('Click ');
        echo '<a href="../management/index.php">';
        echo _('here');
        echo _(' to login');
        echo '</p>';
        echo '</div>';

        // DB Not Running
        echo '<div class="d-none" id="stoppeddb">';
        echo '<p class="form-text">';
        printf(
            '%s. %s. %s. %s %s%s%s. %s. %s, %s, %s.',
            _('Your database connection appears to be invalid'),
            _('FOG is unable to communicate with the database'),
            _('There are many reasons why this could be the case'),
            _('Please check your credentials in'),
            dirname(dirname(__FILE__)),
            DS,
            'fog' . DS . 'config.class.php',
            _('Also confirm that the database is indeed running'),
            _('If credentials are correct'),
            _('and if the Database service is running'),
            _('check to ensure your filesystem has enough space')
        );
        echo '</p>';
        echo '<pre id="dberror" class="d-none"></pre>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-footer">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * When a form is submitted, this function handles it.
     *
     * @return void
     */
    public function indexPost()
    {
        header('Content-type: application/json');
        // Defense in depth: the dispatcher already gates this. Three tiers,
        // keyed on the credential *channel* rather than on install state,
        // because the installer's own non-interactive update runs on upgrades
        // too -- where users already exist and no session is possible. See
        // FOGBase::installTokenHeader()/installTokenParam().
        if (self::installTokenHeader()) {
            // Tier 1, the installer. A header cannot be driven cross-site.
        } elseif (self::isSchemaAdmin()) {
            // Tier 2, a human upgrading. The dispatcher's checkAuthAndCSRF()
            // has already enforced CSRF on this POST; isSchemaAdmin() tightens
            // is_authorized(), which also admits uType 1 mobile users.
        } elseif (self::schemaNeedsDeploy() && self::installTokenParam()) {
            // Tier 3, browser bootstrap. Dies once this deploy brings the
            // schema up to date, which is the point -- this is the copy of the
            // token that reaches stdout, install logs and browser history.
            // Gated on a deploy being outstanding rather than on the install
            // being userless: an upgrade has users and is exactly when the
            // Tier 2 login is unusable. GH-927.
        } else {
            $this->jsonSend(HTTPResponseCodes::HTTP_FORBIDDEN, json_encode(
                [
                    'error' => _('Unauthorized'),
                    'title' => _('Schema Update Fail')
                ]
            ));
        }
        include sprintf(
            '%s%scommons%sschema.php',
            BASEPATH,
            DS,
            DS
        );
        $errors = [];
        $serverFault = false;
        try {
            if (!DatabaseManager::getLink()) {
                throw new \Exception(_('Database connection unavailable.'));
            }
            // Whether there is any INDEXED work. There may be none and still
            // be work to do: the reconcile and the required-row seed below are
            // keyed on what the database actually looks like, not on a
            // position in the array, and they are the only repair a database
            // whose vValue already exceeds the array length can ever get. That
            // used to return here before either of them ran, which is what
            // left such a server permanently "up to date" AND permanently
            // missing whatever a later step was supposed to insert.
            $hasIndexed = count($this->schema) > self::$mySchema;
            $items = $hasIndexed
                ? array_slice(
                    $this->schema,
                    self::$mySchema,
                    null,
                    true
                )
                : [];
            $newSchema = self::getClass('Schema', 1);
            foreach ((array)$items as $version => &$updates) {
                foreach ((array)$updates as &$update) {
                    if (!$update) {
                        continue;
                    }
                    if (is_callable($update)) {
                        $result = $update();
                        if (is_string($result)) {
                            $errors[] = sprintf(
                                "%s: %s\n",
                                _('Update ID'),
                                $version + 1
                            )
                            . ' '
                            . sprintf(
                                "%s: %s\n",
                                _('Function Error'),
                                $result
                            )
                            . ' '
                            . sprintf(
                                "%s: %s\n",
                                _('Function'),
                                print_r($update, 1)
                            );
                            error_log(
                                sprintf(
                                    "%s: %s\n",
                                    _('Update ID'),
                                    $version + 1
                                ),
                                3,
                                BASEPATH . 'fog_schema_update_error.log'
                            );
                            error_log(
                                sprintf(
                                    "%s: %s\n",
                                    _('Function Error'),
                                    $result
                                ),
                                3,
                                BASEPATH . 'fog_schema_update_error.log'
                            );
                            error_log(
                                sprintf(
                                    "%s: %s\n",
                                    _('Function'),
                                    print_r($update, 1)
                                ),
                                3,
                                BASEPATH . 'fog_schema_update_error.log'
                            );
                            unset($update);
                            break 2;
                        }
                    } elseif (false !== self::$DB->query($update)->error) {
                        $skiperrs = [
                            1050, // Can't drop not exist
                            1054, // Column not found.
                            1060, // Duplicate column name
                            1061, // Duplicate index/key name
                            1062, // Duplicate entry
                            1091  // Table not exist.
                        ];
                        $err = self::$DB->errorCode;
                        if (in_array($err, $skiperrs)) {
                            continue;
                        }
                        $errors[] = sprintf(
                            "%s: %s\n",
                            _('Update ID'),
                            $version + 1
                        )
                        . ' '
                        . sprintf(
                            "%s: %s\n",
                            _('Database Error'),
                            self::$DB->error
                        )
                        . ' '
                        . sprintf(
                            "%s: %s\n",
                            _('Variable contains'),
                            print_r($this->schema[$version], 1)
                        )
                        . ' '
                        . sprintf(
                            "%s: %s\n",
                            _('Database SQL'),
                            $update
                        );

                        error_log(
                            sprintf(
                                "%s: %s\n",
                                _('Update ID'),
                                $version + 1
                            ),
                            3,
                            BASEPATH . 'fog_schema_update_error.log'
                        );
                        error_log(
                            sprintf(
                                "%s: %s\n",
                                _('Database Error'),
                                self::$DB->error
                            ),
                            3,
                            BASEPATH . 'fog_schema_update_error.log'
                        );
                        error_log(
                            sprintf(
                                "%s: %s\n",
                                _('Variable contains'),
                                print_r($this->schema[$version], 1)
                            ),
                            3,
                            BASEPATH . 'fog_schema_update_error.log'
                        );
                        error_log(
                            sprintf(
                                "%s: %s\n",
                                _('Database SQL'),
                                $update
                            ),
                            3,
                            BASEPATH . 'fog_schema_update_error.log'
                        );
                        unset($update);
                        break 2;
                    }
                    unset($update);
                }
                $newSchema->set('version', $version + 1);
                unset($updates);
            }
            // Structural reconcile, after every update run.
            //
            // Deliberately NOT an indexed step. vValue is a count of applied
            // array elements, so a step only ever runs for installs sitting
            // below it: index 318 will never fire again for anyone already at
            // 319, and a divergence introduced after that would go unrepaired
            // until someone remembered to append another reconciler step.
            // Running it here ties the reconcile to "an update happened"
            // rather than to a position in the array, so it keeps working for
            // divergences that do not exist yet.
            //
            // 1.5.x was meant to be frozen but keeps taking security-driven
            // schema changes, so the branches will go on filling the same
            // indexes with different migrations. This is what makes that
            // survivable without per-divergence maintenance.
            //
            // Costs one information_schema read and no DDL when there is
            // nothing to repair, which is the normal case.
            $reconcile = SchemaReconciler::reconcile();
            if (is_string($reconcile)) {
                $errors[] = sprintf(
                    "%s: %s\n",
                    _('Schema reconcile'),
                    $reconcile
                );
                // Both destinations on purpose. The file keeps this next to
                // the updater's other failures, but it sits in the web root
                // and is chmod'd to 0200 when the run finishes, so nobody
                // can read it back without root. Mirroring to the PHP error
                // log is what makes the failure actually diagnosable.
                error_log(
                    sprintf("%s: %s\n", _('Schema reconcile'), $reconcile),
                    3,
                    BASEPATH . 'fog_schema_update_error.log'
                );
                error_log(
                    sprintf('%s: %s', _('Schema reconcile'), $reconcile)
                );
            }
            // Required rows, seeded by identity rather than by array position.
            // Same rationale as the reconcile above -- see
            // Schema::seedRequiredRows() -- but for row data, which the
            // reconciler is declared never to touch.
            $seeded = Schema::seedRequiredRows();
            if (is_string($seeded)) {
                $errors[] = sprintf("%s: %s\n", _('Schema row seed'), $seeded);
                error_log(
                    sprintf("%s: %s\n", _('Schema row seed'), $seeded),
                    3,
                    BASEPATH . 'fog_schema_update_error.log'
                );
                error_log(sprintf('%s: %s', _('Schema row seed'), $seeded));
                $seeded = 0;
            }
            if (!$newSchema->save()
                || count($errors) > 0
            ) {
                $serverFault = true;
                throw new \Exception(_('Unable to update schema'));
            }
            // Reported only once everything above has actually run, so a
            // server with no indexed steps left still gets its reconcile and
            // its row seed before being told there was nothing to do.
            if (!$hasIndexed && $seeded < 1) {
                $this->jsonSend(HTTPResponseCodes::HTTP_NO_CONTENT, json_encode(
                    [
                        'msg' => _('Update not required'),
                        'title' => _('Update Not Required')
                    ]
                ));
            }
            $db = self::$DB->returnThis();
            self::$DB->currentDb($db);
            $code = HTTPResponseCodes::HTTP_SUCCESS;
            $msg = json_encode(
                [
                    'msg' => _('Schema updated successfully!'),
                    'title' => _('Schema Update Success')
                ]
            );
        } catch (\Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Schema Update Fail')
                ]
            );
            if ($serverFault) {
                $fatal = implode("\n", $errors);
                error_log(
                    $fatal,
                    3,
                    BASEPATH . 'fog_schema_update_error.log'
                );
            };
        }
        if (file_exists(BASEPATH . 'fog_schema_update_error.log')) {
            chmod(BASEPATH . 'fog_schema_update_error.log', 0200);
        }
        $this->jsonSend($code, $msg);
    }
}
