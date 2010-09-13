<?php
/**
 * Phix
 *
 * LICENSE
 *
 * This source file is subject to the BSD license that is available
 * through the world-wide-web at this URL:
 * http://opensource.org/licenses/bsd-license.php
 *
 * @package   Phix
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace Phix;

/**
 * @package   Phix
 * @author    Jan Sorgalla
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */
class App
{
    const ENV_PRODUCTION  = 'production';
    const ENV_TESTING     = 'testing';
    const ENV_STAGING     = 'staging';
    const ENV_DEVELOPMENT = 'develoment';

    const FLASH_SESSION_KEY = '__PHIX_FLASH';

    /**
     * Flag indicating application is stopped.
     * @var boolean
     */
    private $_stopped = false;

    /**
     * Flag indicating whether session was started by App and should be closed in _shutdown().
     * @var boolean
     */
    private $_sessionStarted = false;

    /**
     * Flag wether to flush automatically.
     * @var boolean
     */
    private $_autoFlush = true;

    /**
     * Assigned routes.
     * @var array
     */
    private $_routes = array();

    /**
     * Dispatcher callback.
     * @var mixed
     */
    private $_dispatcher;

    /**
     * Router callback.
     * @var mixed
     */
    private $_router;

    /**
     * Assigned parameters.
     * @var array
     */
    private $_params = array();

    /**
     * Regitry.
     * @var array
     */
    private $_registry = array();

    /**
     * Bound hooks.
     * @var array
     */
    private $_hooks = array();

    /**
     * Environment.
     * @var string
     */
    private $_env;

    /**
     * Encoding.
     * @var string
     */
    private $_encoding = 'utf-8';

    /**
     * Response headers.
     * @var array
     */
    private $_headers = array();

    /**
     * Response status.
     * @var integer
     */
    private $_status = 200;

    /**
     * Response body.
     * @var string
     */
    private $_output;

    /**
     * Caught exceptions.
     * @var array
     */
    private $_exceptions = array();

    /**
     * View renderer callback.
     * @var mixed
     */
    private $_renderer;

    /**
     * Directory the views are stored.
     * @var string
     */
    private $_viewsDir;

    /**
     * Layout.
     * @var string
     */
    private $_layout;

    /**
     * Views.
     * @var array
     */
    private $_views = array();

    /**
     * Default format.
     * @var string
     */
    private $_defaultFormat = 'html';

    /**
     * Available formats.
     * @var array
     */
    private $_formats = array();

    /**
     * Request headers.
     * @var string
     */
    private $_requestHeaders = array();

    /**
     * Raw request body
     * @var string|false
     */
    private $_requestRawBody;

    /**
     * REQUEST_METHOD
     * @var string
     */
    private $_requestMethod;

    /**
     * REQUEST_URI
     * @var string
     */
    private $_requestUri;

    /**
     * Base URL of request
     * @var string
     */
    private $_baseUrl;

    /**
     * Base path of request
     * @var string
     */
    private $_basePath;

    /**
     * PATH_INFO
     * @var string
     */
    private $_pathInfo;

    /**
     * Server Url
     * @var string
     */
    private $_serverUrl;

