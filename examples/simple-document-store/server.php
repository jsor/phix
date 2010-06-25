<?php

include __DIR__ . '/../../src/Phix.php';

Phix::instance()

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
    ->format('json', function($phix) {
        $curr = $phix->format('json');
        $curr['response'] = function($phix, $status, $data) {
            $response = json_encode($data);

            if (!empty($_GET['callback']) && preg_match('/^[a-zA-Z_$][0-9a-zA-Z_$]*$/', $_GET['callback'])) {
                $response = $_GET['callback'] . '(' . $response . ')';
            }

            return $response;
        };
        $curr['error'] = function($phix, $status, $msg) {
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
    ->hook('flush', function($phix) {
        $phix->header('Content-Length: ' . strlen($phix->output()));
    })

    // -----------------------
    // -- ROUTES -------------
    // -----------------------

    // Show a document
    ->get('/:id', function($phix) {
        $id = $phix->param('id');
        $file = $phix->reg('data_dir') . '/' . $id;
        if (!file_exists($file)) {
            $phix->error(404, array(
                'error'  => 'not_found',
                'reason' => 'missing'
            ));
        } else {
            $phix->status(200);
            $phix->response(json_decode(file_get_contents($file)));
        }
    })
    // Create/Update a document
    ->put('/:id', function($phix) {
        $id = $phix->param('id');
        if (!preg_match('/^[a-z0-9_]+$/', $id)) {
            $phix->error(400, array(
                'error'  => 'illegal_id',
                'reason' => 'Only lowercase characters (a-z), digits (0-9) and the underscore (_) are allowed'
            ));
        } else {
            $file = $phix->reg('data_dir') . '/' . $id;
            if (file_exists($file)) {
                //unlink($file);
            }
            file_put_contents($file, json_encode(array('_id' => $id) + $_POST));

            $phix->status(201);
            $phix->response(array('ok' => true, 'id' => $id));
        }
    })
    // Delete a document
    ->delete('/:id', function($phix) {
        $id = $phix->param('id');
        $file = $phix->reg('data_dir') . '/' . $id;
        if (!file_exists($file)) {
            $phix->error(404, array(
                'error'  => 'not_found',
                'reason' => 'missing'
            ));
        } else {
            unlink($file);
            $phix->status(200);
            $phix->response(array('ok' => true, 'id' => $id));
        }
    })
    // Create a document (with auto-generated id)
    ->post('/', function($phix) {
        $id = md5(uniqid(mt_rand(), true));
        $file = $phix->reg('data_dir') . '/' . $id;
        file_put_contents($file, json_encode(array('_id' => $id) + $_POST));
        $phix->status(201);
        $phix->response(array('ok' => true, 'id' => $id));
    })
    // List all documents
    ->get('/', function($phix) {
        $docs = array();
        foreach (glob($phix->reg('data_dir') . '/*') as $doc) {
            $docs[] = json_decode(file_get_contents($doc));
        }
        $phix->response($docs);
    })

    // -----------------------
    // -- APPLICATION --------
    // -----------------------

    // Run application
    ->run();
