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

// Get base and application path
$rootPath = dirname(__DIR__);

// Set include path
set_include_path(implode(PATH_SEPARATOR, array(
    $rootPath . '/tests',
    $rootPath . '/src',
    get_include_path()
)));

// Setup autoloading
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

// Define filters for clover report
PHP_CodeCoverage_Filter::getInstance()->addDirectoryToWhitelist($rootPath . '/src');

PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist($rootPath . '/tests');

if (defined('PEAR_INSTALL_DIR') && is_dir(PEAR_INSTALL_DIR)) {
    PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(PEAR_INSTALL_DIR);
}

if (defined('PHP_LIBDIR') && is_dir(PEAR_INSTALL_DIR)) {
    PHP_CodeCoverage_Filter::getInstance()->addDirectoryToBlacklist(PHP_LIBDIR);
}

unset($rootPath);
