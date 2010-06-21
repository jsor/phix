Phix
====

[Phix](http://github.com/jsor/phix) is a lightweight and flexible PHP microframework for rapid web application development.

## License ##

Phix is released under the [BSD license](http://opensource.org/licenses/bsd-license.php).

## Prerequisites ##

Phix requires PHP 5.2 but its recommended to use it with PHP 5.3. The test suite requires PHP 5.3 because of its usage of closures/lambdas.
The following PHP extensions are required: dom, json, libxml, pcre, session, SimpleXML and SPL.

## Installation ##

The preferred installation method is via PEAR. At present no PEAR channel has been provided but this does not prevent a simple install! The simplest method of installation is:

    git clone git://github.com/jsor/phix.git phix
    cd phix
    sudo pear install package.xml

The above process will install Phix as a PEAR library.

Note: If installing from a git clone, you may need to delete any previous Phix install via PEAR using:

    sudo pear uninstall Phix

## Credits ##

Phix is inspired by and/or uses code from

* [limonade](http://github.com/sofadesign/limonade)
* [Zend Framework](http://github.com/zendframework/zf2)
