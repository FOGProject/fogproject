<?php
/**
 * An api map/routing mechanism. Simplified and small.
 * Based on klein.php and uses elements of Sinatra for regex
 * matching for routes.
 *
 * PHP Version 5
 *
 * @category AltoRouter
 * @package  AltoRouter
 * @author   Danny van Kooten <no@email.given>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     https://github.com/dannyvankooten/AltoRouter
 */
/**
 * An api map/routing mechanism. Simplified and small.
 * Based on klein.php and uses elements of Sinatra for regex
 * matching for routes.
 *
 * @category AltoRouter
 * @package  AltoRouter
 * @author   Danny van Kooten <no@email.given>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     https://github.com/dannyvankooten/AltoRouter
 */
class AltoRouter
{
    /**
     * Array of all routes (incl. named routes).
     *
     * @var array
     */
    protected $routes = array();
    /**
     * Array of all named routes.
     *
     * @var array
     */
    protected $namedRoutes = array();
    /**
     * Can be used to ignore leading part of the request
     * URL (if main file lives in subdirectory of host).
     *
     * @var string
     */
    protected $basePath = '';
    /**
     * Can be used to set case sensitivity.
     *
     * @var bool
     */
    protected $ignoreCase = false;
    /**
     * Array of default match types (regex helpers)
     *
     * @var array
     */
    protected $matchTypes = array(
        'i'  => '[0-9]++',
        'a'  => '[0-9A-Za-z]++',
        'h'  => '[0-9A-Fa-f]++',
        '*'  => '.+?',
        '**' => '.++',
        ''   => '[^/\.]++'
    );
    /**
     * Array of default parameters.
     *
     * @var array
     */
    protected $defaultParams = array();
    /**
     * Create router in one call from config.
     *
     * @param array  $routes        The default routes to add.
     * @param string $basePath      The basePath at instantiation time.
     * @param array  $matchTypes    Any additions matching types you'd like.
     * @param array  $defaultParams Any default parameters.
     * @param bool   $ignoreCase    Ignore the case in matching?
     *
     * @return void
     */
    public function __construct(
        $routes = array(),
        $basePath = '',
        $matchTypes = array(),
        $defaultParams = array(),
        $ignoreCase = false
    ) {
        $this->addRoutes($routes);
        $this->setBasePath($basePath);
        $this->addMatchTypes($matchTypes);
        $this->addDefaultParams($defaultParams);
        $this->setIgnoreCase($ignoreCase);
    }
    /**
     * Magic method to route get, put, post, patch, and delete
     * to the map method. So you can call router->get(...) or
     * router->post(...) without constant rewriting.
     *
     * $router->get($route, $target, $name);
     * $router->put($route, $target, $name);
     * $router->post($route, $target, $name);
     * $router->patch($route, $target, $name);
     * $router->delete($route, $target, $name);
     *
     * @param string $name      What are we calling.
     * @param array  $arguments The arguments less the method.
     *
     * @return void
     */
    public function __call(
        $name,
        $arguments
    ) {
        $name = strtolower($name);
        $validTypes = array(
            'get' => 'GET',
            'put' => 'PUT',
            'post' => 'POST',
            'patch' => 'PATCH',
            'delete' => 'DELETE'
        );
        // If method type is invalid don't do anything.
        if (!isset($validTypes[$name])) {
            return;
        }
        // Prepend the type to our arguments to pass to the map.
        array_unshift(
            $arguments,
            $validTypes[$name]
        );
        // Pass to the map method.
        call_user_func_array(
            array($this, 'map'),
            $arguments
        );
    }
    /**
     * Retrieves all routes.
     * Useful if you want to process or display routes.
     * Returns array of all routes.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    /**
     * Returns the named routes.
     *
     * @return array
     */
    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }
    /**
     * Returns the base path.
     *
     * @return array
     */
    public function getBasePath()
    {
        return $this->basePath;
    }
    /**
     * Returns the default parameters.
     *
     * @return array
     */
    public function getDefaultParams()
    {
        return $this->getDefaultParams();
    }
    /**
     * Returns ignore case value.
     *
     * @return bool
     */
    public function getIgnoreCase()
    {
        return $this->ignoreCase;
    }
    /**
     * Add multiple routes at once from array in the following format:
     *
     *   $routes = array(
     *      array($method, $route, $target, $name)
     *   );
     *
     * @param array $routes Array of routes you'd like to add.
     *
     * @author Koen Punt
     *
     * @throws Exception
     *
     * @return void
     */
    public function addRoutes($routes)
    {
        if (!is_array($routes)
            && !$routes instanceof \Traversable
        ) {
            $msg = _('Routes should be an array or an instance of Traversable');
            if (defined('HHVM_VERSION')) {
                $msg 
                    = 'Routes should be an array or an instance of Traversable';
            }
            throw new \Exception($msg);
        }
        foreach ($routes as $route) {
            call_user_func_array(array($this, 'map'), $route);
        }
    }
    /**
     * Set the base path.
     * Useful if you are running your application from a subdirectory.
     *
     * @param string $basePath The basepath to set as needed.
     *
     * @return void
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }
    /**
     * Set the ignore case value.
     * Useful to enable/disable case sensitivity.
     *
     * @param bool $ignoreCase Set or not?
     *
     * @return void
     */
    public function setIgnoreCase($ignoreCase)
    {
        $this->ignoreCase = (bool)$ignoreCase;
    }
    /**
     * Add named match types. It uses array_merge so keys can be overwritten.
     *
     * @param array $matchTypes The key is the name and the value is the regex.
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
     * Adds default parameters.
     *
     * @param array $defaultParams The items to add.
     *
     * @return array
     */
    public function addDefaultParams($defaultParams)
    {
        $this->defaultParams = array_merge(
            $this->defaultParams,
            $defaultParams
        );
    }
    /**
     * Map a route to a target.
     *
     * @param string $method One of 5 HTTP Methods,
     * or a pipe-separated list of multiple HTTP Methods
     * (GET|POST|PATCH|PUT|DELETE)
     * @param string $route  The route regex,
     * custom regex must start with an @.
     * You can use multiple pre-set regex filters, like [i:id].
     * @param mixed  $target The target where this route
     * should point to. Can be anything.
     * @param string $name   Optional name of this route.
     * Supply if you want to reverse route this url in your application.
     *
     * @throws Exception
     *
     * @return void
     */
    public function map(
        $method,
        $route,
        $target,
        $name = null
    ) {
        $methods = explode('|', $method);
        foreach ($methods as $method) {
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = array();
            }
            $this->routes[$method][] = array($route, $target, $name);
        }
        if ($name) {
            if (isset($this->namedRoutes[$name])) {
                $msg = sprintf(
                    "%s '%s'",
                    _('Can not redeclare route'),
                    $name
                );
                if (defined('HHVM_VERSION')) {
                    $msg = sprintf(
                        "%s '%s'",
                        'Can not redeclare route',
                        $name
                    );
                }
                throw new \Exception($msg);
            }
            $this->namedRoutes[$name] = $route;
        }
    }
    /**
     * Reversed routing.
     * Generate the URL for a named route. Replace regexes with supplied parameters
     *
     * @param string $routeName The name of the route.
     * @param array  $params    Associative array of parameters
     * to replace placeholders with.
     *
     * @throws Exception
     *
     * @return string The URL of the route with named parameters in place.
     */
    public function generate(
        $routeName,
        array $params = array()
    ) {
        // Check if named route exists
        if (!isset($this->namedRoutes[$routeName])) {
            throw new \Exception(
                "Route '{$routeName}' does not exist."
            );
        }
        // Replace named parameters
        $route = $this->namedRoutes[$routeName];
        // prepend base path to route url again
        $url = $this->basePath . $route;
        // merge with default params.
        $params = array_merge(
            $this->defaultParams,
            $params
        );
        $pattern = '`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`';
        if (preg_match_all($pattern, $route, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $index => $match) {
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
                    // Part is found, replace for param value.
                    $url = str_replace(
                        $block,
                        $params[$param],
                        $url
                    );
                } elseif ($optional && $index !== 0) {
                    $url = str_replace(
                        $pre . $block,
                        '',
                        $url
                    );
                } else {
                    $url = str_replace(
                        $block,
                        '',
                        $url
                    );
                }
            }
        }
        return $url;
    }
    /**
     * Match a given Request Url against stored routes.
     * Returns Array with route information on success,
     * false on failure (no match).
     *
     * @param string $requestUrl    The request url if needed specifically.
     * @param string $requestMethod The request method if needed specifically.
     *
     * @return array|boolean 
     */
    public function match(
        $requestUrl = null,
        $requestMethod = null
    ) {
        $params = array();
        $match = false;
        // set Request Url if it isn't passed as parameter
        if (null === $requestUrl) {
            $requestUrl = $this->getRequestURI() ?: '/';
        }
        // strip base path from request url
        $requestUrl = substr(
            $requestUrl,
            strlen($this->basePath)
        );
        // Strip query string (?a=b) from Request Url
        if (false !== ($strpos = strpos($requestUrl, '?'))) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }
        // set Request Method if it isn't passed as a parameter
        if (null === $requestMethod) {
            $requestMethod = $this->getRequestMethod() ?: 'GET';
        }
        if (empty($this->routes[$requestMethod])) {
            return false;
        }
        foreach ($this->routes[$requestMethod] as $handler) {
            list(
                $route,
                $target,
                $name
            ) = $handler;
            if ('*' === $route) {
                // * wildcard (matches all)
                $match = true;
            } elseif (isset($route[0])
                && $route[0] === '@'
            ) {
                // @ regex delimiter
                $pattern = '`'
                    . substr($route, 1)
                    . '`u'
                    . ($this->ignoreCase ? 'i' : null);
                $match = (1 === preg_match($pattern, $requestUrl, $params));
            } elseif (false === ($position = strpos($route, '['))) {
                // No params in url, do string comparison
                $match = 0 === strcmp($requestUrl, $route);
                /**
                 * We should still check if the mapping matches, otherwise
                 * only routes that contain [matchType:param] will work.
                 * This means we can ONLY do mapped routes using this method.
                 *
                 * Yes, the pattern needs some work first.
                 */
                if (!$match) {
                    $regex = $this->_compileRoute($route);
                    $match = (1 === preg_match($regex, $requestUrl));
                }
            } else {
                // Compare longest non-param string with url
                if (0 !== strncmp($requestUrl, $route, $position)) {
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
                    /**
                     * Send the request method so we can test.
                     * Most likely we  use php://input so we wouldn't
                     * be able to tell by the variables.
                     *
                     * Sending the method with the system allows us to
                     * map a single route that acts upon both types.
                     * You could do this in the function too but we
                     * already know the method, why not just pass it in?
                     */
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
        }
        return false;
    }
    /**
     * Compile the regex for a given route (EXPENSIVE)
     *
     * @param string $route The route to compile.
     *
     * @return string
     */
    private function _compileRoute($route)
    {
        $pattern = '`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`';
        if (preg_match_all($pattern, $route, $matches, PREG_SET_ORDER)) {
            $matchTypes = $this->matchTypes;
            foreach ($matches as $match) {
                list(
                    $block,
                    $pre,
                    $type,
                    $param,
                    $optional
                ) = $match;
                $optional = ('' !== $optional ? '?' : null);
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
                    . ')'
                    . $optional
                    . ')'
                    . $optional;
                $route = str_replace($block, $pattern, $route);
            }
        }
        return sprintf(
            '`^%s$`u%s',
            $route,
            ($this->ignoreCase ? 'i' : null)
        );
    }
    /**
     * Get request URI from $_SERVER.
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
    /**
     * Get the matched result to return.
     * Doing so from a function allows user to override
     * in their own extends.
     *
     * @param string $target The target.
     * @param mixed  $params The parms (how we call).
     * @param string $name   The name of the match.
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
}
