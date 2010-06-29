<?php

include __DIR__ . '/../../src/Phix/App.php';

clearstatcache();

\Phix\App::instance()

    // -----------------------
    // -- CONFIGURATION ------
    // -----------------------

    // Set data dir
    ->reg('data_dir', __DIR__ . '/data')

    // Set default format to JSON
    ->defaultFormat('json')

    // Remove HTML format
    ->format('html', null)

    // Remove XML format
    ->format('xml', null)

    // Manipulate JSON response and error callbacks
    ->format('json', function($app) {
        $curr = $app->format('json');
        $curr['response'] = function($app, $status, $data) {
            $response = json_encode($data);

            if (!empty($_GET['callback']) && preg_match('/^[a-zA-Z_$][0-9a-zA-Z_$]*$/', $_GET['callback'])) {
                $response = $_GET['callback'] . '(' . $response . ')';
            }

            return $response;
        };
        $curr['error'] = function($app, $status, $msg) {
            if (is_string($msg)) {
                $msg = array(
                    'error'  => 'internal',
                    'reason' => $msg
                );
            }
            return json_encode($msg);
        };
        return $curr;
    })

    // Fake request header in case it was not set (we only accept JSON)
    ->requestHeader('Content-type', 'application/json')

    // -----------------------
    // -- HOOKS --------------
    // -----------------------

    // Hook flush() to send Content-Length header
    ->hook('flush', function($app) {
        $app->header('Content-Length: ' . strlen($app->output()));
    })

    // -----------------------
    // -- ROUTES -------------
    // -----------------------

    // Show a document
    ->get('/:id', function($app) {
        $id = $app->param('id');
        $file = $app->reg('data_dir') . '/' . $id;
        if (!file_exists($file)) {
            $app->error(404, array(
                'error'  => 'not_found',
                'reason' => 'missing'
            ));
        } else {
            $app->status(200);
            $app->response(json_decode(file_get_contents($file)));
        }
    })

    // Create/Update a document
    ->put('/:id', function($app) {
        $id = $app->param('id');
        if (!preg_match('/^[a-z0-9_]+$/', $id)) {
            $app->error(400, array(
                'error'  => 'illegal_id',
                'reason' => 'Only lowercase characters (a-z), digits (0-9) and the underscore (_) are allowed'
            ));
        } else {
            $file = $app->reg('data_dir') . '/' . $id;
            if (file_exists($file)) {
                //unlink($file);
            }
            file_put_contents($file, json_encode(array('_id' => $id) + $_POST));

            $app->status(201);
            $app->response(array('ok' => true, 'id' => $id));
        }
    })

    // Delete a document
    ->delete('/:id', function($app) {
        $id = $app->param('id');
        $file = $app->reg('data_dir') . '/' . $id;
        if (!file_exists($file)) {
            $app->error(404, array(
                'error'  => 'not_found',
                'reason' => 'missing'
            ));
        } else {
            unlink($file);
            $app->status(200);
            $app->response(array('ok' => true, 'id' => $id));
        }
    })

    // Create a document (with auto-generated id)
    ->post('/', function($app) {
        $id = md5(uniqid(mt_rand(), true));
        $file = $app->reg('data_dir') . '/' . $id;
        file_put_contents($file, json_encode(array('_id' => $id) + $_POST));
        $app->status(201);
        $app->response(array('ok' => true, 'id' => $id));
    })

    // List all documents
    ->get('/', function($app) {
        $docs = array();
        foreach (glob($app->reg('data_dir') . '/*') as $doc) {
            $docs[] = json_decode(file_get_contents($doc));
        }
        $app->response($docs);
    })

    // -----------------------
    // -- APPLICATION --------
    // -----------------------

    // Run application
    ->run();

