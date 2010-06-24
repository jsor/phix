<?php

include __DIR__ . '/../../src/Phix.php';

define('SDS_VERSION', '0.1.0');

Phix::instance()
    ->defaultFormat('json')
    // Remove HTML format
    ->format('html', null)
    // Remove XML format
    ->format('xml', null)
    // Manipulate JSON response and error callbacks
    ->format('json', function($phix) {
        $curr = $phix->format('json');
        $curr['response'] = function($phix, $status, $data) {
            return json_encode($data);
        };
        $curr['error'] = function($phix, $status, $msg) {
            return json_encode($msg);
        };
        return $curr;
    })
    ->hook('flush', function($phix) {
        $phix->header('Content-Length: ' . strlen($phix->output()));
    })
    ->get('/', function($phix) {
        $phix->response(array('sds' => 'welcome', 'version' => SDS_VERSION));
    })
    ->get('/_all_dbs', function($phix) {
        $phix->response(array_map('basename', glob(__DIR__ . '/data/*', GLOB_ONLYDIR)));
    })
    ->put('/:db', function($phix) {
        $db = $this->param('db');
        if (!preg_match('/^[a-z_][a-z0-9_]+$/', $db)) {
            $this->error(400, array(
                'error'  => 'illegal_database_name',
                'reason' => 'Only lowercase characters (a-z), digits (0-9) and the underscore (_) are allowed'
            ));
        } else {
            if (mkdir(__DIR__ . '/data/' . $db)) {
                $this->status(201);
                $phix->response(array('ok' => true));
            } else {
                $this->error(500);
            }
        }
    })
    ->delete('/:db', function($phix) {
        $db = $this->param('db');
        if (!preg_match('/^[a-z_][a-z0-9_]+$/', $db)) {
            $this->error(400, array(
                'error'  => 'illegal_database_name',
                'reason' => 'Only lowercase characters (a-z), digits (0-9) and the underscore (_) are allowed'
            ));
        } else {
            foreach (scandir(__DIR__ . '/data') as $file) {
                unlink($file);
            }
            rmdir(__DIR__ . '/data/' . $db);
            $this->status(204);
        }
    })
    ->run();
