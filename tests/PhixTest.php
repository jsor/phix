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
 * @package    Phix
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2010-Present Jan Sorgalla
 * @license    http://opensource.org/licenses/bsd-license.php The BSD License
 */

/**
 * @package    Phix
 * @subpackage UnitTests
 * @author     Jan Sorgalla
 * @copyright  Copyright (c) 2010-Present Jan Sorgalla
 * @license    http://opensource.org/licenses/bsd-license.php The BSD License
 */
class PhixTest extends PHPUnit_Framework_TestCase
{
    protected $_serverBackup;
    protected $_getBackup;
    protected $_postBackup;

    public function setUp()
    {
        if (!defined('SID')) {
            define('SID', session_name() . '=1234567890');
        }

        $this->_serverBackup = $_SERVER;
        $this->_getBackup    = $_GET;
        $this->_postBackup   = $_POST;

        $_SERVER = $_GET = $_POST = array();
    }

    public function tearDown()
    {
        $_SERVER = $this->_serverBackup;
        $_GET    = $this->_getBackup;
        $_POST   = $this->_postBackup;
    }

    /**
     * @covers Phix::instance
     */
    public function testInstanceConfiguresInstance()
    {
        $phix = Phix::instance(array('requestUri' => array('/test')));
        $this->assertEquals($phix->requestUri(), '/test');
    }

    /**
     * @covers Phix::__construct
     */
    public function testConstructorConfiguresInstance()
    {
        $phix = new Phix(array('requestUri' => array('/test')));
        $this->assertEquals($phix->requestUri(), '/test');
    }

    /**
     * @covers Phix::configure
     */
    public function testConfigure()
    {
        $phix = new Phix();
        $phix->configure(array('requestUri' => array('/test')));
        $this->assertEquals($phix->requestUri(), '/test');
        $ret = $phix->configure(function() {
            return array('requestUri' => array('/test'));
        });
        $this->assertEquals($phix->requestUri(), '/test');
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::configure
     */
    public function testConfigureThrowsExceptionOnUnderscoredOption()
    {
        $this->setExpectedException('Exception', 'Configuring through private methods is forbidden');
        $phix = new Phix();
        $phix->configure(array('_route' => array()));
    }

    /**
     * @covers Phix::configure
     */
    public function testConfigureThrowsExceptionOnForbiddenMethod()
    {
        $this->setExpectedException('Exception', 'Configuring through method "run" is forbidden');
        $phix = new Phix();
        $phix->configure(array('run' => array()));
    }

    /**
     * @covers Phix::autoFlush
     */
    public function testAutoflush()
    {
        $phix = new Phix();
        $this->assertTrue($phix->autoFlush());
        $phix->autoFlush(false);
        $this->assertFalse($phix->autoFlush());
        $ret = $phix->autoFlush(function() {
            return true;
        });
        $this->assertTrue($phix->autoFlush());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::run
     * @covers Phix::flush
     */
    public function testRunFlushesByDefault()
    {
        ob_start();

        $phix = new Phix();
        $phix
            ->get('/', function($phix) {
                $phix->response('<html/>','html');
            })
            ->requestUri('/')
            ->run();

        $this->assertSame('<html/>', ob_get_clean());
        $this->assertSame(array(), $phix->headers());
        $this->assertSame(null, $phix->output());
    }

    /**
     * @covers Phix::run
     * @covers Phix::flush
     */
    public function testRunDoesNotFlushIfAutoFlushIsFalse()
    {
        ob_start();

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/', function($phix) {
                $phix->response('<html/>','html');
            })
            ->requestUri('/')
            ->run();

        $this->assertSame('', ob_get_clean());
    }

    /**
     * @covers Phix::run
     */
    public function testRunHandlesExceptionsThroughExceptionHandler()
    {
        $e = new Exception('test');

        $phix = new Phix();
        $phix->autoFlush(false)
            ->get('/', function($phix) use ($e) {
                throw $e;
            })
            ->requestUri('/')
            ->run();

        $this->assertSame(array($e), $phix->exceptions());
    }

