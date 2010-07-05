<?php

include __DIR__ . '/../../src/Phix/App.php';

\Phix\App::instance()

    // -------------------------------------------------------------------------
    // -- Configuration --------------------------------------------------------
    // -------------------------------------------------------------------------
    ->configure(function($app) {
        // Database
        $pdo = new PDO('sqlite:blog.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $app->reg('pdo', $pdo);

        // Misc
        $app->reg('site_title', 'My Blog');
    })

    // -------------------------------------------------------------------------
    // -- Layout & Views -------------------------------------------------------
    // -------------------------------------------------------------------------
    ->layout(function($app, array $vars, $format) {
        extract($vars);
        ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo $app->reg('site_title'); ?></title>
</head>
<body>
<h1><a href="<?php echo $app->escape($app->url(array('posts'))); ?>"><?php echo $app->reg('site_title'); ?></a></h1>
<?php echo $content; ?>
</body>
</html>
<?php
        return ob_get_clean();
    })
    ->view('posts/index', function($app, array $vars, $format) {
        extract($vars);
        ob_start();
?>
<ul>
<?php foreach ($posts as $post): ?>
    <li><a href="<?php echo $app->escape($app->url(array('posts', $post['id']))); ?>"><?php echo $app->escape($post['title']); ?></a></li>
<?php endforeach; ?>
</ul>
<?php
        return ob_get_clean();
    })
    ->view('posts/view', function($app, array $vars, $format) {
        extract($vars);
        ob_start();
?>
<h2><?php echo $app->escape($post['title']); ?></h2>
<?php echo $post['content']; ?>
<?php
        return ob_get_clean();
    })

    // -------------------------------------------------------------------------
    // -- Routes ---------------------------------------------------------------
    // -------------------------------------------------------------------------
    ->get('/', function($app) {
        $app->redirect('/posts');
    })
    ->get('/posts/:id', function($app) {
        $sql = "SELECT *
                FROM posts where id=:id";

        $stmt = $app->reg('pdo')->prepare($sql);
        $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);

        if ($stmt->execute() && $post = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($app->currentFormat() == 'html') {
                $app->render('posts/view', compact('post'));
            } else {
                $app->response(compact('post'));
            }
        } else {
            $app->notFound('There is no such post');
        }
    })
    ->put('/posts/:id', function($app) {
        $sql = "SELECT *
                FROM posts where id=:id";

        $stmt = $app->reg('pdo')->prepare($sql);
        $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);

        if ($stmt->execute() && $post = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = date('Y-m-d H:i:s');

            $post['title']    = $app->param('title');
            $post['content']  = $app->param('content');
            $post['modified'] = $date;

            $sql = "UPDATE posts
                    SET title = :title, content = :content, modified = DATETIME('NOW', 'localtime')
                    WHERE id = :id";

            $stmt = $app->reg('pdo')->prepare($sql);

            $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);
            $stmt->bindValue(':title', $post['title'], PDO::PARAM_STR);
            $stmt->bindValue(':content', $post['content'], PDO::PARAM_STR);
            $stmt->bindValue(':modified', $post['modified'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                $location = $app->url(array('post', $post['id']));

                if ($app->currentFormat() == 'html') {
                    $app->redirect($location);
                } else {
                    $app->response(compact('post'));
                }
            } else {
                $app->error('Error updating post');
            }
        } else {
            $app->notFound('There is no such post');
        }
    })
    ->delete('/posts/:id', function($app) {
        $sql = "SELECT *
                FROM posts where id=:id";

        $stmt = $app->reg('pdo')->prepare($sql);
        $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);

        if ($stmt->execute() && $post = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sql = "DELETE FROM posts
                WHERE id = :id";

            $stmt = $app->reg('pdo')->prepare($sql);

            $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($app->currentFormat() == 'html') {
                    $app->redirect(array('posts'));
                } else {
                    $app->status(204);
                }
            } else {
                $app->error('Error deleting post');
            }
        } else {
            $app->notFound('There is no such post');
        }
    })
    ->post('/posts', function($app) {
        $date = date('Y-m-d H:i:s');

        $sql = "INSERT INTO posts (title, content, created, modified)
                VALUES (:title, :content, :created, :modified)";

        $stmt = $app->reg('pdo')->prepare($sql);

        $stmt->bindValue(':title', $app->param('title'), PDO::PARAM_STR);
        $stmt->bindValue(':content', $app->param('content'), PDO::PARAM_STR);
        $stmt->bindValue(':created', $date, PDO::PARAM_STR);
        $stmt->bindValue(':modified', $date, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $post = array(
                'id'       => $app->reg('pdo')->lastInsertId(),
                'title'    => $app->param('title'),
                'content'  => $app->param('content'),
                'created'  => $date,
                'modified' => $date
            );

            $location = $app->url(array('post', $post['id']));

            if ($app->currentFormat() == 'html') {
                $app->redirect($location);
            } else {
                $app->status(201);
                $app->header('Location: ' . $location);
                $app->header('Content-Location: ' . $location);
                $app->response(compact('post', 'location'));
            }
        } else {
            $app->error('Error creating post');
        }
    })
    ->get('/posts', function($app) {
        $sql = "SELECT *
                FROM posts
                ORDER BY modified DESC";

        $stmt = $app->reg('pdo')->prepare($sql);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($app->currentFormat() == 'html') {
            $app->render('posts/index', compact('posts'));
        } else {
            $app->response(compact('posts'));
        }
    })
    ->get('/_setup', function($app) {
        $schema = <<<SCHEMA
CREATE TABLE "posts" (
    "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    "title" VARCHAR,
    "content" TEXT,
    "created" DATETIME,
    "modified" DATETIME
);
SCHEMA;
        $app->reg('pdo')->exec($schema);

        $fixtures = <<<FIXTURES
INSERT INTO posts ('title', 'content', 'created', 'modified') VALUES ('First post', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut non nisl mi, vitae commodo tortor. In sed eros ac ligula aliquet cursus. Vestibulum ut sollicitudin est. In lacinia porttitor dolor ac condimentum. Ut lorem leo, fringilla sed interdum ullamcorper, sagittis hendrerit est. Donec nulla sem, posuere eget luctus tempor, placerat et tortor. Sed felis augue, pretium vitae lobortis nec, feugiat consectetur eros. Morbi tristique mauris nec tellus fringilla a porttitor orci imperdiet. Donec dictum facilisis accumsan. Nam vulputate malesuada elementum. Mauris mattis tincidunt tellus eu porta. Pellentesque dignissim sodales felis, quis placerat eros facilisis sit amet. Fusce ullamcorper enim turpis, id hendrerit massa.', DATETIME('NOW'), DATETIME('NOW'));
INSERT INTO posts ('title', 'content', 'created', 'modified') VALUES ('A second post', 'Fusce diam lacus, viverra eu interdum quis, tincidunt non est. Ut sed lacinia dolor. Phasellus molestie, magna dapibus malesuada sodales, leo tellus posuere metus, a elementum lectus metus in leo. Etiam elit ipsum, pellentesque a malesuada non, convallis vel magna. Aenean nibh turpis, eleifend quis congue at, porttitor id leo. Curabitur fermentum, urna vel interdum euismod, felis purus faucibus nunc, eu vulputate sem arcu eu mauris. Duis nibh tellus, imperdiet nec ullamcorper id, molestie ut diam. Sed consequat vestibulum cursus. Maecenas tempus tristique venenatis. Vivamus tincidunt rhoncus ante, a ornare quam consectetur id. Ut leo lacus, dignissim in lacinia vel, condimentum non sapien. Integer aliquam volutpat pulvinar. Nunc lacinia tincidunt magna a pharetra. Maecenas id pellentesque tortor. Nulla lobortis augue quis mi sodales porttitor. Etiam at nunc sit amet dui dictum cursus id molestie odio. Sed leo purus, elementum ac porta eu, mattis eget augue. Curabitur nec odio a orci tristique placerat. Pellentesque placerat mattis purus, id varius velit rutrum vel.', DATETIME('NOW'), DATETIME('NOW'));
INSERT INTO posts ('title', 'content', 'created', 'modified') VALUES ('This is a third post', 'Praesent quis diam sit amet mi sagittis auctor viverra ultricies dui. Donec dui felis, sagittis sit amet tincidunt vel, rhoncus at tortor. Phasellus faucibus sodales tincidunt. Aliquam erat libero, adipiscing non dignissim non, interdum et ipsum. Suspendisse euismod magna quis massa pretium luctus. Sed eget commodo erat. Cras nibh ligula, sollicitudin vitae dignissim sit amet, lobortis eget metus. Maecenas sit amet orci leo. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus fermentum mi ut tortor volutpat ultricies. Suspendisse et sapien mauris. Morbi et eros a elit consectetur dignissim nec in dolor.', DATETIME('NOW'), DATETIME('NOW'));
FIXTURES;
        $app->reg('pdo')->exec($fixtures);
    })

    ->run();
