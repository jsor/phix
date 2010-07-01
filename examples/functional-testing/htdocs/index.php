<?php

set_include_path(implode(PATH_SEPARATOR, array(
    __DIR__ . '/..',
    __DIR__ . '/../../../src',
    get_include_path()
)));

spl_autoload_register(function($className) {
    if (false !== strripos($className, '\\')) {
        $replace = '\\';
    } else {
        $replace = '_';
    }

    require str_replace($replace, DIRECTORY_SEPARATOR, $className) . '.php';

    return true;
}, true, true);

\app\MyPhixApp::instance()->run();
