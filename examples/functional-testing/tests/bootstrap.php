<?php

set_include_path(implode(PATH_SEPARATOR, array(
    __DIR__ . '/../../../src',
    get_include_path()
)));

spl_autoload_register(function($className) {
    if (strpos($className, 'PHPUnit_') === false && strpos($className, 'Phix\\') === false) {
        return;
    }

    if (false !== strripos($className, '\\')) {
        $replace = '\\';
    } else {
        $replace = '_';
    }

    require str_replace($replace, DIRECTORY_SEPARATOR, $className) . '.php';
}, true, true);
