<?php
/**
 * The api builder.
 * Based off AltoRouter at https://github.com/dannyvankooten/AltoRouter
 *
 * PHP Version 5
 *
 * @category Router
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The api builder.
 * Based off AltoRouter at https://github.com/dannyvankooten/AltoRouter
 *
 * @category Router
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Router
{
    /**
     * The array of routes including named routes.
     *
     * @var array
     */
    protected $routes = array();
    /**
     * The array of named routes.
     *
     * @var array
     */
    protected $namedRoutes = array();
    /**
     * Base path as needed.  If using a subdirectory which fog does we need
     * to adjust the base path.
     *
     * @var string
     */
    protected $basePath = '';
    /**
     * Match types regex helpers.
     *
     * @var array
     */
    protected $matchTypes = array(
        'i' => '[0-9]++',
        'a' => '[0-9A-Za-z]++',
        'h' => '[0-9A-Fa-f]++',
        '*' => '.+?',
        '**' => '.++',
        '' => '[^/\.]++'
    );
    /**
     * Create router in one call from config.
     *
     * @param array $routes     The routes to add
     * @param array $basePath   The basepath to work with.
     * @param array $matchTypes The match patterns if needed.
     *
     * @return void
     */
    public function __construct(
        $routes = array(),
        $basePath = '',
        $matchTypes = array()
    ) {
        $this->addRoutes($routes);
        $this->setBasePath($basePath);
        $this->addMatchTypes($matchTypes);
    }
    /**
     * Magic method to route get, put, patch, delete, and post
     * to the map method.
     *
     * @param string $name      What are we calling.
     * @param array  $arguments The item we're calling.
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $name = strtolower($name);
        $validTypes = array(
            'get' => 'GET',
            'patch' => 'PATCH',
            'post' => 'POST',
            'put' => 'PUT',
            'delete' => 'DELETE'
        );
        if (!isset($validTypes[$name])) {
            die(http_response_code(405));
        }
        array_unshift($arguments, $validTypes[$name]);
        call_user_func_array(array($this, 'map'), $arguments);
    }
    /**
     * Retrieves all routes.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    /**
     * Add multiple routes from array.
     * Format is:
     * $routes = array(
     *     array($method, $route, $target, $name)
     * );
     *
     * @param array $routes The routes to add
     *
     * @throws Exception
     *
     * @return void
     */
    public function addRoutes($routes)
    {
        if (!is_array($routes)
            && !$routes instanceof Traversable
        ) {
            throw new Exception(
                _('Routes should be an array or an instance of Traversable')
            );
        }
        foreach ($routes as $route) {
            call_user_func_array(
                array(
                    $this,
                    'map'
                ),
                $route
            );
        }
    }
    /**
     * Set the base path.
     * Useful if you're running from a subdirectory.
     *
     * @param string $basePath The path to set.
     *
     * @return void
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }
    /**
     * Add named match types. It uses array_merge
     * so keys can be overwritten.
     *
     * @param array $matchTypes The match types to add.
     *
     * @return void
     */
    public function addMatchTypes($matchTypes)
    {
        $this->matchTypes = array_merge(
            $this->matchTypes,
            $matchTypes
        );
    }
    /**
     * Maps the route to a target.
     *
     * @param string $method One of 5 HTTP Methods, or a pipe-separated
     * list of mutliple HTTP Methods (GET|POST|PATCH|PUT|DELETE)
     * @param string $route  The route regex, custom must start with @.
     * @param mixed  $target The target where the route should point to.
     * @param string $name   The name of the route. Useful for reverse routes.
     *
     * @throws Exception
     *
     * @return void
     */
    public function map(
        $method,
        $route,
        $target,
        $name = ''
    ) {
        $this->routes[] = array(
            $method,
            $route,
            $target,
            $name
        );
        if ($name) {
            if (isset($this->namedRoutes[$name])) {
                // Add to the named route in a regex patern.
                // If the route already exists, skip.
                // If it doesn't exist append to the route list.
                if (false !== strpos($route, $this->namedRoutes[$name])) {
                    return;
                }
                $this->namedRoutes[$name] = sprintf(
                    '%s|%s',
                    $this->namedRoutes[$name],
                    $route
                );
                return;
            } 
            $this->namedRoutes[$name] = $route;
        }
        return;
    }
    /**
     * Reversed routing.
     *
     * Generate the URL for a named route. Replace regexes with supplied
     * parameters.
     *
     * @param string $routeName The Name of the route.
     * @param array  $params    The associative array of params to replace
     * place holders with.
     *
     * @throws Exception
     *
     * @return string
     */
    public function generate(
        $routeName,
        array $params = array()
    ) {
        // Check if named route exists.
        if (!isset($this->namedRoutes[$routeName])) {
            throw new Exception(
                sprintf(
                    '%s {%s} %s.',
                    _('Route'),
                    $routeName,
                    _('does not exist')
                )
            );
        }
        // Replace named parameters.
        $route = $this->namedRoutes[$routeName];
        // Prepend base path to route url
        $url = sprintf(
            '%s%s',
            $this->basePath,
            $route
        );
        // Setup our pattern searching
        $pattern = '`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`';
        // If our pattern is found perform
        if (preg_match_all($pattern, $route, $matchs, PREG_SET_ORDER)) {
            // Loop our matches.
            foreach ($matches as $match) {
                // Break up the match.
                list(
                    $block,
                    $pre,
                    $type,
                    $param,
                    $optional
                ) = $match;
                if ($pre) {
                    $block = substr($block, 1);
                }
                if (isset($params[$param])) {
                    $url = str_replace(
                        $block,
                        $params[$param],
                        $url
                    );
                } elseif ($optional) {
                    $url = str_replace(
                        sprintf(
                            '%s%s',
                            $pre,
                            $block
                        ),
                        '',
                        $url
                    );
                }
            }
        }
        return $url;
    }
    /**
     * Match a given request url.
     *
     * @param string $requestUrl    The url to check
     * @param string $requestMethod The method of the request.
     *
     * @return array|boolean Array with route or false on failure.
     */
    public function match(
        $requestUrl = null,
        $requestMethod = null
    ) {
        $params = array();
        $match = false;
        // Set request url if it isn't passed as parameter
        if (null === $requestUrl) {
            $requestUrl = $this->getRequestURI() ?: '/';
        }
        // Strip base path from the request url.
        $requestUrl = substr($requestUrl, strlen($this->basePath));

        // Strip query string (?a=b) from Request url
        if (false !== ($strpos = strpos($requestUrl, '?'))) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }
        // Set request method if it isn't already passed.
        if (null === $requestMethod) {
            $requestMethod = $this->getRequestMethod() ?: '/';
        }
        foreach ($this->routes as $handler) {
            list(
                $methods,
                $route,
                $target,
                $name
            ) = $handler;
            $method_match = (false !== stripos($methods, $requestMethod));
            // Method did not match, continue to next route.
            if (!$method_match) {
                continue;
            }
            if ('*' === $route) {
                $match = true;
            } elseif (isset($route[0])
                && '@' === $route[0]
            ) {
                $pattern = '`'.substr($route, 1).'`u';
                $match = (1 === preg_match($pattern, $requestUrl, $params));
            } elseif (false === ($position = strpos($route, '['))) {
                $match = (0 === strcmp($requestUrl, $route));
                if (!$match) {
                    $regex = $this->_compileRoute($route);
                    $match = (1 === preg_match($regex, $requestUrl));
                }
            } else {
                $optional = (
                    '?' === substr(
                        $route,
                        strpos(
                            $route,
                            ']',
                            $position
                        ) + 1,
                        1
                    )
                );
                if ($optional) {
                    $signBefore = substr($route, $position - 1, 1);
                    $optional = in_array($signBefore, array('/','.'));
                }
                if ($optional) {
                    $strncmp = strncmp($requestUrl, $route, $position);
                    if (-1 !== $strncmp
                        && 0 !== $strncmp
                    ) {
                        continue;
                    }
                } elseif (0 !== strncmp($requestUrl, $route, $position)) {
                    continue;
                }
                $regex = $this->_compileRoute($route);
                $match = (1 === preg_match($regex, $requestUrl, $params));
            }
            if ($match) {
                if ($params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        }
                    }
                    $params['method'] = $requestMethod;
                }
                $result = $this->getMatchedResult(
                    $target,
                    $params,
                    $name
                );
                if ($result) {
                    return $result;
                }
            }
            unset($handler);
        }
        return false;
    }
    /**
     * Get the matched result to return.
     * Allows user to override if need be.
     *
     * @param string $target The target.
     * @param mixed  $params The params (how we call).
     * @param string $name   The name of this match.
     *
     * @return array
     */
    protected function getMatchedResult(
        $target,
        $params,
        $name
    ) {
        return array(
            'target' => $target,
            'params' => $params,
            'name' => $name
        );
    }
    /**
     * Compile the regex for the given route (EXPENSIVE)
     *
     * @param mixed $route The route to process.
     *
     * @return string
     */
    private function _compileRoute($route)
    {
        $pattern = '`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`';
        $newroutes = array();
        $routes = explode('|', $route);
        foreach ($routes as $newroute) {
            if (preg_match_all($pattern, $newroute, $matches, PREG_SET_ORDER)) {
                $matchTypes = $this->matchTypes;
                foreach ($matches as $match) {
                    list(
                        $block,
                        $pre,
                        $type,
                        $param,
                        $optional
                    ) = $match;
                    if (isset($matchTypes[$type])) {
                        $type = $matchTypes[$type];
                    }
                    if ('.' === $pre) {
                        $pre = '\.';
                    }
                    // Older versions of PCRE require the 'P' in (?P<named>)
                    $pattern = '(?:'
                        . ('' !== $pre ? $pre : null)
                        . '('
                        . ('' !== $param ? "?P<$param>" : null)
                        . $type
                        . '))'
                        . ('' !== $optional ? '?' : null);
                    $newroute = str_replace($block, $pattern, $newroute);
                }
                $newroutes[] = $newroute;
            }
        }
        return sprintf(
            '`^%s$`u',
            implode('|', $newroutes)
        );
    }
    /**
     * Get request URI from $_SERVER
     *
     * @return string
     */
    protected function getRequestURI()
    {
        return filter_input(INPUT_SERVER, 'REQUEST_URI');
    }
    /**
     * Get request method from $_SERVER
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return filter_input(INPUT_SERVER, 'REQUEST_METHOD');
    }
}
