<?php
/**
 * The request for rolling urls.
 *
 * PHP version 5
 *
 * @category FOGRollingURL
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
/**
 * The request for rolling urls.
 *
 * @category FOGRollingURL
 *
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 *
 * @link     https://fogproject.org
 */
class FOGRollingURL
{
    /**
     * The url to work.
     *
     * @var string
     */
    public $url = false;
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
    public $post_data = null;
    /**
     * The headers to use for the url.
     *
     * @var array
     */
    public $headers = null;
    /**
     * Any special options needed for curl.
     *
     * @var array
     */
    public $options = null;
    /**
     * Initialize the class at call time.
     *
     * @param string $url       the url to initialize
     * @param string $method    the method for the request
     * @param array  $post_data the data to send
     * @param array  $headers   the headers to send
     * @param array  $options   the options to use
     */
    public function __construct(
        $url,
        $method = 'GET',
        $post_data = null,
        $headers = null,
        $options = null
    ) {
        $this->url = $url;
        $this->method = $method;
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;
    }
    /**
     * Destroys the class when no longer needed.
     */
    public function __destruct()
    {
        unset(
            $this->url,
            $this->method,
            $this->post_data,
            $this->headers,
            $this->options
        );
    }
}
