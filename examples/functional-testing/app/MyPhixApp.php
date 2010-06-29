<?php

class MyPhixApp extends Phix
{
    public function __construct($config = null)
    {
        $this
            ->viewsDir(__DIR__ . '/views')
            ->layout('layout')
            ->reg('site_title', 'My Application')
            ->get('/', function($phix) {
                $phix->render('home');
            })
            ->get('/unreachable', function($phix) {
                $phix->redirect('/');
            })
            ->get('/notfound', function($phix) {
                $phix->notFound('Ooops. The URL ' . $phix->escape($phix->requestUri()) . ' is not there.');
            });

        parent::__construct($config);
    }
}