<?php
/**
 * Phix
 *
 * LICENSE
 *
 * This source file is subject to the BSD license that is available
 * through the world-wide-web at this URL:
 * https://github.com/jsor/phix/blob/master/LICENSE
 *
 * @package    Phix
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2010-Present Jan Sorgalla
 * @license    https://github.com/jsor/phix/blob/master/LICENSE The BSD License
 */

namespace Phix;

/**
 * @package    Phix
 * @subpackage UnitTests
 * @author     Jan Sorgalla
 * @copyright  Copyright (c) 2010-Present Jan Sorgalla
 * @license    https://github.com/jsor/phix/blob/master/LICENSE The BSD License
 */
class AppTestCaseTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $appConfig = array(
            'requestUri' => array(
                '/'
            ),
            'serverUrl' => array(
                'http://localhost'
            ),
            'layout' => array(
                'layout'
            ),
            'viewsDir' => array(
                dirname(__FILE__) . '/_files/views',
            ),
            'routes' => array(
                array(
                    array(
                        'GET',
                        '/',
                        function($app) {
                            $app->render('view', array('controller' => 'index'));
                        }
                    ),
                    array(
                        array('GET', 'POST'),
                        '/foo',
                        function($app) {
                            $app->render('view', array('controller' => 'foo'));
                        }
                    ),
                    array(
                        'GET',
                        '/bar',
                        function($app) {
                            $app->render('bar');
                        }
                    ),
                    array(
                        'GET',
                        '/invalid-xml',
                        function($app) {
                            $app->response('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>vvdv', 'xml');
                        }
                    ),
                )
            )
        );

        $this->testCase = new AppTestCase();
        $this->testCase->setApp(new App($appConfig));
    }

    public function tearDown()
    {
        $this->testCase = null;
    }

    /**
     * @covers \Phix\AppTestCase::setApp
     */
    public function testSetAppAcceptsAppInstance()
    {
        $testCase = new AppTestCase();
        
        $app = new App();
        $testCase->setApp(new App());
        $this->assertEquals($testCase->getApp(), $app);
    }

    /**
     * @covers \Phix\AppTestCase::getApp
     */
    public function testGetAppReturnsDefaultInstanceIfNoneAssigned()
    {
        $testCase = new AppTestCase();
        $this->assertEquals($testCase->getApp(), new App());
    }

    /**
     * @covers \Phix\AppTestCase::runApp
     */
    public function testRunShouldDispatchSpecifiedUrlAndRequestMethod()
    {
        $this->testCase->runApp('/foo', 'POST');
        $this->assertContains('foo', $this->testCase->getApp()->output());
        $this->assertContains('POST', $this->testCase->getApp()->requestMethod());
    }

    /**
     * @covers \Phix\AppTestCase::assertXpath
     * @covers \Phix\AppTestCase::assertXpathContentContains
     */
    public function testAssertXpathShouldDoNothingForValidResponseContent()
    {
        $this->testCase->runApp('/bar');
        $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bar')]");
        $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'baz')]");
        $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bat')]");
        $this->testCase->assertXpathContentContains("//legend[contains(@class, 'bat')]", "La di da");

        $this->testCase->runApp('/bar?format=xml');
        $this->testCase->assertXpath("//foo[contains(@bar, 'baz')]");
        $this->testCase->assertXpathContentContains("//foo[contains(@bar, 'baz')]", "La di da");
    }

    /**
     * @covers \Phix\AppTestCase::assertXpath
     * @covers \Phix\AppTestCase::assertXpathContentContains
     */
    public function testAssertionsShouldIncreasePhpUnitAssertionCounter()
    {
        $this->testAssertXpathShouldDoNothingForValidResponseContent();
        $this->assertTrue(0 < $this->testCase->getNumAssertions());
        $this->assertTrue(4 <= $this->testCase->getNumAssertions());
    }

    /**
     * @covers \Phix\AppTestCase::assertXpath
     * @covers \Phix\AppTestCase::assertXpathContentContains
     */
    public function testAssertXpathShouldThrowExceptionsForInValidResponseContent()
    {
        $this->testCase->runApp('/bar');

        try {
            $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bogus')]");
            $this->fail("Invalid assertions should throw exceptions; assertion against //div[@id='foo']//legend[contains(@class, 'bogus')] failed");
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }

        try {
            $this->testCase->assertXpathContentContains("//legend[contains(@class, 'bat')]", 'La do da');
            $this->fail("Invalid assertions should throw exceptions; assertion against //legend[contains(@class, 'bat')] failed");
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers \Phix\AppTestCase::assertXpath
     */
    public function testAssertXpathShouldThrowExceptionsForInValidResponseXml()
    {
        $this->setExpectedException('Exception', 'Error parsing document (type == xml)');
        $this->testCase->runApp('/invalid-xml');
        try{
            $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bogus')]");
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers \Phix\AppTestCase::assertXpathContentContains
     */
    public function testAssertXpathContentContainsShouldThrowExceptionsForInValidResponseXml()
    {
        $this->setExpectedException('Exception', 'Error parsing document (type == xml)');
        $this->testCase->runApp('/invalid-xml');
        try {
            $this->testCase->assertXpathContentContains("//legend[contains(@class, 'bat')]", 'La do da');
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers \Phix\AppTestCase::assertRedirect
     * @covers \Phix\AppTestCase::assertRedirectTo
     */
    public function testRedirectAssertionsShouldDoNothingForValidAssertions()
    {
        $this->testCase->getApp()->redirect('/foo');
        $this->testCase->assertRedirect();
        $this->testCase->assertRedirectTo('http://localhost/foo');
    }

    /**
     * @covers \Phix\AppTestCase::assertRedirect
     * @covers \Phix\AppTestCase::assertRedirectTo
     */
    public function testRedirectAssertionShouldThrowExceptionForInvalidComparison()
    {
        $this->testCase->getApp()->reset();

        try {
            $this->testCase->assertRedirect();
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }

        $this->testCase->getApp()->redirect('/bar');

        try {
            $this->testCase->assertRedirectTo('http://localhost/foo');
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers \Phix\AppTestCase::assertStatus
     */
    public function testStatusAssertionShouldDoNothingForValidComparison()
    {
        $this->testCase->getApp()->reset();
        $this->testCase->assertStatus(200);
        $this->testCase->getApp()->status(500);
        $this->testCase->assertStatus(500);
    }

    /**
     * @covers \Phix\AppTestCase::assertStatus
     */
    public function testStatusAssertionShouldThrowExceptionForInvalidComparison()
    {
        $this->testCase->getApp()->reset();

        try {
            $this->testCase->assertStatus(500);
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }

        $this->testCase->getApp()->status(500);

        try {
            $this->testCase->assertStatus(200);
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers \Phix\AppTestCase::assertHeader
     * @covers \Phix\AppTestCase::assertHeaderContains
     * @covers \Phix\AppTestCase::assertHeaderRegex
     */
    public function testHeaderAssertionShouldDoNothingForValidComparison()
    {
        $this->testCase->getApp()->reset();

        $this->testCase->getApp()->header('Content-Type: x-application/my-foo');
        $this->testCase->assertHeader('Content-Type');
        $this->testCase->assertHeaderContains('Content-Type', 'my-foo');
        $this->testCase->assertHeaderRegex('Content-Type', '#^[a-z-]+/[a-z-]+$#i');
    }

    /**
     * @covers \Phix\AppTestCase::assertHeader
     * @covers \Phix\AppTestCase::assertHeaderContains
     * @covers \Phix\AppTestCase::assertHeaderRegex
     */
    public function testHeaderAssertionShouldThrowExceptionForInvalidComparison()
    {
        $this->testCase->getApp()->reset();
        $this->testCase->getApp()->header('Content-Type: x-application/my-foo');

        try {
            $this->testCase->assertHeader('X-Bogus');
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
        try {
            $this->testCase->assertHeaderContains('Content-Type', 'my-bar');
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
        try {
            $this->testCase->assertHeaderRegex('Content-Type', '#^\d+#i');
            $this->fail();
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }
}
