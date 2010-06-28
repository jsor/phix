Simple Document Store
=====================

This example shows a simple document store with a RESTful interface.

Documents are stored in the JSON format. The API is similar to the [CouchDB API](http://wiki.apache.org/couchdb/HTTP_Document_API) (but does not support databases).

API
---

<table>
    <tr>
        <th>Method</th>
        <th>URL</th>
        <th>Description</th>
        <th>Example</th>
    </tr>
    <tr>
        <td>GET</td>
        <td>/</td>
        <td>List all documents</td>
        <td>`curl -X GET http://simple-document-store/`</td>
    </tr>
    <tr>
        <td>GET</td>
        <td>/:id</td>
        <td>Read a document with the ID :id</td>
        <td>`curl -X GET http://simple-document-store/6e1295ed6c29495e54cc05947f18c8af`</td>
    </tr>
    <tr>
        <td>PUT</td>
        <td>/:id</td>
        <td>Create/Update a document with the ID :id</td>
        <td>`curl -X PUT http://simple-document-store/6e1295ed6c29495e54cc05947f18c8af -d '{"title":"There is Nothing Left to Lose","artist":"Foo Fighters"}'`</td>
    </tr>
    <tr>
        <td>POST</td>
        <td>/</td>
        <td>Create a document with an auto-generated ID</td>
        <td>`curl -X POST http://localhost -d '{"title":"There is Nothing Left to Lose","artist":"Foo Fighters"}'`</td>
    </tr>
    <tr>
        <td>DELETE</td>
        <td>/:id</td>
        <td>Delete a document with the ID :id</td>
        <td>`curl -X DELETE http://simple-document-store/6e1295ed6c29495e54cc05947f18c8af`</td>
    </tr>
</table>

Notes
------------

1. The ./data directory should be writeable from PHP, either by setting relevant permissions or changing the directories' ownership to the user under which HTTP requests are served. For example:

        sudo chown -R www-data:www-data ./data

2. Ensure that your Apache configuration allows the .htaccess to be executed (i.e. by setting `AllowOverride` to `All`) and has [mod_rewrite](http://httpd.apache.org/docs/2.0/mod/mod_rewrite.html) enabled.