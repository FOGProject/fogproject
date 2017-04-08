<?php
/**
 * Creates our routes for api configuration.
 *
 * PHP Version 5
 *
 * @category Route
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
/**
 * Creates our routes for api configuration.
 *
 * @category Route
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org/
 */
class Route extends FOGBase
{
    /**
     * The api setup is enabled?
     *
     * @var bool
     */
    private static $_enabled = false;
    /**
     * The currently defined token.
     *
     * @var string
     */
    private static $_token = '';
    /**
     * HTTPS set or not store protocol to use.
     *
     * @var string
     */
    public static $httpproto = false;
    /**
     * HTTP_HOST variable.
     *
     * @var string
     */
    public static $httphost = '';
    /**
     * AltoRouter object container.
     *
     * @var AltoRouter
     */
    public static $router = false;
    /**
     * Stores the data to print.
     *
     * @var mixed
     */
    public static $data;
    /**
     * Initialize element.
     *
     * @return void
     */
    public function __construct()
    {
        /**
         * Set proto and host.
         */
        self::$httpproto = 'http'
            . (
                filter_input(INPUT_SERVER, 'HTTPS') ?
                's' :
                ''
            );
        self::$httphost = filter_input(INPUT_SERVER, 'HTTP_HOST');
        list(
            self::$_enabled,
            self::$_token
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_API_ENABLED',
                    'FOG_API_TOKEN'
                )
            ),
            'value'
        );
        /**
         * If API is not enabled redirect to home page.
         */
        if (!self::$_enabled) {
            header(
                'Location: ',
                sprintf(
                    '%s://%s/fog/management/index.php',
                    self::$httpproto,
                    self::$httphost
                )
            );
            exit;
        }
        /**
         * Test our token.
         */
        self::_testToken();
        /**
         * Test our authentication.
         */
        self::_testAuth();
        /**
         * Ensure api has unlimited time.
         */
        ignore_user_abort(true);
        session_write_close();
        set_time_limit(0);
        /**
         * If the router is already defined,
         * don't re-instantiate it.
         */
        if (self::$router) {
            return;
        }
        self::$router = new AltoRouter;
    }
    /**
     * Test token information.
     *
     * @return void
     */
    private static function _testToken()
    {
        $passtoken = base64_decode(
            filter_input(INPUT_SERVER, 'HTTP_FOG_API_TOKEN')
        );
        if ($passtoken !== self::$_token) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_FORBIDDEN
            );
        }
    }
    /**
     * Test authentication.
     *
     * @return void
     */
    private static function _testAuth()
    {
        $auth = self::$FOGUser->passwordValidate(
            $_SERVER['PHP_AUTH_USER'],
            $_SERVER['PHP_AUTH_PW']
        );
        if (!$auth) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_UNAUTHORIZED
            );
        }
    }
    /**
     * Test validity of the class.
     *
     * @param string $class The class to work with.
     *
     * @return void
     */
    private static function _testValid($class)
    {
        if (!in_array(strtolower($class), self::$validClasses)) {
            HTTPResponseCodes::breakHead(
                HTTPResponseCodes::HTTP_NOT_IMPLEMENTED
            );
        }
    }
    /**
     * Presents the equivalent of a page's list all.
     *
     * @param string $class The class to work with.
     *
     * @return void
     */
    public static function list($class)
    {
    }
    /**
     * Presents the equivalent of a page's search.
     *
     * @param string $class The class to work with.
     * @param string $item  The "search".
     *
     * @return void
     */
    public static function search($class, $item)
    {
    }
    /**
     * Enables editing/updating a specified object.
     *
     * @return void
     */
    public static function edit()
    {
    }
    /**
     * Sets an error message.
     *
     * @param string $message The error message to pass.
     *
     * @return void
     */
    public static function setErrorMessage($message)
    {
        self::$data['error'] = $message;
    }
    /**
     * Generates a default means to print data to screen.
     *
     * @param mixed $data The data to print.
     *
     * @return void
     */
    public static function printer($data)
    {
        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}
