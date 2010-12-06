Phix
====

[Phix](https://github.com/jsor/phix) is a lightweight and flexible PHP 5.3+ microframework for rapid web application development.

## License ##

Phix is released under the [BSD license](https://github.com/jsor/phix/blob/master/LICENSE).

## Prerequisites ##

Phix requires PHP 5.3.0 or higher. The following PHP extensions are required: dom, json, libxml, pcre, session, SimpleXML and SPL.

## Installation ##

The preferred installation method is via PEAR. At present no PEAR channel has been provided but this does not prevent a simple install! The simplest method of installation is:

    git clone git://github.com/jsor/phix.git phix
    cd phix
    sudo pear install package.xml

The above process will install Phix as a PEAR library.

Note: If installing from a git clone, you may need to delete any previous Phix install via PEAR using:

    sudo pear uninstall Phix

## Introduction ##

Phix is a DSL for quickly creating web applications in PHP with minimal effort:

    <?php
    include_once 'Phix/App.php';

    $app = new \Phix\App();
    $app
        ->get('/hello/:name', function(\Phix\App $app) {
            echo 'Hello ' . $app->param('name') . '!';
        })
        ->run();

Put the code from above in a `index.php` and save the file to your root web directory.

Go to the following URL to be greeted by Phix (replace Jan with your first name):

    http://localhost/index.php/hello/Jan

If you have `mod_rewrite` installed, copy the following code to a `.htaccess` file and save it to the root web directory:

    RewriteEngine on
    RewriteCond %{REQUEST_FILENAME} -s [OR]
    RewriteCond %{REQUEST_FILENAME} -l [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^.*$ - [QSA,NC,L]
    RewriteRule ^.*$ index.php [QSA,NC,L]

You can then omit the index.php part of the URL:

    http://localhost/hello/Jan

## Routes ##

A route is a HTTP method paired with an URL matching pattern. Each route is associated with a callback:

    <?php
    $app
        ->get('/', function(\Phix\App $app) {
            // Show something
        })
        ->post('/', function(\Phix\App $app) {
            // Create something
        })
        ->put('/', function(\Phix\App $app) {
            // Update something
        })
        ->delete('/', function(\Phix\App $app) {
            // Delete something
        });

Routes are matched in reverse order (LIFO, "Last In, First Out") they are defined. The first route that matches the request is invoked.

The callback receives the `\Phix\App` instance as the first argument.

When `PUT` or `DELETE` methods are not supported (for example in HTML form submission), you can use the `_method` parameter in `POST` requests:

    <form action="/" method="post">
        <input type="hidden" name="_method" value="PUT">
        <input type="submit" value="Update">
    </form>

You can also send a HTTP `POST` and set the method override header as follows:

    X-HTTP-Method-Override: PUT

Route patterns may include named parameters, accessible via the `params` method:

    <?php
    $app
        ->get('/hello/:name', function(\Phix\App $app) {
            echo 'Hello ' . $app->param('name') . '!';
        });

Route patterns may also include wildcard parameters. Associated values are available through numeric indexes, in the same order as in the pattern:

    <?php
    $app
        ->get('/say/*/to/*', function(\Phix\App $app) {
            // matches /say/hello/to/world
            $app->param(0); // hello
            $app->param(1); // world
        })
        ->get('download/*.*', function(\Phix\App $app) {
            // matches /download/file.xml
            $app->param(0); // file
            $app->param(1); // xml
        });

Unlike the simple wildcard character `*`, the double wildcard character `**` specifies a string that may contain a `/`:

    <?php
    $app
        ->get('download/**', function(\Phix\App $app) {
            // matches /download/path/to/file.xml
            $app->param(0); // path/to/file.xml
        });

A route pattern may also be a regular expression if it begins with a `^`:

    <?php
    $app
        ->get('^/my/own/(\d+)/regexp', function(\Phix\App $app) {
            // matches /say/hello/to/world
            $app->param(0); // 12
        });

Wildcard parameters and regular expressions may be named too:

    <?php
    $app
        ->get(array('/say/*/to/**', array('what', 'who')), function(\Phix\App $app) {
            // matches /say/hello/to/world
            $app->param('what'); // hello
            $app->param('who'); // world
        });

The route methods accept two additional arguments. The first is an array with default values and the second is a callback function. The callback function is executed right after the route is matched. If the function returns an array, this data is added as additional params. If it returns false, the route is omitted and Phix tries to find the next matching route:

    <?php
    $app
        ->get(
            '/foo/:bar',
            function(\Phix\App $app) {
            },
            array(
                'bar' => 'baz' // Param "bar" defaults to "baz"
            ),
            function(\Phix\App $app, $params) {
                return false; // Route will never match
            }
        );

## Credits ##

Phix is inspired by and/or uses code from

* [Sinatra](https://github.com/sinatra/sinatra)
* [limonade](https://github.com/sofadesign/limonade)
* [Zend Framework](https://github.com/zendframework/zf2)
