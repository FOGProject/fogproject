<?php
/**
 * The api builder.
 *
 * PHP Version 5
 *
 * @category API
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The api builder.
 *
 * PHP Version 5
 *
 * @category API
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class API
{
    /**
     * Method request.
     *
     * @var string
     */
    protected static $method = '';
    /**
     * Endpoint. The model requested in the uri.
     *
     * @var string
     */
    protected static $endpoint = '';
    /**
     * Verb. The optional additional descriptor.
     *
     * @var string
     */
    protected static $verb = '';
    /**
     * Args. Any additioanl args.
     *
     * @var array
     */
    protected static $args = array();
    /**
     * File stores the input of PUT request.
     *
     * @var mixed
     */
    protected static $file = null;
    /**
     * Request information.
     *
     * @var mixed
     */
    protected static $request = '';
    /**
     * Constructor. Allow for CORS, assemble and
     * pre-process the data.
     *
     * @param string $request The Request to process.
     *
     * @return void
     */
    public function __construct($request)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: *');
        header('Content-Type: application/json');
        self::$args = explode(
            '/',
            rtrim(
                $request,
                '/'
            )
        );
        self::$endpoint = array_shift(self::$args);
        if (array_key_exists(0, self::$args)
            && !is_numeric(self::$args[0])
        ) {
            self::$verb = array_shift(self::$args);
        }

        self::$method = $_SERVER['REQUEST_METHOD'];
        if (self::$method == 'POST'
            && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)
        ) {
            switch ($_SERVER['HTTP_X_HTTP_METHOD']) {
            case 'DELETE':
                self::$method = 'DELETE';
                break;
            case 'PUT':
                self::$method = 'PUT';
                break;
            default:
                throw new Exception(_('Unexpected Header'));
            }
        }
        switch (self::$method) {
        case 'DELETE':
        case 'POST':
            self::$request = self::_cleanInputs($_POST);
            break;
        case 'GET':
            self::$request = self::_cleanInputs($_GET);
            break;
        case 'PUT':
            self::$request = self::_cleanInputs($_GET);
            self::$file = file_get_contents("php://input");
            break;
        default:
            self::_response(_('Invalid Method'), 405);
            break;
        }
    }
    /**
     * Process api information.
     *
     * @return mixed
     */
    public function processAPI()
    {
        if (method_exists(self, self::$endpoint)) {
            return self::_response(
                sprintf(
                    '%s: %s',
                    _('No Endpoint'),
                    self::$endpoint
                ),
                404
            );
        }
    }
    /**
     * Returns our response.
     *
     * @param mixed $data   The data to send.
     * @param int   $status The status code to send.
     *
     * @return string
     */
    private static function _response($data, $status = 200)
    {
        header(
            sprintf(
                'HTTP/1.1 %s %s',
                $status,
                self::_requestStatus($status)
            )
        );
        return json_encode($data);
    }
    /**
     * Clean inputs.
     *
     * @param mixed $data The data to clean.
     *
     * @return mixed
     */
    private static function _cleanInputs($data)
    {
        $cleanInput = array();
        if (is_array($data)) {
            foreach ($data as $k => &$v) {
                $cleanInput[$k] = self::_cleanInputs($v);
                unset($v);
            }
        } else {
            $cleanInput = trim(
                strip_tags(
                    $data
                )
            );
        }
        return $cleanInput;
    }
    /**
     * Returns our status string.
     *
     * @param int $code The code of the response.
     *
     * @return string
     */
    private static function _responseStatus($code)
    {
        $status = array(
            200 => _('OK'),
            404 => _('Not Found'),
            405 => _('Method Not Allowed'),
            500 => _('Internal Server Error')
        );
        return isset($status[$code]) ? $status[$code] : $status[500];
    }
}
