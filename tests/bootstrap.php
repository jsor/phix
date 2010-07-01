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

// Get base and application path
$rootPath = dirname(dirname(__FILE__));

// Define filters for clover report
PHPUnit_Util_Filter::addDirectoryToWhitelist($rootPath . '/src');

PHPUnit_Util_Filter::addDirectoryToFilter($rootPath . '/tests');

if (defined('PEAR_INSTALL_DIR') && is_dir(PEAR_INSTALL_DIR)) {
    PHPUnit_Util_Filter::addDirectoryToFilter(PEAR_INSTALL_DIR);
}
if (defined('PHP_LIBDIR') && is_dir(PEAR_INSTALL_DIR)) {
    PHPUnit_Util_Filter::addDirectoryToFilter(PHP_LIBDIR);
}

set_include_path(implode(PATH_SEPARATOR, array(
    $rootPath . '/tests',
    $rootPath . '/src',
    get_include_path()
)));

unset($rootPath);

/**
 * Setup autoloading
 */
spl_autoload_register(function($className) {
    if (false !== strripos($className, '\\')) {
        $replace = '\\';
    } else {
        $replace = '_';
    }

    require str_replace($replace, DIRECTORY_SEPARATOR, $className) . '.php';

    return true;
}, true, true);