Functional Testing of Phix Applications
=======================================

If you write applications for production, you should cover it with functional tests. This example shows how to do it utilizing `PhixTestCase` and [PHPUnit](http://www.phpunit.de).

To share the Phix application between your test suite and your web application, we simply create a new class `MyPhixApp` inside the `apps` folder which extends `Phix` and configures it inside of its constructor.

The folder `tests` contains the test suite, the folder `htdocs` (which is the `DOCUMENT_ROOT`) the web application endpoint.

To run the test suite, first ensure that you have PHPUnit installed (Checkout the [PHPUnit documentation](http://www.phpunit.de/manual/current/en/installation.html) for how to do so).

You can then simply run `phpunit` from inside the `tests` folder from the command line.

    $ cd tests/
    $ phpunit
