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
class PhixTestCaseTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $phixConfig = array(
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
                        function($phix) {
                            $phix->render('view', array('controller' => 'index'));
                        }
                    ),
                    array(
                        array('GET', 'POST'),
                        '/foo',
                        function($phix) {
                            $phix->render('view', array('controller' => 'foo'));
                        }
                    ),
                    array(
                        'GET',
                        '/bar',
                        function($phix) {
                            $phix->render('bar');
                        }
                    ),
                    array(
                        'GET',
                        '/invalid-xml',
                        function($phix) {
                            $phix->response('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>vvdv', 'xml');
                        }
                    ),
                )
            )
        );

        $this->testCase = new PhixTestCase();
        $this->testCase->setPhix(new Phix($phixConfig));
    }

    public function tearDown()
    {
        $this->testCase = null;
    }

    /**
     * @covers PhixTestCase::setPhix
     */
    public function testSetPhixAcceptsPhixInstance()
    {
        $testCase = new PhixTestCase();
        
        $phix = new Phix();
        $testCase->setPhix(new Phix());
        $this->assertEquals($testCase->getPhix(), $phix);
    }

    /**
     * @covers PhixTestCase::getPhix
     */
    public function testGetPhixReturnsDefaultInstanceIfNoneAssigned()
    {
        $testCase = new PhixTestCase();
        $this->assertEquals($testCase->getPhix(), new Phix());
    }

    /**
     * @covers PhixTestCase::runPhix
     */
    public function testRunShouldDispatchSpecifiedUrlAndRequestMethod()
    {
        $this->testCase->runPhix('/foo', 'POST');
        $this->assertContains('foo', $this->testCase->getPhix()->output());
        $this->assertContains('POST', $this->testCase->getPhix()->requestMethod());
    }

    /**
     * @covers PhixTestCase::assertXpath
     * @covers PhixTestCase::assertXpathContentContains
     */
    public function testAssertXpathShouldDoNothingForValidResponseContent()
    {
        $this->testCase->runPhix('/bar');
        $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bar')]");
        $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'baz')]");
        $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bat')]");
        $this->testCase->assertXpathContentContains("//legend[contains(@class, 'bat')]", "La di da");

        $this->testCase->runPhix('/bar?format=xml');
        $this->testCase->assertXpath("//foo[contains(@bar, 'baz')]");
        $this->testCase->assertXpathContentContains("//foo[contains(@bar, 'baz')]", "La di da");
    }

    /**
     * @covers PhixTestCase::assertXpath
     * @covers PhixTestCase::assertXpathContentContains
     */
    public function testAssertionsShouldIncreasePhpUnitAssertionCounter()
    {
        $this->testAssertXpathShouldDoNothingForValidResponseContent();
        $this->assertTrue(0 < $this->testCase->getNumAssertions());
        $this->assertTrue(4 <= $this->testCase->getNumAssertions());
    }

    /**
     * @covers PhixTestCase::assertXpath
     * @covers PhixTestCase::assertXpathContentContains
     */
    public function testAssertXpathShouldThrowExceptionsForInValidResponseContent()
    {
        $this->testCase->runPhix('/bar');

        try {
            $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bogus')]");
            $this->fail("Invalid assertions should throw exceptions; assertion against //div[@id='foo']//legend[contains(@class, 'bogus')] failed");
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }

        try {
            $this->testCase->assertXpathContentContains("//legend[contains(@class, 'bat')]", 'La do da');
            $this->fail("Invalid assertions should throw exceptions; assertion against //legend[contains(@class, 'bat')] failed");
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers PhixTestCase::assertXpath
     */
    public function testAssertXpathShouldThrowExceptionsForInValidResponseXml()
    {
        $this->setExpectedException('Exception', 'Error parsing document (type == xml)');
        $this->testCase->runPhix('/invalid-xml');
        try{
            $this->testCase->assertXpath("//div[@id='foo']//legend[contains(@class, 'bogus')]");
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers PhixTestCase::assertXpathContentContains
     */
    public function testAssertXpathContentContainsShouldThrowExceptionsForInValidResponseXml()
    {
        $this->setExpectedException('Exception', 'Error parsing document (type == xml)');
        $this->testCase->runPhix('/invalid-xml');
        try {
            $this->testCase->assertXpathContentContains("//legend[contains(@class, 'bat')]", 'La do da');
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers PhixTestCase::assertRedirect
     * @covers PhixTestCase::assertRedirectTo
     */
    public function testRedirectAssertionsShouldDoNothingForValidAssertions()
    {
        $this->testCase->getPhix()->redirect('/foo');
        $this->testCase->assertRedirect();
        $this->testCase->assertRedirectTo('http://localhost/foo');
    }

    /**
     * @covers PhixTestCase::assertRedirect
     * @covers PhixTestCase::assertRedirectTo
     */
    public function testRedirectAssertionShouldThrowExceptionForInvalidComparison()
    {
        $this->testCase->getPhix()->reset();

        try {
            $this->testCase->assertRedirect();
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }

        $this->testCase->getPhix()->redirect('/bar');

        try {
            $this->testCase->assertRedirectTo('http://localhost/foo');
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers PhixTestCase::assertStatus
     */
    public function testStatusAssertionShouldDoNothingForValidComparison()
    {
        $this->testCase->getPhix()->reset();
        $this->testCase->assertStatus(200);
        $this->testCase->getPhix()->status(500);
        $this->testCase->assertStatus(500);
    }

    /**
     * @covers PhixTestCase::assertStatus
     */
    public function testStatusAssertionShouldThrowExceptionForInvalidComparison()
    {
        $this->testCase->getPhix()->reset();

        try {
            $this->testCase->assertStatus(500);
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }

        $this->testCase->getPhix()->status(500);

        try {
            $this->testCase->assertStatus(200);
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }

    /**
     * @covers PhixTestCase::assertHeader
     * @covers PhixTestCase::assertHeaderContains
     * @covers PhixTestCase::assertHeaderRegex
     */
    public function testHeaderAssertionShouldDoNothingForValidComparison()
    {
        $this->testCase->getPhix()->reset();

        $this->testCase->getPhix()->header('Content-Type: x-application/my-foo');
        $this->testCase->assertHeader('Content-Type');
        $this->testCase->assertHeaderContains('Content-Type', 'my-foo');
        $this->testCase->assertHeaderRegex('Content-Type', '#^[a-z-]+/[a-z-]+$#i');
    }

    /**
     * @covers PhixTestCase::assertHeader
     * @covers PhixTestCase::assertHeaderContains
     * @covers PhixTestCase::assertHeaderRegex
     */
    public function testHeaderAssertionShouldThrowExceptionForInvalidComparison()
    {
        $this->testCase->getPhix()->reset();
        $this->testCase->getPhix()->header('Content-Type: x-application/my-foo');

        try {
            $this->testCase->assertHeader('X-Bogus');
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
        try {
            $this->testCase->assertHeaderContains('Content-Type', 'my-bar');
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
        try {
            $this->testCase->assertHeaderRegex('Content-Type', '#^\d+#i');
            $this->fail();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
        }
    }
}
