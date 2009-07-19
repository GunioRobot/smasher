<?php

set_exception_handler('handle_exception');

require 'smasher.php';

$options = array(
    'conf'     => 'smasher.xml',
    'type'     => NULL,
    'group'    => NULL,
    'nominify' => false
);

if (isset($_GET['conf'])) {
    $options['conf'] = $_GET['conf'];
}

if (!isset($_GET['type'])) {
    throw new Exception('No type specified');
} else {
    $options['type'] = $_GET['type'];
}

if (!isset($_GET['group'])) {
    throw new Exception('No group specified');
} else {
    $options['group'] = $_GET['group'];
}

$minify = !isset($_GET['nominify']);

$smasher = new Smasher($options['conf']);

if ($options['type'] === 'css') {
    header('Content-Type: text/css');
    echo $smasher->build_css($options['group'], !$options['nominify']);
} else if ($options['type'] === 'js') {
    header('Content-Type: text/javascript');
    echo $smasher->build_js($options['group'], !$options['nominify']);
} else {
    throw new Exception('Invalid type: ' . $options['type']);
}

// -- Functions ---------------------------------------------------------------

function handle_exception(Exception $ex)
{
    header('HTTP/1.0 404 Not Found');
    header('Content-type: text/html');

    echo '<html>',
           '<head><title></title></head>',
           '<body>',
             '<h1>Smasher error</h1>',
             '<p>', htmlentities($ex->getMessage()), '</p>',
           '</body>',
         '</html>';

    exit;
}
