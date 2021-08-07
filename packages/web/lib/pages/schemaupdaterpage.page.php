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
            self::redirect('../management/index.php');
        }
        $this->name = 'Database Schema Installer / Updater';
    }
    /**
     * The first page displayed if on GUI
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('Database Schema Installer / Updater');
        $vals = [
            "\n",
        ];

        $buttons = self::makeButton(
            'schema-send',
            _('Install/Update now'),
            'btn btn-primary hidden runningdb pull-right'
        );

        echo self::makeFormTag(
            'form-horizontal',
            'schema-update-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid" id="schema-modify">';
        echo '<div class="box-body">';
        echo '<!-- Schema Update -->';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Database Install | Update');
        echo '</h4>';
        echo '</div>';

        echo '<div class="box-body">';

        // DB Running
        echo '<div class="hidden runningdb" id="runningdb">';
        echo '<p class="help-block">';
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
        echo '<p class="help-block">';
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
        echo '<div class="hidden" id="completed">';
        echo '<p class="help-block">';
        echo _('Click ');
        echo '<a href="../management/index.php">';
        echo _('here');
        echo _(' to login');
        echo '</p>';
        echo '</div>';

        // DB Not Running
        echo '<div class="hidden" id="stoppeddb">';
        echo '<p class="help-block">';
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
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-footer with-border">';
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
                throw new Exception(_('Database connection unavailable.'));
            }
            if (count($this->schema) <= self::$mySchema) {
                http_response_code(HTTPResponseCodes::HTTP_NO_CONTENT);
                echo json_encode(
                    [
                        'msg' => _('Update not required'),
                        'title' => _('Update Not Required')
                    ]
                );
                exit;
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
            if (!$newSchema->save()
                || count($errors) > 0
            ) {
                $serverFault = true;
                throw new Exception(_('Unable to update schema'));
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
        } catch (Exception $e) {
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
            if ($serverfault) {
                $fatal = implode("\n", $errors);
                error_log(
                    $fatal,
                    3,
                    BASEPATH . 'fog_schema_update_error.log'
                );
            };
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
}
