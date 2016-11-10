<?php
/**
 * The request for rolling urls.
 *
 * PHP version 5
 *
 * @category FOGRollingURL
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The request for rolling urls.
 *
 * @category FOGRollingURL
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGRollingURL
{
    /**
     * The url to work.
     *
     * @var string
     */
    public $url = '';
    /**
     * The method (GET, POST, DELETE, etc...).
     *
     * @var string
     */
    public $method = 'GET';
    /**
     * The data to send for the method.
     *
     * @var array
     */
    public $postData = array();
    /**
     * The headers to use for the url.
     *
     * @var array
     */
    public $headers = array();
    /**
     * Any special options needed for curl.
     *
     * @var array
     */
    public $options = array();
    /**
     * Initialize the class at call time.
     *
     * @param string $url      the url to initialize
     * @param string $method   the method for the request
     * @param array  $postData the data to send
     * @param array  $headers  the headers to send
     * @param array  $options  the options to use
     */
    public function __construct(
        $url,
        $method = 'GET',
        $postData = array(),
        $headers = array(),
        $options = array()
    ) {
        $this->url = $url;
        $this->method = $method;
        $this->postData = $postData;
        $this->headers = $headers;
        $this->options = $options;
    }
    /**
     * Destroys the class when no longer needed.
     */
    public function __destruct()
    {
        $this->url = '';
        $this->method = 'GET';
        $this->postData = array();
        $this->headers = array();
        $this->options = array();
    }
}
