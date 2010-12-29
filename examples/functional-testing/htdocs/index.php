<?php

set_include_path(implode(PATH_SEPARATOR, array(
    __DIR__ . '/../../../src',
    get_include_path()
)));

spl_autoload_register(function($className) {
    if (strpos($className, 'Phix\\') === false) {
        return;
    }

    require str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
}, true, true);

include_once __DIR__ . '/../app/MyPhixApp.php';

MyPhixApp::instance()->run();
