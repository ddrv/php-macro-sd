<?php

/** @var ContainerInterface $container */

use App\App;
use Ddrv\ServerRequestWizard\ServerRequestWizard;
use Psr\Container\ContainerInterface;

$container = require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

/** @var ServerRequestWizard $requestWizard */
$requestWizard = $container->get(ServerRequestWizard::class);
$request = $requestWizard->create($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);

/** @var App $app */
$app = $container->get(App::class);

$response = $app->web($request);


foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        $header = sprintf('%s: %s', $name, $value);
        header($header, false);
    }
}

$statusLine = sprintf(
    'HTTP/%s %s %s',
    $response->getProtocolVersion(),
    $response->getStatusCode(),
    $response->getReasonPhrase()
);
header($statusLine, true, $response->getStatusCode());

$body = $response->getBody();
if ($body->isSeekable()) {
    $body->rewind();
}

while (!$body->eof()) {
    echo $body->read(4096);
}