    /**
     * Status list.
     * @var array
     */
    private $_statusPhrases =  array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',

        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        226 => 'IM Used',

        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        510 => 'Not Extended'
    );

    /**
     * Create App instance.
     *
     * @param array $config (Can be a callable)
     * @return \Phix\App
     */
    public static function instance($config = null)
    {
        $class = get_called_class();
        return new $class($config);
    }

    /**
     * Contructor.
     *
     * @param array $config (Can be a callable)
     */
    public function __construct($config = null)
    {
        $this->setDefaultFormats();

        $this->_setup();

        if (null !== $config) {
            $this->configure($config);
        }
    }

    /**
     * Setup hook intended for usage in subclasses.
     *
     * @return void
     */
    protected function _setup()
    {
    }

    /**
     * Configure App.
     *
     * @param array $config (Can be a callable)
     * @return \Phix\App
     */
    public function configure($config)
    {
        if (is_callable($config)) {
            $config = call_user_func($config, $this);
        }

        if (!is_array($config)) {
            return $this;
        }

        $forbidden = array(
            'configure',
            'run',
        );

        foreach ($config as $method => $args) {
            if ($method[0] == '_') {
                throw new \Exception('Configuring through private methods is forbidden');
            }

            if (in_array($method, $forbidden)) {
                throw new \Exception('Configuring through method "' . $method . '" is forbidden');
            }

            call_user_func_array(array($this, $method), $args);
        }

        return $this;
    }

    /**
     * Set/Get whether to flush automatically.
     *
     * @param boolean $bool (Can be a callable)
     * @return boolean|\Phix\App
     */
    public function autoFlush($autoFlush = true)
    {
        if (func_num_args() == 0) {
            return $this->_autoFlush;
        }

        if (is_callable($autoFlush)) {
            $autoFlush = call_user_func($autoFlush, $this);
        }

        $this->_autoFlush = (bool) $autoFlush;

        return $this;
    }

    /**
     * Run application.
     *
     * @return \Phix\App
     */
    public function run()
    {
        try {
            $this->_stopped = false;

            $this->_init();
            $this->_run();
            $this->_shutdown();
        } catch (\Exception $e) {
            $this->exceptionHandler($e);
        }

        if ($this->autoFlush()) {
            $this->flush();
        }

        return $this;
    }

    /**
     * Flush headers and output.
     *
     * @return \Phix\App
     */
    public function flush()
    {
        if (false !== $this->trigger('flush')) {
            if (!headers_sent()) {
                $statusSent = false;
                foreach ($this->headers() as $header) {
                    if (!$statusSent) {
                        header($header, false, $this->status());
                        $statusSent = true;
                    } else {
                        header($header, false);
                    }
                }

                if (!$statusSent) {
                    header('HTTP/1.1 ' . $this->status());
                }
            }

            if ($this->requestMethod() != 'HEAD') {
                echo $this->output();
            }
        }

        $this->_headers = array();
        $this->_output  = null;

        $this->trigger('flush_end');

        return $this;
    }

    /**
     * Reset state.
     *
     * @return \Phix\App
     */
    public function reset()
    {
        if (false === $this->trigger('reset')) {
            return $this;
        }

        $this->_stopped    = false;
        $this->_status     = 200;
        $this->_headers    = array();
        $this->_output     = null;
        $this->_exceptions = array();

        $this->params(array(), true);

        $this->trigger('reset_end');

        return $this;
    }

    /**
     * Initialize application.
     *
     * @return void
     */
    private function _init()
    {
        if (false === $this->trigger('init')) {
            return;
        }

        $vary = array();

        // Process Accept-Header
        if (false !== ($accept = $this->requestHeader('Accept'))) {
            $defaultFormat = $this->format($this->defaultFormat());
            $contentTypes = $defaultFormat['contenttype']['request'];

            if (!is_array($contentTypes)) {
                $contentTypes = array($contentTypes);
            }

            $found = false;

            foreach ($contentTypes as $contentType) {
                if (strstr($accept, $contentType)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                foreach ($this->formats() as $format => $options) {
                    $contentTypes = $options['contenttype']['request'];

                    if (!is_array($contentTypes)) {
                        $contentTypes = array($contentTypes);
                    }

                    foreach ($contentTypes as $contentType) {
                        if (strstr($accept, $contentType)) {
                            $this->param('format', $format);
                            $vary[] = 'Accept';
                            break 2;
                        }
                    }
                }
            }
        }

        // Process Range-Header
        if (false !== ($range = $this->requestHeader('Range'))) {
            if (preg_match('/^([^\=]+)=((\d*-\d*,? ?)+)$/', $range, $matches)) {
                list($start, $end) = explode('-', $matches[2]);
                $this->param('range_type', $matches[1]); // Range type may be "bytes" or "items"
                $this->param('range_start', (int) $start);
                $this->param('range_end', (int) $end);
                $vary[] = 'Range';
            }
        }

        // Process raw body
        $rawBody = $this->requestRawBody();

        if ($rawBody) {
            $processed = false;
            $requestContentType = $this->requestHeader('Content-Type');

            foreach ($this->formats() as $format => $options) {
                $contentTypes = $options['contenttype']['request'];

                if (!is_array($contentTypes)) {
                    $contentTypes = array($contentTypes);
                }

                foreach ($contentTypes as $contentType) {
                    if (strstr($requestContentType, $contentType)) {
                        if (isset($options['unserialize']) && is_callable($options['unserialize'])) {
                            $unserialized = call_user_func($options['unserialize'], $this, $rawBody);
                            if (is_array($unserialized)) {
                                $_POST = array_merge($_POST, $unserialized);
                            }
                            $processed = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$processed) {
                $requestMethod = $this->requestMethod();
                if ($requestMethod == 'PUT' || $requestMethod == 'DELETE') {
                    if (strstr($requestContentType, 'application/x-www-form-urlencoded')) {
                        parse_str($rawBody, $params);
                        $_POST = array_merge($_POST, $params);
                    } else {
                        $putFile = tempnam(ini_get('upload_tmp_dir'), 'PUTUpload_');
                        file_put_contents($putFile, $rawBody);

                        $_FILES = array(
                            'rawbody' => array(
                                'name'             => $putFile,
                                'type'             => !empty($requestContentType) ? $requestContentType : 'application/octet-stream',
                                'size'             => strlen($rawBody),
                                'tmp_name'         => $putFile,
                                'error'            => UPLOAD_ERR_OK,
                                'is_uploaded_file' => false
                            )
                        );
                    }
                } elseif ($requestMethod == 'POST') {
                    if (!strstr($requestContentType, 'application/x-www-form-urlencoded') && !strstr($requestContentType, 'multipart/form-data')) {
                        $postFile = tempnam(ini_get('upload_tmp_dir'), 'POSTUpload_');
                        file_put_contents($postFile, $rawBody);

                        $_FILES = array(
                            'rawbody' => array(
                                'name'             => $postFile,
                                'type'             => !empty($requestContentType) ? $requestContentType : 'application/octet-stream',
                                'size'             => strlen($rawBody),
                                'tmp_name'         => $postFile,
                                'error'            => UPLOAD_ERR_OK,
                                'is_uploaded_file' => false
                            )
                        );
                    }
                }
            }
        }

        // Set Vary-Header
        if (count($vary) > 0) {
            $this->header('Vary: ' . implode(', ', $vary));
        }

        $this->trigger('init_end');
    }

    /**
     * Run application.
     *
     * @return void
     */
    private function _run()
    {
        if ($this->_stopped) {
            return;
        }

        if (false === $this->trigger('run')) {
            return;
        }

        $requestMethod = $this->requestMethod();
        $pathInfo      = $this->pathInfo();

        $params = array('route' => &$route, 'request_method' => &$requestMethod);

        $route = call_user_func($this->router(), $this, $this->routes(), $requestMethod, $pathInfo);

        if (false === $route) {
            if (false !== $this->trigger('run_no_route', $params)) {
                $this->notFound();
            }
            return;
        }

        if (false === $this->trigger('run_dispatch', $params)) {
            return;
        }

        if ($this->requestMethod() == 'HEAD') {
            ob_start();
        }

        $this->params($route['params']);
        call_user_func($this->dispatcher(), $this, $route['controller']);

        if ($this->requestMethod() == 'HEAD') {
            ob_end_clean();
        }

        $this->trigger('run_end');
    }

    /**
     * Shutdown application.
     *
     * @return void
     */
    private function _shutdown()
    {
        if ($this->_stopped) {
            return;
        }

        $this->_stopped = true;

        if (false === $this->trigger('shutdown')) {
            return;
        }

        if ($this->_sessionStarted && defined('SID')) {
            session_write_close();
        }

        $this->_sessionStarted = false;

        $this->trigger('shutdown_end');
    }

    /**
     * Whether the application was stopped in _shutdown().
     *
     * @return boolean
     */
    public function stopped()
    {
        return $this->_stopped;
    }

    /**
     * Escape a string for safe displaying in views.
     *
     * Note that $doubleEncode is false by default.
     *
     * @param string $string
     * @param string $quoteStyle
     * @param string $charset
     * @param boolean $doubleEncode
     * @return string
     */
    public function escape($string, $quoteStyle = ENT_COMPAT, $charset = null, $doubleEncode = false)
    {
        if (!$charset) {
            $charset = strtoupper($this->encoding());
        }

        return htmlspecialchars($string, $quoteStyle, $charset, $doubleEncode);
    }

    /**
     * Set/Get encoding used through the application.
     *
     * @param string $encoding The encoding (Can be a callable)
     * @return string|\Phix\App
     */
    public function encoding($encoding = null)
    {
        if (func_num_args() == 0) {
            return $this->_encoding;
        }

        if (is_callable($encoding)) {
            $encoding = call_user_func($encoding, $this);
        }

        $this->_encoding = $encoding;

        return $this;
    }

    /**
     * Set/Get output.
     *
     * @param string $output The output (Can be a callable)
     * @return string|\Phix\App
     */
    public function output($output = null)
    {
        if (func_num_args() == 0) {
            return $this->_output;
        }

        if (is_callable($output)) {
            $output = call_user_func($output, $this);
        }

        $this->_output = $output;

        return $this;
    }

    /**
     * Set response output and format.
     *
     * @param string $output The output (Can be a callable)
     * @param string $format The format (Can be a callable)
     * @return string|\Phix\App
     */
    public function response($output, $format = null)
    {
        $formats = $this->formats();

        if (null === $format) {
            $format = $this->currentFormat();
        } else {
            if (is_callable($format)) {
                $format = call_user_func($format, $this);
            }

            if (!isset($formats[$format])) {
                throw new \Exception('Invalid format "' . $format . '"');
            }
        }

        $contentType = $formats[$format]['contenttype']['response'];
        $this->header('Content-Type: ' . $contentType . ';charset=' . strtolower($this->encoding()));

        if (is_callable($output)) {
            $output = call_user_func($output, $this);
        }

        if (!is_string($output) && isset($formats[$format]['response'])) {
            $output = call_user_func($formats[$format]['response'], $this, $this->status(), $output);
        }

        $this->output($output);

        return $this;
    }

    /**
     * Shortcut for sending javascript output with proper http headers.
     *
     * @param string $output The output (Can be a callable)
     * @return string|\Phix\App
     */
    public function javascript($output)
    {
        if (is_callable($output)) {
            $output = call_user_func($output, $this);
        }

        $this->header('Content-Type: text/javascript;charset=' . strtolower($this->encoding()));
        $this->output($output);

        return $this;
    }

    /**
     * Shortcut for sending css output with proper http headers.
     *
     * @param string $output The output (Can be a callable)
     * @return string|\Phix\App
     */
    public function css($output)
    {
        if (is_callable($output)) {
            $output = call_user_func($output, $this);
        }

        $this->header('Content-Type: text/css;charset=' . strtolower($this->encoding()));
        $this->output($output);

        return $this;
    }

    /**
     * Set/Get multiple hooks.
     *
     * @param string $hooks The hooks
     * @param boolean $reset Whether to reset existing hooks
     * @return array|\Phix\App
     */
    public function hooks(array $hooks = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_hooks;
        }

        if ($reset) {
            $this->_hooks = array();
        }

        foreach ($hooks as $hook) {
            $this->hook($hook[0], $hook[1], isset($hook[2]) ? $hook[2] : null);
        }

        return $this;
    }

    /**
     * Register a hook.
     *
     * @param string $event The event
     * @param mixed $callback The callback
     * @param mixed $index The index
     * @return \Phix\App
     */
    public function hook($event, $callback, $index = null)
    {
        if (null === $index) {
            $this->_hooks[$event][] = $callback;
        } else {
            if (isset($this->_hooks[$event][$index])) {
                throw new \Exception('There is already a hook registered at index "' . $index . '"');
            }

            $this->_hooks[$event][$index] = $callback;
        }

        return $this;
    }

    /**
     * Unregister hook(s).
     *
     * @param string $event The event
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function unhook($event = null, $callback = null)
    {
        if (func_num_args() == 0) {
            $this->_hooks = array();
        } elseif (func_num_args() == 1) {
            if (isset($this->_hooks[$event])) {
                unset($this->_hooks[$event]);
            }
        } else {
            if (isset($this->_hooks[$event])) {
                foreach ($this->_hooks[$event] as $index => $cb) {
                    if ($cb === $callback) {
                        unset($this->_hooks[$event][$index]);
                    }
                }

                $this->_hooks[$event] = array_values($this->_hooks[$event]);
            }
        }

        return $this;
    }

    /**
     * Trigger event and notify all hooks.
     *
     * @param string $event The event
     * @param array $params Params to be passed to the listeners
     * @return boolean Whether to prevent default execution
     */
    public function trigger($event, $params = array())
    {
        $ret = true;

        if (isset($this->_hooks[$event])) {
            foreach ($this->_hooks[$event] as $callback) {
                if (false === call_user_func($callback, $this, $params)) {
                    $ret = false;
                }
            }
        }

        return $ret;
    }

    /**
     * Set/Get environment.
     *
     * @param string $env The environment (Can be a callable)
     * @return string|\Phix\App
     */
    public function env($env = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_env) {
                if (isset($_SERVER['PHIX_ENV'])) {
                    $this->_env = $_SERVER['PHIX_ENV'];
                } elseif (getenv('PHIX_ENV')) {
                    $this->_env = getenv('PHIX_ENV');
                } else {
                    $this->_env = self::ENV_PRODUCTION;
                }
            }

            return $this->_env;
        }

        if (is_callable($env)) {
            $env = call_user_func($env, $this);
        }

        $this->_env = $env;

        return $this;
    }

    /**
     * Set/Get parameter.
     *
     * @param string $key The key
     * @param mixed $value The value (Can be a callable)
     * @return string|\Phix\App
     */
    public function param($key, $value = null)
    {
        if (func_num_args() == 1) {
            if (isset($this->_params[$key])) {
                return $this->_params[$key];
            }

            return null;
        }

        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        }

        $this->_params[$key] = $value;

        return $this;
    }

    /**
     * Set/Get multiple parameters.
     *
     * @param array $params The parameters
     * @param boolean $reset Whether to reset the existing parameters
     * @return string|\Phix\App
     */
    public function params(array $params = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_params;
        }

        if ($reset) {
            $this->_params = array();
        }

        foreach ($params as $key => $val) {
            $this->param($key, $val);
        }

        return $this;
    }

    /**
     * Connect a url matching pattern to a controller for the request method GET (and HEAD).
     *
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function get($pattern, $controller, array $defaults = array(), $callback = null)
    {
        return $this->route('GET', $pattern, $controller, $defaults, $callback);
    }

    /**
     * Connect a url matching pattern to a controller for the request method POST.
     *
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function post($pattern, $controller, array $defaults = array(), $callback = null)
    {
        return $this->route('POST', $pattern, $controller, $defaults, $callback);
    }

    /**
     * Connect a url matching pattern to a controller for the request method PUT.
     *
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function put($pattern, $controller, array $defaults = array(), $callback = null)
    {
        return $this->route('PUT', $pattern, $controller, $defaults, $callback);
    }

    /**
     * Connect a url matching pattern to a controller for the request method DELETE.
     *
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function delete($pattern, $controller, array $defaults = array(), $callback = null)
    {
        return $this->route('DELETE', $pattern, $controller, $defaults, $callback);
    }

    /**
     * Connect a url matching pattern to a controller for the request method HEAD.
     *
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function head($pattern, $controller, array $defaults = array(), $callback = null)
    {
        return $this->route('HEAD', $pattern, $controller, $defaults, $callback);
    }

    /**
     * Connect a url matching pattern to a controller for the given request method(s).
     *
     * @author Fabrice Luraine
     * @param string|array $methods The request method(s)
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return \Phix\App
     */
    public function route($methods, $patternOrArray, $controller, array $defaults = array(), $callback = null)
    {
        if (is_array($patternOrArray)) {
            $pattern = array_shift($patternOrArray);
            $names   = $patternOrArray[0];
        } else {
            $pattern = $patternOrArray;
            $names   = array();
        }

        $singleAsteriskSubpattern  = '(?:/([^\/]*))?';
        $doubleAsteriskSubpattern  = '(?:/(.*))?';
        $optionalSlashSubpattern   = '(?:/*?)';
        $noSlashAsteriskSubpattern = '(?:([^\/]*))?';

        if (empty($pattern) || $pattern == '/') {
            $pattern = '#^' . $optionalSlashSubpattern . '$#';
        } elseif ($pattern[0] == '^') {
            if ($pattern{strlen($pattern) - 1} != '$') {
                $pattern .= '$';
            }
            $pattern = '#' . $pattern . '#i';
        } else {
            $parsed = array();
            $elts = explode('/', $pattern);

            $parametersCount = 0;

            foreach ($elts as $elt) {
                if (empty($elt)) {
                    continue;
                }

                $name = null;

                // Extracting double asterisk **
                if ($elt == '**') {
                    $parsed[] = $doubleAsteriskSubpattern;
                    $name = $parametersCount;

                // Extracting single asterisk *
                } elseif ($elt == '*') {
                    $parsed[] = $singleAsteriskSubpattern;
                    $name = $parametersCount;

                // Extracting named parameters :my_param
                } elseif ($elt[0] == ':') {
                    if (preg_match('/^:([^\:]+)$/', $elt, $matches)) {
                        $parsed[] = $singleAsteriskSubpattern;
                        $name = $matches[1];
                    }
                } elseif (strpos($elt, '*') !== false) {
                    $subElts = explode('*', $elt);
                    $parsedSub = array();
                    foreach ($subElts as $subElt) {
                        $parsedSub[] = preg_quote($subElt, '#');
                        $name = $parametersCount;
                    }

                    $parsed[] = '/' . implode($noSlashAsteriskSubpattern, $parsedSub);
                } else {
                    $parsed[] = '/' . preg_quote($elt, '#');
                }

                if (is_null($name)) {
                    continue;
                }

                if (!array_key_exists($parametersCount, $names) || is_null($names[$parametersCount])) {
                    $names[$parametersCount] = $name;
                }

                $parametersCount++;
            }

            $pattern = '#^' . implode('', $parsed) . $optionalSlashSubpattern . '?$#i';
        }

        if (!is_array($methods)) {
            $methods = array($methods);
        }

        $methods = array_map('strtoupper', $methods);

        if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        foreach ($methods as $method) {
            $this->_routes[$method][] = array(
                'method'     => $method,
                'pattern'    => $pattern,
                'names'      => $names,
                'controller' => $controller,
                'defaults'   => $defaults,
                'callback'   => $callback
            );
        }

        return $this;
    }

    /**
     * Connect routes.
     *
     * @param array $routes The routes
     * @param boolean $reset Whether to reset existing routes
     * @return \Phix\App
     */
    public function routes(array $routes = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_routes;
        }

        if ($reset) {
            $this->_routes = array();
        }

        foreach ($routes as $route) {
            $this->route($route[0], $route[1], $route[2]);
        }

        return $this;
    }

    /**
     * Get/Set the router.
     *
     * @author Fabrice Luraine
     * @param mixed $router The router callback
     * @return mixed|\Phix\App
     */
    public function router($router = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_router) {
                $this->_router = function(\Phix\App $app, $routes, $requestMethod, $pathInfo) {
                    $requestMethod = strtoupper($requestMethod);

                    if (!isset($routes[$requestMethod])) {
                        return false;
                    }

                    foreach (array_reverse($routes[$requestMethod]) as $route) {
                        if (preg_match($route['pattern'], $pathInfo, $matches)) {
                            $params = $_GET + $route['defaults'];

                            if (count($matches) > 1) {
                                array_shift($matches);

                                $numMatches = count($matches);
                                $names = array_values($route['names']);
                                $numNames = count($names);

                                if ($numMatches < $numNames) {
                                    $a = array_fill(0, $numNames - $numMatches, null);
                                    $matches = array_merge($matches, $a);
                                } elseif ($numMatches > $numNames) {
                                    $names = range($numNames, $numMatches - 1);
                                }

                                $params = array_combine($names, $matches) + $params;
                            }

                            if (is_callable($route['callback'])) {
                                $ret = call_user_func($route['callback'], $app, $params);

                                if (false === $ret) {
                                    continue;
                                }

                                if (is_array($ret)) {
                                    $params = $ret + $params;
                                }
                            }

                            $route['params'] = $params;

                            return $route;
                        }
                    }

                    return false;
                };
            }

            return $this->_router;
        }

        $this->_router = $router;

        return $this;
    }

    /**
     * Get/Set the dispatcher.
     *
     * @param mixed $dispatcher The dispatcher callback
     * @return mixed|\Phix\App
     */
    public function dispatcher($dispatcher = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_dispatcher) {
                $this->_dispatcher = function(\Phix\App $app, $controller) {
                    $obLevel = ob_get_level();
                    ob_start();

                    try {
                        call_user_func($controller, $app);
                    } catch (\Exception $e) {
                        // Clean output buffer on error
                        $curObLevel = ob_get_level();
                        if ($curObLevel > $obLevel) {
                            do {
                                ob_get_clean();
                                $curObLevel = ob_get_level();
                            } while ($curObLevel > $obLevel);
                        }
                        throw $e;
                    }

                    $content = ob_get_clean();
                    if ($content != '') {
                        $app->response($content);
                    }
                };
            }

            return $this->_dispatcher;
        }

        $this->_dispatcher = $dispatcher;

        return $this;
    }

    /**
     * Set/Get session data.
     *
     * Automatically starts session if not started.
     *
     * @param string $key The key
     * @param mixed $value The value (Can be a callable)
     * @return mixed|\Phix\App
     */
    public function session($key, $value = null)
    {
        if (!defined('SID')) {
            if (!session_start()) {
                throw new \Exception("An error occured while trying to start the session");
            }
            $this->_sessionStarted = true;
        }

        if (func_num_args() == 1) {
            if (isset($_SESSION[$key])) {
                return $_SESSION[$key];
            }

            return null;
        }

        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        }

        if (null === $value) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        } else {
            $_SESSION[$key] = $value;
        }

        return $this;
    }

    /**
     * Set/Get flash messages.
     *
     * @param mixed $message The message (Can be a callable)
     * @return mixed|\Phix\App
     */
    public function flash($message = null)
    {
        if (func_num_args() == 0) {
            $messages = $this->session(self::FLASH_SESSION_KEY);

            if (!is_array($messages)) {
                $messages = array();
            }

            $this->session(self::FLASH_SESSION_KEY, null);

            return $messages;
        }

        if (is_callable($message)) {
            $message = call_user_func($message, $this);
        }

        $messages = $this->session(self::FLASH_SESSION_KEY);

        if (!is_array($messages)) {
            $messages = array();
        }

        $messages[] = $message;

        $this->session(self::FLASH_SESSION_KEY, $messages);

        return $this;
    }

    /**
     * Set/Get to registry.
     *
     * @param string $key The key
     * @param string $value The value (Can be a callable)
     * @return mixed|\Phix\App
     */
    public function reg($key, $value = null)
    {
        if (func_num_args() == 1) {
            if (isset($this->_registry[$key])) {
                return $this->_registry[$key];
            }

            return null;
        }

        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        }

        $this->_registry[$key] = $value;

        return $this;
    }

    /**
     * Set/Get multiple to registry.
     *
     * @param array $regs The regs
     * @param boolean $reset Whether to reset existing registry
     * @return mixed|\Phix\App
     */
    public function regs(array $regs = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_registry;
        }

        if ($reset) {
            $this->_registry = array();
        }

        foreach ($regs as $key => $val) {
            $this->reg($key, $val);
        }

        return $this;
    }

    /**
     * Set/Get the HTTP response status code.
     *
     * @param integer $status The status (Can be a callable)
     * @return integer|\Phix\App
     */
    public function status($status = null)
    {
        if (func_num_args() == 0) {
            return $this->_status;
        }

        if (is_callable($status)) {
            $status = call_user_func($status, $this);
        }

        if (!isset($this->_statusPhrases[$status])) {
            throw new \Exception('Invalid status code "' . $status . '"');
        }

        $this->_status = $status;

        return $this;
    }

    /**
     * Wether the application is in "redirection" state.
     *
     * @return boolean
     */
    public function redirected()
    {
        $status = $this->status();

        if (300 <= $status && 307 >= $status) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set HTTP redirection.
     *
     * @param string $url The url (Can be a callable)
     * @param integer $status The status (Can be a callable)
     * @return integer|\Phix\App
     */
    public function redirect($url, $status = 302)
    {
        if (is_callable($url)) {
            $url = call_user_func($url, $this);
        }

        if (is_array($url)) {
            $url = $this->serverUrl() . $this->url($url);
        } else {
            if (!preg_match('|^[a-z]+://|', $url)) {
                $base = rtrim($this->baseUrl(), '/');
                if (!empty($base) && '/' != $base && strpos($url, $base) !== 0) {
                    $url = $base . '/' . ltrim($url, '/');
                } else {
                    $url = '/' . ltrim($url, '/');
                }

                $url = $this->serverUrl() . '/' . ltrim($url, '/');
            }
        }

        $this->status($status);
        $this->header('Location: ' . $url);

        return $this;
    }

    /**
     * Assemble a url from the given parameters.
     *
     * @param array $params The parameters (Can be a callable)
     * @return string
     */
    public function url($params)
    {
        if (is_callable($params)) {
            $params = call_user_func($params, $this);
        }

        $url = $this->baseUrl();

        foreach ($params as $value) {
            $url .= '/' . urlencode($value);
        }

        $url = '/' . ltrim($url, '/');

        return $url;
    }

    /**
     * Set/Get a HTTP status phrase.
     *
     * @param integer $status The status
     * @param string $phrase The phrase (Can be a callable)
     * @return string|\Phix\App
     */
    public function statusPhrase($status, $phrase = null)
    {
        if (func_num_args() == 1) {
            if (isset($this->_statusPhrases[$status])) {
                return $this->_statusPhrases[$status];
            }

            return null;
        }

        if (is_callable($phrase)) {
            $phrase = call_user_func($phrase, $this);
        }

        $this->_statusPhrases[$status] = $phrase;

        return $this;
    }

    /**
     * Set a HTTP response header.
     *
     * @param string $header The header (Can be a callable)
     * @param boolean $replace Whether to replace exiting headers
     * @return \Phix\App
     */
    public function header($header, $replace = true)
    {
        if (is_callable($header)) {
            $header = call_user_func($header, $this);
        }

        if ($replace) {
            list($name,) = explode(':', $header);
            $name .= ':';
            foreach ($this->_headers as $key => $val) {
                if (stripos($val, $name) === 0) {
                    unset($this->_headers[$key]);
                }
            }

            array_values($this->_headers);
        }

        $this->_headers[] = $header;

        return $this;
    }

    /**
     * Set/Get multiple HTTP response headers.
     *
     * @param array $headers The headers
     * @param boolean $reset Whether to reset existing headers
     * @return array|\Phix\App
     */
    public function headers(array $headers = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_headers;
        }

        if ($reset) {
            $this->_headers = array();
        }

        foreach ($headers as $header) {
            if (!is_array($header) || is_callable($header)) {
                $header = array($header);
            }

            $this->header($header[0], isset($header[1]) ? $header[1] : true);
        }

        return $this;
    }

    /**
     * Set/Get directory where views are located.
     *
     * @param string $viewsDir The views directory (Can be a callable)
     * @return string|\Phix\App
     */
    public function viewsDir($viewsDir = null)
    {
        if (func_num_args() == 0) {
            return $this->_viewsDir;
        }

        if (is_callable($viewsDir)) {
            $viewsDir = call_user_func($viewsDir, $this);
        }

        $this->_viewsDir = $viewsDir;

        return $this;
    }

    /**
     * Set/Get the layout.
     *
     * @param mixed $layout The layout
     * @return mixed|\Phix\App
     */
    public function layout($layout = null)
    {
        if (func_num_args() == 0) {
            return $this->_layout;
        }

        $this->_layout = $layout;

        return $this;
    }

    /**
     * Set/Get a named view.
     *
     * @param string|array $name The name or an array of name andformat
     * @param mixed $view The view
     * @return mixed|\Phix\App
     */
    public function view($name, $view = null)
    {
        $format = null;

        if (is_array($name)) {
            list($name, $format) = $name;

            if (is_callable($format)) {
                $format = call_user_func($format, $this);
            }

            $formats = $this->formats();

            if (!isset($formats[$format])) {
                throw new \Exception('Invalid format "' . $format . '"');
            }
        }

        if (null === $format) {
            $format = $this->defaultFormat();
        }

        if (func_num_args() == 1) {
            if (isset($this->_views[$name][$format])) {
                return $this->_views[$name][$format];
            }

            return null;
        }

        $this->_views[$name][$format] = $view;

        return $this;
    }

    /**
     * Set/Get the view renderer.
     *
     * @param mixed $renderer The renderer callback
     * @return mixed|\Phix\App
     */
    public function renderer($renderer = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_renderer) {
                $this->_renderer = function(\Phix\App $app, $viewFilename, array $vars) {
                    ob_start();
                    extract($vars);
                    include $viewFilename;
                    return ob_get_clean();
                };
            }

            return $this->_renderer;
        }

        $this->_renderer = $renderer;

        return $this;
    }

    /**
     * Render a view and set as response.
     *
     * @param string $view The view
     * @param array $vars The vars to pass to the view
     * @param string $format The format to render (Can be a callable)
     * @param string $layout The layout to use
     * @return \Phix\App
     */
    public function render($view, array $vars = array(), $format = null, $layout = null)
    {
        if (null === $format) {
            $format = $this->currentFormat();
        } else {
            if (is_callable($format)) {
                $format = call_user_func($format, $this);
            }
        }

        $content = $this->renderView($view, $vars, $format);

        if (false !== $layout) {
            if (null === ($formatConfig = $this->format($format))) {
                throw new \Exception('Invalid format "' . $format . '"');
            }

            if (false !== $formatConfig['view']['layout']) {
                if (null === $layout) {
                    $layout = $this->layout();
                }

                if (null !== $formatConfig['view']['layout']) {
                    $layout = $formatConfig['view']['layout'];
                }

                if (null !== $layout) {
                    $vars = array('content' => $content);
                    $content = $this->renderView($layout, $vars, $format);
                }
            }
        }

        $this->response($content, $format);

        return $this;
    }

    public function renderView($view, array $vars = array(), $format = null)
    {
        if (null === $format) {
            $format = $this->currentFormat();
        } else {
            if (is_callable($format)) {
                $format = call_user_func($format, $this);
            }
        }

        if (null === $this->format($format)) {
            throw new \Exception('Invalid format "' . $format . '"');
        }

        if (is_scalar($view) && null !== ($registered = $this->view(array($view, $format)))) {
            $view = $registered;
        }

        if (is_callable($view)) {
            $content = call_user_func($view, $this, $vars, $format);
        } elseif (false !== ($viewFilename = $this->viewFilename($view, $format))) {
            $content = call_user_func($this->renderer(), $this, $viewFilename, $vars);
        } else {
            if (count($vars) > 0) {
                $content = vsprintf($view, $vars);
            } else {
                $content = (string) $view;
            }
        }

        return $content;
    }

    /**
     * Create a view filename from the given view and format.
     *
     * @param string $view The view
     * @param string $format The format
     * @return string|boolean
     */
    public function viewFilename($view, $format)
    {
        if (null === ($format = $this->format($format))) {
            return false;
        }

        $file       = $view;
        $default    = $this->format($this->defaultFormat());
        $extensions = $default['view']['extension'];

        if (!is_array($extensions)) {
            $extensions = array($extensions);
        }

        foreach ($extensions as $extension) {
            if (substr($file, -strlen($extension)) == $extension) {
                $file = substr($file, 0, -strlen($extension));
            }
        }

        $path = $file;

        if (null !== ($viewsDir = $this->viewsDir())) {
            $path = rtrim($viewsDir, '/\\') . DIRECTORY_SEPARATOR . $file;
        }

        $extensions = $format['view']['extension'];

        if (!is_array($extensions)) {
            $extensions = array($extensions);
        }

        foreach ($extensions as $extension) {
            $full = $path . $extension;
            if (file_exists($full)) {
                return $full;
            }
        }

        return false;
    }

    /**
     * Get the current format.
     *
     * @return string
     */
    public function currentFormat()
    {
        if (null !== ($format = $this->param('format'))) {
            $formats = $this->formats();
            if (isset($formats[$format])) {
                return $format;
            }
        }

        return $this->defaultFormat();
    }

    /**
     * Set/Get the default format.
     *
     * @param string $defaultFormat The default format (Can be a callable)
     * @return string|\Phix\App
     */
    public function defaultFormat($defaultFormat = null)
    {
        if (func_num_args() == 0) {
            return $this->_defaultFormat;
        }

        if (is_callable($defaultFormat)) {
            $defaultFormat = call_user_func($defaultFormat, $this);
        }

        $this->_defaultFormat = $defaultFormat;

        return $this;
    }

    /**
     * Set/Get the a format and its configuration.
     *
     * @param string $format The format
     * @param array $config The configuration (Can be a callable)
     * @return array|\Phix\App
     */
    public function format($format, $config = null)
    {
        if (func_num_args() == 1) {
            if (isset($this->_formats[$format])) {
                return $this->_formats[$format];
            }

            return null;
        }

        if (is_callable($config)) {
            $config = call_user_func($config, $this);
        }

        if (null === $config) {
            if ($format == $this->defaultFormat()) {
                throw new \Exception('Removing the default format is not allowed');
            }

            unset($this->_formats[$format]);
        } else {
            $this->_formats[$format] = $config;
        }

        return $this;
    }

    /**
     * Set/Get multiple formats and their configuration.
     *
     * @param array $formats The formats
     * @param bool $reset Whether to reset existing formats
     * @return array|\Phix\App
     */
    public function formats(array $formats = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_formats;
        }

        if ($reset) {
            $this->_formats = array();
        }

        foreach ($formats as $key => $val) {
            $this->format($key, $val);
        }

        return $this;
    }

    /**
     * Set default formats.
     *
     * @return void
     */
    public function setDefaultFormats()
    {
        $this->_formats = array(
            'html' => array(
                'view' => array(
                    'layout'    => null,
                    'extension' => array('.html.php', '.html.phtml', '.html', '.php', '.phtml')
                ),
                'contenttype' => array(
                    'request'  => array('text/html', 'application/xhtml+xml'),
                    'response' => 'text/html'
                ),
                'error' => function(\Phix\App $app, $status, $msg) {
                    return '<!DOCTYPE html>' .
                           '<html>' .
                             '<head></head>' .
                             '<body>' .
                               '<h1>' . $app->statusPhrase($status) . '</h1>' .
                               '<p>' . $app->escape($msg) . '</p>' .
                             '</body>' .
                           '</html>';
                },
                'response' => function(\Phix\App $app, $status, $data) {
                    return '<!DOCTYPE html>' .
                           '<html>' .
                             '<head><title>' . $app->statusPhrase($status) . '</title></head>' .
                             '<body>' .
                               '<h1>' . $app->statusPhrase($status) . '</h1>' .
                               '<pre>' . $app->escape(print_r($data, true)) . '</pre>' .
                             '</body>' .
                           '</html>';
                }
            ),
            'json' => array(
                'view' => array(
                    'layout'    => false,
                    'extension' => array('.json.php', '.json.phtml', '.json')
                ),
                'contenttype' => array(
                    'request'  => array('application/json'),
                    'response' => 'application/json'
                ),
                'error' => function(\Phix\App $app, $status, $msg) {
                    return json_encode(array('status' => 'error', 'message' => $msg));
                },
                'response' => function(\Phix\App $app, $status, $data) {
                    $statusString = 200 <= $status && 206 >= $status ? 'success' : 'fail';
                    $response = json_encode(array('status' => $statusString, 'data' => $data));

                    // Handle JSONP callbacks
                    if (!empty($_GET['callback']) && preg_match('/^[a-zA-Z_$][0-9a-zA-Z_$]*$/', $_GET['callback'])) {
                        $response = $_GET['callback'] . '(' . $response . ')';
                    }

                    return $response;
                },
                'unserialize' => function(\Phix\App $app, $string) {
                    return json_decode($string, true);
                }
            ),
            'xml' => array(
                'view' => array(
                    'layout'    => false,
                    'extension' => array('.xml.php', '.xml.phtml', '.xml')
                ),
                'contenttype' => array(
                    'request'  => array('text/xml', 'application/xml'),
                    'response' => 'text/xml'
                ),
                'error' => function(\Phix\App $app, $status, $msg) {
                    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                           '<response>' .
                             '<status>error</status>' .
                             '<message>' . $app->escape($msg) . '</message>' .
                           '</response>';
                },
                'response' => function(\Phix\App $app, $status, $data) {
                    $arrayToXml = function(array $array, $root) use (&$arrayToXml) {
                        $xml  = '';
                        $wrap = true;
                        foreach ($array as $key => $value) {
                            if (is_object($value)) {
                                $value = get_object_vars($value);
                            }
                            if (is_array($value)) {
                                if (is_numeric($key)) {
                                    $key  = $root;
                                    $wrap = false;
                                }
                                $xml .= $arrayToXml($value, $key);
                            } else {
                                if (is_numeric($key)) {
                                    $wrap = false;
                                    $xml .= '<' . $root . '>' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '</' . $root . '>';
                                } else {
                                    $xml .= '<' . $key . '>' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '</' . $key . '>';
                                }
                            }
                        }

                        if ($wrap) {
                            $xml = '<' . $root . '>' . $xml . '</' . $root . '>';
                        }

                        return $xml;
                    };

                    $statusString = 20 <= $status && 206 >= $status ? 'success' : 'fail';
                    if (is_object($data)) {
                        $data = get_object_vars($data);
                    }

                    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                           '<response>' .
                             '<status>' . $statusString . '</status>' .
                             $arrayToXml($data, 'data') .
                           '</response>';
                },
                'unserialize' => function(\Phix\App $app, $string) {
                    $xmlToArray = function(\SimpleXMLElement $xmlObject) use (&$xmlToArray) {
                        $config = array();

                        // Search for children
                        if (count($xmlObject->children()) > 0) {
                            foreach ($xmlObject->children() as $key => $value) {
                                if (count($value->children()) > 0) {
                                    $value = $xmlToArray($value);
                                } else {
                                    $value = (string) $value;
                                }

                                if (array_key_exists($key, $config)) {
                                    if (!is_array($config[$key]) || !array_key_exists(0, $config[$key])) {
                                        $config[$key] = array($config[$key]);
                                    }

                                    $config[$key][] = $value;
                                } else {
                                    $config[$key] = $value;
                                }
                            }
                        } else {
                            $config = (string) $xmlObject;
                        }

                        return $config;
                    };

                    return $xmlToArray(simplexml_load_string($string));
                }
            )
        );
    }

    /**
     * Set/Get the value of the given HTTP header.
     *
     * Pass the header name as the plain, HTTP-specified header name.
     * Ex.: Ask for 'Accept' to get the Accept header, 'Accept-Encoding'
     * to get the Accept-Encoding header.
     *
     * @param string $name HTTP header name
     * @param string $value HTTP header value (Can be a callable)
     * @return string|false|\Phix\App
     */
    public function requestHeader($name, $value = null)
    {
        $name = strtolower($name);

        if (func_num_args() == 1) {
            if (!isset($this->_requestHeaders[$name])) {
                $this->_requestHeaders[$name] = false;

                // Try to get it from the $_SERVER array first
                $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
                if (!empty($_SERVER[$temp])) {
                    $this->_requestHeaders[$name] = $_SERVER[$temp];
                } else {
                    // This seems to be the only way to get the Authorization header on
                    // Apache
                    if (function_exists('apache_request_headers')) {
                        $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
                        if (!empty($headers[$name])) {
                            $this->_requestHeaders[$name] = $headers[$name];
                        }
                    }
                }
            }

            return $this->_requestHeaders[$name];
        }

        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        }

        $this->_requestHeaders[$name] = $value;

        return $this;
    }

    /**
     * Set/Get the raw body of the request.
     *
     * @param string $requestRawBody The raw body of the request (Can be a callable)
     * @return string|false Raw body, or false if not present
     */
    public function requestRawBody($requestRawBody = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_requestRawBody) {
                $body = file_get_contents('php://input');

                if (strlen(trim($body)) > 0) {
                    $this->_requestRawBody = $body;
                } else {
                    $this->_requestRawBody = false;
                }
            }

            return $this->_requestRawBody;
        }

        if (is_callable($requestRawBody)) {
            $requestRawBody = call_user_func($requestRawBody, $this);
        }

        if (null !== $requestRawBody && strlen(trim($requestRawBody)) == 0) {
            $requestRawBody = false;
        }

        $this->_requestRawBody = $requestRawBody;

        return $this;
    }

    /**
     * Get/Set the method by which the request was made.
     *
     * @param string $requestMethod The request method (Can be a callable)
     * @return string|\Phix\App
     */
    public function requestMethod($requestMethod = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_requestMethod) {
                if (false !== ($method = $this->requestHeader('X-HTTP-Method-Override'))) {
                    $requestMethod = $method;
                } else {
                    $requestMethod = array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : 'GET';
                }

                if ($requestMethod == 'POST' && array_key_exists('_method', $_POST)) {
                    $requestMethod = $_POST['_method'];
                }

                $requestMethod = strtoupper($requestMethod);

                $this->_requestMethod = $requestMethod;
            }

            return $this->_requestMethod;
        }

        if (is_callable($requestMethod)) {
            $requestMethod = call_user_func($requestMethod, $this);
        }

        $this->_requestMethod = $requestMethod;

        return $this;
    }

    /**
     * Set/Get the REQUEST_URI on which the instance operates.
     *
     * If no request URI is passed, uses the value in $_SERVER['REQUEST_URI'],
     * $_SERVER['HTTP_X_REWRITE_URL'], or $_SERVER['ORIG_PATH_INFO'] + $_SERVER['QUERY_STRING'].
     *
     * @param string|null $requestUri (Can be a callable)
     * @return string|\Phix\App
     */
    public function requestUri($requestUri = null)
    {
        if (func_num_args() == 0) {
            if ($this->_requestUri === null) {
                if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
                    $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
                } elseif (
                // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
                isset($_SERVER['IIS_WasUrlRewritten'])
                        && $_SERVER['IIS_WasUrlRewritten'] == '1'
                        && isset($_SERVER['UNENCODED_URL'])
                        && $_SERVER['UNENCODED_URL'] != ''
                ) {
                    $requestUri = $_SERVER['UNENCODED_URL'];
                } elseif (isset($_SERVER['REQUEST_URI'])) {
                    $requestUri = $_SERVER['REQUEST_URI'];
                    // Http proxy reqs setup request uri with scheme and host [and port]
                    // and the url path, only use url path
                    $serverUrl = $this->serverUrl();
                    if (strpos($requestUri, $serverUrl) === 0) {
                        $requestUri = substr($requestUri, strlen($serverUrl));
                    }
                } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
                    $requestUri = $_SERVER['ORIG_PATH_INFO'];
                    if (!empty($_SERVER['QUERY_STRING'])) {
                        $requestUri .= '?' . $_SERVER['QUERY_STRING'];
                    }
                } else {
                    throw new \Exception('Could not detect request uri');
                }

                $this->_requestUri = $requestUri;
            }

            return $this->_requestUri;

        }

        if (is_callable($requestUri)) {
            $requestUri = call_user_func($requestUri, $this);
        }

        if (null !== $requestUri) {
            // Set GET items, if available
            if (false !== ($pos = strpos($requestUri, '?'))) {
                // Get key => value pairs and set $_GET
                $query = substr($requestUri, $pos + 1);
                parse_str($query, $vars);
                $_GET = array_merge($_GET, $vars);
            }
        }

        $this->_requestUri = $requestUri;

        return $this;
    }

    /**
     * Set the base URL of the request; i.e., the segment leading to the script name.
     *
     * E.g.:
     * - /admin
     * - /myapp
     * - /subdir/index.php
     *
     * Do not use the full URI when providing the base. The following are
     * examples of what not to use:
     * - http://example.com/admin (should be just /admin)
     * - http://example.com/subdir/index.php (should be just /subdir/index.php)
     *
     * If no $baseUrl is provided, attempts to determine the base URL from the
     * environment, using SCRIPT_FILENAME, SCRIPT_NAME, PHP_SELF, and
     * ORIG_SCRIPT_NAME in its determination.
     *
     * @param string|null $baseUrl (Can be a callable)
     * @return string|\Phix\App
     */
    public function baseUrl($baseUrl = null)
    {
        if (func_num_args() == 0) {
            if ($this->_baseUrl === null) {
                $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

                if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
                    $baseUrl = $_SERVER['SCRIPT_NAME'];
                } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
                    $baseUrl = $_SERVER['PHP_SELF'];
                } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
                    $baseUrl = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
                } else {
                    // Backtrack up the script_filename to find the portion matching
                    // php_self
                    $path    = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
                    $file    = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
                    $segs    = explode('/', trim($file, '/'));
                    $segs    = array_reverse($segs);
                    $index   = 0;
                    $last    = count($segs);
                    $baseUrl = '';
                    do {
                        $seg     = $segs[$index];
                        $baseUrl = '/' . $seg . $baseUrl;
                        ++$index;
                    } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
                }

                // Does the baseUrl have anything in common with the request_uri?
                $requestUri = $this->requestUri();

                if (0 === strpos($requestUri, $baseUrl)) {
                    // full $baseUrl matches
                    $this->_baseUrl = rtrim($baseUrl, '/');
                    return $this->_baseUrl;
                }

                if (0 === strpos($requestUri, dirname($baseUrl))) {
                    // directory portion of $baseUrl matches
                    $this->_baseUrl = rtrim(dirname($baseUrl), '/');
                    return $this->_baseUrl;
                }

                $truncatedRequestUri = $requestUri;
                if (($pos = strpos($requestUri, '?')) !== false) {
                    $truncatedRequestUri = substr($requestUri, 0, $pos);
                }

                $basename = basename($baseUrl);
                if (empty($basename) || !strpos($truncatedRequestUri, $basename)) {
                    // no match whatsoever; set it blank
                    $this->_baseUrl = '';
                    return $this->_baseUrl;
                }

                // If using mod_rewrite or ISAPI_Rewrite strip the script filename
                // out of baseUrl. $pos !== 0 makes sure it is not matching a value
                // from PATH_INFO or QUERY_STRING
                if ((strlen($requestUri) >= strlen($baseUrl))
                        && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))) {
                    $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
                }

                $this->_baseUrl = rtrim($baseUrl, '/');
            }

            return $this->_baseUrl;
        }

        if (is_callable($baseUrl)) {
            $baseUrl = call_user_func($baseUrl, $this);
        }

        if (null !== $baseUrl) {
            $baseUrl = rtrim($baseUrl, '/');
        }

        $this->_baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Set the base path for the URL.
     *
     * @param string|null $basePath (Can be a callable)
     * @return string|\Phix\App
     */
    public function basePath($basePath = null)
    {
        if (func_num_args() == 0) {
            if ($this->_basePath === null) {
                $filename = (isset($_SERVER['SCRIPT_FILENAME']))
                        ? basename($_SERVER['SCRIPT_FILENAME'])
                        : '';

                $baseUrl = $this->baseUrl();
                if (empty($baseUrl)) {
                    $this->_basePath = '';
                    return $this->_basePath;
                }

                if (basename($baseUrl) === $filename) {
                    $basePath = dirname($baseUrl);
                } else {
                    $basePath = $baseUrl;
                }

                if (substr(PHP_OS, 0, 3) === 'WIN') {
                    $basePath = str_replace('\\', '/', $basePath);
                }

                $this->_basePath = rtrim($basePath, '/');
            }

            return $this->_basePath;
        }

        if (is_callable($basePath)) {
            $basePath = call_user_func($basePath, $this);
        }

        if (null !== $basePath) {
            $basePath = rtrim($basePath, '/');
        }

        $this->_basePath = $basePath;

        return $this;
    }

    /**
     * Set/Get the PATH_INFO string.
     *
     * @param string|null $pathInfo (Can be a callable)
     * @return string|\Phix\App
     */
    public function pathInfo($pathInfo = null)
    {
        if (func_num_args() == 0) {
            if ($this->_pathInfo === null) {
                $baseUrl = $this->baseUrl();

                if (null === ($requestUri = $this->requestUri())) {
                    $this->_pathInfo = '';
                    return $this->_pathInfo;
                }

                // Remove the query string from REQUEST_URI
                if (false !== ($pos = strpos($requestUri, '?'))) {
                    $requestUri = substr($requestUri, 0, $pos);
                }

                if (null !== $baseUrl
                        && ((!empty($baseUrl) && 0 === strpos($requestUri, $baseUrl))
                                || empty($baseUrl))
                        && false === ($pathInfo = substr($requestUri, strlen($baseUrl)))
                ) {
                    // If substr() returns false then PATH_INFO is set to an empty string
                    $pathInfo = '';
                } elseif (null === $baseUrl
                        || (!empty($baseUrl) && false === strpos($requestUri, $baseUrl))
                ) {
                    $pathInfo = $requestUri;
                }

                $this->_pathInfo = $pathInfo;
            }

            return $this->_pathInfo;
        }

        if (is_callable($pathInfo)) {
            $pathInfo = call_user_func($pathInfo, $this);
        }

        $this->_pathInfo = $pathInfo;

        return $this;
    }

    /**
     * Set/Get the server url.
     *
     * @param string|null $serverUrl (Can be a callable)
     * @return string|\Phix\App
     */
    public function serverUrl($serverUrl = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_serverUrl) {
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

                if (!empty($_SERVER['HTTP_HOST'])) {
                    $this->_serverUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
                } elseif (!empty($_SERVER['SERVER_NAME'])) {
                    $name = $_SERVER['SERVER_NAME'];
                    $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;

                    if (($scheme == 'http' && $port == 80) || ($scheme == 'https' && $port == 443)) {
                        $host = $name;
                    } else {
                        $host = $name . ':' . $port;
                    }

                    $this->_serverUrl = $scheme . '://' . $host;
                } else {
                    $this->_serverUrl = '';
                }
            }

            return $this->_serverUrl;
        }

        if (is_callable($serverUrl)) {
            $serverUrl = call_user_func($serverUrl, $this);
        }

        $this->_serverUrl = $serverUrl;

        return $this;
    }

    /**
     * Shortcut for triggering a 404 Not Found error.
     *
     * @param string $msg The message (Can be a callable)
     * @return \Phix\App
     */
    public function notFound($msg = null)
    {
        if (false === $this->trigger('not_found')) {
            return $this;
        }

        if (is_callable($msg)) {
            $msg = call_user_func($msg, $this);
        }

        if (null === $msg) {
            $msg = 'The requested URL ' . $this->escape($this->requestUri()) . ' was not found on this server.';
        }

        $this->error(404, $msg);

        $this->trigger('not_found_end');

        return $this;
    }

    /**
     * Trigger an error and set response for the requested format.
     *
     * @param integer $status The HTTP status code
     * @param string $msg The message (Can be a callable)
     * @param string $format The format (Can be a callable)
     * @return \Phix\App
     */
    public function error($status, $msg = null, $format = null)
    {
        if (false === $this->trigger('error', array('status' => &$status, 'msg' => &$msg, 'format' => &$format))) {
            return $this;
        }

        if (is_callable($status)) {
            $status = call_user_func($status, $this);
        }

        if (!isset($this->_statusPhrases[$status])) {
            $status = 500;
        }

        $this->status($status);

        if (is_callable($msg)) {
            $msg = call_user_func($msg, $this);
        }

        if (null === $msg) {
            $msg = 'The server encountered an internal error and was unable to complete your request.';
        }

        $formats = $this->formats();

        if (null === $format) {
            $format = $this->currentFormat();
        } else {
            if (is_callable($format)) {
                $format = call_user_func($format, $this);
            }

            if (!isset($formats[$format])) {
                throw new \Exception('Invalid format "' . $format . '"');
            }
        }

        $error = $formats[$format]['error'];
        $this->response(call_user_func($error, $this, $status, $msg), $format);

        $this->_shutdown();

        $this->trigger('error_end');

        return $this;
    }

    /**
     * Exception handler.
     *
     * @param \Exception $exception The exception
     * @return boolean
     */
    public function exceptionHandler(\Exception $exception)
    {
        if (false === $this->trigger('exception_handler', array('exception' => $exception))) {
            return false;
        }

        $this->_exceptions[] = $exception;

        if ($this->env() == self::ENV_PRODUCTION) {
            $msg = null;
        } else {
            $msg = 'Exception: ' . $exception->getMessage();
        }

        $this->error(500, $msg);

        $this->trigger('exception_handler_end');

        return false;
    }

    /**
     * Return handled exceptions.
     *
     * @return array
     */
    public function exceptions()
    {
        return $this->_exceptions;
    }
}
