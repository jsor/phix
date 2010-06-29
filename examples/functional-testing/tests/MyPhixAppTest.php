<?php

class MyPhixAppTest extends PhixTestCase
{
    public function setUp()
    {
        $this->setPhix(new MyPhixApp());
    }

    public function testHomeViewContainsH1Tag()
    {
        $this->runPhix('/');

        $this->assertXpath('//h1');
    }

    public function testHomeViewContainsGreeting()
    {
        $this->runPhix('/');

        $this->assertXpathContentContains('//p', 'Welcome to my test application.');
    }

    public function testHomeReturnsJsonIfFormatParamIsSet()
    {
        $this->runPhix('/?format=json');

        $this->assertHeaderContains('Content-Type', 'application/json');
    }

    public function testUnreachableRedirectsToHome()
    {
        $this->runPhix('/unreachable');

        $this->assertRedirectTo('/');
    }

    public function testNotFoundShowsError()
    {
        $this->runPhix('/notfound');

        $this->assertStatus(404);
        $this->assertRegexp('/Ooops. The URL (.)+ is not there./', $this->_phix->output());
    }
}