    /**
     * @covers Phix::flush
     */
    public function testFlush()
    {
        ob_start();

        $phix = new Phix();
        $phix
            ->hook('flush', function() {
                return false;
            })
            ->get('/', function($phix) {
                $phix->response('<html/>','html');
            })
            ->requestUri('/')
            ->run();

        $this->assertSame('', ob_get_clean());
    }

    /**
     * @covers Phix::reset
     */
    public function testReset()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->param('foo','bar')
            ->get('/', function($phix) {
                $phix->errorHandler(E_USER_NOTICE, 'Test error', __FILE__, __LINE__);
                $phix->error(404);
            })
            ->requestUri('/')
            ->run();

        $phix->reset();

        $this->assertSame(200, $phix->status());
        $this->assertSame(null, $phix->output());
        $this->assertSame(array(), $phix->errors());
        $this->assertSame(array(), $phix->params());

        $phix
            ->param('foo','bar')
            ->hook('reset',function() {
                return false;
            })
            ->run();

        $phix->reset();

        $this->assertSame(404, $phix->status());
        $this->assertNotSame(null, $phix->output());
        $this->assertNotSame(array(), $phix->errors());
        $this->assertSame('bar', $phix->param('foo'));
    }

    /**
     * @covers Phix::_startup
     * @covers Phix::stopped
     */
    public function testStartupWithoutHook()
    {
        ob_start();

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('shutdown', function() {
                return false;
            })
            ->get('/', function($phix) {
                $phix->response('<html/>','html');
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($phix->stopped());
        $this->assertSame(array($phix, 'errorHandler'), set_error_handler(function() { return false; }));
        restore_error_handler();
        restore_error_handler();
    }

    /**
     * @covers Phix::_startup
     * @covers Phix::stopped
     */
    public function testStartupWithHook()
    {
        ob_start();

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('shutdown', function() {
                return false;
            })
            ->hook('startup', function() {
                return false;
            })
            ->get('/', function($phix) {
                $phix->response('<html/>','html');
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($phix->stopped());
        $this->assertNotSame(array($phix, 'errorHandler'), set_error_handler(function() { return false; }));
        restore_error_handler();
        restore_error_handler();
    }

    /**
     * @covers Phix::escape
     */
    public function testEscape()
    {
        $valuesExpected = array(
            'string' => 'string',
            '<' => '&lt;',
            '>' => '&gt;',
            '\'' => '\'',
            '"' => '&quot;',
            '&' => '&amp;',

            // Test that it does not double encode by default
            '&lt;'   => '&lt;',
            '&gt;'   => '&gt;',
            '&quot;' => '&quot;',
            '&amp;'  => '&amp;'
        );

        $phix = new Phix();
        foreach ($valuesExpected as $input => $output) {
            $this->assertEquals($output, $phix->escape($input));
        }
    }

    /**
     * @covers Phix::encoding
     */
    public function testEncoding()
    {
        $phix = new Phix();
        $this->assertEquals('utf-8', $phix->encoding());
        $phix->encoding('ISO-8859-1');
        $this->assertEquals('ISO-8859-1', $phix->encoding());
        $ret = $phix->encoding(function() {
            return 'ISO-8859-2';
        });
        $this->assertEquals('ISO-8859-2', $phix->encoding());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::output
     */
    public function testOutput()
    {
        $phix = new Phix();
        $this->assertNull($phix->output());
        $phix->output('foo');
        $this->assertEquals('foo', $phix->output());
        $ret = $phix->output(function() {
            return 'bar';
        });
        $this->assertEquals('bar', $phix->output());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::env
     */
    public function testEnv()
    {
        $phix = new Phix();
        $this->assertEquals(Phix::ENV_PRODUCTION, $phix->env());
        $phix->env(null);

        $_SERVER['PHIX_ENV'] = Phix::ENV_STAGING;
        $this->assertEquals(Phix::ENV_STAGING, $phix->env());
        unset($_SERVER['PHIX_ENV']);
        $phix->env(null);

        $this->assertEquals(Phix::ENV_PRODUCTION, $phix->env());
        $phix->env(null);

        if (function_exists('setenv') && setenv('PHIX_ENV', Phix::ENV_TESTING)) {
            $this->assertEquals(Phix::ENV_TESTING, $phix->env());
            setenv('PHIX_ENV', '');
            $phix->env(null);
        }

        $phix->env(Phix::ENV_DEVELOPMENT);
        $this->assertEquals(Phix::ENV_DEVELOPMENT, $phix->env());
        $phix->env(null);

        $ret = $phix->env(function() {
            return 'callback';
        });
        $this->assertEquals('callback', $phix->env());
        $this->assertEquals($ret, $phix);
        $phix->env(null);
    }

    /**
     * @covers Phix::param
     */
    public function testParam()
    {
        $phix = new Phix();
        $this->assertNull($phix->param('foo'));
        $phix->param('foo', 'bar');
        $this->assertEquals('bar', $phix->param('foo'));
        $ret = $phix->param('foo', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $phix->param('foo'));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::params
     */
    public function testParams()
    {
        $params = array(
            'foo' => 'bar',
            'bar' => 'baz',
        );

        $phix = new Phix();
        $this->assertSame(array(), $phix->params());
        $phix->params($params);
        $this->assertEquals($params, $phix->params());
        $phix->param('ping', 'pong');
        $this->assertEquals($params + array('ping' => 'pong'), $phix->params());
        $ret = $phix->params($params, true);
        $this->assertEquals($params, $phix->params());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::router
     */
    public function testRouter()
    {
        $router = function() {
            return null;
        };

        $phix = new Phix();
        $defaultRouter = $phix->router();
        $this->assertEquals($defaultRouter[0], 'Phix');
        $this->assertEquals($defaultRouter[1], 'defaultRouter');
        $ret = $phix->router($router);
        $this->assertEquals($router, $phix->router());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::dispatcher
     */
    public function testDispatcher()
    {
        $dispatcher = function() {
            return null;
        };

        $phix = new Phix();
        $defaultDispatcher = $phix->dispatcher();
        $this->assertEquals($defaultDispatcher[0], 'Phix');
        $this->assertEquals($defaultDispatcher[1], 'defaultDispatcher');
        $ret = $phix->dispatcher($dispatcher);
        $this->assertEquals($dispatcher, $phix->dispatcher());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::session
     */
    public function testSession()
    {
        $phix = new Phix();
        $this->assertNull($phix->session('foo'));
        $phix->session('foo', 'bar');
        $this->assertSame('bar', $phix->session('foo'));
        $phix->session('foo', null);
        $this->assertArrayNotHasKey('foo', $_SESSION);
        $ret = $phix->session('foo', function() {
            return array('bar', 'baz');
        });
        $this->assertSame(array('bar', 'baz'), $phix->session('foo'));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::flash
     */
    public function testFlash()
    {
        $phix = new Phix();
        $this->assertSame(array(), $phix->flash());
        $phix->flash('foo');
        $this->assertSame(array('foo'), $phix->flash());
        $this->assertSame(array(), $phix->flash());
        $ret = $phix->flash(function() {
            return 'bar';
        });
        $this->assertSame(array('bar'), $phix->flash());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::option
     */
    public function testOption()
    {
        $phix = new Phix();
        $this->assertNull($phix->option('foo'));
        $phix->option('foo', 'bar');
        $this->assertEquals('bar', $phix->option('foo'));
        $ret = $phix->option('foo', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $phix->option('foo'));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::options
     */
    public function testOptions()
    {
        $options = array(
            'foo' => 'bar',
            'bar' => 'baz',
        );

        $phix = new Phix();
        $this->assertSame(array(), $phix->options());
        $phix->options($options);
        $this->assertEquals($options, $phix->options());
        $phix->option('ping', 'pong');
        $this->assertEquals($options + array('ping' => 'pong'), $phix->options());
        $ret = $phix->options($options, true);
        $this->assertEquals($options, $phix->options());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::status
     */
    public function testStatus()
    {
        $phix = new Phix();
        $this->assertSame(200, $phix->status());
        $phix->status(500);
        $this->assertSame(500, $phix->status());
        $ret = $phix->status(function() {
            return 301;
        });
        $this->assertSame(301, $phix->status());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::status
     */
    public function testStatusThrowsExceptionOnInvalidStatusCode()
    {
        $this->setExpectedException('Exception', 'Invalid status code "-1"');
        $phix = new Phix();
        $phix->status(-1);
    }

    /**
     * @covers Phix::redirect
     */
    public function testRedirect()
    {
        $phix = new Phix(array(
            'baseUrl' => array('/foo'),
            'serverUrl' => array('http://example.com')
        ));

        $phix->redirect(array('bar'));
        $this->assertSame(302, $phix->status());
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));
        
        $phix->reset();

        $phix->redirect('/foo/bar', 301);
        $this->assertSame(301, $phix->status());
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));

        $phix->redirect('/bar');
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));

        $phix->reset();

        $ret = $phix->redirect(function() {
            return '/foo/bar';
        });
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::url
     */
    public function testUrl()
    {
        $phix = new Phix(array(
            'baseUrl' => array('/foo')
        ));

        $url = $phix->url(array('bar'));
        $this->assertEquals('/foo/bar', $url);

        $phix->url(function() {
            return array('bar');
        });
        $this->assertEquals('/foo/bar', $url);
    }

    /**
     * @covers Phix::statusPhrase
     */
    public function testStatusPhrase()
    {
        $phix = new Phix();
        $this->assertNull($phix->statusPhrase(-1));
        $this->assertEquals('OK', $phix->statusPhrase(200));

        $ret = $phix->statusPhrase(200, 'Foo');
        $this->assertEquals('Foo', $phix->statusPhrase(200));

        $ret = $phix->statusPhrase(200, function() {
           return 'Bar';
        });
        $this->assertEquals('Bar', $phix->statusPhrase(200));

        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::viewsDir
     */
    public function testViewsDir()
    {
        $phix = new Phix();
        $this->assertNull($phix->viewsDir());
        $phix->viewsDir('/foo/bar');
        $this->assertSame('/foo/bar', $phix->viewsDir());
        $ret = $phix->viewsDir(function() {
            return '/bar/baz';
        });
        $this->assertSame('/bar/baz', $phix->viewsDir());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::layout
     */
    public function testLayout()
    {
        $phix = new Phix();
        $this->assertNull($phix->layout());
        $phix->layout('foo');
        $this->assertSame('foo', $phix->layout());
        $ret = $phix->layout(function() {
            return 'bar';
        });
        $this->assertSame('bar', $phix->layout());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::renderer
     */
    public function testRenderer()
    {
        $renderer = function() {
            return null;
        };

        $phix = new Phix();
        $defaultRenderer = $phix->renderer();
        $this->assertEquals($defaultRenderer[0], 'Phix');
        $this->assertEquals($defaultRenderer[1], 'defaultRenderer');
        $ret = $phix->renderer($renderer);
        $this->assertEquals($renderer, $phix->renderer());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::defaultFormat
     */
    public function testDefaultFormat()
    {
        $phix = new Phix();
        $this->assertEquals('html', $phix->defaultFormat());
        $phix->defaultFormat('json');
        $this->assertSame('json', $phix->defaultFormat());
        $ret = $phix->defaultFormat(function() {
            return 'xml';
        });
        $this->assertSame('xml', $phix->defaultFormat());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::format
     */
    public function testFormat()
    {
        $phix = new Phix();
        $this->assertArrayHasKey('view', $phix->format('html'));
        $this->assertArrayHasKey('header', $phix->format('html'));
        $this->assertArrayHasKey('error', $phix->format('html'));

        $this->assertArrayHasKey('view', $phix->format('json'));
        $this->assertArrayHasKey('header', $phix->format('json'));
        $this->assertArrayHasKey('error', $phix->format('json'));

        $this->assertArrayHasKey('view', $phix->format('xml'));
        $this->assertArrayHasKey('header', $phix->format('xml'));
        $this->assertArrayHasKey('error', $phix->format('xml'));

        $foo = array(
            'view' => array(
                'layout'    => false,
                'extension' => array('.foo.php', '.foo.phtml', '.foo')
            ),
            'header' => array(
                'accept'       => array('application/foo'),
                'content-type' => 'application/foo'
            ),
            'error' => function() {
                return 'Foo';
            }
        );


        $this->assertNull($phix->format('foo'));
        $phix->format('foo', $foo);
        $this->assertSame($foo, $phix->format('foo'));

        $phix = new Phix();

        $ret = $phix->format('foo', function() use ($foo) {
            return $foo;
        });

        $this->assertSame($foo, $phix->format('foo'));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::formats
     */
    public function testFormats()
    {
        $phix = new Phix();
        $this->assertArrayHasKey('html', $phix->formats());
        $this->assertArrayHasKey('json', $phix->formats());
        $this->assertArrayHasKey('xml', $phix->formats());

        $current = $phix->formats();
        $formats = array(
            'foo' => array(
                'view' => array(
                    'layout'    => false,
                    'extension' => array('.foo.php', '.foo.phtml', '.foo')
                ),
                'header' => array(
                    'accept'       => array('application/foo'),
                    'content-type' => 'application/foo'
                ),
                'error' => function() {
                    return 'Foo';
                }
            )
        );

        $phix->formats($formats);
        $this->assertEquals($current + $formats, $phix->formats());
        $ret = $phix->formats($formats, true);
        $this->assertEquals($formats, $phix->formats());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::defaultFormatHtmlError
     */
    public function testDefaultFormatHtmlError()
    {
        $error = Phix::defaultFormatHtmlError(new Phix(), 404, 'foo');
        $this->assertEquals('<!DOCTYPE html><html><head></head><body><h1>Not Found</h1><p>foo</p></body></html>', $error);
    }

    /**
     * @covers Phix::defaultFormatJsonError
     */
    public function testDefaultFormatJsonError()
    {
        $error = Phix::defaultFormatJsonError(new Phix(), 404, 'foo');
        $this->assertEquals('{"status":"error","message":"foo"}', $error);
    }

    /**
     * @covers Phix::defaultFormatXmlError
     */
    public function testDefaultFormatXmlError()
    {
        $error = Phix::defaultFormatXmlError(new Phix(), 404, 'foo');
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>error</status><message>foo</message></response>', $error);
    }


    /**
     * @covers Phix::requestHeader
     */
    public function testRequestHeader()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'UTF-8';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'text/json';

        $phix = new Phix();
        $this->assertEquals('UTF-8', $phix->requestHeader('Accept-Encoding'));
        $this->assertEquals('text/json', $phix->requestHeader('Content-Type'));

        $this->assertFalse($phix->requestHeader('X-No-Such-Thing'));

        $phix->requestHeader('X-Foo', 'bar');
        $this->assertEquals('bar', $phix->requestHeader('X-Foo'));

        $ret = $phix->requestHeader('X-Bar', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $phix->requestHeader('X-Bar'));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::requestMethod
     * @group 123
     */
    public function testRequestMethod()
    {
        $phix = new Phix();

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->assertEquals('HEAD', $phix->requestMethod());

        $phix = new Phix();

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'POST';
        $this->assertEquals('POST', $phix->requestMethod());

        $phix = new Phix();

        $_POST['_method'] = 'PUT';
        $this->assertEquals('PUT', $phix->requestMethod());

        $ret = $phix->requestMethod(function() {
            return 'DELETE';
        });
        $this->assertEquals('DELETE', $phix->requestMethod());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::requestUri
     */
    public function testRequestUri()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/mycontroller/myaction?foo=bar';

        $phix = new Phix();
        $this->assertEquals('/mycontroller/myaction?foo=bar', $phix->requestUri());
        $phix->requestUri('/archives/past/4?set=this&unset=that');
        $this->assertEquals('/archives/past/4?set=this&unset=that', $phix->requestUri());
        $this->assertEquals('this', $phix->param('set'));
        $this->assertEquals('that', $phix->param('unset'));

        $ret = $phix->requestUri(function() {
            return '/archives/past/4?set=this&unset=that';
        });
        $this->assertEquals('/archives/past/4?set=this&unset=that', $phix->requestUri());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::requestUri
     */
    public function testRequestUriDoesNotPassUriThroughUrldecode()
    {
        $phix = new Phix();
        $phix->requestUri('/foo/bar?foo=bar%20baz');
        $requestUri = $phix->requestUri();
        $this->assertNotEquals('/foo/bar?foo=bar baz', $requestUri);
        $this->assertEquals('/foo/bar?foo=bar%20baz', $requestUri);
    }

    /**
     * @covers Phix::baseUrl
     */
    public function testBaseUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/mycontroller/myaction?foo=bar';

        $phix = new Phix();
        $this->assertEquals('/', $phix->baseUrl());

        $phix->baseUrl('/foo');
        $this->assertEquals('/foo', $phix->baseUrl());

        $ret = $phix->baseUrl(function() {
            return '/bar';
        });
        $this->assertEquals('/bar', $phix->baseUrl());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::baseUrl
     */
    public function testSetBaseUrlUsingPhpSelf()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/index.php/news/3?var1=val1&var2=val2';
        $_SERVER['SCRIPT_NAME'] = '/home.php';
        $_SERVER['PHP_SELF'] = '/index.php/news/3';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/html/index.php';
        $_GET = array(
            'var1' => 'val1',
            'var2' => 'val2'
        );

        $phix = new Phix();
        $this->assertEquals('/index.php', $phix->baseUrl());
    }

    /**
     * @covers Phix::baseUrl
     */
    public function testSetBaseUrlUsingOrigScriptName()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/index.php/news/3?var1=val1&var2=val2';
        $_SERVER['SCRIPT_NAME'] = '/home.php';
        $_SERVER['PHP_SELF'] = '/home.php';
        $_SERVER['ORIG_SCRIPT_NAME']= '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/html/index.php';
        $_GET = array(
            'var1' => 'val1',
            'var2' => 'val2'
        );

        $phix = new Phix();
        $this->assertEquals('/index.php', $phix->baseUrl());
    }

    /**
     * @covers Phix::baseUrl
     */
    public function testSetBaseUrlAutoDiscoveryUsingRequestUri()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/index.php/news/3?var1=val1&var2=val2';
        $_SERVER['PHP_SELF'] = '/index.php/news/3';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/html/index.php';
        $_GET = array(
            'var1' => 'val1',
            'var2' => 'val2'
        );

        $phix = new Phix();
        $this->assertEquals('/index.php', $phix->baseUrl());
    }

    /**
     * @covers Phix::baseUrl
     */
    public function testSetBaseUrlAutoDiscoveryUsingXRewriteUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        unset($_SERVER['REQUEST_URI']);
        $_SERVER['HTTP_X_REWRITE_URL'] = '/index.php/news/3?var1=val1&var2=val2';
        $_SERVER['PHP_SELF'] = '/index.php/news/3';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/html/index.php';
        $_GET = array(
            'var1' => 'val1',
            'var2' => 'val2'
        );

        $phix = new Phix();
        $this->assertEquals('/index.php', $phix->baseUrl());
    }

    /**
     * @covers Phix::baseUrl
     */
    public function testSetBaseUrlAutoDiscoveryUsingOrigPathInfo()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        unset($_SERVER['REQUEST_URI']);
        $_SERVER['ORIG_PATH_INFO'] = '/index.php/news/3';
        $_SERVER['QUERY_STRING'] = 'var1=val1&var2=val2';
        $_SERVER['PHP_SELF'] = '/index.php/news/3';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/html/index.php';
        $_GET = array(
            'var1' => 'val1',
            'var2' => 'val2'
        );

        $phix = new Phix();
        $this->assertEquals('/index.php', $phix->baseUrl());

    }

    /**
     * @covers Phix::baseUrl
     */
    public function testSetBaseUrlWithScriptNameAsGetParam()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/article/archive?foo=index.php';
        $_SERVER['QUERY_STRING'] = 'foo=index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/tests/index.php';

        $phix = new Phix();
        $this->assertEquals('', $phix->baseUrl());
        $this->assertEquals('/article/archive', $phix->pathInfo());
    }
    
    /**
     * @covers Phix::basePath
     */
    public function testGetBasePathIsEmptyStringIfNoneSet()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/test';

        $phix = new Phix();
        $this->assertEquals('', $phix->basePath());

        $phix->basePath('/foo');
        $this->assertEquals('/foo', $phix->basePath());

        $ret = $phix->basePath(function() {
            return '/bar';
        });
        $this->assertEquals('/bar', $phix->basePath());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::basePath
     */
    public function testBasePathAutoDiscovery()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/html/index.php/news/3?var1=val1&var2=val2';
        $_SERVER['PHP_SELF'] = '/html/index.php/news/3';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/html/index.php';
        $_GET = array(
            'var1' => 'val1',
            'var2' => 'val2'
        );

        $phix = new Phix();
        $this->assertEquals('/html', $phix->basePath(), $phix->basePath());
    }

    /**
     * @covers Phix::basePath
     */
    public function testBasePathAutoDiscoveryWithPhpFile()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/dir/action';
        $_SERVER['PHP_SELF'] = '/dir/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/dir/index.php';

        $phix = new Phix();
        $this->assertEquals('/dir', $phix->basePath(), $phix->basePath());
    }

    /**
     * @covers Phix::pathInfo
     */
    public function testPathInfo()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/mycontroller/myaction?foo=bar';

        $phix = new Phix();
        $this->assertEquals('mycontroller/myaction', $phix->pathInfo());

        $phix->pathInfo('foo');
        $this->assertEquals('foo', $phix->pathInfo());

        $ret = $phix->pathInfo(function() {
            return 'bar';
        });
        $this->assertEquals('bar', $phix->pathInfo());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::pathInfo
     */
    public function testPathInfoNeedingBaseUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/test/index.php/ctrl-name/act-name';

        $phix = new Phix();
        $this->assertEquals('/test/index.php/ctrl-name/act-name', $phix->requestUri());
        $phix->baseUrl('/test/index.php');
        $this->assertEquals('/test/index.php', $phix->baseUrl());

        $requestUri = $phix->requestUri();
        $baseUrl = $phix->baseUrl();
        $pathInfo = substr($requestUri, strlen($baseUrl));
        $this->assertTrue($pathInfo ? true : false);

        $this->assertEquals('/ctrl-name/act-name', $phix->pathInfo(), "Expected $pathInfo;");
    }

    /**
     * @covers Phix::serverUrl
     */
    public function testServerUrl()
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $phix = new Phix();
        $this->assertEquals('https://localhost', $phix->serverUrl());

        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['SERVER_PORT'] = 80;

        $phix = new Phix();
        $this->assertEquals('http://example.com', $phix->serverUrl());

        $_SERVER['SERVER_PORT'] = 123;
        $phix = new Phix();
        $this->assertEquals('http://example.com:123', $phix->serverUrl());

        $phix = new Phix();
        $phix->serverUrl('http://foo.bar');
        $this->assertEquals('http://foo.bar', $phix->serverUrl());

        $ret = $phix->serverUrl(function() {
            return 'http://bar.baz';
        });
        $this->assertEquals('http://bar.baz', $phix->serverUrl());
        $this->assertEquals($ret, $phix);
    }
}
