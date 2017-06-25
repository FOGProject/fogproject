<?php
/**
 * Handles the display of schema and schema updating in general.
 *
 * PHP version 5
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
        if ($schema->get('version') >= FOG_SCHEMA) {
            self::redirect('index.php');
        }
        $this->name = 'Database Schema Installer / Updater';
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
        echo '<form class="form-horizontal" action="'
            . $this->formAction
            . '" method="post">';
        echo '<div class="col-xs-offset-4 col-xs-4">';
        echo '<input type="hidden" name="fogverified"/>';
        echo '<button type="submit" class="btn btn-primary btn-block" name='
            . '"confirm">';
        echo _('Install/Update Now');
        echo '</button>';
        echo '</div>';
        echo '</form>';
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
        if (!isset($_POST['fogverified'])) {
            return;
        }
        if (!isset($_POST['confirm'])) {
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
