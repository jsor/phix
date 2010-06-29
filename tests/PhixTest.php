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
            ->header('X-Foo: bar')
            ->header('X-Bar: baz')
            ->get('/', function($phix) {
                $phix->response('<html/>', 'html');
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
                $phix->response('<html/>', 'html');
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
                $phix->response('<html/>', 'html');
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
            ->param('foo', 'bar')
            ->get('/', function($phix) {
                $phix->error(404);
            })
            ->requestUri('/')
            ->run();

        $phix->reset();

        $this->assertSame(200, $phix->status());
        $this->assertSame(null, $phix->output());
        $this->assertSame(array(), $phix->params());

        $phix
            ->param('foo', 'bar')
            ->hook('reset',function() {
                return false;
            })
            ->run();

        $phix->reset();

        $this->assertSame(404, $phix->status());
        $this->assertNotSame(null, $phix->output());
        $this->assertSame('bar', $phix->param('foo'));
    }

    /**
     * @covers Phix::_init
     */
    public function test_InitProcessesAcceptHeader()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Accept', 'application/json')
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('json', $phix->param('format'));
        $this->assertTrue(in_array('Vary: Accept', $phix->headers()));

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Accept', 'text/xml')
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('xml', $phix->param('format'));
        $this->assertTrue(in_array('Vary: Accept', $phix->headers()));

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Accept', 'text/html')
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertNull($phix->param('format'));
    }

    /**
     * @covers Phix::_init
     */
    public function test_InitProcessesRangeHeader()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Range', 'items=0-24')
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('items', $phix->param('range_type'));
        $this->assertEquals(0, $phix->param('range_start'));
        $this->assertEquals(24, $phix->param('range_end'));
        $this->assertTrue(in_array('Vary: Range', $phix->headers()));

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Range', 'bytes=100-200')
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertEquals('bytes', $phix->param('range_type'));
        $this->assertEquals(100, $phix->param('range_start'));
        $this->assertEquals(200, $phix->param('range_end'));
        $this->assertTrue(in_array('Vary: Range', $phix->headers()));
    }

    /**
     * @covers Phix::_init
     */
    public function test_InitProcessesRawBody()
    {
        $_POST = array();

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Content-Type', 'application/json')
            ->requestRawBody(json_encode(array('foo' => 'bar', 'bar' => 'baz')))
            ->get('/', function($phix) {
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

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestHeader('Content-Type', 'text/xml')
            ->requestRawBody($rawBody)
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('foo', $_POST);
        $this->assertArrayHasKey('bar', $_POST);
        $this->assertEquals('bar', $_POST['foo']);
        $this->assertEquals('baz', $_POST['bar']);

        $_POST = array();

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestMethod('PUT')
            ->requestHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->requestRawBody(http_build_query(array('foo' => 'bar', 'bar' => 'baz'), null, '&'))
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertArrayHasKey('foo', $_POST);
        $this->assertArrayHasKey('bar', $_POST);
        $this->assertEquals('bar', $_POST['foo']);
        $this->assertEquals('baz', $_POST['bar']);

        $_FILES = array();

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestMethod('PUT')
            ->requestHeader('Content-Type', 'text/plain')
            ->requestRawBody('Blablabla')
            ->get('/', function($phix) {
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

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestMethod('POST')
            ->requestHeader('Content-Type', 'text/plain')
            ->requestRawBody('Blablabla')
            ->get('/', function($phix) {
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
     * @covers Phix::_init
     * @covers Phix::stopped
     */
    public function test_InitWithHook()
    {
        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->hook('init', function() {
                return false;
            })
            ->hook('init_end', function() use (&$called) {
                $called = true;
                return false;
            })
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertFalse($called);
        $this->assertTrue($phix->stopped());
    }

    /**
     * @covers Phix::_run
     */
    public function test_RunTriggersAllHooks()
    {
        $runCalled = false;
        $runDispatchCalled = false;
        $runEndCalled = false;

        $phix = new Phix();
        $phix
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
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($runCalled);
        $this->assertTrue($runDispatchCalled);
        $this->assertTrue($runEndCalled);
    }

    /**
     * @covers Phix::_run
     */
    public function test_RunTriggersOnlyFirstHookIfFirstHookReturnsFalse()
    {
        $runCalled = false;
        $runDispatchCalled = false;
        $runEndCalled = false;

        $phix = new Phix();
        $phix
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
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($runCalled);
        $this->assertFalse($runDispatchCalled);
        $this->assertFalse($runEndCalled);
    }

    /**
     * @covers Phix::_run
     */
    public function test_RunTriggersOnlyFirstAndSecondHookIfSecondHookReturnsFalse()
    {
        $runCalled = false;
        $runDispatchCalled = false;
        $runEndCalled = false;

        $phix = new Phix();
        $phix
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
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($runCalled);
        $this->assertTrue($runDispatchCalled);
        $this->assertFalse($runEndCalled);
    }

    /**
     * @covers Phix::_run
     */
    public function test_RunTriggersRunNoRoute()
    {
        $runNoRouteCalled = false;

        $phix = new Phix();
        $phix
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
     * @covers Phix::_run
     */
    public function test_RunProducesNoOutputForHead()
    {
        ob_start();
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/', function($phix) {
                echo 'foo';
            })
            ->requestUri('/')
            ->requestMethod('HEAD')
            ->run();

        $this->assertSame('', ob_get_clean());
    }

    /**
     * @covers Phix::_shutdown
     */
    public function test_ShutdownTriggersAllHooks()
    {
        $shutdownCalled = false;
        $shutdownEndCalled = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('shutdown', function() use (&$shutdownCalled) {
                $shutdownCalled = true;
            })
            ->hook('shutdown_end', function() use (&$shutdownEndCalled) {
                $shutdownEndCalled = true;
            })
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($shutdownCalled);
        $this->assertTrue($shutdownEndCalled);
    }

    /**
     * @covers Phix::_shutdown
     */
    public function test_ShutdownTriggersOnlyFirstHookIfFirstHookReturnsFalse()
    {
        $shutdownCalled = false;
        $shutdownEndCalled = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            // Hook shutdown event to prevent restoring of the error handler
            ->hook('shutdown', function() use (&$shutdownCalled) {
                $shutdownCalled = true;
                return false;
            })
            ->hook('shutdown_end', function() use (&$shutdownEndCalled) {
                $shutdownEndCalled = true;
            })
            ->get('/', function($phix) {
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($shutdownCalled);
        $this->assertFalse($shutdownEndCalled);
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
     * @covers Phix::response
     */
    public function testResponse()
    {
        $phix = new Phix();
        $phix->response('<foo/>');
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));
        $this->assertEquals('<foo/>', $phix->output());

        $phix = new Phix();
        $phix->response('<foo/>', 'html');
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));
        $this->assertEquals('<foo/>', $phix->output());

        $phix = new Phix();
        $phix->response('{}', 'json');
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $phix->headers()));
        $this->assertEquals('{}', $phix->output());

        $phix = new Phix();
        $phix->status(412);
        $phix->response(array('foo' => 'bar'), 'json');
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $phix->headers()));
        $this->assertEquals('{"status":"fail","data":{"foo":"bar"}}', $phix->output());

        $phix = new Phix();
        $phix->response(array('foo' => 'bar'), 'json');
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $phix->headers()));
        $this->assertEquals('{"status":"success","data":{"foo":"bar"}}', $phix->output());

        $phix = new Phix();
        $phix->response('<xml/>', 'xml');
        $this->assertTrue(in_array('Content-Type: text/xml;charset=utf-8', $phix->headers()));
        $this->assertEquals('<xml/>', $phix->output());

        $phix = new Phix();
        $phix->response(array('foo' => 'bar'), 'xml');
        $this->assertTrue(in_array('Content-Type: text/xml;charset=utf-8', $phix->headers()));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>success</status><data><foo>bar</foo></data></response>', $phix->output());

        $phix = new Phix();
        $phix->status(412);
        $phix->response(array('foo' => 'bar'), 'xml');
        $this->assertTrue(in_array('Content-Type: text/xml;charset=utf-8', $phix->headers()));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>fail</status><data><foo>bar</foo></data></response>', $phix->output());
    }

    /**
     * @covers Phix::response
     */
    public function testResponseThrowsExceptionOnInvalidFormat()
    {
        $this->setExpectedException('Exception', 'Invalid format "bogus"');
        $phix = new Phix();
        $phix->response('123', 'bogus');
    }

    /**
     * @covers Phix::hooks
     */
    public function testHooks()
    {
        $hooks = array(
            array('init', function() {}),
            array('run', function() {})
        );

        $phix = new Phix();
        $this->assertSame(array(), $phix->hooks());
        $phix->hooks($hooks);
        $this->assertArrayHasKey('init', $phix->hooks());
        $this->assertArrayHasKey('run', $phix->hooks());
        $ret = $phix->hooks(array(), true);
        $this->assertSame(array(), $phix->hooks());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::hook
     */
    public function testHook()
    {
        $phix = new Phix();
        $phix->hook('init', 'callback1');
        $this->assertArrayHasKey('init', $phix->hooks());
        $this->assertSame(array('init' => array(0 => 'callback1')), $phix->hooks());
        $ret = $phix->hook('init', 'callback2', 2);
        $this->assertSame(array('init' => array(0 => 'callback1', 2 => 'callback2')), $phix->hooks());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::hook
     */
    public function testHookThrowsExceptionOnDuplicateIndex()
    {
        $this->setExpectedException('Exception', 'There is already a hook registered at index "2"');
        $phix = new Phix();
        $phix->hook('init', 'callback1', 2);
        $phix->hook('init', 'callback2', 2);
    }

    /**
     * @covers Phix::unhook
     */
    public function testUnhook()
    {
        $phix = new Phix();
        $phix->hook('init', 'callback1');
        $phix->unhook();
        $this->assertSame(array(), $phix->hooks());
        
        $phix->hook('init', 'callback1');
        $phix->hook('setup', 'callback2');
        $phix->unhook('init');
        $this->assertSame(array('setup' => array(0 => 'callback2')), $phix->hooks());

        $phix->hook('setup', 'callback3');
        $ret = $phix->unhook('setup', 'callback3');
        $this->assertSame(array('setup' => array(0 => 'callback2')), $phix->hooks());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::trigger
     */
    public function testTrigger()
    {
        $called1 = false;
        $called2 = false;

        $phix = new Phix();

        $phix->hook('init', function() use (&$called1) {
            $called1 = true;
            return false;
        });
        $phix->hook('setup', function() use (&$called2) {
            $called2 = true;
            return true;
        });

        $ret = $phix->trigger('init');

        $this->assertTrue($called1);
        $this->assertFalse($called2);
        $this->assertFalse($ret);

        $ret = $phix->trigger('setup');

        $this->assertTrue($called2);
        $this->assertTrue($ret);
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
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->route(array('GET'), '/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testGetRouteAlsoAssignsHeadRoute()
    {
        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->route('GET', '/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->requestMethod('HEAD')
            ->run();

        $this->assertTrue($called);

        $called = false;

        $phix = new Phix();
        $phix
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
     * @covers Phix::head
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testSimpleHeadRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->head('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testSimpleGetRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers Phix::post
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testSimplePostRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $called = false;

        $phix = new Phix();
        $phix
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
     * @covers Phix::put
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testSimplePutRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $called = false;

        $phix = new Phix();
        $phix
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
     * @covers Phix::delete
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testSimpleDeleteRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $called = false;

        $phix = new Phix();
        $phix
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
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testEmptyRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testSingleSlashRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/', function() use(&$called) {
                $called = true;
            })
            ->requestUri('')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testNamedParameterRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test/:foo', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('bar', $phix->param('foo'));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testInlineWildcardRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test/foo*baz', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foobarbaz')
            ->run();

        $this->assertTrue($called);
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testWildcardRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test/*/*', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foo/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('foo', $phix->param(0));
        $this->assertEquals('bar', $phix->param(1));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testWildcardWithNamedParameterRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get(array('/test/*/*', array('param1', 'param2')), function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foo/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('foo', $phix->param('param1'));
        $this->assertEquals('bar', $phix->param('param2'));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testDoubleWildcardRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test/**', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/foo/bar')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('foo/bar', $phix->param(0));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testRegexpRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('^/test/(\d+)/foo', function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/123456/foo')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('123456', $phix->param(0));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testRegexpWithNamedParameterRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get(array('^/test/(\d+)/foo', array('bar')), function() use(&$called) {
                $called = true;
            })
            ->requestUri('/test/123456/foo')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('123456', $phix->param('bar'));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testRouteSetsDefaults()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get(array('^/test/((\d+)/)?foo', array('', 'bar')), function() use(&$called) {
                $called = true;
            }, array('bar' => 'baz'))
            ->requestUri('/test/foo')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('baz', $phix->param('bar'));

        $phix->reset();

        $phix
            ->requestUri('/test/1234/foo')
            ->pathInfo(null)
            ->run();

        $this->assertEquals('1234', $phix->param('bar'));
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     */
    public function testRouteRouteCallbackReturningFalseDontMatchRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            }, array(), function() {
                return false;
            })
            ->requestUri('/test')
            ->run();

        $this->assertFalse($called);
        $this->assertEquals(404, $phix->status());
    }

    /**
     * @covers Phix::get
     * @covers Phix::route
     * @covers Phix::_route
     * @covers Phix::defaultRouter
     * @covers Phix::_dispatch
     * @group 123
     */
    public function testRouteRouteCallbackReturningArrayPopulatesToParams()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->get('/test', function() use(&$called) {
                $called = true;
            }, array(), function() {
                return array('foo' => 'bar');
            })
            ->requestUri('/test')
            ->run();

        $this->assertTrue($called);
        $this->assertEquals('bar', $phix->param('foo'));
    }

    /**
     * @covers Phix::routes
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

        $phix = new Phix();
        $this->assertSame(array(), $phix->routes());
        $phix->routes($routes);
        $this->assertArrayHasKey('GET', $phix->routes());
        $ret = $phix->routes(array(), true);
        $this->assertSame(array(), $phix->routes());
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
     * @covers Phix::defaultDispatcher
     */
    public function testDefaultDispatcher()
    {
        $phix = new Phix();
        Phix::defaultDispatcher($phix, function($phix) {
            $phix->output('foo');
        });
        $this->assertEquals('foo', $phix->output());
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
     * @covers Phix::reg
     */
    public function testReg()
    {
        $phix = new Phix();
        $this->assertNull($phix->reg('foo'));
        $phix->reg('foo', 'bar');
        $this->assertEquals('bar', $phix->reg('foo'));
        $ret = $phix->reg('foo', function() {
            return 'baz';
        });
        $this->assertEquals('baz', $phix->reg('foo'));
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::regs
     */
    public function testRegs()
    {
        $regs = array(
            'foo' => 'bar',
            'bar' => 'baz',
        );

        $phix = new Phix();
        $this->assertSame(array(), $phix->regs());
        $phix->regs($regs);
        $this->assertEquals($regs, $phix->regs());
        $phix->reg('ping', 'pong');
        $this->assertEquals($regs + array('ping' => 'pong'), $phix->regs());
        $ret = $phix->regs($regs, true);
        $this->assertEquals($regs, $phix->regs());
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
     * @covers Phix::redirected
     */
    public function testRedirect()
    {
        $phix = new Phix(array(
            'baseUrl' => array('/foo'),
            'serverUrl' => array('http://example.com')
        ));
        $this->assertFalse($phix->redirected());

        $phix->redirect(array('bar'));
        $this->assertSame(302, $phix->status());
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));
        $this->assertTrue($phix->redirected());
        
        $phix->reset();
        $this->assertFalse($phix->redirected());

        $phix->redirect('/foo/bar', 301);
        $this->assertSame(301, $phix->status());
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));
        $this->assertTrue($phix->redirected());

        $phix->redirect('/bar');
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));
        $this->assertTrue($phix->redirected());

        $phix->reset();
        $this->assertFalse($phix->redirected());

        $ret = $phix->redirect(function() {
            return '/foo/bar';
        });
        $this->assertTrue(in_array('Location: http://example.com/foo/bar', $phix->headers()));
        $this->assertTrue($phix->redirected());
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
     * @covers Phix::header
     */
    public function testHeader()
    {
        $phix = new Phix();

        $ret = $phix->header(function($phix) {
            return 'Location: http://example.com';
        });
        $this->assertTrue(in_array('Location: http://example.com', $phix->headers()));
        $this->assertEquals($ret, $phix);

        $phix->reset();

        $phix->header('Location: http://localhost');
        $this->assertTrue(in_array('Location: http://localhost', $phix->headers()));

        $phix->header('Location: http://127.0.0.1', true);
        $this->assertTrue(in_array('Location: http://127.0.0.1', $phix->headers()));
        $this->assertFalse(in_array('Location: http://localhost', $phix->headers()));
    }

    /**
     * @covers Phix::headers
     */
    public function testHeaders()
    {
        $phix = new Phix();

        $phix->header('X-Foo: bar');

        $phix->headers(array(
            'X-Bar: baz',
            'Location: http://localhost',
            array('Location: http://127.0.0.1', true)
        ), true);

        $this->assertFalse(in_array('X-Foo: bar', $phix->headers()));
        $this->assertTrue(in_array('X-Bar: baz', $phix->headers()));

        $this->assertTrue(in_array('Location: http://127.0.0.1', $phix->headers()));
        $this->assertFalse(in_array('Location: http://localhost', $phix->headers()));
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
     * @covers Phix::defaultRenderer
     */
    public function testDefaultRenderer()
    {
        $phix = new Phix();
        $phix->viewsDir(dirname(__FILE__) . '/_files/views');

        $content = Phix::defaultRenderer($phix, function($phix, array $vars, $format) {
            return 'foo';
        }, array(), 'html');
        $this->assertEquals('foo', $content);

        $content = Phix::defaultRenderer($phix, 'view', array('controller' => 'foo'), 'html');
        $this->assertEquals('foo', $content);

        $content = Phix::defaultRenderer($phix, 'Just a string', array(), 'html');
        $this->assertEquals('Just a string', $content);

        $content = Phix::defaultRenderer($phix, 'Just a %s', array('string'), 'html');
        $this->assertEquals('Just a string', $content);
    }

    /**
     * @covers Phix::render
     */
    public function testRender()
    {
        $phix = new Phix();
        $phix->viewsDir(dirname(__FILE__) . '/_files/views');

        $content = $phix->render(function($phix, array $vars, $format) {
            return 'foo';
        }, array(), 'html');
        $this->assertEquals('foo', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));

        $phix->reset();

        $phix->render('Just a string', array(), 'html');
        $this->assertEquals('Just a string', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));

        $phix->reset();

        $phix->render('view', array('controller' => 'foo'), 'html');
        $this->assertEquals('foo', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));

        $phix->reset();

        $phix->render('view', array('controller' => 'foo'));
        $this->assertEquals('foo', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));

        $phix->reset();

        $phix->param('format', 'html');
        $phix->render('view', array('controller' => 'foo'));
        $this->assertEquals('foo', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));

        $phix->reset();

        $phix->layout('layout');
        $phix->render('view', array('controller' => 'foo'));
        $this->assertStringStartsWith('<!DOCTYPE html>', $phix->output());
        $this->assertRegExp('/foo<\/body>/', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));
        
        $phix->reset();

        $phix->render('view', array('controller' => 'foo'), 'json');
        $this->assertEquals(json_encode(array('status' => 'success', 'data' => array('controller' => 'foo'))), $phix->output());
        $this->assertTrue(in_array('Content-Type: application/json;charset=utf-8', $phix->headers()));
    }

    /**
     * @covers Phix::render
     */
    public function testRenderThrowsExceptionOnInvalidFormat()
    {
        $this->setExpectedException('Exception', 'Invalid format "bogus"');
        $phix = new Phix();
        $phix->render('123', array(), 'bogus');
    }

    /**
     * @covers Phix::viewFilename
     */
    public function testViewFilename()
    {
        $phix = new Phix();
        $phix->viewsDir(dirname(__FILE__) . '/_files/views');

        $this->assertFalse($phix->viewFilename('view', 'bogus'));
        $this->assertFalse($phix->viewFilename('bogus', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.html.php', $phix->viewFilename('view', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.html.php', $phix->viewFilename('view.html.php', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.html.php', $phix->viewFilename('view.php', 'html'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'view.json.php', $phix->viewFilename('view', 'json'));
        $this->assertEquals(dirname(__FILE__) . '/_files/views' . DIRECTORY_SEPARATOR . 'bar.xml.php', $phix->viewFilename('bar', 'xml'));
    }

    /**
     * @covers Phix::currentFormat
     */
    public function testCurrentFormat()
    {
        $phix = new Phix();
        $this->assertEquals('html', $phix->currentFormat());
        $phix->param('format', 'json');
        $this->assertSame('json', $phix->currentFormat());
        $phix->param('format', 'invalid');
        $this->assertSame('html', $phix->currentFormat());
    }

    /**
     * @covers Phix::defaultFormat
     */
    public function testDefaultFormat()
    {
        $phix = new Phix();
        $this->assertEquals('html', $phix->defaultFormat());
        $phix->defaultFormat('foo');
        $this->assertSame('foo', $phix->defaultFormat());
        $ret = $phix->defaultFormat(function() {
            return 'bar';
        });
        $this->assertSame('bar', $phix->defaultFormat());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::format
     */
    public function testFormat()
    {
        $phix = new Phix();
        $this->assertArrayHasKey('view', $phix->format('html'));
        $this->assertArrayHasKey('contenttype', $phix->format('html'));
        $this->assertArrayHasKey('error', $phix->format('html'));

        $this->assertArrayHasKey('view', $phix->format('json'));
        $this->assertArrayHasKey('contenttype', $phix->format('json'));
        $this->assertArrayHasKey('error', $phix->format('json'));

        $this->assertArrayHasKey('view', $phix->format('xml'));
        $this->assertArrayHasKey('contenttype', $phix->format('xml'));
        $this->assertArrayHasKey('error', $phix->format('xml'));

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


        $this->assertNull($phix->format('foo'));
        $phix->format('foo', $foo);
        $this->assertSame($foo, $phix->format('foo'));

        $phix = new Phix();

        $phix->format('foo', function() use ($foo) {
            return $foo;
        });

        $this->assertSame($foo, $phix->format('foo'));


        $ret = $phix->format('foo', null);
        $this->assertArrayNotHasKey('foo', $phix->formats());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::format
     */
    public function testFormatThrowsExceptionIfDefaultFormatIsRemoved()
    {
        $this->setExpectedException('Exception', 'Removing the default format is not allowed');
        $phix = new Phix();
        $phix->format('html', null);
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
     * @covers Phix::defaultFormatHtmlResponse
     */
    public function testDefaultFormatHtmlResponse()
    {
        $response = Phix::defaultFormatHtmlResponse(new Phix(), 200, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
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

        $response = Phix::defaultFormatHtmlResponse(new Phix(), 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
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

    /**
     * @covers Phix::defaultFormatJsonResponse
     */
    public function testDefaultFormatJsonResponse()
    {
        $response = Phix::defaultFormatJsonResponse(new Phix(), 200, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('{"status":"success","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}}', $response);

        $response = Phix::defaultFormatJsonResponse(new Phix(), 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('{"status":"fail","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}}', $response);
    }

    /**
     * @covers Phix::defaultFormatJsonResponse
     */
    public function testDefaultFormatJsonResponseHandlesJSONPCallback()
    {
        $_GET = array(
            'callback' => 'jsonp1234'
        );
        $response = Phix::defaultFormatJsonResponse(new Phix(), 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('jsonp1234({"status":"fail","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}})', $response);
    }

    /**
     * @covers Phix::defaultFormatJsonResponse
     */
    public function testDefaultFormatJsonResponseIgnoresInvalidJSONPCallback()
    {
        $_GET = array(
            'callback' => '1234'
        );
        $response = Phix::defaultFormatJsonResponse(new Phix(), 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('{"status":"fail","data":{"foo":"bar","bar":{"key1":"val1"},"baz":["val2","val3"]}}', $response);
    }

    /**
     * @covers Phix::defaultFormatXmlResponse
     * @covers Phix::_arrayToXml
     */
    public function testDefaultFormatXmlResponse()
    {
        $response = Phix::defaultFormatXmlResponse(new Phix(), 200, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>success</status><data><foo>bar</foo><bar><key1>val1</key1></bar><baz>val2</baz><baz>val3</baz></data></response>', $response);

        $response = Phix::defaultFormatXmlResponse(new Phix(), 412, array('foo' => 'bar', 'bar' => array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>fail</status><data><foo>bar</foo><bar><key1>val1</key1></bar><baz>val2</baz><baz>val3</baz></data></response>', $response);

        $response = Phix::defaultFormatXmlResponse(new Phix(), 200, (object) array('foo' => 'bar', 'bar' => (object) array('key1' => 'val1'), 'baz' => array('val2', 'val3')));
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response><status>success</status><data><foo>bar</foo><bar><key1>val1</key1></bar><baz>val2</baz><baz>val3</baz></data></response>', $response);
    }

    /**
     * @covers Phix::defaultFormatJsonUnserialize
     */
    public function testDefaultFormatJsonUnserialize()
    {
        $obj = new stdClass();
        $obj->bar = array('baz', 'test');
        $json = json_encode(array('foo' => 'bar', 'obj' => $obj));

        $arr = Phix::defaultFormatJsonUnserialize(new Phix(), $json);

        $expected = array('foo' => 'bar', 'obj' => array('bar' =>  array('baz', 'test')));
        $this->assertSame($expected, $arr);
    }

    /**
     * @covers Phix::defaultFormatXmlUnserialize
     * @covers Phix::_xmlToArray
     */
    public function testDefaultFormatXmlUnserialize()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<data>' .
               '<foo>bar</foo>' .
               '<obj><bar>baz</bar><bar>test</bar></obj>' .
               '</data>';

        $arr = Phix::defaultFormatXmlUnserialize(new Phix(), $xml);

        $expected = array('foo' => 'bar', 'obj' => array('bar' => array('baz', 'test')));
        $this->assertSame($expected, $arr);
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
     * @covers Phix::requestRawBody
     */
    public function testRequestRawBody()
    {
        $phix = new Phix();
        $this->assertFalse($phix->requestRawBody());
        $phix->requestRawBody('foo');
        $this->assertSame('foo', $phix->requestRawBody());
        $ret = $phix->requestRawBody(function() {
            return 'bar';
        });
        $this->assertSame('bar', $phix->requestRawBody());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::requestMethod
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
        $this->assertArrayHasKey('set', $_GET);
        $this->assertArrayHasKey('unset', $_GET);
        $this->assertEquals('this', $_GET['set']);
        $this->assertEquals('that', $_GET['unset']);

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
        $this->assertEquals('', $phix->baseUrl());

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
        $this->assertEquals('/mycontroller/myaction', $phix->pathInfo());

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

    /**
     * @covers Phix::notFound
     */
    public function testNotFoundWithHook()
    {
        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->hook('not_found', function() use(&$called) {
                $called = true;
                return false;
            })
            ->notFound();

        $this->assertTrue($called);
        $this->assertNotEquals(404, $phix->status());
        $this->assertFalse($phix->stopped());
    }

    /**
     * @covers Phix::notFound
     */
    public function testNotFoundSetsStatusAndMessage()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->requestUri('/foo');

        $phix->notFound();

        $this->assertEquals(404, $phix->status());
        $this->assertTrue($phix->stopped());

        $phix->reset();

        $ret = $phix->notFound(function($phix) {
            return 'Foo message';
        });
        $this->assertRegExp('/Foo message/', $phix->output());
        $this->assertEquals($ret, $phix);
    }

    /**
     * @covers Phix::error
     */
    public function testErrorWithHook()
    {
        $called = false;

        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->hook('error', function($phix, $params) use(&$called) {
                $called = true;
                $params['status'] = 501;
                return false;
            })
            ->error(500);

        $this->assertTrue($called);
        $this->assertNotEquals(501, $phix->status());
        $this->assertFalse($phix->stopped());
    }

    /**
     * @covers Phix::error
     */
    public function testErrorWithHookManipulatesParams()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->hook('error', function($phix, $params) use(&$called) {
                $params['status'] = 501;
                $params['msg'] = 'Nope, not implemented';
            })
            ->error(500);

        $this->assertEquals(501, $phix->status());
        $this->assertRegExp('/Nope, not implemented/', $phix->output());
    }

    /**
     * @covers Phix::error
     */
    public function testErrorWithCallbacks()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->error(function() {
                return 501;
            }, function() {
                return 'Nope, not implemented';
            }, function() {
                return 'html';
            });

        $this->assertEquals(501, $phix->status());
        $this->assertRegExp('/Nope, not implemented/', $phix->output());
        $this->assertTrue(in_array('Content-Type: text/html;charset=utf-8', $phix->headers()));
    }

    /**
     * @covers Phix::error
     */
    public function testErrorResetsStatusTo500ForInvalidStatus()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->error(-1);

        $this->assertEquals(500, $phix->status());
    }

    /**
     * @covers Phix::error
     */
    public function testErrorResetsFormatToHtmlForInvalidFormat()
    {
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->param('format', 'pdf')
            ->error(500);

        $this->assertRegExp('/<html>/', $phix->output());
    }

    /**
     * @covers Phix::error
     */
    public function testErrorThrowsExceptionForInvalidPassedFormat()
    {
        $this->setExpectedException('Exception', 'Invalid format "pdf"');
        $phix = new Phix();
        $phix
            ->autoFlush(false)
            ->error(500, null, 'pdf');
    }
}
