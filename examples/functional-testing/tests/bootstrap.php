<?php

// Define filters for clover report
PHPUnit_Util_Filter::addDirectoryToWhitelist(__DIR__ . '/../app');

PHPUnit_Util_Filter::addDirectoryToFilter(__DIR__);
PHPUnit_Util_Filter::addDirectoryToFilter(__DIR__ . '/../../../src');

set_include_path(implode(PATH_SEPARATOR, array(
    __DIR__ . '/../app',
    __DIR__ . '/../../../src',
    get_include_path()
)));

function phixTestClassLoader($className)
{
    if (false !== strripos($className, '\\')) {
        $replace = '\\';
    } else {
        $replace = '_';
    }

    require str_replace($replace, DIRECTORY_SEPARATOR, $className) . '.php';

    return true;
}

spl_autoload_register('phixTestClassLoader', true, true);
