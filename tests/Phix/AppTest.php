<?php
/**
 * App
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

namespace Phix;

/**
 * @package    Phix
 * @subpackage UnitTests
 * @author     Jan Sorgalla
 * @copyright  Copyright (c) 2010-Present Jan Sorgalla
 * @license    http://opensource.org/licenses/bsd-license.php The BSD License
 */
class AppTest extends \PHPUnit_Framework_TestCase
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
     * @covers \Phix\App::instance
     */
    public function testInstanceConfiguresInstance()
    {
        $app = App::instance(array('requestUri' => array('/test')));
        $this->assertEquals($app->requestUri(), '/test');
    }

    /**
     * @covers \Phix\App::__construct
     */
    public function testConstructorConfiguresInstance()
    {
        $app = new App(array('requestUri' => array('/test')));
        $this->assertEquals($app->requestUri(), '/test');
    }

    /**
     * @covers \Phix\App::configure
     */
    public function testConfigure()
    {
        $app = new App();
        $app->configure(array('requestUri' => array('/test')));
        $this->assertEquals($app->requestUri(), '/test');
        $ret = $app->configure(function() {
            return array('requestUri' => array('/test'));
        });
        $this->assertEquals($app->requestUri(), '/test');
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::configure
     */
    public function testConfigureThrowsExceptionOnUnderscoredOption()
    {
        $this->setExpectedException('\Exception', 'Configuring through private methods is forbidden');
        $app = new App();
        $app->configure(array('_route' => array()));
    }

    /**
     * @covers \Phix\App::configure
     */
    public function testConfigureThrowsExceptionOnForbiddenMethod()
    {
        $this->setExpectedException('\Exception', 'Configuring through method "run" is forbidden');
        $app = new App();
        $app->configure(array('run' => array()));
    }

    /**
     * @covers \Phix\App::autoFlush
     */
    public function testAutoflush()
    {
        $app = new App();
        $this->assertTrue($app->autoFlush());
        $app->autoFlush(false);
        $this->assertFalse($app->autoFlush());
        $ret = $app->autoFlush(function() {
            return true;
        });
        $this->assertTrue($app->autoFlush());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::run
     * @covers \Phix\App::flush
     */
    public function testRunFlushesByDefault()
    {
        ob_start();

        $app = new App();
        $app
            ->header('X-Foo: bar')
            ->header('X-Bar: baz')
            ->get('/', function($app) {
                $app->response('<html/>', 'html');
            })
            ->requestUri('/')
            ->run();

        $this->assertSame('<html/>', ob_get_clean());
        $this->assertSame(array(), $app->headers());
        $this->assertSame(null, $app->output());
    }

    /**
     * @covers \Phix\App::run
     * @covers \Phix\App::flush
     */
    public function testRunDoesNotFlushIfAutoFlushIsFalse()
    {
        ob_start();

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/', function($app) {
                $app->response('<html/>', 'html');
            })
            ->requestUri('/')
            ->run();

        $this->assertSame('', ob_get_clean());
    }

    /**
     * @covers \Phix\App::run
     */
    public function testRunHandlesExceptionsThroughExceptionHandler()
    {
        $e = new \Exception('test');

        $app = new App();
        $app->autoFlush(false)
            ->get('/', function($app) use ($e) {
                throw $e;
            })
            ->requestUri('/')
            ->run();

        $this->assertSame(array($e), $app->exceptions());
    }

    /**
     * @covers \Phix\App::flush
     */
    public function testFlush()
    {
        ob_start();

        $app = new App();
        $app
            ->hook('flush', function() {
                return false;
            })
            ->get('/', function($app) {
                $app->response('<html/>', 'html');
            })
            ->requestUri('/')
            ->run();

        $this->assertSame('', ob_get_clean());
    }

    /**
     * @covers \Phix\App::reset
     */
    public function testReset()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->param('foo', 'bar')
            ->get('/', function($app) {
                $app->error(404);
            })
            ->requestUri('/')
            ->run();

        $app->reset();

        $this->assertSame(200, $app->status());
        $this->assertSame(null, $app->output());
        $this->assertSame(array(), $app->params());

        $app
            ->param('foo', 'bar')
            ->hook('reset',function() {
                return false;
            })
            ->run();

        $app->reset();

        $this->assertSame(404, $app->status());
        $this->assertNotSame(null, $app->output());
        $this->assertSame('bar', $app->param('foo'));
    }

    /**
     * @covers \Phix\App::_init
     */
    public function test_InitProcessesAcceptHeader()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Accept', 'application/json')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('json', $app->param('format'));
        $this->assertTrue(in_array('Vary: Accept', $app->headers()));

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Accept', 'text/xml')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('xml', $app->param('format'));
        $this->assertTrue(in_array('Vary: Accept', $app->headers()));

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Accept', 'text/html')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertNull($app->param('format'));
    }

    /**
     * @covers \Phix\App::_init
     */
    public function test_InitProcessesRangeHeader()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Range', 'items=0-24')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('items', $app->param('range_type'));
        $this->assertEquals(0, $app->param('range_start'));
        $this->assertEquals(24, $app->param('range_end'));
        $this->assertTrue(in_array('Vary: Range', $app->headers()));

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Range', 'bytes=100-200')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('bytes', $app->param('range_type'));
        $this->assertEquals(100, $app->param('range_start'));
        $this->assertEquals(200, $app->param('range_end'));
        $this->assertTrue(in_array('Vary: Range', $app->headers()));
    }

    /**
     * @covers \Phix\App::_init
     */
    public function test_InitProcessesRawBody()
    {
        $_POST = array();

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Content-Type', 'application/json')
            ->requestRawBody(json_encode(array('foo' => 'bar', 'bar' => 'baz')))
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('foo', $_POST);
        $this->assertArrayHasKey('bar', $_POST);
        $this->assertEquals('bar', $_POST['foo']);
        $this->assertEquals('baz', $_POST['bar']);

        $_POST = array();

        $rawBody = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                   '<request>' .
                   '<foo>bar</foo>' .
                   '<bar>baz</bar>' .
                   '</request>';

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestHeader('Content-Type', 'text/xml')
            ->requestRawBody($rawBody)
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('foo', $_POST);
        $this->assertArrayHasKey('bar', $_POST);
        $this->assertEquals('bar', $_POST['foo']);
        $this->assertEquals('baz', $_POST['bar']);

        $_POST = array();

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestMethod('PUT')
            ->requestHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->requestRawBody(http_build_query(array('foo' => 'bar', 'bar' => 'baz'), null, '&'))
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('foo', $_POST);
        $this->assertArrayHasKey('bar', $_POST);
        $this->assertEquals('bar', $_POST['foo']);
        $this->assertEquals('baz', $_POST['bar']);

        $_FILES = array();

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestMethod('PUT')
            ->requestHeader('Content-Type', 'text/plain')
            ->requestRawBody('Blablabla')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('rawbody', $_FILES);
        $this->assertArrayHasKey('name', $_FILES['rawbody']);
        $this->assertArrayHasKey('type', $_FILES['rawbody']);
        $this->assertArrayHasKey('size', $_FILES['rawbody']);
        $this->assertArrayHasKey('tmp_name', $_FILES['rawbody']);
        $this->assertArrayHasKey('error', $_FILES['rawbody']);
        $this->assertArrayHasKey('is_uploaded_file', $_FILES['rawbody']);
        $this->assertEquals('text/plain', $_FILES['rawbody']['type']);
        $this->assertEquals(strlen('Blablabla'), $_FILES['rawbody']['size']);
        $this->assertEquals(UPLOAD_ERR_OK, $_FILES['rawbody']['error']);
        $this->assertFalse($_FILES['rawbody']['is_uploaded_file']);
        $this->assertStringEqualsFile($_FILES['rawbody']['tmp_name'], 'Blablabla');

        $_FILES = array();

        $app = new App();
        $app
            ->autoFlush(false)
            ->requestMethod('POST')
            ->requestHeader('Content-Type', 'text/plain')
            ->requestRawBody('Blablabla')
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('rawbody', $_FILES);
        $this->assertArrayHasKey('name', $_FILES['rawbody']);
        $this->assertArrayHasKey('type', $_FILES['rawbody']);
        $this->assertArrayHasKey('size', $_FILES['rawbody']);
        $this->assertArrayHasKey('tmp_name', $_FILES['rawbody']);
        $this->assertArrayHasKey('error', $_FILES['rawbody']);
        $this->assertArrayHasKey('is_uploaded_file', $_FILES['rawbody']);
        $this->assertEquals('text/plain', $_FILES['rawbody']['type']);
        $this->assertEquals(strlen('Blablabla'), $_FILES['rawbody']['size']);
        $this->assertEquals(UPLOAD_ERR_OK, $_FILES['rawbody']['error']);
        $this->assertFalse($_FILES['rawbody']['is_uploaded_file']);
        $this->assertStringEqualsFile($_FILES['rawbody']['tmp_name'], 'Blablabla');
    }

    /**
     * @covers \Phix\App::_init
     * @covers \Phix\App::stopped
     */
    public function test_InitWithHook()
    {
        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->hook('init', function() {
                return false;
            })
            ->hook('init_end', function() use (&$called) {
                $called = true;
                return false;
            })
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertFalse($called);
        $this->assertTrue($app->stopped());
    }

    /**
     * @covers \Phix\App::_run
     */
    public function test_RunTriggersAllHooks()
    {
        $runCalled = false;
        $runDispatchCalled = false;
        $runEndCalled = false;

        $app = new App();
        $app
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('run', function() use (&$runCalled) {
                $runCalled = true;
            })
            ->hook('run_dispatch', function() use (&$runDispatchCalled) {
                $runDispatchCalled = true;
            })
            ->hook('run_end', function() use (&$runEndCalled) {
                $runEndCalled = true;
            })
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($runCalled);
        $this->assertTrue($runDispatchCalled);
        $this->assertTrue($runEndCalled);
    }

    /**
     * @covers \Phix\App::_run
     */
    public function test_RunTriggersOnlyFirstHookIfFirstHookReturnsFalse()
    {
        $runCalled = false;
        $runDispatchCalled = false;
        $runEndCalled = false;

        $app = new App();
        $app
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('run', function() use (&$runCalled) {
                $runCalled = true;
                return false;
            })
            ->hook('run_dispatch', function() use (&$runDispatchCalled) {
                $runDispatchCalled = true;
            })
            ->hook('run_end', function() use (&$runEndCalled) {
                $runEndCalled = true;
            })
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($runCalled);
        $this->assertFalse($runDispatchCalled);
        $this->assertFalse($runEndCalled);
    }

    /**
     * @covers \Phix\App::_run
     */
    public function test_RunTriggersOnlyFirstAndSecondHookIfSecondHookReturnsFalse()
    {
        $runCalled = false;
        $runDispatchCalled = false;
        $runEndCalled = false;

        $app = new App();
        $app
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('run', function() use (&$runCalled) {
                $runCalled = true;
            })
            ->hook('run_dispatch', function() use (&$runDispatchCalled) {
                $runDispatchCalled = true;
                return false;
            })
            ->hook('run_end', function() use (&$runEndCalled) {
                $runEndCalled = true;
            })
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($runCalled);
        $this->assertTrue($runDispatchCalled);
        $this->assertFalse($runEndCalled);
    }

    /**
     * @covers \Phix\App::_run
     */
    public function test_RunTriggersRunNoRoute()
    {
        $runNoRouteCalled = false;

        $app = new App();
        $app
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('run_no_route', function() use (&$runNoRouteCalled) {
                $runNoRouteCalled = true;
            })
            ->requestUri('/foo')
            ->run();

        $this->assertTrue($runNoRouteCalled);
    }

    /**
     * @covers \Phix\App::_run
     */
    public function test_RunProducesNoOutputForHead()
    {
        ob_start();
        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/', function($app) {
                echo 'foo';
            })
            ->requestUri('/')
            ->requestMethod('HEAD')
            ->run();

        $this->assertSame('', ob_get_clean());
    }

    /**
     * @covers \Phix\App::_shutdown
     */
    public function test_ShutdownTriggersAllHooks()
    {
        $shutdownCalled = false;
        $shutdownEndCalled = false;

        $app = new App();
        $app
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('shutdown', function() use (&$shutdownCalled) {
                $shutdownCalled = true;
            })
            ->hook('shutdown_end', function() use (&$shutdownEndCalled) {
                $shutdownEndCalled = true;
            })
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($shutdownCalled);
        $this->assertTrue($shutdownEndCalled);
    }

    /**
     * @covers \Phix\App::_shutdown
     */
    public function test_ShutdownTriggersOnlyFirstHookIfFirstHookReturnsFalse()
    {
        $shutdownCalled = false;
        $shutdownEndCalled = false;

        $app = new App();
        $app
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('shutdown', function() use (&$shutdownCalled) {
                $shutdownCalled = true;
                return false;
            })
            ->hook('shutdown_end', function() use (&$shutdownEndCalled) {
                $shutdownEndCalled = true;
            })
            ->get('/', function($app) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($shutdownCalled);
        $this->assertFalse($shutdownEndCalled);
    }

    /**
     * @covers \Phix\App::escape
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

        $app = new App();
        foreach ($valuesExpected as $input => $output) {
            $this->assertEquals($output, $app->escape($input));
        }
    }

    /**
     * @covers \Phix\App::encoding
     */
    public function testEncoding()
    {
        $app = new App();
        $this->assertEquals('utf-8', $app->encoding());
        $app->encoding('ISO-8859-1');
        $this->assertEquals('ISO-8859-1', $app->encoding());
        $ret = $app->encoding(function() {
            return 'ISO-8859-2';
        });
        $this->assertEquals('ISO-8859-2', $app->encoding());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::output
     */
    public function testOutput()
    {
        $app = new App();
        $this->assertNull($app->output());
        $app->output('foo');
        $this->assertEquals('foo', $app->output());
        $ret = $app->output(function() {
            return 'bar';
        });
        $this->assertEquals('bar', $app->output());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::response
     */
    public function testResponse()
    {
        $app = new App();
        $app->response('<foo/>');
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));
        $this->assertEquals('<foo/>', $app->output());

        $app = new App();
        $app->response('<foo/>', 'html');
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));
        $this->assertEquals('<foo/>', $app->output());

        $app = new App();
        $app->response('{}', 'json');
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $app->headers()));
        $this->assertEquals('{}', $app->output());

        $app = new App();
        $app->status(412);
        $app->response(array('foo' => 'bar'), 'json');
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $app->headers()));
        $this->assertEquals('{"status":"fail","data":{"foo":"bar"}}', $app->output());

        $app = new App();
        $app->response(array('foo' => 'bar'), 'json');
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $app->headers()));
        $this->assertEquals('{"status":"success","data":{"foo":"bar"}}', $app->output());

        $app = new App();
        $app->response('<xml/>', 'xml');
        $this->assertTrue(in_array('Content-Type: text/xml;charset=utf-8', $app->headers()));
        $this->assertEquals('<xml/>', $app->output());

        $app = new App();
        $app->response(array('foo' => 'bar'), 'xml');
        $this->assertTrue(in_array('Content-Type: text/xml;charset=utf-8', $app->headers()));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>success</status><data><foo>bar</foo></data></response>', $app->output());

        $app = new App();
        $app->status(412);
        $app->response(function() {
            return array('foo' => 'bar');
        }, function() {
            return 'xml';

        });
        $this->assertTrue(in_array('Content-Type: text/xml;charset=utf-8', $app->headers()));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>fail</status><data><foo>bar</foo></data></response>', $app->output());
    }

    /**
     * @covers \Phix\App::javascript
     */
    public function testJavascript()
    {
        $app = new App();
        $app->javascript('$(function() {});');
        $this->assertTrue(in_array('Content-Type: text/javascript;charset=utf-8', $app->headers()));
        $this->assertEquals('$(function() {});', $app->output());

        $app = new App();
        $app->javascript(function() {
            return '$(function() {});';
        });
        $this->assertTrue(in_array('Content-Type: text/javascript;charset=utf-8', $app->headers()));
        $this->assertEquals('$(function() {});', $app->output());
    }

    /**
     * @covers \Phix\App::css
     */
    public function testCss()
    {
        $app = new App();
        $app->css('$(function() {});');
        $this->assertTrue(in_array('Content-Type: text/css;charset=utf-8', $app->headers()));
        $this->assertEquals('$(function() {});', $app->output());

        $app = new App();
        $app->css(function() {
            return '$(function() {});';
        });
        $this->assertTrue(in_array('Content-Type: text/css;charset=utf-8', $app->headers()));
        $this->assertEquals('$(function() {});', $app->output());
    }

    /**
     * @covers \Phix\App::response
     */
    public function testResponseThrowsExceptionOnInvalidFormat()
    {
        $this->setExpectedException('\Exception', 'Invalid format "bogus"');
        $app = new App();
        $app->response('123', 'bogus');
    }

    /**
     * @covers \Phix\App::hooks
     */
    public function testHooks()
    {
        $hooks = array(
            array('init', function() {}),
            array('run', function() {})
        );

        $app = new App();
        $this->assertSame(array(), $app->hooks());
        $app->hooks($hooks);
        $this->assertArrayHasKey('init', $app->hooks());
        $this->assertArrayHasKey('run', $app->hooks());
        $ret = $app->hooks(array(), true);
        $this->assertSame(array(), $app->hooks());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::hook
     */
    public function testHook()
    {
        $app = new App();
        $app->hook('init', 'callback1');
        $this->assertArrayHasKey('init', $app->hooks());
        $this->assertSame(array('init' => array(0 => 'callback1')), $app->hooks());
        $ret = $app->hook('init', 'callback2', 2);
        $this->assertSame(array('init' => array(0 => 'callback1', 2 => 'callback2')), $app->hooks());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::hook
     */
    public function testHookThrowsExceptionOnDuplicateIndex()
    {
        $this->setExpectedException('\Exception', 'There is already a hook registered at index "2"');
        $app = new App();
        $app->hook('init', 'callback1', 2);
        $app->hook('init', 'callback2', 2);
    }

    /**
     * @covers \Phix\App::unhook
     */
    public function testUnhook()
    {
        $app = new App();
        $app->hook('init', 'callback1');
        $app->unhook();
        $this->assertSame(array(), $app->hooks());
        
        $app->hook('init', 'callback1');
        $app->hook('setup', 'callback2');
        $app->unhook('init');
        $this->assertSame(array('setup' => array(0 => 'callback2')), $app->hooks());

        $app->hook('setup', 'callback3');
        $ret = $app->unhook('setup', 'callback3');
        $this->assertSame(array('setup' => array(0 => 'callback2')), $app->hooks());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::trigger
     */
    public function testTrigger()
    {
        $called1 = false;
        $called2 = false;

        $app = new App();

        $app->hook('init', function() use (&$called1) {
            $called1 = true;
            return false;
        });
        $app->hook('setup', function() use (&$called2) {
            $called2 = true;
            return true;
        });

        $ret = $app->trigger('init');

        $this->assertTrue($called1);
        $this->assertFalse($called2);
        $this->assertFalse($ret);

        $ret = $app->trigger('setup');

        $this->assertTrue($called2);
        $this->assertTrue($ret);
    }

    /**
     * @covers \Phix\App::env
     */
    public function testEnv()
    {
        $app = new App();
        $this->assertEquals(App::ENV_PRODUCTION, $app->env());
        $app->env(null);

        $_SERVER['PHIX_ENV'] = App::ENV_STAGING;
        $this->assertEquals(App::ENV_STAGING, $app->env());
        unset($_SERVER['PHIX_ENV']);
        $app->env(null);

        $this->assertEquals(App::ENV_PRODUCTION, $app->env());
        $app->env(null);

        if (function_exists('setenv') && setenv('PHIX_ENV', App::ENV_TESTING)) {
            $this->assertEquals(App::ENV_TESTING, $app->env());
            setenv('PHIX_ENV', '');
            $app->env(null);
        }

        $app->env(App::ENV_DEVELOPMENT);
        $this->assertEquals(App::ENV_DEVELOPMENT, $app->env());
        $app->env(null);

        $ret = $app->env(function() {
            return 'callback';
        });
        $this->assertEquals('callback', $app->env());
        $this->assertEquals($ret, $app);
        $app->env(null);
    }

    /**
     * @covers \Phix\App::param
     */
    public function testParam()
    {
        $app = new App();
        $this->assertNull($app->param('foo'));
        $app->param('foo', 'bar');
        $this->assertEquals('bar', $app->param('foo'));
        $ret = $app->param('foo', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $app->param('foo'));
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::params
     */
    public function testParams()
    {
        $params = array(
            'foo' => 'bar',
            'bar' => 'baz',
        );

        $app = new App();
        $this->assertSame(array(), $app->params());
        $app->params($params);
        $this->assertEquals($params, $app->params());
        $app->param('ping', 'pong');
        $this->assertEquals($params + array('ping' => 'pong'), $app->params());
        $ret = $app->params($params, true);
        $this->assertEquals($params, $app->params());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->route(array('GET'), '/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testGetRouteAlsoAssignsHeadRoute()
    {
        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->route('GET', '/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->requestMethod('HEAD')
            ->run();

        $this->assertTrue($called);

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->requestMethod('HEAD')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::head
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testSimpleHeadRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->head('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testSimpleGetRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::post
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testSimplePostRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->post('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->requestMethod('POST')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::put
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testSimplePutRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->put('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->requestMethod('PUT')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::delete
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testSimpleDeleteRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->delete('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->requestMethod('DELETE')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testEmptyRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testSingleSlashRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/', function() use(&$called) {
                $called = true;
            })
            ->requestUri('')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testNamedParameterRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test/:foo', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('bar', $app->param('foo'));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testInlineWildcardRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test/foo*baz', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foobarbaz')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testWildcardRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test/*/*', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foo/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('foo', $app->param(0));
        $this->assertEquals('bar', $app->param(1));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testWildcardWithNamedParameterRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get(array('/test/*/*', array('param1', 'param2')), function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foo/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('foo', $app->param('param1'));
        $this->assertEquals('bar', $app->param('param2'));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testDoubleWildcardRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test/**', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foo/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('foo/bar', $app->param(0));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testRegexpRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('^/test/(\d+)/foo', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/123456/foo')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('123456', $app->param(0));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testRegexpWithNamedParameterRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get(array('^/test/(\d+)/foo', array('bar')), function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/123456/foo')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('123456', $app->param('bar'));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testRouteSetsDefaults()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get(array('^/test/((\d+)/)?foo', array('', 'bar')), function() use(&$called) {
                $called = true;
            }, array('bar' => 'baz'))
            ->requestUri('/test/foo')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('baz', $app->param('bar'));

        $app->reset();

        $app
            ->requestUri('/test/1234/foo')
            ->pathInfo(null)
            ->run();

        $this->assertEquals('1234', $app->param('bar'));
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testRouteRouteCallbackReturningFalseDontMatchRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            }, array(), function() {
                return false;
            })
            ->requestUri('/test')
            ->run();

        $this->assertFalse($called);
        $this->assertEquals(404, $app->status());
    }

    /**
     * @covers \Phix\App::get
     * @covers \Phix\App::router
     * @covers \Phix\App::route
     * @covers \Phix\App::_route
     * @covers \Phix\App::_dispatch
     */
    public function testRouteRouteCallbackReturningArrayPopulatesToParams()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            }, array(), function() {
                return array('foo' => 'bar');
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('bar', $app->param('foo'));
    }

    /**
     * @covers \Phix\App::router
     */
    public function testDefaultRouterReturnsFalseIfNoRouteForSuppliedRequestMethod()
    {
        $app = new App();
        $callback = $app->router();
        $this->assertFalse($callback($app, array('GET' => array()), 'POST', '/'));
    }

    /**
     * @covers \Phix\App::routes
     */
    public function testRoutes()
    {
        $routes = array(
            array(
                'GET',
                '/',
                function() {}
            )
        );

        $app = new App();
        $this->assertSame(array(), $app->routes());
        $app->routes($routes);
        $this->assertArrayHasKey('GET', $app->routes());
        $ret = $app->routes(array(), true);
        $this->assertSame(array(), $app->routes());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::router
     */
    public function testRouter()
    {
        $router = function() {
            return null;
        };

        $app = new App();
        $defaultRouter = $app->router();
        $this->assertTrue(is_callable($defaultRouter));
        $ret = $app->router($router);
        $this->assertEquals($router, $app->router());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::dispatcher
     */
    public function testDispatcher()
    {
        $dispatcher = function() {
            return null;
        };

        $app = new App();
        $defaultDispatcher = $app->dispatcher();
        $this->assertTrue(is_callable($defaultDispatcher));
        $ret = $app->dispatcher($dispatcher);
        $this->assertEquals($dispatcher, $app->dispatcher());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::dispatcher
     */
    public function testDefaultDispatcher()
    {
        $app = new App();
        $callback = $app->dispatcher();
        $callback($app, function($app) {
            $app->output('foo');
        });
        $this->assertEquals('foo', $app->output());
    }

    /**
     * @covers \Phix\App::session
     */
    public function testSession()
    {
        $app = new App();
        $this->assertNull($app->session('foo'));
        $app->session('foo', 'bar');
        $this->assertSame('bar', $app->session('foo'));
        $app->session('foo', null);
        $this->assertArrayNotHasKey('foo', $_SESSION);
        $ret = $app->session('foo', function() {
            return array('bar', 'baz');
        });
        $this->assertSame(array('bar', 'baz'), $app->session('foo'));
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::flash
     */
    public function testFlash()
    {
        $app = new App();
        $this->assertSame(array(), $app->flash());
        $app->flash('foo');
        $this->assertSame(array('foo'), $app->flash());
        $this->assertSame(array(), $app->flash());
        $ret = $app->flash(function() {
            return 'bar';
        });
        $this->assertSame(array('bar'), $app->flash());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::reg
     */
    public function testReg()
    {
        $app = new App();
        $this->assertNull($app->reg('foo'));
        $app->reg('foo', 'bar');
        $this->assertEquals('bar', $app->reg('foo'));
        $ret = $app->reg('foo', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $app->reg('foo'));
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::regs
     */
    public function testRegs()
    {
        $regs = array(
            'foo' => 'bar',
            'bar' => 'baz',
        );

        $app = new App();
        $this->assertSame(array(), $app->regs());
        $app->regs($regs);
        $this->assertEquals($regs, $app->regs());
        $app->reg('ping', 'pong');
        $this->assertEquals($regs + array('ping' => 'pong'), $app->regs());
        $ret = $app->regs($regs, true);
        $this->assertEquals($regs, $app->regs());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::status
     */
    public function testStatus()
    {
        $app = new App();
        $this->assertSame(200, $app->status());
        $app->status(500);
        $this->assertSame(500, $app->status());
        $ret = $app->status(function() {
            return 301;
        });
        $this->assertSame(301, $app->status());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::status
     */
    public function testStatusThrowsExceptionOnInvalidStatusCode()
    {
        $this->setExpectedException('\Exception', 'Invalid status code "-1"');
        $app = new App();
        $app->status(-1);
    }

    /**
     * @covers \Phix\App::redirect
     * @covers \Phix\App::redirected
     */
    public function testRedirect()
    {
        $app = new App(array(
            'baseUrl' => array('/foo'),
            'serverUrl' => array('http://example.com')
        ));
        $this->assertFalse($app->redirected());

        $app->redirect(array('bar'));
        $this->assertSame(302, $app->status());
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $app->headers()));
        $this->assertTrue($app->redirected());
        
        $app->reset();
        $this->assertFalse($app->redirected());

        $app->redirect('/foo/bar', 301);
        $this->assertSame(301, $app->status());
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $app->headers()));
        $this->assertTrue($app->redirected());

        $app->redirect('/bar');
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $app->headers()));
        $this->assertTrue($app->redirected());

        $app->reset();
        $this->assertFalse($app->redirected());

        $ret = $app->redirect(function() {
            return '/foo/bar';
        });
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $app->headers()));
        $this->assertTrue($app->redirected());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::url
     */
    public function testUrl()
    {
        $app = new App(array(
            'baseUrl' => array('/foo')
        ));

        $url = $app->url(array('bar'));
        $this->assertEquals('/foo/bar', $url);

        $app->url(function() {
            return array('bar');
        });
        $this->assertEquals('/foo/bar', $url);
    }

    /**
     * @covers \Phix\App::statusPhrase
     */
    public function testStatusPhrase()
    {
        $app = new App();
        $this->assertNull($app->statusPhrase(-1));
        $this->assertEquals('OK', $app->statusPhrase(200));

        $ret = $app->statusPhrase(200, 'Foo');
        $this->assertEquals('Foo', $app->statusPhrase(200));

        $ret = $app->statusPhrase(200, function() {
           return 'Bar';
        });
        $this->assertEquals('Bar', $app->statusPhrase(200));

        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::header
     */
    public function testHeader()
    {
        $app = new App();

        $ret = $app->header(function($app) {
            return 'Location: http://example.com';
        });
        $this->assertTrue(in_array('Location: http://example.com', $app->headers()));
        $this->assertEquals($ret, $app);

        $app->reset();

        $app->header('Location: http://localhost');
        $this->assertTrue(in_array('Location: http://localhost', $app->headers()));

        $app->header('Location: http://127.0.0.1', true);
        $this->assertTrue(in_array('Location: http://127.0.0.1', $app->headers()));
        $this->assertFalse(in_array('Location: http://localhost', $app->headers()));
    }

    /**
     * @covers \Phix\App::headers
     */
    public function testHeaders()
    {
        $app = new App();

        $app->header('X-Foo: bar');

        $app->headers(array(
            'X-Bar: baz',
            'Location: http://localhost',
            array('Location: http://127.0.0.1', true)
        ), true);

        $this->assertFalse(in_array('X-Foo: bar', $app->headers()));
        $this->assertTrue(in_array('X-Bar: baz', $app->headers()));

        $this->assertTrue(in_array('Location: http://127.0.0.1', $app->headers()));
        $this->assertFalse(in_array('Location: http://localhost', $app->headers()));
    }

    /**
     * @covers \Phix\App::viewsDir
     */
    public function testViewsDir()
    {
        $app = new App();
        $this->assertNull($app->viewsDir());
        $app->viewsDir('/foo/bar');
        $this->assertSame('/foo/bar', $app->viewsDir());
        $ret = $app->viewsDir(function() {
            return '/bar/baz';
        });
        $this->assertSame('/bar/baz', $app->viewsDir());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::layout
     */
    public function testLayout()
    {
        $app = new App();
        $this->assertNull($app->layout());
        $app->layout('foo');
        $this->assertSame('foo', $app->layout());
        $func = function() {
            return 'bar';
        };
        $ret = $app->layout($func);
        $this->assertSame($func, $app->layout());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::view
     */
    public function testView()
    {
        $app = new App();
        $this->assertNull($app->view('foo'));
        $app->view('foo', 'bar');
        $this->assertEquals('bar', $app->view('foo'));
        $func = function() {
            return 'baz';
        };
        $ret = $app->view('foo', $func);
        $this->assertEquals($func, $app->view('foo'));
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::renderer
     */
    public function testRenderer()
    {
        $renderer = function() {
            return null;
        };

        $app = new App();
        $defaultRenderer = $app->renderer();
        $this->assertTrue(is_callable($defaultRenderer));
        $ret = $app->renderer($renderer);
        $this->assertEquals($renderer, $app->renderer());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::renderer
     */
    public function testDefaultRenderer()
    {
        $app = new App();
        $app->viewsDir(dirname(__FILE__) . '/_files/views');

        $callback = $app->renderer();

        $content = $callback($app, function($app, array $vars, $format) {
            return 'foo';
        }, array(), 'html');
        $this->assertEquals('foo', $content);

        $content = $callback($app, 'view', array('controller' => 'foo'), 'html');
        $this->assertEquals('foo', $content);

        $content = $callback($app, 'Just a string', array(), 'html');
        $this->assertEquals('Just a string', $content);

        $content = $callback($app, 'Just a %s', array('string'), 'html');
        $this->assertEquals('Just a string', $content);
    }

    /**
     * @covers \Phix\App::render
     */
    public function testRender()
    {
        $app = new App();
        $app->viewsDir(dirname(__FILE__) . '/_files/views');

        $content = $app->render(function($app, array $vars, $format) {
            return 'foo';
        }, array(), 'html');
        $this->assertEquals('foo', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));

        $app->reset();

        $app->render('Just a string', array(), 'html');
        $this->assertEquals('Just a string', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));

        $app->reset();

        $app->render('view', array('controller' => 'foo'), function() {
            return 'html';
        });
        $this->assertEquals('foo', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));

        $app->reset();

        $app->render('view', array('controller' => 'foo'));
        $this->assertEquals('foo', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));

        $app->reset();

        $app->param('format', 'html');
        $app->render('view', array('controller' => 'foo'));
        $this->assertEquals('foo', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));

        $app->reset();

        $app->layout('layout');
        $app->render('view', array('controller' => 'foo'));
        $this->assertStringStartsWith('<!DOCTYPE html>', $app->output());
        $this->assertRegExp('/foo<\/body>/', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));
        
        $app->reset();

        $app->render('view', array('controller' => 'foo'), 'json');
        $this->assertEquals(json_encode(array('status' => 'success', 'data' => array('controller' => 'foo'))), $app->output());
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $app->headers()));

        $app->reset();

        $app->view('view', function() {
            return 'bar';
        });
        $app->render('view', array('controller' => 'foo'), 'json');
        $this->assertEquals('bar', $app->output());
    }

    /**
     * @covers \Phix\App::render
     */
    public function testRenderThrowsExceptionOnInvalidFormat()
    {
        $this->setExpectedException('\Exception', 'Invalid format "bogus"');
        $app = new App();
        $app->render('123', array(), 'bogus');
    }

    /**
     * @covers \Phix\App::viewFilename
     */
    public function testViewFilename()
    {
        $app = new App();
        $app->viewsDir(dirname(__FILE__) . '/_files/views');

        $this->assertFalse($app->viewFilename('view', 'bogus'));
        $this->assertFalse($app->viewFilename('bogus', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.html.php', $app->viewFilename('view', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.html.php', $app->viewFilename('view.html.php', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.html.php', $app->viewFilename('view.php', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.json.php', $app->viewFilename('view', 'json'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'bar.xml.php', $app->viewFilename('bar', 'xml'));
    }

    /**
     * @covers \Phix\App::currentFormat
     */
    public function testCurrentFormat()
    {
        $app = new App();
        $this->assertEquals('html', $app->currentFormat());
        $app->param('format', 'json');
        $this->assertSame('json', $app->currentFormat());
        $app->param('format', 'invalid');
        $this->assertSame('html', $app->currentFormat());
    }

    /**
     * @covers \Phix\App::defaultFormat
     */
    public function testDefaultFormat()
    {
        $app = new App();
        $this->assertEquals('html', $app->defaultFormat());
        $app->defaultFormat('foo');
        $this->assertSame('foo', $app->defaultFormat());
        $ret = $app->defaultFormat(function() {
            return 'bar';
        });
        $this->assertSame('bar', $app->defaultFormat());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::format
     */
    public function testFormat()
    {
        $app = new App();
        $this->assertArrayHasKey('view', $app->format('html'));
        $this->assertArrayHasKey('contenttype', $app->format('html'));
        $this->assertArrayHasKey('error', $app->format('html'));

        $this->assertArrayHasKey('view', $app->format('json'));
        $this->assertArrayHasKey('contenttype', $app->format('json'));
        $this->assertArrayHasKey('error', $app->format('json'));

        $this->assertArrayHasKey('view', $app->format('xml'));
        $this->assertArrayHasKey('contenttype', $app->format('xml'));
        $this->assertArrayHasKey('error', $app->format('xml'));

        $foo = array(
            'view' => array(
                'layout'    => false,
                'extension' => array('.foo.php', '.foo.phtml', '.foo')
            ),
            'contenttype' => array(
                'request'  => array('application/foo'),
                'response' => 'application/foo'
            ),
            'error' => function() {
                return 'Foo';
            }
        );


        $this->assertNull($app->format('foo'));
        $app->format('foo', $foo);
        $this->assertSame($foo, $app->format('foo'));

        $app = new App();

        $app->format('foo', function() use ($foo) {
            return $foo;
        });

        $this->assertSame($foo, $app->format('foo'));


        $ret = $app->format('foo', null);
        $this->assertArrayNotHasKey('foo', $app->formats());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::format
     */
    public function testFormatThrowsExceptionIfDefaultFormatIsRemoved()
    {
        $this->setExpectedException('\Exception', 'Removing the default format is not allowed');
        $app = new App();
        $app->format('html', null);
    }

    /**
     * @covers \Phix\App::formats
     */
    public function testFormats()
    {
        $app = new App();
        $this->assertArrayHasKey('html', $app->formats());
        $this->assertArrayHasKey('json', $app->formats());
        $this->assertArrayHasKey('xml', $app->formats());

        $current = $app->formats();
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

        $app->formats($formats);
        $this->assertEquals($current + $formats, $app->formats());
        $ret = $app->formats($formats, true);
        $this->assertEquals($formats, $app->formats());
        $this->assertEquals($ret, $app);
    }

    public function testFormatHtmlErrorCallback()
    {
        $app = new App();
        $format = $app->format('html');
        $callback = $format['error'];
        $error = $callback($app, 404, 'foo');
        $this->assertEquals('<!DOCTYPE html><html><head></head><body><h1>Not Found</h1><p>foo</p></body></html>', $error);
    }

    public function testFormatJsonErrorCallback()
    {
        $app = new App();
        $format = $app->format('json');
        $callback = $format['error'];
        $error = $callback($app, 404, 'foo');
        $this->assertEquals('{"status":"error","message":"foo"}', $error);
    }

    public function testFormatXmlErrorCallback()
    {
        $app = new App();
        $format = $app->format('xml');
        $callback = $format['error'];
        $error = $callback($app, 404, 'foo');
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>error</status><message>foo</message></response>', $error);
    }

    public function testFormatHtmlResponseCallback()
    {
        $app = new App();
        $format = $app->format('html');
        $callback = $format['response'];

        $response = $callback($app, 200, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $expected = '<!DOCTYPE html><html><head><title>OK</title></head><body><h1>OK</h1><pre>Array
(
    [foo] =&gt; bar
    [bar] =&gt; Array
        (
            [key1] =&gt; val1
        )

    [baz] =&gt; Array
        (
            [0] =&gt; val2
            [1] =&gt; val3
        )

)
</pre></body></html>';
        $this->assertEquals(str_replace(array("\r\n", "\t"), array("\n", "    "), $expected), $response);

        $response = $callback($app, 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $expected = '<!DOCTYPE html><html><head><title>Precondition Failed</title></head><body><h1>Precondition Failed</h1><pre>Array
(
    [foo] =&gt; bar
    [bar] =&gt; Array
        (
            [key1] =&gt; val1
        )

    [baz] =&gt; Array
        (
            [0] =&gt; val2
            [1] =&gt; val3
        )

)
</pre></body></html>';
        $this->assertEquals(str_replace(array("\r\n", "\t"), array("\n", "    "), $expected), $response);
    }

    public function testFormatJsonResponseCallback()
    {
        $app = new App();
        $format = $app->format('json');
        $callback = $format['response'];

        $response = $callback($app, 200, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('{"status":"success","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}}', $response);

        $response = $callback($app, 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('{"status":"fail","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}}', $response);
    }

    public function testDefaultFormatJsonResponseHandlesJSONPCallback()
    {
        $_GET = array(
            'callback' => 'jsonp1234'
        );

        $app = new App();
        $format = $app->format('json');
        $callback = $format['response'];

        $response = $callback($app, 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('jsonp1234({"status":"fail","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}})', $response);
    }

    public function testDefaultFormatJsonResponseIgnoresInvalidJSONPCallback()
    {
        $_GET = array(
            'callback' => '1234'
        );

        $app = new App();
        $format = $app->format('json');
        $callback = $format['response'];

        $response = $callback($app, 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('{"status":"fail","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}}', $response);
    }

    public function testDefaultFormatXmlResponse()
    {
        $app = new App();
        $format = $app->format('xml');
        $callback = $format['response'];

        $response = $callback($app, 200, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>success</status><data><foo>bar</foo><bar><key1>val1</key1></bar><baz>val2</baz><baz>val3</baz></data></response>', $response);

        $response = $callback($app, 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>fail</status><data><foo>bar</foo><bar><key1>val1</key1></bar><baz>val2</baz><baz>val3</baz></data></response>', $response);

        $response = $callback($app, 200, (object) array('foo' => 'bar', 'bar' => (object) array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>success</status><data><foo>bar</foo><bar><key1>val1</key1></bar><baz>val2</baz><baz>val3</baz></data></response>', $response);
    }

    public function testFormatJsonUnserializeCallback()
    {
        $app = new App();
        $format = $app->format('json');
        $callback = $format['unserialize'];

        $obj = new \stdClass();
        $obj->bar = array('baz', 'test');
        $json = json_encode(array('foo' => 'bar', 'obj' => $obj));

        $arr = $callback($app, $json);

        $expected = array('foo' => 'bar', 'obj' => array('bar' =>  array('baz', 'test')));
        $this->assertSame($expected, $arr);
    }

    public function testFormatXmlUnserializeCallback()
    {
        $app = new App();
        $format = $app->format('xml');
        $callback = $format['unserialize'];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<data>' .
               '<foo>bar</foo>' .
               '<obj><bar>baz</bar><bar>test</bar></obj>' .
               '</data>';

        $arr = $callback($app, $xml);

        $expected = array('foo' => 'bar', 'obj' => array('bar' => array('baz', 'test')));
        $this->assertSame($expected, $arr);
    }

    /**
     * @covers \Phix\App::requestHeader
     */
    public function testRequestHeader()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'UTF-8';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'text/json';

        $app = new App();
        $this->assertEquals('UTF-8', $app->requestHeader('Accept-Encoding'));
        $this->assertEquals('text/json', $app->requestHeader('Content-Type'));

        $this->assertFalse($app->requestHeader('X-No-Such-Thing'));

        $app->requestHeader('X-Foo', 'bar');
        $this->assertEquals('bar', $app->requestHeader('X-Foo'));

        $ret = $app->requestHeader('X-Bar', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $app->requestHeader('X-Bar'));
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::requestRawBody
     */
    public function testRequestRawBody()
    {
        $app = new App();
        $this->assertFalse($app->requestRawBody());
        $app->requestRawBody('foo');
        $this->assertSame('foo', $app->requestRawBody());
        $ret = $app->requestRawBody(function() {
            return 'bar';
        });
        $this->assertSame('bar', $app->requestRawBody());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::requestRawBody
     */
    public function testRequestRawBodyReturnsFalseOnEmptyString()
    {
        $app = new App();
        $app->requestRawBody('');
        $this->assertFalse($app->requestRawBody());
    }

    /**
     * @covers \Phix\App::requestMethod
     */
    public function testRequestMethod()
    {
        $app = new App();

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->assertEquals('HEAD', $app->requestMethod());

        $app = new App();

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'POST';
        $this->assertEquals('POST', $app->requestMethod());

        $app = new App();

        $_POST['_method'] = 'PUT';
        $this->assertEquals('PUT', $app->requestMethod());

        $ret = $app->requestMethod(function() {
            return 'DELETE';
        });
        $this->assertEquals('DELETE', $app->requestMethod());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::requestUri
     */
    public function testRequestUri()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/mycontroller/myaction?foo=bar';

        $app = new App();
        $this->assertEquals('/mycontroller/myaction?foo=bar', $app->requestUri());
        $app->requestUri('/archives/past/4?set=this&unset=that');
        $this->assertEquals('/archives/past/4?set=this&unset=that', $app->requestUri());
        $this->assertArrayHasKey('set', $_GET);
        $this->assertArrayHasKey('unset', $_GET);
        $this->assertEquals('this', $_GET['set']);
        $this->assertEquals('that', $_GET['unset']);

        $ret = $app->requestUri(function() {
            return '/archives/past/4?set=this&unset=that';
        });
        $this->assertEquals('/archives/past/4?set=this&unset=that', $app->requestUri());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::requestUri
     */
    public function testRequestUriDoesNotPassUriThroughUrldecode()
    {
        $app = new App();
        $app->requestUri('/foo/bar?foo=bar%20baz');
        $requestUri = $app->requestUri();
        $this->assertNotEquals('/foo/bar?foo=bar baz', $requestUri);
        $this->assertEquals('/foo/bar?foo=bar%20baz', $requestUri);
    }

    /**
     * @covers \Phix\App::baseUrl
     */
    public function testBaseUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/mycontroller/myaction?foo=bar';

        $app = new App();
        $this->assertEquals('', $app->baseUrl());

        $app->baseUrl('/foo');
        $this->assertEquals('/foo', $app->baseUrl());

        $ret = $app->baseUrl(function() {
            return '/bar';
        });
        $this->assertEquals('/bar', $app->baseUrl());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::baseUrl
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

        $app = new App();
        $this->assertEquals('/index.php', $app->baseUrl());
    }

    /**
     * @covers \Phix\App::baseUrl
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

        $app = new App();
        $this->assertEquals('/index.php', $app->baseUrl());
    }

    /**
     * @covers \Phix\App::baseUrl
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

        $app = new App();
        $this->assertEquals('/index.php', $app->baseUrl());
    }

    /**
     * @covers \Phix\App::baseUrl
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

        $app = new App();
        $this->assertEquals('/index.php', $app->baseUrl());
    }

    /**
     * @covers \Phix\App::baseUrl
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

        $app = new App();
        $this->assertEquals('/index.php', $app->baseUrl());

    }

    /**
     * @covers \Phix\App::baseUrl
     */
    public function testSetBaseUrlWithScriptNameAsGetParam()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/article/archive?foo=index.php';
        $_SERVER['QUERY_STRING'] = 'foo=index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/tests/index.php';

        $app = new App();
        $this->assertEquals('', $app->baseUrl());
        $this->assertEquals('/article/archive', $app->pathInfo());
    }
    
    /**
     * @covers \Phix\App::basePath
     */
    public function testGetBasePathIsEmptyStringIfNoneSet()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/test';

        $app = new App();
        $this->assertEquals('', $app->basePath());

        $app->basePath('/foo');
        $this->assertEquals('/foo', $app->basePath());

        $ret = $app->basePath(function() {
            return '/bar';
        });
        $this->assertEquals('/bar', $app->basePath());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::basePath
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

        $app = new App();
        $this->assertEquals('/html', $app->basePath(), $app->basePath());
    }

    /**
     * @covers \Phix\App::basePath
     */
    public function testBasePathAutoDiscoveryWithPhpFile()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/dir/action';
        $_SERVER['PHP_SELF'] = '/dir/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/var/web/dir/index.php';

        $app = new App();
        $this->assertEquals('/dir', $app->basePath(), $app->basePath());
    }

    /**
     * @covers \Phix\App::pathInfo
     */
    public function testPathInfo()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/mycontroller/myaction?foo=bar';

        $app = new App();
        $this->assertEquals('/mycontroller/myaction', $app->pathInfo());

        $app->pathInfo('foo');
        $this->assertEquals('foo', $app->pathInfo());

        $ret = $app->pathInfo(function() {
            return 'bar';
        });
        $this->assertEquals('bar', $app->pathInfo());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::pathInfo
     */
    public function testPathInfoNeedingBaseUrl()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/test/index.php/ctrl-name/act-name';

        $app = new App();
        $this->assertEquals('/test/index.php/ctrl-name/act-name', $app->requestUri());
        $app->baseUrl('/test/index.php');
        $this->assertEquals('/test/index.php', $app->baseUrl());

        $requestUri = $app->requestUri();
        $baseUrl = $app->baseUrl();
        $pathInfo = substr($requestUri, strlen($baseUrl));
        $this->assertTrue($pathInfo ? true : false);

        $this->assertEquals('/ctrl-name/act-name', $app->pathInfo(), "Expected $pathInfo;");
    }

    /**
     * @covers \Phix\App::serverUrl
     */
    public function testServerUrl()
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $app = new App();
        $this->assertEquals('https://localhost', $app->serverUrl());

        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['SERVER_PORT'] = 80;

        $app = new App();
        $this->assertEquals('http://example.com', $app->serverUrl());

        $_SERVER['SERVER_PORT'] = 123;
        $app = new App();
        $this->assertEquals('http://example.com:123', $app->serverUrl());

        $app = new App();
        $app->serverUrl('http://foo.bar');
        $this->assertEquals('http://foo.bar', $app->serverUrl());

        $ret = $app->serverUrl(function() {
            return 'http://bar.baz';
        });
        $this->assertEquals('http://bar.baz', $app->serverUrl());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::notFound
     */
    public function testNotFoundWithHook()
    {
        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->hook('not_found', function() use(&$called) {
                $called = true;
                return false;
            })
            ->notFound();

        $this->assertTrue($called);
        $this->assertNotEquals(404, $app->status());
        $this->assertFalse($app->stopped());
    }

    /**
     * @covers \Phix\App::notFound
     */
    public function testNotFoundSetsStatusAndMessage()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->requestUri('/foo');

        $app->notFound();

        $this->assertEquals(404, $app->status());
        $this->assertTrue($app->stopped());

        $app->reset();

        $ret = $app->notFound(function($app) {
            return 'Foo message';
        });
        $this->assertRegExp('/Foo message/', $app->output());
        $this->assertEquals($ret, $app);
    }

    /**
     * @covers \Phix\App::error
     */
    public function testErrorWithHook()
    {
        $called = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->hook('error', function($app, $params) use(&$called) {
                $called = true;
                $params['status'] = 501;
                return false;
            })
            ->error(500);

        $this->assertTrue($called);
        $this->assertNotEquals(501, $app->status());
        $this->assertFalse($app->stopped());
    }

    /**
     * @covers \Phix\App::error
     */
    public function testErrorWithHookManipulatesParams()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->hook('error', function($app, $params) use(&$called) {
                $params['status'] = 501;
                $params['msg'] = 'Nope, not implemented';
            })
            ->error(500);

        $this->assertEquals(501, $app->status());
        $this->assertRegExp('/Nope, not implemented/', $app->output());
    }

    /**
     * @covers \Phix\App::error
     */
    public function testErrorWithCallbacks()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->error(function() {
                return 501;
            }, function() {
                return 'Nope, not implemented';
            }, function() {
                return 'html';
            });

        $this->assertEquals(501, $app->status());
        $this->assertRegExp('/Nope, not implemented/', $app->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $app->headers()));
    }

    /**
     * @covers \Phix\App::error
     */
    public function testErrorResetsStatusTo500ForInvalidStatus()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->error(-1);

        $this->assertEquals(500, $app->status());
    }

    /**
     * @covers \Phix\App::error
     */
    public function testErrorResetsFormatToHtmlForInvalidFormat()
    {
        $app = new App();
        $app
            ->autoFlush(false)
            ->param('format', 'pdf')
            ->error(500);

        $this->assertRegExp('/<html>/', $app->output());
    }

    /**
     * @covers \Phix\App::error
     */
    public function testErrorThrowsExceptionForInvalidPassedFormat()
    {
        $this->setExpectedException('\Exception', 'Invalid format "pdf"');
        $app = new App();
        $app
            ->autoFlush(false)
            ->error(500, null, 'pdf');
    }

    /**
     * @covers \Phix\App::exceptionHandler
     * @covers \Phix\App::exceptions
     */
    public function testExceptionHandler()
    {
        $exception = new \Exception('Test-Exception');

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/', function() use($exception) {
                throw $exception;
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue(in_array($exception, $app->exceptions(), true));
        $this->assertEquals(500, $app->status());
        $this->assertNotRegexp('/Test-Exception/', $app->output());

        $app
            ->reset()
            ->env(App::ENV_DEVELOPMENT)
            ->run();

        $this->assertRegexp('/Test-Exception/', $app->output());
    }

    /**
     * @covers \Phix\App::exceptionHandler
     * @covers \Phix\App::exceptions
     */
    public function testExceptionHandlerWithHooks()
    {
        $called1 = false;
        $called2 = false;

        $app = new App();
        $app
            ->autoFlush(false)
            ->get('/', function() {
                throw new \Exception('Test-Exception');
            })
            ->hook('exception_handler', function() use(&$called1) {
                $called1 = true;
                return false;
            })
            ->hook('exception_handler_end', function() use(&$called2) {
                $called2 = true;
            })
            ->requestUri('/')
            ->run();

            $this->assertTrue($called1);
            $this->assertFalse($called2);
    }
}
