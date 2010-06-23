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

/**
 * @package   Phix
 * @author    Jan Sorgalla
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */
class Phix
{
    const ENV_PRODUCTION  = 'production';
    const EVN_TESTING     = 'testing';
    const ENV_STAGING     = 'staging';
    const ENV_DEVELOPMENT = 'develoment';

    const FLASH_SESSION_KEY = '__PHIX_FLASH';

    /**
     * Flag indicating application is stopped.
     * @var boolean
     */
    private $_stopped = false;

    /**
     * Flag indicating whether to restore error handler in _shutdown().
     * @var boolean
     */
    private $_restoreErrorHandler = false;

    /**
     * Flag indicating whether session was started by Phix and should be closed in _shutdown().
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
     * Assigned options.
     * @var array
     */
    private $_options = array();

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
     * Caught errors.
     * @var array
     */
    private $_errors = array();

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
     * Default format.
     * @var string
     */
    private $_defaultFormat = 'html';

    /**
     * Available formats.
     * @var array
     */
    private $_formats = array(
        'html' => array(
            'view' => array(
                'layout'    => null,
                'extension' => array('.html.php', '.html.phtml', '.html', '.php', '.phtml')
            ),
            'contenttype' => array(
                'request'  => array('text/html', 'application/xhtml+xml'),
                'response' => 'text/html'
            ),
            'error' => array('Phix', 'defaultFormatHtmlError')
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
            'error' => array('Phix', 'defaultFormatJsonError'),
            'unserialize' => array('Phix', 'defaultFormatJsonUnserialize')
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
            'error' => array('Phix', 'defaultFormatXmlError'),
            'unserialize' => array('Phix', 'defaultFormatXmlUnserialize')
        )
    );

    /**
     * Request headers.
     * @var string
     */
    private $_requestHeaders = array();

    /**
     * Raw request body
     * @var string|false
     */
    protected $_requestRawBody;

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
     * Create Phix instance.
     *
     * @param array $config (Can be a callable)
     * @return Phix
     */
    public static function instance($config = null)
    {
        return new self($config);
    }

    /**
     * Contructor.
     *
     * @param array $config (Can be a callable)
     */
    public function __construct($config = null)
    {
        if (null !== $config) {
            $this->configure($config);
        }
    }

    /**
     * Configure Phix.
     *
     * @param array $config (Can be a callable)
     * @return Phix
     */
    public function configure($config)
    {
        if (is_callable($config)) {
            $config = call_user_func($config, $this);
        }

        $forbidden = array(
            'configure',
            'run',
        );

        foreach ($config as $method => $args) {
            if ($method[0] == '_') {
                throw new Exception('Configuring through private methods is forbidden');
            }

            if (in_array($method, $forbidden)) {
                throw new Exception('Configuring through method "' . $method . '" is forbidden');
            }

            call_user_func_array(array($this, $method), $args);
        }

        return $this;
    }

