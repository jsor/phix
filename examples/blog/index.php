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
    <link rel="stylesheet" type="text/css" href="<?php echo $app->url(array('css', 'blog.css')); ?>" media="all">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js" type="text/javascript"></script>
    <script src="<?php echo $app->url(array('js', 'jquery.relatizeDate.js')); ?>" type="text/javascript"></script>
    <script src="<?php echo $app->url(array('js', 'blog.js')); ?>" type="text/javascript"></script>
</head>
<body>
    <div id="container">
        <h1><a href="<?php echo $app->escape($app->url(array('posts'))); ?>"><?php echo $app->reg('site_title'); ?></a></h1>
        <p><a href="<?php echo $app->url(array('posts', 'add')); ?>">Add Post</a></p>
        <?php echo $content; ?>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    })
    ->view('posts/index', function($app, array $vars, $format) {
        extract($vars);
        ob_start();
?>
<ul class="postlist">
<?php foreach ($posts as $post): ?>
    <li><a href="<?php echo $app->escape($app->url(array('posts', $post['id']))); ?>"><?php echo $app->escape($post['title']); ?></a> (<span class="date"><?php echo date('r', strtotime($post['created'])); ?></span>)</li>
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
<p><a href="<?php echo $app->url(array('posts', $post['id'], 'edit')); ?>">Edit Post</a></p>
<?php echo $post['content']; ?>
<hr>
<form action="<?php echo $app->url(array('posts', $post['id'])); ?>" method="post">
    <input type="hidden" name="_method" value="DELETE">
    <input type="submit" value="Delete Post">
</form>
<?php
        return ob_get_clean();
    })
    ->view('posts/form', function($app, array $vars, $format) {
        extract($vars);
        ob_start();

        if (isset($post)) {
            $action = $app->url(array('posts', $post['id']));
            $method = 'PUT';
            $headline = 'Edit Post: ' . $post['title'];
        } else {
            $action = $app->url(array('posts'));
            $method = 'POST';
            $headline = 'Add Post';
        }

        $title   = isset($_POST['title']) ? $_POST['title'] : (isset($post['title']) ? $post['title'] : '');
        $content = isset($_POST['content']) ? $_POST['content'] : (isset($post['content']) ? $post['content'] : '');
?>
<h2><?php echo $app->escape($headline); ?></h2>
<form action="<?php echo $action; ?>" method="post">
    <input type="hidden" name="_method" value="<?php echo $method; ?>">
    <label for="title">Title</label>
    <input id="title" type="text" name="title" value="<?php echo $app->escape($title); ?>">
    <label for="content">Content</label>
    <textarea id="content" cols="35" rows="10" name="content"><?php echo $app->escape($content); ?></textarea>
    <input type="submit">
</form>
<?php
        return ob_get_clean();
    })
    ->view(array('*', 'json'), function($app, array $vars, $format) {
        $statusString = 200 <= $app->status() && 206 >= $app->status() ? 'success' : 'fail';
        $response = json_encode(array('status' => $statusString, 'data' => $vars));

        // Handle JSONP callbacks
        if (!empty($_GET['callback']) && preg_match('/^[a-zA-Z_$][0-9a-zA-Z_$]*$/', $_GET['callback'])) {
            $response = $_GET['callback'] . '(' . $response . ')';
        }

        return $response;
    })
    ->view(array('*', 'xml'), function($app, array $vars, $format) {
        $arrayToXml = function(array $array, $root) use (&$arrayToXml) {
            $xml  = '';
            $wrap = true;
            foreach ($array as $key => $value) {
                if (is_object($value)) {
                    $value = get_object_vars($value);
                }
                if (is_array($value)) {
                    if (is_numeric($key)) {
                        $key  = $root;
                        $wrap = false;
                    }
                    $xml .= $arrayToXml($value, $key);
                } else {
                    if (is_numeric($key)) {
                        $wrap = false;
                        $xml .= '<' . $root . '>' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '</' . $root . '>';
                    } else {
                        $xml .= '<' . $key . '>' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '</' . $key . '>';
                    }
                }
            }

            if ($wrap) {
                $xml = '<' . $root . '>' . $xml . '</' . $root . '>';
            }

            return $xml;
        };

        $statusString = 20 <= $app->status() && 206 >= $app->status() ? 'success' : 'fail';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
               '<response>' .
                 '<status>' . $statusString . '</status>' .
                 $arrayToXml($vars, 'data') .
               '</response>';
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
            $app->render('posts/view', compact('post'));
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

            $post['title']    = $_POST['title'];
            $post['content']  = $_POST['content'];
            $post['modified'] = $date;

            $sql = "UPDATE posts
                    SET title = :title, content = :content, modified = :modified
                    WHERE id = :id";

            $stmt = $app->reg('pdo')->prepare($sql);

            $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);
            $stmt->bindValue(':title', $post['title'], PDO::PARAM_STR);
            $stmt->bindValue(':content', $post['content'], PDO::PARAM_STR);
            $stmt->bindValue(':modified', $post['modified'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                $location = $app->url(array('posts', $post['id']));

                if ($app->currentFormat() == 'html') {
                    $app->redirect($location);
                } else {
                    $app->render('*', compact('post'));
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
    ->get('/posts/:id/edit', function($app) {
        $sql = "SELECT *
                FROM posts where id=:id";

        $stmt = $app->reg('pdo')->prepare($sql);
        $stmt->bindValue(':id', $app->param('id'), PDO::PARAM_INT);

        if ($stmt->execute() && $post = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($app->currentFormat() == 'html') {
                $app->render('posts/form', compact('post'));
            } else {
                $app->error(400, 'No "' . $app->escape($app->currentFormat()) . '" representation available');
            }
        } else {
            $app->notFound('There is no such post');
        }
    })
    ->get('/posts/add', function($app) {
        if ($app->currentFormat() == 'html') {
            $app->render('posts/form');
        } else {
            $app->error(400, 'No "' . $app->escape($app->currentFormat()) . '" representation available');
        }
    })
    ->post('/posts', function($app) {
        $date = date('Y-m-d H:i:s');

        $sql = "INSERT INTO posts (title, content, created, modified)
                VALUES (:title, :content, :created, :modified)";

        $stmt = $app->reg('pdo')->prepare($sql);

        $stmt->bindValue(':title', $_POST['title'], PDO::PARAM_STR);
        $stmt->bindValue(':content', $_POST['content'], PDO::PARAM_STR);
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

            $location = $app->url(array('posts', $post['id']));

            if ($app->currentFormat() == 'html') {
                $app->redirect($location);
            } else {
                $app->status(201);
                $app->header('Location: ' . $location);
                $app->header('Content-Location: ' . $location);
                $app->render('*', compact('post', 'location'));
            }
        } else {
            $app->error('Error creating post');
        }
    })
    ->get('/posts', function($app) {
        $sql = "SELECT *
                FROM posts
                ORDER BY created DESC, id DESC";

        $stmt = $app->reg('pdo')->prepare($sql);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $app->render('posts/index', compact('posts'));
    })
    ->get('/css/blog.css', function($app) {
        $app->css('
body {
    font-family: Helvetica,Arial,FreeSans;
}
#container {
    width: 800px;
    margin: 0 auto;
}
label {
    display: block;
}
input, textarea {
    display: block;
    margin-bottom: 10px;
}
');
    })
    ->get('/js/blog.js', function($app) {
        $app->javascript('
$(function() {
    $(".date").relatizeDate();
});
');
    })
    ->get('/js/jquery.relatizeDate.js', function($app) {
        $app->javascript('(function(c){c.fn.relatizeDate=function(){return c(this).each(function(){c(this).text(c.relatizeDate(this))})};c.relatizeDate=function(b){return c.relatizeDate.timeAgoInWords(new Date(c(b).text()))};$r=c.relatizeDate;c.extend(c.relatizeDate,{shortDays:["Sun","Mon","Tue","Wed","Thu","Fri","Sat"],days:["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],shortMonths:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],months:["January","February","March","April", "May","June","July","August","September","October","November","December"],strftime:function(b,a){var f=b.getDay(),g=b.getMonth(),h=b.getHours(),i=b.getMinutes(),d=function(e){e=e.toString(10);return Array(2-e.length+1).join("0")+e};return a.replace(/\%([aAbBcdHImMpSwyY])/g,function(e){switch(e[1]){case "a":return $r.shortDays[f];case "A":return $r.days[f];case "b":return $r.shortMonths[g];case "B":return $r.months[g];case "c":return b.toString();case "d":return d(b.getDate());case "H":return d(h); case "I":return d((h+12)%12);case "m":return d(g+1);case "M":return d(i);case "p":return h>12?"PM":"AM";case "S":return d(b.getSeconds());case "w":return f;case "y":return d(b.getFullYear()%100);case "Y":return b.getFullYear().toString()}})},timeAgoInWords:function(b,a){return $r.distanceOfTimeInWords(b,new Date,a)},distanceOfTimeInWords:function(b,a,f){a=parseInt((a.getTime()-b.getTime())/1E3);if(a<60)return"less than a minute ago";else if(a<120)return"about a minute ago";else if(a<2700)return parseInt(a/ 60).toString()+" minutes ago";else if(a<7200)return"about an hour ago";else if(a<86400)return"about "+parseInt(a/3600).toString()+" hours ago";else if(a<172800)return"1 day ago";else{a=parseInt(a/86400).toString();if(a>5){a="%B %d, %Y";if(f)a+=" %I:%M %p";return $r.strftime(b,a)}else return a+" days ago"}}})})(jQuery);');
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
