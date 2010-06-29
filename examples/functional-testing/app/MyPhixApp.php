<?php

namespace app;

class MyPhixApp extends \Phix\App
{
    public function __construct($config = null)
    {
        $this
            ->viewsDir(__DIR__ . '/views')
            ->layout('layout')
            ->reg('site_title', 'My Application')
            ->get('/', function($app) {
                $app->render('home');
            })
            ->get('/unreachable', function($app) {
                $app->redirect('/');
            })
            ->get('/notfound', function($app) {
                $app->notFound('Ooops. The URL ' . $app->escape($app->requestUri()) . ' is not there.');
            });

        parent::__construct($config);
    }
}