    /**
     * Set/Get whether to flush automatically.
     *
     * @param boolean $bool (Can be a callable)
     * @return boolean|Phix
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
     * @return Phix
     */
    public function run()
    {
        try {
            $this->_startup();
            $this->_init();
            $this->_run();
            $this->_shutdown();
        } catch (Exception $e) {
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
     * @return Phix
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
     * @return Phix
     */
    public function reset()
    {
        if (false === $this->trigger('reset')) {
            return $this;
        }

        $this->_status     = 200;
        $this->_headers    = array();
        $this->_output     = null;

        $this->_errors     = array();
        $this->_exceptions = array();

        $this->params(array(), true);

        $this->trigger('reset_end');

        return $this;
    }

    /**
     * Startup application.
     *
     * @return void
     */
    private function _startup()
    {
        $this->_stopped = false;

        if (false === $this->trigger('startup')) {
            return;
        }

        set_error_handler(array($this, 'errorHandler'));
        $this->_restoreErrorHandler = true;

        $this->trigger('startup_end');
    }

    /**
     * Initialize application.
     *
     * @return void
     */
    private function _init()
    {
        if ($this->_stopped) {
            return;
        }

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
                            $_POST = array_merge($_POST, call_user_func($options['unserialize'], $this, $rawBody));
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

        if (false === ($route = $this->_route($requestMethod, $pathInfo))) {
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

        $this->_dispatch($route);

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

        if ($this->_restoreErrorHandler) {
            restore_error_handler();
        }

        $this->_restoreErrorHandler = false;

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
     * @return string|Phix
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
     * @return string|Phix
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
     * @return string|Phix
     */
    public function response($output, $format = null)
    {
        if (null === $format) {
            $format = $this->defaultFormat();
        }

        if (is_callable($format)) {
            $format = call_user_func($format, $this);
        }

        $formats = $this->formats();

        if (!isset($formats[$format])) {
            throw new Exception('Invalid format "' . $format . '"');
        }

        $contentType = $formats[$format]['contenttype']['response'];
        $this->header('Content-Type: ' . $contentType . ';charset=' . strtolower($this->encoding()));

        $this->output($output);

        return $this;
    }

    /**
     * Set/Get multiple hooks.
     *
     * @param string $hooks The hooks
     * @param boolean $reset Whether to reset existing hooks
     * @return array|Phix
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
     * @return Phix
     */
    public function hook($event, $callback, $index = null)
    {
        if (null === $index) {
            $this->_hooks[$event][] = $callback;
        } else {
            if (isset($this->_hooks[$event][$index])) {
                throw new Exception('There is already a hook registered at index "' . $index . '"');
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
     * @return Phix
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
     * @return string|Phix
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
     * @return string|Phix
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
     * @return string|Phix
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
     * @return Phix
     */
    public function get($pattern, $controller, array $defaults = array(), $callback = null)
    {
        return $this->route(array('HEAD', 'GET'), $pattern, $controller, $defaults, $callback);
    }

    /**
     * Connect a url matching pattern to a controller for the request method POST.
     *
     * @param string $pattern The pattern
     * @param mixed $controller The controller
     * @param array $defaults The default params
     * @param mixed $callback The callback
     * @return Phix
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
     * @return Phix
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
     * @return Phix
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
     * @return Phix
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
     * @return Phix
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

        foreach ($methods as $method) {
            $method = strtoupper($method);

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
     * @return Phix
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
     * Call the router and return a matching route for the given request method and pathinfo.
     *
     * @param string $requestMethod The request method
     * @param string $pathInfo The pathinfo
     * @return array|false
     */
    private function _route($requestMethod, $pathInfo)
    {
        return call_user_func($this->router(), $this, $this->_routes, $requestMethod, $pathInfo);
    }

    /**
     * Get/Set the router.
     *
     * @param mixed $router The router callback
     * @return mixed|Phix
     */
    public function router($router = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_router) {
                $this->_router = array('Phix', 'defaultRouter');
            }

            return $this->_router;
        }

        $this->_router = $router;

        return $this;
    }

    /**
     * The default router callback.
     *
     * @author Fabrice Luraine
     * @param Phix $phix The Phix instance
     * @param array $routes The routes
     * @param string $requestMethod The request method
     * @param string $pathInfo The pathinfo
     * @return array|false
     */
    public static function defaultRouter($phix, $routes, $requestMethod, $pathInfo)
    {
        $requestMethod = strtoupper($requestMethod);

        if (!isset($routes[$requestMethod])) {
            return false;
        }

        foreach ($routes[$requestMethod] as $route) {
            if (preg_match($route['pattern'], $pathInfo, $matches)) {
                $params = $route['defaults'];

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
                    $ret = call_user_func($route['callback'], $phix);

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
    }

    /**
     * Set the params and call the dispatcher.
     *
     * @param array $route The route
     * @return void
     */
    private function _dispatch($route)
    {
        $this->params($route['params'] + $_GET);
        call_user_func($this->dispatcher(), $this, $route['controller']);
    }

    /**
     * Get/Set the dispatcher.
     *
     * @param mixed $dispatcher The dispatcher callback
     * @return mixed|Phix
     */
    public function dispatcher($dispatcher = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_dispatcher) {
                $this->_dispatcher = array('Phix', 'defaultDispatcher');
            }

            return $this->_dispatcher;
        }

        $this->_dispatcher = $dispatcher;

        return $this;
    }

    /**
     * The default dispatcher callback.
     *
     * @param Phix $phix The Phix instance
     * @param mixed $controller The controller
     * @return void
     */
    public static function defaultDispatcher($phix, $controller)
    {
        call_user_func($controller, $phix);
    }

    /**
     * Set/Get session data.
     *
     * Automatically starts session if not started.
     *
     * @param string $key The key
     * @param mixed $value The value (Can be a callable)
     * @return mixed|Phix
     */
    public function session($key, $value = null)
    {
        if (!defined('SID')) {
            if (!session_start()) {
                throw new Exception("An error occured while trying to start the session");
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
     * @return mixed|Phix
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
     * Set/Get options.
     *
     * @param string $key The key
     * @param string $value The value (Can be a callable)
     * @return mixed|Phix
     */
    public function option($key, $value = null)
    {
        if (func_num_args() == 1) {
            if (isset($this->_options[$key])) {
                return $this->_options[$key];
            }

            return null;
        }

        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        }

        $this->_options[$key] = $value;

        return $this;
    }

    /**
     * Set/Get multiple options.
     *
     * @param array $options The options
     * @param boolean $reset Whether to reset existing options
     * @return mixed|Phix
     */
    public function options(array $options = array(), $reset = false)
    {
        if (func_num_args() == 0) {
            return $this->_options;
        }

        if ($reset) {
            $this->_options = array();
        }

        foreach ($options as $key => $val) {
            $this->option($key, $val);
        }

        return $this;
    }

    /**
     * Set/Get the HTTP response status code.
     *
     * @param integer $status The status (Can be a callable)
     * @return integer|Phix
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
            throw new Exception('Invalid status code "' . $status . '"');
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
     * @return integer|Phix
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
     * @return string|Phix
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
     * @return Phix
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
     * @return array|Phix
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
     * @return string|Phix
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
     * Set/Get the layout view.
     *
     * @param string $layout The layout (Can be a callable)
     * @return string|Phix
     */
    public function layout($layout = null)
    {
        if (func_num_args() == 0) {
            return $this->_layout;
        }

        if (is_callable($layout)) {
            $layout = call_user_func($layout, $this);
        }

        $this->_layout = $layout;

        return $this;
    }

    /**
     * Set/Get the view renderer.
     *
     * @param mixed $renderer The renderer callback
     * @return mixed|Phix
     */
    public function renderer($renderer = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_renderer) {
                $this->_renderer = array('Phix', 'defaultRenderer');
            }

            return $this->_renderer;
        }

        $this->_renderer = $renderer;

        return $this;
    }

    /**
     * Default renderer implementation.
     *
     * @param Phix $phix The Phix instance
     * @param string $view The view to render
     * @param array $vars The vars to pass to the view
     * @return string
     */
    public static function defaultRenderer($phix, $view, array $vars, $format)
    {
        if (is_callable($view)) {
            $content = call_user_func($view, $phix, $vars, $format);
        } elseif (false !== ($viewFilename = $phix->viewFilename($view, $format))) {
            ob_start();
            extract($vars);
            include $viewFilename;
            $content = ob_get_clean();
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
     * Render a view at set as response.
     *
     * @param string $view The view
     * @param array $vars The vars to pass to the view
     * @param string $format The format to render
     * @param string $layout The layout to use
     * @return Phix
     */
    public function render($view, array $vars = array(), $format = null, $layout = null)
    {
        if (null === $format) {
            $format = $this->param('format');
        }

        if (null === $format) {
            $format = $this->defaultFormat();
        }

        $formats = $this->formats();

        if (!isset($formats[$format])) {
            throw new Exception('Invalid format "' . $format . '"');
        }

        $content = call_user_func($this->renderer(), $this, $view, $vars, $format);

        if (false !== $layout) {
            if (false !== $formats[$format]['view']['layout']) {
                if (null !== $formats[$format]['view']['layout']) {
                    $layout = $formats[$format]['view']['layout'];
                }

                if (null === $layout) {
                    $layout = $this->layout();
                }

                if (null !== $layout) {
                    $vars = array('content' => $content);
                    $content = call_user_func($this->renderer(), $this, $layout, $vars, $format);
                }
            }
        }

        $this->response($content, $format);

        return $this;
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
     * Set/Get the default format.
     *
     * @param string $defaultFormat The default format (Can be a callable)
     * @return string|Phix
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
     * @return array|Phix
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

        $this->_formats[$format] = $config;

        return $this;
    }

    /**
     * Set/Get multiple formats and their configuration.
     *
     * @param array $formats The formats
     * @param bool $reset Whether to reset existing formats
     * @return array|Phix
     */
    public function formats(array $formats = array(), $reset = false)
    {
        static $default;

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
     * Default HTML error callback.
     *
     * @param Phix $phix The Phix instance
     * @param integer $status The HTTP status code
     * @param string $msg The error message
     * @return string
     */
    public static function defaultFormatHtmlError($phix, $status, $msg)
    {
        return '<!DOCTYPE html>' .
               '<html>' .
                 '<head></head>' .
                 '<body>' .
                   '<h1>' . $phix->statusPhrase($status) . '</h1>' .
                   '<p>' . $phix->escape($msg) . '</p>' .
                 '</body>' .
               '</html>';
    }

    /**
     * Default JSON error callback.
     *
     * @param Phix $phix The Phix instance
     * @param integer $status The HTTP status code
     * @param string $msg The error message
     * @return string
     */
    public static function defaultFormatJsonError($phix, $status, $msg)
    {
        return json_encode(array('status' => 'error', 'message' => $msg));
    }

    /**
     * Default XML error callback.
     *
     * @param Phix $phix The Phix instance
     * @param integer $status The HTTP status code
     * @param string $msg The error message
     * @return string
     */
    public static function defaultFormatXmlError($phix, $status, $msg)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<response>' .
                 '<status>error</status>' .
                 '<message>' . $phix->escape($msg) . '</message>' .
               '</response>';
    }

    /**
     * Default JSON unserialize callback.
     *
     * @param Phix $phix The Phix instance
     * @param string $string The string to unserialize
     * @return array
     */
    public static function defaultFormatJsonUnserialize($phix, $string)
    {
        return json_decode($string, true);
    }

    /**
     * Default XML unserialize callback.
     *
     * @param Phix $phix The Phix instance
     * @param string $string The string to unserialize
     * @return array
     */
    public static function defaultFormatXmlUnserialize($phix, $string)
    {
        return self::_xmlToArray(simplexml_load_string($string));
    }

    /**
     * Returns a string or an associative and possibly multidimensional array from
     * a SimpleXMLElement.
     *
     * @param  SimpleXMLElement $xmlObject Convert a SimpleXMLElement into an array
     * @return array|string
     */
    protected static function _xmlToArray(SimpleXMLElement $xmlObject)
    {
        $config = array();

        // Search for children
        if (count($xmlObject->children()) > 0) {
            foreach ($xmlObject->children() as $key => $value) {
                if (count($value->children()) > 0) {
                    $value = self::_xmlToArray($value);
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
     * @return string|false|Phix
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

        $this->_requestRawBody = $requestRawBody;

        return $this;
    }

    /**
     * Get/Set the method by which the request was made.
     *
     * @param string $requestMethod The request method (Can be a callable)
     * @return string|Phix
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
     * @return string|Phix
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
                    throw new Exception('Could not detect request uri');
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
     * @return string|Phix
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
     * @return string|Phix
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
     * @return string|Phix
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
     * @return string|Phix
     */
    public function serverUrl($serverUrl = null)
    {
        if (func_num_args() == 0) {
            if (null === $this->_serverUrl) {
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

                if (!empty($_SERVER['HTTP_HOST'])) {
                    $host = $_SERVER['HTTP_HOST'];
                } else {
                    $name   = $_SERVER['SERVER_NAME'];
                    $port   = $_SERVER['SERVER_PORT'];

                    if (($scheme == 'http' && $port == 80) || ($scheme == 'https' && $port == 443)) {
                        $host = $name;
                    } else {
                        $host = $name . ':' . $port;
                    }
                }

                $this->_serverUrl = $scheme . '://' . $host;
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
     * @return Phix
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

        return $this;
    }

    /**
     * Trigger an error and set response for the requested format.
     *
     * @param integer $status The HTTP status code
     * @param string $msg The message (Can be a callable)
     * @return Phix
     */
    public function error($status, $msg = null)
    {
        if (false === $this->trigger('error', array('status' => &$status, 'msg' => &$msg))) {
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

        $format = $this->param('format');

        if (null === $format) {
            $format = $this->defaultFormat();
        }

        $formats = $this->formats();

        if (!isset($formats[$format])) {
            $format = 'html';
        }

        $error = $formats[$format]['error'];
        $this->response(call_user_func($error, $this, $status, $msg), $format);

        $this->_shutdown();

        return $this;
    }

    /**
     * Exception handler.
     *
     * @param Exception $exception The exception
     * @return boolean
     */
    public function exceptionHandler(Exception $exception)
    {
        if (false === $this->trigger('exception_handler', array('exception' => &$exception))) {
            return false;
        }

        $this->_exceptions[] = $exception;

        if ($this->env() == self::ENV_PRODUCTION) {
            $msg = null;
        } else {
            $msg = 'Exception: ' . $exception->getMessage();
        }

        $this->error(500, $msg);

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

    /**
     * Error handler.
     *
     * @param integer $errno
     * @param string $errstr
     * @param string $errfile
     * @param integer $errline
     * @param array $errcontext
     * @return boolean
     */
    public function errorHandler($errno, $errstr, $errfile, $errline, array $errcontext = array())
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $error = array(
            'errno'      => &$errno,
            'errstr'     => &$errstr,
            'errfile'    => &$errfile,
            'errline'    => &$errline,
            'errcontext' => &$errcontext
        );

        if (false === $this->trigger('error_handler', $error)) {
            return false;
        }

        $this->_errors[] = $error;

        if ($this->errorDoHalt($errno)) {
            if (false !== $this->trigger('error_handler_fatal', $error)) {
                if ($this->env() == self::ENV_PRODUCTION) {
                    $msg = null;
                } else {
                    $msg = 'Error: ' . $errstr;
                }
                $this->error(500, $msg);
            }

            $this->_shutdown();
        }

        return false;
    }

    /**
     * Return handled errors.
     *
     * @return array
     */
    public function errors()
    {
        return $this->_errors;
    }

    /**
     * Whether to halt application.
     *
     * @param integer $errno
     * @return boolean
     */
    public function errorDoHalt($errno)
    {
        return !($errno == E_NOTICE ||
                 $errno == E_WARNING ||
                 $errno == E_CORE_WARNING ||
                 $errno == E_COMPILE_WARNING ||
                 $errno == E_USER_WARNING ||
                 $errno == E_USER_NOTICE ||
                 $errno == E_DEPRECATED ||
                 $errno == E_USER_DEPRECATED);
    }
}
