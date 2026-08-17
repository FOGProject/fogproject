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
     * The relevant calling node url
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
        if ($schema->get('version') >= FOG_SCHEMA) {
            self::redirect('index.php');
        }
        $this->name = _('Database Schema Installer / Updater');
        $this->menu = array();
        $this->subMenu = array();
    }
    /**
     * The first page displayed if on GUI
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('Database Schema Installer / Updater');
        $vals = array(
            "\n",
        );
        // Success
        echo '<div class="panel panel-info hiddeninitially" id="dbRunning">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Install/Update');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-body">';
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
        echo '<pre>';
        echo 'mysqldump --allow-keywords -x -v fog > fogbackup.sql</p</pre>';
        echo '</div>';
        echo '</div>';
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
        echo '<br/>';
        echo '<br/>';
        printf(
            '%s %s?',
            _('Are you sure you wish to'),
            _('install or update the FOG database')
        );
        echo '<br/>';
        echo '<br/>';
        // Established install, no admin session: there is nothing safe to
        // offer here, so ask them to log in instead of showing a button that
        // can only 403. The login form posts back to ?node=schema and lands
        // straight back on this page.
        //
        // validSchemaBootstrap() first, though: a caller holding the install
        // token must not be sent to a login form. The installer's fallback
        // prints this URL for them, and on an old enough database the login
        // reads schema the deploy has not created yet -- see GH-927. The token
        // IS the credential in that case, exactly as it is for the installer.
        if (!self::validSchemaBootstrap()
            && self::hasFogUsers()
            && !self::isSchemaAdmin()
        ) {
            printf(
                '<p>%s.</p>',
                _('Log in as a FOG administrator to apply this update')
            );
            // mainLoginForm() echoes; its form posts name="login" back to
            // $this->formAction (?node=schema), which management/index.php
            // hands to processMainLogin() on the next request.
            self::getClass('ProcessLogin')->mainLoginForm();
        } else {
            echo '<form class="form-horizontal" action="'
                . $this->formAction
                . '" method="post">';
            echo '<div class="col-xs-offset-4 col-xs-4">';
            // Carry the token into the POST so the deploy runs without a
            // session. Emitted solely to a caller who already proved
            // possession, and only while a deploy is outstanding -- so it is
            // never disclosed, and it stops working the moment this deploy
            // brings the schema up to date. Not gated on hasFogUsers(): an
            // upgrade has users and still needs this path. GH-927.
            if (self::installTokenParam()) {
                echo '<input type="hidden" name="fogtoken" value="'
                    . Initiator::e(FOG_SCHEMA_INSTALL_TOKEN)
                    . '"/>';
            }
            if (self::isSchemaAdmin()) {
                echo '<input type="hidden" name="_csrf" value="'
                    . Initiator::e(CSRF::token())
                    . '"/>';
            }
            echo '<input type="hidden" name="fogverified"/>';
            echo '<button type="submit" class="btn btn-primary btn-block" name='
                . '"confirm">';
            echo _('Install/Update Now');
            echo '</button>';
            echo '</div>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        // Failure
        echo '<div class="panel panel-danger hiddeninitially" id="dbNotRunning">';
        echo '<div class="panel-heading">';
        echo '<h4 class="title">';
        echo _('Database not available');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
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
        echo '<pre id="dberror" class="hiddeninitially"></pre>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * When a form is submitted, this function handles it.
     *
     * @return void
     */
    public function indexPost()
    {
        // Three tiers, keyed on the credential *channel* rather than on
        // install state, because the installer's own non-interactive update
        // runs on upgrades too -- where users already exist and no session is
        // possible. See FOGBase::installTokenHeader()/installTokenParam().
        if (self::installTokenHeader()) {
            // Tier 1, the installer. A header cannot be driven cross-site, so
            // there is no CSRF exposure to protect against here.
        } elseif (self::isSchemaAdmin()) {
            // Tier 2, a human upgrading. Needs CSRF: without it any logged-in
            // user could be made to POST a schema deploy from a hostile page.
            CSRF::requireForStateChanging();
        } elseif (self::schemaNeedsDeploy() && self::installTokenParam()) {
            // Tier 3, browser bootstrap. Dies once this deploy brings the
            // schema up to date, which is the point -- this is the copy of the
            // token that reaches stdout, install logs and browser history.
            // Gated on a deploy being outstanding rather than on the install
            // being userless: an upgrade has users and is exactly when the
            // Tier 2 login can be unusable. GH-927.
        } else {
            http_response_code(403);
            printf('<p>%s</p>', _('Unauthorized'));
            return;
        }
        include sprintf(
            '%s%scommons%sschema.php',
            BASEPATH,
            DS,
            DS
        );
        $errors = array();
        try {
            if (!DatabaseManager::getLink()) {
                throw new Exception(_('No connection available'));
            }
            if (count($this->schema) <= self::$mySchema) {
                throw new Exception(_('Update not required!'));
            }
            $items = array_slice(
                $this->schema,
                self::$mySchema,
                null,
                true
            );
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
                                '<p><b>%s %s:</b>'
                                . ' %s<br/><br/><b>%s %s:</b>'
                                . ' <pre>%s</pre></p>'
                                . '<p><b>%s:</b>'
                                . ' <pre>%s</pre></p>',
                                _('Update'),
                                _('ID'),
                                $version + 1,
                                _('Function'),
                                _('Error'),
                                $result,
                                _('Function'),
                                print_r($update, 1)
                            );
                            unset($update);
                            break 2;
                        }
                    } elseif (false !== self::$DB->query($update)->error) {
                        $dups = array(
                            1050, // Can't drop not exist
                            1054, // Column not found.
                            1060, // Duplicate column name
                            1061, // Duplicate index/key name
                            1062, // Duplicate entry
                            1091  // Table not exist.
                        );
                        $err = self::$DB->errorCode;
                        if (in_array(self::$DB->errorCode, $dups)) {
                            continue;
                        }
                        $errors[] = sprintf(
                            '<p><b>%s %s:</b>'
                            . ' %s<br/><br/><b>%s %s:</b>'
                            . ' <pre>%s</pre></p>'
                            . '<p><b>%s:</b>'
                            . ' <pre>%s</pre></p>'
                            . '<p><b>%s:</b>'
                            . ' <pre>%s</pre></p>',
                            _('Update'),
                            _('ID'),
                            $version + 1,
                            _('Database'),
                            _('Error'),
                            self::$DB->error,
                            _('Variable contains'),
                            print_r($this->schema[$version], 1),
                            _('Database SQL'),
                            $update
                        );
                        unset($update);
                        break 2;
                    }
                    unset($update);
                }
                $newSchema->set('version', $version + 1);
                unset($updates);
            }
            if (!$newSchema->save()
                || count($errors) > 0
            ) {
                $fatalerrmsg = '';
                $fatalerrmsg = sprintf(
                    '<p>%s</p>',
                    _('Install / Update Failed!')
                );
                if (count($errors)) {
                    $fatalerrmsg .= sprintf(
                        '<h2>%s</h2>%s',
                        _('The following errors occurred'),
                        implode('<hr/>', $errors)
                    );
                }
                throw new Exception($fatalerrmsg);
            }
            $db = self::$DB->returnThis();
            self::$DB->currentDb($db);
            $text = sprintf(
                '<p>%s</p><p>%s <a href="index.php">%s</a> %s</p>',
                _('Install / Update Successful!'),
                _('Click'),
                _('here'),
                _('to login')
            );
            if (count($errors)) {
                $text = sprintf(
                    '<h2>%s</h2>%s',
                    _('The following errors occured'),
                    implode('<hr/>', $errors)
                );
            }
            if (self::$ajax) {
                echo json_encode($text);
                exit;
            }
            echo $text;
        } catch (Exception $e) {
            printf('<p>%s</p>', $e->getMessage());
            http_response_code(404);
        }
    }
}
