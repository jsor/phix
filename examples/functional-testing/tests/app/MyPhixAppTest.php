<?php

include_once __DIR__ . '/../../app/MyPhixApp.php';

class MyPhixAppTest extends \Phix\AppTestCase
{
    public function setUp()
    {
        $this->setApp(new MyPhixApp());
    }

    public function testHomeViewContainsH1Tag()
    {
        $this->runApp('/');

        $this->assertXpath('//h1');
    }

    public function testHomeViewContainsGreeting()
    {
        $this->runApp('/');

        $this->assertXpathContentContains('//p', 'Welcome to my test application.');
    }

    public function testHomeReturnsJsonIfFormatParamIsSet()
    {
        $this->runApp('/?format=json');

        $this->assertHeaderContains('Content-Type', 'application/json');
    }

    public function testUnreachableRedirectsToHome()
    {
        $this->runApp('/unreachable');

        $this->assertRedirectTo('/');
    }

    public function testNotFoundShowsError()
    {
        $this->runApp('/notfound');

        $this->assertStatus(404);
        $this->assertRegexp('/Ooops. The URL (.)+ is not there./', $this->_app->output());
    }
}